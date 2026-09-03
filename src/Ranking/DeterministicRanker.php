<?php

declare(strict_types=1);

final class DeterministicRanker
{
    public function __construct(private readonly array $config)
    {
    }

    public function evaluate(array $article, string $section): array
    {
        $rules = $this->config['sections'][$section];
        $title = $this->normalise((string) $article['title']);
        $text = $this->normalise((string) $article['title'] . ' ' . (string) ($article['excerpt'] ?? ''));

        [$generalImportance, $generalMatches] = $this->termScore($title, $text, $this->config['general']['importance_terms']);
        [$sectionImportance, $sectionImportanceMatches] = $this->termScore($title, $text, $rules['importance_terms']);
        [$relevanceTerms, $relevanceMatches] = $this->termScore($title, $text, $rules['relevance_terms']);
        [$practicalTerms, $practicalMatches] = $this->termScore($title, $text, $this->config['general']['practical_terms']);
        [$penalty, $penaltyMatches] = $this->termScore($title, $text, $this->config['general']['low_value_terms'], false);

        $importance = $this->bound(10 + $generalImportance + $sectionImportance - ($penalty * 0.75));
        $relevance = $this->bound(22 + $relevanceTerms - ($penalty * 0.55));
        $practical = $this->bound(8 + $practicalTerms - ($penalty * 0.35));
        $evidence = $this->sourceEvidence((int) $article['trust_level'], (string) $article['source_type']);
        $novelty = $this->noveltyScore($article['source_updated_at'] ?? $article['published_at'] ?? null);

        if (preg_match('/\b\d+(?:\.\d+)?\s*(?:%|bn|billion|million|trillion|€|\$)\b/u', $text) === 1) {
            $importance = $this->bound($importance + 5);
            $practical = $this->bound($practical + 4);
        }
        if (str_ends_with(trim((string) $article['title']), '?')) {
            $importance = $this->bound($importance - 5);
        }

        $scores = [
            'importance' => $importance,
            'relevance' => $relevance,
            'evidence' => $evidence,
            'practical_impact' => $practical,
            'novelty' => $novelty,
        ];

        return [
            'article' => $article,
            'scores' => $scores,
            'deterministic_score' => $this->weightedTotal($scores),
            'matches' => [
                'importance' => array_slice(array_values(array_unique([...$sectionImportanceMatches, ...$generalMatches])), 0, 4),
                'relevance' => array_slice(array_values(array_unique($relevanceMatches)), 0, 4),
                'practical' => array_slice(array_values(array_unique($practicalMatches)), 0, 3),
                'penalties' => array_slice(array_values(array_unique($penaltyMatches)), 0, 3),
            ],
            'selected' => false,
            'why' => '',
        ];
    }

    public function applyCorroboration(array $evaluation, array $cluster): array
    {
        $sourceCount = $cluster['source_count'];
        $reputableCount = $cluster['reputable_source_count'];
        $evidenceBoost = min(24, max(0, $reputableCount - 1) * 9);
        if ($cluster['has_primary'] && $reputableCount > 1) {
            $evidenceBoost += 5;
        }
        $evaluation['scores']['evidence'] = $this->bound($evaluation['scores']['evidence'] + $evidenceBoost);
        if ($reputableCount >= 2) {
            $evaluation['scores']['importance'] = $this->bound($evaluation['scores']['importance'] + min(8, ($reputableCount - 1) * 3));
        }
        $evaluation['deterministic_score'] = $this->weightedTotal($evaluation['scores']);
        return $evaluation;
    }

    public function qualifies(array $evaluation, string $section, array $cluster): bool
    {
        $rules = $this->config['sections'][$section];
        $type = (string) $evaluation['article']['source_type'];
        $unsupportedAnalysis = in_array($type, ['signal', 'contrarian'], true) && $cluster['reputable_source_count'] < 1;
        if ($unsupportedAnalysis) {
            return false;
        }

        return $evaluation['deterministic_score'] >= $rules['selection_threshold']
            && $evaluation['scores']['importance'] >= $rules['minimum_importance']
            && $evaluation['scores']['relevance'] >= $rules['minimum_relevance'];
    }

