<?php

declare(strict_types=1);

final class NewsPresenter
{
    public static function presentSnapshot(array $snapshot): array
    {
        $state = $snapshot['state'];
        $stories = array_map([self::class, 'presentArticle'], $snapshot['articles']);
        $status = $state['status'] ?? 'never';
        $llmState = $snapshot['llm_state'] ?? ['ready' => false, 'state' => 'pending', 'message' => 'Local-AI status is unavailable.', 'run' => null];
        $llmRun = $llmState['run'] ?? null;

        return [
            'stories' => $stories,
            'status' => $status,
            'stale' => $snapshot['stale'],
            'has_data' => $snapshot['has_data'],
            'archive_count' => $snapshot['archive_count'],
            'batch_id' => $snapshot['ranking_run']['batch_id'] ?? $state['published_batch_id'] ?? null,
            'last_success' => $state['last_success_at'] ?? null,
            'last_success_display' => self::dateLabel($state['last_success_at'] ?? null),
            'last_attempt_display' => self::dateLabel($state['last_attempt_at'] ?? null),
            'warning' => $state['warning'] ?? null,
            'latest_attempt' => $snapshot['latest_attempt'],
            'ranking_ready' => $snapshot['ranking_ready'] ?? false,
            'ranking_version' => $snapshot['ranking_run']['ranking_version'] ?? null,
            'candidate_count' => (int) ($snapshot['ranking_run']['candidate_count'] ?? 0),
            'cluster_count' => (int) ($snapshot['ranking_run']['cluster_count'] ?? 0),
            'selected_count' => (int) ($snapshot['ranking_run']['selected_count'] ?? count($stories)),
            'ranking_duration_ms' => isset($snapshot['ranking_run']['duration_ms'])
                ? (int) $snapshot['ranking_run']['duration_ms']
                : null,
            'visible_count' => count($stories),
            'llm_ready' => (bool) ($llmState['ready'] ?? false),
            'llm_status' => $llmState['state'] ?? 'pending',
            'llm_message' => $llmState['message'] ?? null,
            'llm_model' => $llmRun['resolved_model'] ?? $llmRun['model'] ?? null,
            'llm_candidate_count' => (int) ($llmRun['candidate_count'] ?? 0),
            'llm_selected_count' => (int) ($llmRun['selected_count'] ?? 0),
            'llm_duration_ms' => isset($llmRun['duration_ms']) ? (int) $llmRun['duration_ms'] : null,
        ];
    }

    private static function presentArticle(array $article): array
    {
        $trust = (int) $article['trust_level'];
        $type = (string) $article['source_type'];
        $typeLabel = ucwords(str_replace('_', ' ', $type));
        $ranked = array_key_exists('deterministic_score', $article);
        $llmCurrent = $ranked && (bool) ($article['llm_current'] ?? false);
        $sourceCount = (int) ($article['cluster_source_count'] ?? 1);
        $evidenceSourceCount = (int) ($article['cluster_evidence_source_count'] ?? 1);
        $reportCount = (int) ($article['cluster_article_count'] ?? 1);
        $relatedSources = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($article['related_sources'] ?? $article['source_name'])),
        )));
        $relatedLinks = self::relatedLinks((string) ($article['related_links_raw'] ?? ''));

        return [
            'article_id' => (int) $article['id'],
            'tag' => ($llmCurrent ? 'GEMMA' : ($ranked ? 'RANKED' : 'LIVE INTAKE')) . ' · ' . $typeLabel,
            'confidence' => $ranked ? ((int) $article['evidence_confidence']) . '/100' : 'Source ' . $trust . '/5',
            'confidence_title' => $ranked
                ? 'Evidence score combines configured source trust and distinct-publisher corroboration. It is not a probability that the claim is true.'
                : 'This is source-level trust before article-level evidence and corroboration are available.',
            'headline' => $article['title'],
            'summary' => $article['excerpt'] ?: 'This feed supplied no excerpt. Open the original source for the article details.',
            'why' => $ranked
                    ? ($article['deterministic_explanation'] ?? $article['why_selected'])
                    : self::whyItAppears($type, (string) $article['source_name']),
            'why_label' => $ranked ? 'Deterministic basis' : 'Why it appears',
            'source' => $article['source_name'] . ($article['author'] ? ' · ' . $article['author'] : ''),
            'published' => self::dateLabel($article['published_at']),
            'source_updated' => self::dateLabel($article['source_updated_at']),
            'retrieved' => self::dateLabel($article['last_retrieved_at']),
            'url' => $article['canonical_url'],
            'link_label' => 'Open original',
            'rank_score' => $ranked ? (float) $article['deterministic_score'] : null,
            'score_breakdown' => $ranked ? [
                'Importance' => (int) $article['importance_score'],
                'Relevance' => (int) $article['relevance_score'],
                'Evidence' => (int) $article['evidence_confidence'],
                'Practical' => (int) $article['practical_impact_score'],
                'Novelty' => (int) $article['novelty_score'],
            ] : [],
            'corroboration' => $ranked
                ? match (true) {
                    $evidenceSourceCount > 1 => sprintf(
                        '%d reputable publishers · %d total publishers · %d related reports',
                        $evidenceSourceCount,
                        $sourceCount,
                        $reportCount,
                    ),
                    $sourceCount > 1 => sprintf(
                        '1 evidence source · %d total publishers including signals/analysis · %d related reports',
                        $sourceCount,
                        $reportCount,
                    ),
                    default => 'Single-source cluster',
                }
                : null,
            'related_sources' => $relatedSources,
            'related_links' => $relatedLinks,
            'topic_tags' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($article['topic_tags'] ?? '')),
            ))),
            'deterministic_explanation' => $ranked ? ($article['deterministic_explanation'] ?? $article['why_selected']) : null,
            'business_angle' => null,
            'llm_model' => $llmCurrent ? $article['llm_model'] : null,
            'llm_reason_code' => $llmCurrent ? $article['why_selected'] : null,
            'llm_reason_label' => $llmCurrent ? self::reasonLabel((string) $article['why_selected']) : null,
            'feedback_action' => $article['feedback_action'] ?? null,
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

    private static function reasonLabel(string $code): string
    {
        return match ($code) {
            'major_consequence' => 'major consequence',
            'strong_profile_match' => 'strong profile match',
            'practical_impact' => 'practical impact',
            'well_corroborated' => 'well corroborated',
            'routine_update' => 'routine update',
            'weak_evidence' => 'weak evidence',
            'duplicate_event' => 'duplicate event',
            'low_profile_relevance' => 'low profile relevance',
            default => 'profile selection',
        };
    }

    /** @return list<array{name:string,url:string}> */
    private static function relatedLinks(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $links = [];
        foreach (explode(',', $raw) as $entry) {
            [$name, $url] = array_pad(explode(chr(31), $entry, 2), 2, '');
            if (trim($name) !== '' && filter_var(trim($url), FILTER_VALIDATE_URL)) {
                $links[] = ['name' => trim($name), 'url' => trim($url)];
            }
        }
        return $links;
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
