<?php

declare(strict_types=1);

final class RankingService
{
    public function __construct(
        private readonly RankingRepository $repository,
        private readonly DeterministicRanker $ranker,
        private readonly StoryClusterer $clusterer,
        private readonly array $rankingConfig,
        private readonly array $newsConfig,
    ) {
    }

    public function ensureRanked(string $section, ?string $batchId = null): array
    {
        $sectionConfig = $this->newsConfig['sections'][$section] ?? null;
        if (!is_array($sectionConfig)) {
            throw new InvalidArgumentException('Unsupported ranking section.');
        }
        $batchId ??= $this->repository->publishedBatchId($sectionConfig['state_key']);
        if ($batchId === null) {
            return ['ranked' => false, 'reason' => 'no_published_batch'];
        }
        $version = $this->rankingConfig['version'];
        if ($this->repository->hasSuccessfulRun($batchId, $section, $version)) {
            return ['ranked' => false, 'reason' => 'already_ranked', 'batch_id' => $batchId];
        }
        return $this->rank($section, $batchId);
    }

    public function rank(string $section, string $batchId): array
    {
        $started = microtime(true);
        $sectionRules = $this->rankingConfig['sections'][$section] ?? null;
        $newsSection = $this->newsConfig['sections'][$section] ?? null;
        if (!is_array($sectionRules) || !is_array($newsSection)) {
            throw new InvalidArgumentException('Unsupported ranking section.');
        }

        try {
            $articles = $this->repository->candidates(
                $section,
                $newsSection['ingest_max_age_hours'],
                $this->rankingConfig['candidate_limit'],
            );
            $evaluations = array_map(
                fn (array $article): array => $this->ranker->evaluate($article, $section),
                $articles,
            );
            $clusters = $this->clusterer->cluster($evaluations, $sectionRules['cluster_hours']);
            $evaluationsById = [];
            $persistedClusters = [];
            $selectedCount = 0;

            foreach ($clusters as $cluster) {
                $sources = [];
                $reputableSources = [];
                $types = [];
                foreach ($cluster['members'] as $member) {
                    $sources[$member['article']['source_id']] = $member['article']['source_name'];
                    $types[] = $member['article']['source_type'];
                    if ((int) $member['article']['trust_level'] >= 3
                        && !in_array($member['article']['source_type'], ['signal', 'contrarian'], true)) {
                        $reputableSources[$member['article']['source_id']] = true;
                    }
                }
                $clusterContext = [
                    'source_count' => count($sources),
                    'reputable_source_count' => count($reputableSources),
                    'has_primary' => count(array_intersect($types, ['primary', 'primary_community'])) > 0,
                    'source_names' => array_values($sources),
                ];

                $rankedMembers = [];
                foreach ($cluster['members'] as $member) {
                    $rankedMembers[] = $this->ranker->applyCorroboration($member, $clusterContext);
                }
                usort($rankedMembers, static fn (array $a, array $b): int => $b['deterministic_score'] <=> $a['deterministic_score']);
                $representative = $this->chooseRepresentative($rankedMembers);
                $selected = $this->ranker->qualifies($representative, $section, $clusterContext);
                if ($selected) {
                    $selectedCount++;
                }

                foreach ($rankedMembers as $member) {
                    $isRepresentative = (int) $member['article']['id'] === (int) $representative['article']['id'];
                    $member['selected'] = $isRepresentative && $selected;
                    $member['why'] = $isRepresentative
                        ? $this->ranker->explanation($member, $section, $clusterContext, $selected)
                        : 'Grouped as a related report under the cluster representative; it remains available in the archive.';
                    $member['cluster'] = $clusterContext;
                    $evaluationsById[$member['article']['id']] = $member;
                }

                $persistedClusters[] = [
                    'cluster_key' => $cluster['cluster_key'],
                    'representative_id' => (int) $representative['article']['id'],
                    'article_ids' => array_map(static fn (array $member): int => (int) $member['article']['id'], $rankedMembers),
                ];
            }

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $this->repository->save(
                $batchId,
                $section,
                $this->rankingConfig['version'],
                array_values($evaluationsById),
                $persistedClusters,
                $selectedCount,
                $durationMs,
            );
            return [
                'ranked' => true,
                'batch_id' => $batchId,
                'candidate_count' => count($evaluationsById),
                'cluster_count' => count($persistedClusters),
                'selected_count' => $selectedCount,
                'duration_ms' => $durationMs,
            ];
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $this->repository->recordFailure($batchId, $section, $this->rankingConfig['version'], $durationMs, $exception->getMessage());
            throw $exception;
        }
    }

    private function chooseRepresentative(array $members): array
    {
        $best = $members[0];
        foreach ($members as $member) {
            if (!in_array($member['article']['source_type'], ['primary', 'primary_community'], true)) {
                continue;
            }
            if ($member['deterministic_score'] >= $best['deterministic_score'] - 6) {
                return $member;
            }
        }
        return $best;
    }
}
