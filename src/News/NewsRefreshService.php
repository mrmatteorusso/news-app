<?php

declare(strict_types=1);

final class NewsRefreshService
{
    public function __construct(
        private readonly NewsRepository $repository,
        private readonly FeedParser $parser,
        private readonly HttpClient $http,
        private readonly TopicTagger $tagger,
        private readonly array $sources,
        private readonly array $config,
        private readonly RankingService $ranking,
        private readonly LlmEnrichmentService $enrichment,
    ) {
    }

    public function refresh(string $displaySection, string $trigger): array
    {
        $section = $this->sectionConfig($displaySection);
        if ($trigger === 'page_open' && $this->repository->isFresh($section['state_key'], $section['cache_minutes'])) {
            $ranking = $this->attemptRanking($displaySection);
            return [
                'skipped_cache' => true,
                'batch_id' => null,
                'result' => ['status' => 'cached', 'candidate_count' => 0, 'warning' => null],
                'ranking' => $ranking,
                'snapshot' => $this->currentSnapshot($displaySection, $section),
            ];
        }

        $sources = $this->activeSources($section['source_category']);
        if ($sources === []) {
            throw new RuntimeException('No active live feeds are configured for this section.');
        }

        $batchId = strtoupper(substr($section['state_key'], 0, 3)) . '-NEWS-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $this->repository->startBatch($batchId, $section['state_key'], $trigger);
        $batchCompleted = false;

        try {
            $requests = [];
            foreach ($sources as $source) {
                $requests[$source['id']] = ['url' => $source['url']];
            }
            $responses = $this->http->getMany($requests);
            $itemsByUrl = [];
            $calls = [];

            foreach ($sources as $source) {
                $response = $responses[$source['id']] ?? [
                    'ok' => false,
                    'status' => 0,
                    'body' => '',
                    'error' => 'No HTTP response was returned.',
                    'duration_ms' => 0,
                ];
                $callStarted = gmdate('Y-m-d H:i:s', time() - max(0, (int) ceil($response['duration_ms'] / 1000)));
                if (!$response['ok']) {
                    $calls[] = [
                        'source_id' => $source['id'],
                        'status' => 'failed',
                        'http_status' => $response['status'] ?: null,
                        'item_count' => 0,
                        'started_at' => $callStarted,
                        'error' => $source['name'] . ': ' . ($response['error'] ?: 'HTTP ' . $response['status']),
                    ];
                    continue;
                }

                try {
                    $parsed = $this->parser->parse($response['body'], $source['url']);
                    $accepted = [];
                    foreach ($parsed as $item) {
                        if (!$this->withinIntakeWindow($item['published_at'], $section['ingest_max_age_hours'])) {
                            continue;
                        }
                        $item['source_id'] = $source['id'];
                        $classification = $this->tagger->classify($item['title'], $item['excerpt'], $displaySection);
                        $item['topic_tags'] = $classification['topics'];
                        $item['extra_sections'] = $classification['extra_sections'];
                        $accepted[] = $item;
                        $itemsByUrl[$item['canonical_url']] = $item;
                    }
                    $calls[] = [
                        'source_id' => $source['id'],
                        'status' => 'success',
                        'http_status' => $response['status'],
                        'item_count' => count($accepted),
                        'started_at' => $callStarted,
                        'error' => null,
                    ];
                } catch (Throwable $exception) {
                    $calls[] = [
                        'source_id' => $source['id'],
                        'status' => 'failed',
                        'http_status' => $response['status'],
                        'item_count' => 0,
                        'started_at' => $callStarted,
                        'error' => $source['name'] . ': ' . $exception->getMessage(),
                    ];
                }
            }

            $result = $this->repository->completeBatch(
                $batchId,
                $section['state_key'],
                $displaySection,
                array_values($itemsByUrl),
                $calls,
                $this->config['retention_days'],
            );
            $batchCompleted = true;
            $ranking = $result['status'] === 'failed'
                ? ['ranked' => false, 'reason' => 'intake_failed']
                : $this->attemptRanking($displaySection, $batchId);
            return [
                'skipped_cache' => false,
                'batch_id' => $batchId,
                'result' => $result,
                'ranking' => $ranking,
                'snapshot' => $this->currentSnapshot($displaySection, $section),
            ];
        } catch (Throwable $exception) {
            if (!$batchCompleted) {
                $this->repository->failRunningBatch($batchId, $section['state_key'], $exception->getMessage());
            }
            throw $exception;
        }
    }

    public function snapshot(string $displaySection): array
    {
        $section = $this->sectionConfig($displaySection);
        $this->attemptRanking($displaySection);
        return $this->currentSnapshot($displaySection, $section);
    }

    private function currentSnapshot(string $displaySection, array $section): array
    {
        $llmContext = null;
        try {
            $llmContext = $this->enrichment->context($displaySection);
        } catch (Throwable $exception) {
            error_log('Unable to load Stage 5 profile context: ' . $exception->getMessage());
        }
        $snapshot = $this->repository->latestSnapshot(
            $displaySection,
            $section['state_key'],
            $section['cache_minutes'],
            $section['display_limit'],
            $llmContext,
        );
        $snapshot['llm_state'] = $this->enrichment->status(
            $displaySection,
            $snapshot['ranking_run']['batch_id'] ?? null,
        );
        return $snapshot;
    }

    private function attemptRanking(string $displaySection, ?string $batchId = null): array
    {
        try {
            return $this->ranking->ensureRanked($displaySection, $batchId);
        } catch (Throwable $exception) {
            error_log(sprintf('Stage 4 ranking failed for %s: %s', $displaySection, $exception->getMessage()));
            return [
                'ranked' => false,
                'reason' => 'ranking_failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function activeSources(?string $sourceCategory = null): array
    {
        return array_values(array_filter($this->sources, static function (array $source) use ($sourceCategory): bool {
            $active = ($source['enabled'] ?? false)
                && ($source['stage3_active'] ?? false)
                && in_array($source['refresh_method'] ?? '', ['rss', 'atom'], true);
            return $active && ($sourceCategory === null || in_array($sourceCategory, $source['categories'] ?? [], true));
        }));
    }

    private function sectionConfig(string $displaySection): array
    {
        if (!isset($this->config['sections'][$displaySection])) {
            throw new InvalidArgumentException('Unsupported news section.');
        }
        return $this->config['sections'][$displaySection];
    }

    private function withinIntakeWindow(?string $publishedAt, int $maxAgeHours): bool
    {
        if ($publishedAt === null) {
            return true;
        }
        $timestamp = strtotime($publishedAt . ' UTC');
        return $timestamp === false || $timestamp >= time() - ($maxAgeHours * 3600);
    }
}
