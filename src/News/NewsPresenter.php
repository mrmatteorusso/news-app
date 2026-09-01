<?php

declare(strict_types=1);

final class NewsPresenter
{
    public static function presentSnapshot(array $snapshot): array
    {
        $state = $snapshot['state'];
        $stories = array_map([self::class, 'presentArticle'], $snapshot['articles']);
        $status = $state['status'] ?? 'never';

        return [
            'stories' => $stories,
            'status' => $status,
            'stale' => $snapshot['stale'],
            'has_data' => $snapshot['has_data'],
            'archive_count' => $snapshot['archive_count'],
            'batch_id' => $state['published_batch_id'] ?? null,
            'last_success' => $state['last_success_at'] ?? null,
            'last_success_display' => self::dateLabel($state['last_success_at'] ?? null),
            'last_attempt_display' => self::dateLabel($state['last_attempt_at'] ?? null),
            'warning' => $state['warning'] ?? null,
            'latest_attempt' => $snapshot['latest_attempt'],
        ];
    }

    private static function presentArticle(array $article): array
    {
        $trust = (int) $article['trust_level'];
        $type = (string) $article['source_type'];
        $typeLabel = ucwords(str_replace('_', ' ', $type));

        return [
            'article_id' => (int) $article['id'],
            'tag' => 'LIVE INTAKE · ' . $typeLabel,
            'confidence' => 'Source ' . $trust . '/5',
            'confidence_title' => 'This is source-level trust, not yet article-level evidence confidence. Stage 4 adds corroboration and ranking.',
            'headline' => $article['title'],
            'summary' => $article['excerpt'] ?: 'This feed supplied no excerpt. Open the original source for the article details.',
            'why' => self::whyItAppears($type, (string) $article['source_name']),
            'why_label' => 'Why it appears',
            'source' => $article['source_name'] . ($article['author'] ? ' · ' . $article['author'] : ''),
            'published' => self::dateLabel($article['published_at']),
            'source_updated' => self::dateLabel($article['source_updated_at']),
            'retrieved' => self::dateLabel($article['last_retrieved_at']),
            'url' => $article['canonical_url'],
            'link_label' => 'Open original',
        ];
    }

    private static function whyItAppears(string $type, string $sourceName): string
    {
        return match ($type) {
            'primary', 'primary_community' => 'Recent primary-source candidate from ' . $sourceName . '. Stage 4 will judge consequence and relevance.',
            'contrarian' => 'Recent skeptical counterweight from ' . $sourceName . '; it is analysis, not primary evidence, and will require corroboration.',
            'signal' => 'Recent discovery signal from ' . $sourceName . '. It is not verified news and must be traced to an original source.',
            'field_expert' => 'Recent specialist analysis from ' . $sourceName . '. Stage 4 will test whether the evidence and practical impact justify selection.',
            default => 'Recent feed candidate from ' . $sourceName . '. It has passed safe intake and recency checks but has not yet been importance-ranked.',
        };
    }

    private static function dateLabel(?string $value): string
    {
        if (!$value) {
            return 'Not supplied';
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('d M Y H:i');
        } catch (Throwable) {
            return 'Not supplied';
        }
    }
}