    public function explanation(array $evaluation, string $section, array $cluster, bool $selected): string
    {
        $scores = $evaluation['scores'];
        $rules = $this->config['sections'][$section];
        $matched = array_values(array_unique([
            ...$evaluation['matches']['importance'],
            ...$evaluation['matches']['relevance'],
        ]));
        $matchText = $matched === [] ? 'its category and source context' : implode(', ', array_slice($matched, 0, 4));
        $corroboration = match (true) {
            $cluster['reputable_source_count'] > 1 => sprintf(
                '%d reputable, distinct publishers corroborate the cluster',
                $cluster['reputable_source_count'],
            ),
            $cluster['source_count'] > 1 => sprintf(
                'one reputable report is accompanied by %d signal or analysis source(s)',
                $cluster['source_count'] - 1,
            ),
            default => 'it is currently a single-source cluster',
        };

        if ($selected) {
            return sprintf(
                'Selected deterministically: it matched %s; %s. Total %.1f/100 (importance %d, relevance %d, evidence %d).',
                $matchText,
                $corroboration,
                $evaluation['deterministic_score'],
                $scores['importance'],
                $scores['relevance'],
                $scores['evidence'],
            );
        }

        $reasons = [];
        if ($evaluation['deterministic_score'] < $rules['selection_threshold']) {
            $reasons[] = sprintf('total %.1f is below %.0f', $evaluation['deterministic_score'], $rules['selection_threshold']);
        }
        if ($scores['importance'] < $rules['minimum_importance']) {
            $reasons[] = 'importance is below the category minimum';
        }
        if ($scores['relevance'] < $rules['minimum_relevance']) {
            $reasons[] = 'relevance is below the category minimum';
        }
        if (in_array($evaluation['article']['source_type'], ['signal', 'contrarian'], true) && $cluster['reputable_source_count'] < 1) {
            $reasons[] = 'an uncorroborated signal or contrarian analysis cannot be promoted as fact';
        }
        return 'Not selected: ' . implode('; ', $reasons ?: ['it did not pass the deterministic rule set']) . '.';
    }

    private function termScore(string $title, string $text, array $terms, bool $preferTitle = true): array
    {
        $score = 0.0;
        $matches = [];
        foreach ($terms as $term => $weight) {
            $needle = $this->normalise((string) $term);
            if ($needle === '' || !str_contains($text, $needle)) {
                continue;
            }
            $score += (float) $weight * ($preferTitle && str_contains($title, $needle) ? 1.25 : 0.75);
            $matches[] = trim((string) $term);
        }
        return [$score, $matches];
    }

    private function sourceEvidence(int $trust, string $type): int
    {
        $boost = match ($type) {
            'primary' => 18,
            'primary_community' => 12,
            'mainstream' => 9,
            'field_expert' => 7,
            'investigative' => 6,
            'specialist', 'ecosystem' => 4,
            'contrarian' => 1,
            default => 0,
        };
        $score = ($trust * 13) + $boost;
        if ($type === 'signal') {
            $score = min($score, 28);
        }
        return $this->bound($score);
    }

    private function noveltyScore(?string $value): int
    {
        if (!$value) {
            return 38;
        }
        $timestamp = strtotime($value . ' UTC');
        if ($timestamp === false) {
            return 38;
        }
        $hours = max(0, (time() - $timestamp) / 3600);
        return match (true) {
            $hours <= 6 => 100,
            $hours <= 24 => 90,
            $hours <= 48 => 78,
            $hours <= 96 => 62,
            $hours <= 168 => 46,
            $hours <= 336 => 28,
            default => 15,
        };
    }

    private function weightedTotal(array $scores): float
    {
        $total = 0.0;
        foreach ($this->config['weights'] as $key => $weight) {
            $total += $scores[$key] * $weight;
        }
        return round($total, 1);
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}%€$]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return ' ' . trim($value) . ' ';
    }

    private function bound(float|int $value): int
    {
        return (int) round(max(0, min(100, $value)));
    }
}
