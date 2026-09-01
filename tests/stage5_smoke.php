<?php

declare(strict_types=1);

require_once '/var/www/src/News/NewsRepository.php';
require_once '/var/www/src/Ranking/DeterministicRanker.php';
require_once '/var/www/src/Ranking/StoryClusterer.php';
require_once '/var/www/src/Ranking/RankingRepository.php';
require_once '/var/www/src/Ranking/RankingService.php';
require_once '/var/www/src/Feedback/FeedbackRepository.php';
require_once '/var/www/src/Llm/LlmClientInterface.php';
require_once '/var/www/src/Llm/LlmRepository.php';
require_once '/var/www/src/Llm/ProfileLoader.php';
require_once '/var/www/src/Llm/LlmEnrichmentService.php';

function stage5Ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class Stage5FakeClient implements LlmClientInterface
{
    /** @param list<string|Throwable> $responses */
    public function __construct(private array $responses)
    {
    }

    public function configuredModel(): string
    {
        return 'gemma-3-4b-test';
    }

    public function complete(array $messages, array $jsonSchema): array
    {
        $response = array_shift($this->responses);
        if ($response instanceof Throwable) {
            throw $response;
        }
        if (!is_string($response)) {
            throw new RuntimeException('The fake local-model response queue is empty.');
        }
        return [
            'model' => 'gemma-3-4b-test-resolved',
            'content' => $response,
            'prompt_tokens' => 120,
            'completion_tokens' => 45,
            'duration_ms' => 321,
        ];
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec((string) file_get_contents('/var/www/database/schema.sql'));

$newsRepository = new NewsRepository($pdo);
$sources = [
    ['id' => 'official', 'name' => 'Official Office', 'source_type' => 'primary', 'trust_level' => 5],
    ['id' => 'wire', 'name' => 'Independent Wire', 'source_type' => 'mainstream', 'trust_level' => 4],
];
$newsRepository->seedSources(array_map(static fn (array $source): array => [
    ...$source,
    'url' => 'https://' . $source['id'] . '.example.test/feed',
    'categories' => ['breaking'],
    'geography' => 'global',
    'enabled' => true,
    'refresh_method' => 'rss',
    'notes' => 'Stage 5 fixture',
], $sources));

$now = gmdate('Y-m-d H:i:s');
$items = [
    [
        'canonical_url' => 'https://official.example.test/resignation',
        'source_id' => 'official',
        'title' => 'Prime minister resigns after government collapses in emergency',
        'excerpt' => 'The prime minister resigns during a state of emergency after the government collapses.',
    ],
    [
        'canonical_url' => 'https://wire.example.test/government-collapse',
        'source_id' => 'wire',
        'title' => 'Government collapses as prime minister resigns',
        'excerpt' => 'An independent report confirms the resignation and government collapse.',
    ],
];
$items = array_map(static fn (array $item): array => [
    ...$item,
    'author' => null,
    'published_at' => $now,
    'source_updated_at' => $now,
    'content_hash' => hash('sha256', $item['title']),
], $items);
$calls = array_map(static fn (array $source): array => [
    'source_id' => $source['id'],
    'status' => 'success',
    'http_status' => 200,
    'item_count' => 1,
    'started_at' => $now,
    'error' => null,
], $sources);

$newsRepository->startBatch('TEST-LOCAL-AI', 'breaking', 'manual_section');
$newsRepository->completeBatch('TEST-LOCAL-AI', 'breaking', 'breaking', $items, $calls, 90);
$rankingConfig = require '/var/www/config/ranking.php';
$rankingService = new RankingService(
    new RankingRepository($pdo),
    new DeterministicRanker($rankingConfig),
    new StoryClusterer(),
    $rankingConfig,
    require '/var/www/config/news.php',
);
$ranked = $rankingService->rank('breaking', 'TEST-LOCAL-AI');
stage5Ensure($ranked['selected_count'] === 1, 'The Stage 5 fixture must produce one deterministic survivor.');

$llmConfig = require '/var/www/config/llm.php';
$llmConfig['model'] = 'gemma-3-4b-test';
$llmConfig['chunk_size'] = 8;
$llmRepository = new LlmRepository($pdo);
$candidate = $llmRepository->candidates('TEST-LOCAL-AI', 'breaking', 30)[0] ?? null;
stage5Ensure(is_array($candidate), 'The deterministic survivor was not offered to the local model.');
$articleId = (int) $candidate['article_id'];
$validResponse = json_encode(['items' => [[
    'article_id' => $articleId,
    'keep' => true,
    'reason_code' => 'major_consequence',
]]], JSON_THROW_ON_ERROR);

$feedbackRepository = new FeedbackRepository($pdo);
$service = new LlmEnrichmentService(
    $llmRepository,
    $feedbackRepository,
    new ProfileLoader('/var/www/profiles/templates', '/tmp/stage5-private-profiles-not-present', $llmConfig['profile_map']),
    new Stage5FakeClient([$validResponse, '{"items": []}']),
    $llmConfig,
);

$diversityMethod = new ReflectionMethod($service, 'enforceSelectionDiversity');
$diverseOutputs = $diversityMethod->invoke($service, [
    ['article_id' => 101, 'keep' => true, 'reason_code' => 'major_consequence'],
    ['article_id' => 102, 'keep' => true, 'reason_code' => 'strong_profile_match'],
], [
    ['article_id' => 101, 'title' => 'Nepal flood disaster casualties mount', 'excerpt' => 'Hundreds died in the Nepal flood disaster.'],
    ['article_id' => 102, 'title' => 'Relief after Nepal flood disaster rescue', 'excerpt' => 'One missing visitor was found after the Nepal flood disaster.'],
]);
stage5Ensure($diverseOutputs[0]['keep'] === true && $diverseOutputs[1]['keep'] === false, 'The deterministic diversity guard did not reject a secondary event angle.');

$success = $service->enrich('breaking', 'TEST-LOCAL-AI');
stage5Ensure($success['status'] === 'success', 'A valid local-model response was not saved.');
stage5Ensure($success['selected_count'] === 1, 'The local model did not retain the expected story.');
stage5Ensure($success['duration_ms'] === 321, 'Local-model duration telemetry was not retained.');

$context = $service->context('breaking');
$snapshot = $newsRepository->latestSnapshot('breaking', 'breaking', 15, 24, $context);
stage5Ensure(count($snapshot['articles']) === 1, 'The AI-filtered snapshot should contain the retained story.');
stage5Ensure((int) $snapshot['articles'][0]['llm_current'] === 1, 'The current prompt/profile result was not recognised.');
stage5Ensure($snapshot['articles'][0]['llm_relevance_score'] === null, 'The minimal selector should not generate a relevance score.');
stage5Ensure($snapshot['articles'][0]['why_selected'] === 'major_consequence', 'The selector reason code was not persisted.');
stage5Ensure($snapshot['articles'][0]['evaluated_summary'] === null, 'The minimal selector should not generate a summary.');
stage5Ensure(str_contains((string) $snapshot['articles'][0]['deterministic_explanation'], 'Selected deterministically'), 'The deterministic explanation was overwritten.');
stage5Ensure((int) $pdo->query("SELECT COUNT(*) FROM llm_runs WHERE status = 'success'")->fetchColumn() === 1, 'The successful local-model run was not logged.');

$savedFeedback = $feedbackRepository->save($articleId, 'breaking', 'useful');
stage5Ensure($savedFeedback['action'] === 'useful', 'Feedback was not saved.');
$feedbackContext = $feedbackRepository->context('breaking', 20);
stage5Ensure(($feedbackContext[0]['action'] ?? null) === 'useful', 'Recent feedback was not available to the next prompt.');

$failed = $service->enrich('breaking', 'TEST-LOCAL-AI', true);
stage5Ensure($failed['status'] === 'failed', 'Malformed local-model output should fail the whole review.');
$warning = $service->status('breaking', 'TEST-LOCAL-AI');
stage5Ensure($warning['ready'] === true && $warning['state'] === 'warning', 'A failed retry should retain and warn about the previous AI briefing.');
$retained = $newsRepository->latestSnapshot('breaking', 'breaking', 15, 24, $context);
stage5Ensure(count($retained['articles']) === 1, 'A malformed retry removed the previous AI briefing.');
stage5Ensure($retained['articles'][0]['why_selected'] === 'major_consequence', 'A malformed retry partially overwrote stored AI results.');
stage5Ensure((int) $pdo->query("SELECT COUNT(*) FROM llm_runs WHERE status = 'failed'")->fetchColumn() === 1, 'The failed local-model run was not logged.');

echo "Stage 5 smoke test passed: minimal selection, strict JSON, persistence, feedback, and fallback retention.\n";
