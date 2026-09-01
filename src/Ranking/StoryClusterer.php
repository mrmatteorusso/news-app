<?php

declare(strict_types=1);

final class StoryClusterer
{
    private const STOP_WORDS = [
        'about', 'after', 'again', 'against', 'among', 'announces', 'before', 'being', 'between',
        'could', 'from', 'have', 'into', 'latest', 'more', 'news', 'over', 'says', 'that', 'their',
        'there', 'these', 'they', 'this', 'through', 'under', 'update', 'with', 'would', 'your',
        'world', 'market', 'markets', 'business', 'technology', 'crypto', 'bitcoin', 'ethereum',
        'artificial', 'intelligence', 'model', 'models', 'report', 'reports', 'amid', 'plans', 'new',
    ];

    public function cluster(array $evaluations, int $clusterHours): array
    {
        usort($evaluations, static fn (array $a, array $b): int => $b['deterministic_score'] <=> $a['deterministic_score']);
        $clusters = [];

        foreach ($evaluations as $evaluation) {
            $tokens = $this->tokens((string) $evaluation['article']['title']);
            $timestamp = $this->timestamp($evaluation['article']['source_updated_at'] ?? $evaluation['article']['published_at'] ?? null);
            $matchedIndex = null;
            $bestSimilarity = 0.0;

            foreach ($clusters as $index => $cluster) {
                if (!$this->withinWindow($timestamp, $cluster['timestamp'], $clusterHours)) {
                    continue;
                }
                $similarity = $this->similarity($tokens, $cluster['tokens']);
                if ($similarity > $bestSimilarity && $this->isMatch($tokens, $cluster['tokens'], $similarity)) {
                    $bestSimilarity = $similarity;
                    $matchedIndex = $index;
                }
            }

            if ($matchedIndex === null) {
                $clusters[] = [
                    'tokens' => $tokens,
                    'timestamp' => $timestamp,
                    'members' => [$evaluation],
                ];
            } else {
                $clusters[$matchedIndex]['members'][] = $evaluation;
                // Keep the highest-ranked representative's tokens fixed. Growing a
                // union here permits transitive topic drift: A resembles B, B
                // resembles C, but A and C may describe different events.
            }
        }

        foreach ($clusters as &$cluster) {
            $articleIds = array_map(static fn (array $member): int => (int) $member['article']['id'], $cluster['members']);
            sort($articleIds);
            $cluster['cluster_key'] = hash('sha256', implode('|', $articleIds));
        }
        unset($cluster);
        return $clusters;
    }

    private function tokens(string $title): array
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($title, 'UTF-8'));
        $ascii = is_string($ascii) ? $ascii : mb_strtolower($title, 'UTF-8');
        $parts = preg_split('/[^a-z0-9]+/', strtolower($ascii), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) < 3 || in_array($part, self::STOP_WORDS, true)) {
                continue;
            }
            $tokens[] = $part;
        }
        return array_values(array_unique($tokens));
    }

    private function similarity(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }
        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique([...$left, ...$right]));
        return $union === 0 ? 0.0 : $intersection / $union;
    }

    private function isMatch(array $left, array $right, float $jaccard): bool
    {
        $shared = array_values(array_intersect($left, $right));
        $intersection = count($shared);
        $containment = min(count($left), count($right)) === 0
            ? 0.0
            : $intersection / min(count($left), count($right));
        $hasSpecificToken = count(array_filter($shared, static fn (string $token): bool => strlen($token) >= 5)) > 0;
        return $hasSpecificToken && (($intersection >= 3 && ($jaccard >= 0.28 || $containment >= 0.55)) || ($intersection >= 2 && $jaccard >= 0.45));
    }

    private function timestamp(?string $value): ?int
    {
        if (!$value) {
            return null;
        }
        $timestamp = strtotime($value . ' UTC');
        return $timestamp === false ? null : $timestamp;
    }

    private function withinWindow(?int $left, ?int $right, int $hours): bool
    {
        return $left === null || $right === null || abs($left - $right) <= ($hours * 3600);
    }
}
