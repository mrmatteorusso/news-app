<?php

declare(strict_types=1);

require_once '/var/www/src/News/NewsRepository.php';
require_once '/var/www/src/Ranking/DeterministicRanker.php';
require_once '/var/www/src/Ranking/StoryClusterer.php';
require_once '/var/www/src/Ranking/RankingRepository.php';
require_once '/var/www/src/Ranking/RankingService.php';

function stage4Ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
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
    ['id' => 'signal', 'name' => 'Community Signal', 'source_type' => 'signal', 'trust_level' => 1],
];
$newsRepository->seedSources(array_map(static fn (array $source): array => [
    ...$source,
    'url' => 'https://' . $source['id'] . '.example.test/feed',
    'categories' => ['breaking'],
    'geography' => 'global',
    'enabled' => true,
    'refresh_method' => 'rss',
    'notes' => 'Stage 4 fixture',
], $sources));

$now = gmdate('Y-m-d H:i:s');
$items = [
    [
        'canonical_url' => 'https://official.example.test/resignation',
        'source_id' => 'official',
        'title' => 'Prime minister resigns after government collapse',
        'excerpt' => 'The government collapses and the prime minister resigns during an official emergency.',
    ],
    [
        'canonical_url' => 'https://wire.example.test/government-collapse',
        'source_id' => 'wire',
        'title' => 'Government collapses as prime minister resigns',
        'excerpt' => 'An independent report confirms the resignation and government collapse.',
    ],
    [
        'canonical_url' => 'https://signal.example.test/celebrity-rumour',
        'source_id' => 'signal',
        'title' => 'Celebrity rumour could become the next viral story',
        'excerpt' => 'An unsupported community prediction without primary evidence.',
    ],
    [
        'canonical_url' => 'https://wire.example.test/old-government-report',
        'source_id' => 'wire',
        'title' => 'Prime minister resigns in an older government collapse report',
        'excerpt' => 'A previously important report is retained in the archive after its Breaking display window.',
        'published_at' => gmdate('Y-m-d H:i:s', time() - (72 * 3600)),
        'source_updated_at' => gmdate('Y-m-d H:i:s', time() - (72 * 3600)),
    ],
];
$items = array_map(static fn (array $item): array => [
    ...$item,
    'author' => null,
    'published_at' => $item['published_at'] ?? $now,
    'source_updated_at' => $item['source_updated_at'] ?? $now,
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

$newsRepository->startBatch('TEST-RANK', 'breaking', 'manual_section');
$newsRepository->completeBatch('TEST-RANK', 'breaking', 'breaking', $items, $calls, 90);

$rankingConfig = require '/var/www/config/ranking.php';
$newsConfig = require '/var/www/config/news.php';
$rankingRepository = new RankingRepository($pdo);
$rankingService = new RankingService(
    $rankingRepository,
    new DeterministicRanker($rankingConfig),
    new StoryClusterer(),
    $rankingConfig,
    $newsConfig,
);
$result = $rankingService->rank('breaking', 'TEST-RANK');

stage4Ensure($result['candidate_count'] === 3, 'Every archive candidate should be evaluated.');
stage4Ensure($result['cluster_count'] === 2, 'Similar reports should form one cluster while an unrelated signal stays separate.');
stage4Ensure($result['selected_count'] === 1, 'Only one representative of the corroborated major event should be selected.');
stage4Ensure((int) $pdo->query('SELECT COUNT(*) FROM article_evaluations')->fetchColumn() === 3, 'Article-level evaluations were not retained.');
stage4Ensure((int) $pdo->query("SELECT COUNT(*) FROM article_evaluations WHERE selected = 1")->fetchColumn() === 1, 'Exactly one cluster representative should be selected.');
stage4Ensure((int) $pdo->query("SELECT selected FROM article_evaluations ae INNER JOIN articles a ON a.id = ae.article_id WHERE a.source_id = 'signal'")->fetchColumn() === 0, 'An unsupported community signal was promoted.');
stage4Ensure((string) $pdo->query('SELECT ranking_version FROM article_evaluations LIMIT 1')->fetchColumn() === $rankingConfig['version'], 'Ranking version was not persisted.');

$snapshot = $newsRepository->latestSnapshot('breaking', 'breaking', 15, 24);
stage4Ensure($snapshot['ranking_ready'] === true, 'The latest successful ranking run was not found.');
stage4Ensure(count($snapshot['articles']) === 1, 'The snapshot should show one selected cluster representative.');
stage4Ensure((int) $snapshot['articles'][0]['cluster_source_count'] === 2, 'Corroboration must count distinct publishers.');
stage4Ensure((int) $snapshot['articles'][0]['cluster_evidence_source_count'] === 2, 'Evidence corroboration must count distinct reputable publishers.');
stage4Ensure((int) $snapshot['archive_count'] === 4, 'Clustering and freshness filtering must not delete the underlying archive.');
stage4Ensure((int) $pdo->query("SELECT COUNT(*) FROM articles WHERE canonical_url = 'https://wire.example.test/old-government-report'")->fetchColumn() === 1, 'An old hidden story should remain stored in SQLite.');
stage4Ensure((int) $snapshot['articles'][0]['importance_score'] > 0, 'The score breakdown was not exposed.');

$rankingRepository->recordFailure('TEST-RANK', 'breaking', 'synthetic-failed-version', 4, 'Synthetic ranking failure');
$afterRankingFailure = $newsRepository->latestSnapshot('breaking', 'breaking', 15, 24);
stage4Ensure($afterRankingFailure['ranking_run']['ranking_version'] === $rankingConfig['version'], 'A failed ranking replaced the last successful ranked version.');
stage4Ensure(count($afterRankingFailure['articles']) === 1, 'A failed ranking removed the previous ranked briefing.');

$driftFixture = static fn (int $id, string $title, float $score): array => [
    'article' => ['id' => $id, 'title' => $title, 'published_at' => gmdate('Y-m-d H:i:s'), 'source_updated_at' => null],
    'deterministic_score' => $score,
];
$driftClusters = (new StoryClusterer())->cluster([
    $driftFixture(201, 'Alpha bravo charlie delta event', 90),
    $driftFixture(202, 'Alpha bravo charlie echo foxtrot golf', 80),
    $driftFixture(203, 'Echo foxtrot golf hotel event', 70),
], 24);
stage4Ensure(count($driftClusters) === 2, 'A cluster drifted through a related member into a different event.');

$newsRepository->startBatch('TEST-RANK-FAIL', 'breaking', 'manual_section');
$newsRepository->completeBatch('TEST-RANK-FAIL', 'breaking', 'breaking', [], [[
    'source_id' => 'official',
    'status' => 'failed',
    'http_status' => 503,
    'item_count' => 0,
    'started_at' => $now,
    'error' => 'Synthetic outage',
]], 90);
$retained = $newsRepository->latestSnapshot('breaking', 'breaking', 15, 24);
stage4Ensure($retained['ranking_run']['batch_id'] === 'TEST-RANK', 'A failed intake replaced the last successful ranked batch.');
stage4Ensure(count($retained['articles']) === 1, 'A failed intake removed the previous ranked briefing.');

echo "Stage 4 smoke test passed: score, cluster, corroborate, select, archive, and retain on failure.\n";
