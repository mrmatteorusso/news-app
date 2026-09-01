<?php

declare(strict_types=1);

final class NewsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function seedSources(array $sources): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sources (id, name, url, category, geography, source_type, trust_level, enabled, refresh_method, notes)
             VALUES (:id, :name, :url, :category, :geography, :source_type, :trust_level, :enabled, :refresh_method, :notes)
             ON CONFLICT(id) DO UPDATE SET
                name = excluded.name, url = excluded.url, category = excluded.category,
                geography = excluded.geography, source_type = excluded.source_type,
                trust_level = excluded.trust_level, enabled = excluded.enabled,
                refresh_method = excluded.refresh_method, notes = excluded.notes'
        );
        foreach ($sources as $source) {
            $statement->execute([
                ':id' => $source['id'],
                ':name' => $source['name'],
                ':url' => $source['url'],
                ':category' => implode(',', $source['categories']),
                ':geography' => $source['geography'],
                ':source_type' => $source['source_type'],
                ':trust_level' => $source['trust_level'],
                ':enabled' => $source['enabled'] ? 1 : 0,
                ':refresh_method' => $source['refresh_method'],
                ':notes' => $source['notes'] ?? null,
            ]);
        }
    }

    public function startBatch(string $batchId, string $stateKey, string $trigger): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO refresh_batches (id, section, trigger_type, status, started_at)
             VALUES (:id, :section, :trigger, 'running', :started_at)"
        );
        $statement->execute([
            ':id' => $batchId,
            ':section' => $stateKey,
            ':trigger' => $trigger,
            ':started_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function completeBatch(
        string $batchId,
        string $stateKey,
        string $displaySection,
        array $items,
        array $calls,
        int $retentionDays,
    ): array {
        $completedAt = gmdate('Y-m-d H:i:s');
        $successCount = count(array_filter($calls, static fn (array $call): bool => $call['status'] === 'success'));
        $failedCalls = array_values(array_filter($calls, static fn (array $call): bool => $call['status'] === 'failed'));
        $status = $successCount === 0 ? 'failed' : ($failedCalls === [] ? 'success' : 'partial');
        $warnings = array_values(array_unique(array_filter(array_column($failedCalls, 'error'))));
        $warning = $warnings === [] ? null : implode(' ', $warnings);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($retentionDays * 86400));

        $this->pdo->beginTransaction();
        try {
            $fetchStatement = $this->pdo->prepare(
                'INSERT INTO source_fetches
                    (batch_id, source_id, request_kind, status, http_status, item_count, started_at, completed_at, error_message)
                 VALUES
                    (:batch_id, :source_id, \'scan\', :status, :http_status, :item_count, :started_at, :completed_at, :error_message)'
            );
            foreach ($calls as $call) {
                $fetchStatement->execute([
                    ':batch_id' => $batchId,
                    ':source_id' => $call['source_id'],
                    ':status' => $call['status'],
                    ':http_status' => $call['http_status'],
                    ':item_count' => $call['item_count'],
                    ':started_at' => $call['started_at'],
                    ':completed_at' => $completedAt,
                    ':error_message' => $call['error'],
                ]);
            }

            $articleStatement = $this->pdo->prepare(
                'INSERT INTO articles
                    (canonical_url, source_id, title, excerpt, author, published_at, source_updated_at,
                     first_retrieved_at, last_retrieved_at, content_hash, raw_payload, expires_at)
                 VALUES
                    (:canonical_url, :source_id, :title, :excerpt, :author, :published_at, :source_updated_at,
                     :first_retrieved_at, :last_retrieved_at, :content_hash, NULL, :expires_at)
                 ON CONFLICT(canonical_url) DO UPDATE SET
                    title = excluded.title,
                    excerpt = COALESCE(excluded.excerpt, articles.excerpt),
                    author = COALESCE(excluded.author, articles.author),
                    published_at = COALESCE(excluded.published_at, articles.published_at),
                    source_updated_at = COALESCE(excluded.source_updated_at, articles.source_updated_at),
                    last_retrieved_at = excluded.last_retrieved_at,
                    content_hash = excluded.content_hash,
                    expires_at = excluded.expires_at'
            );
            $articleIdStatement = $this->pdo->prepare('SELECT id FROM articles WHERE canonical_url = :canonical_url');
            $sectionStatement = $this->pdo->prepare(
                'INSERT INTO article_sections (article_id, section, first_seen_at, last_seen_at, last_batch_id)
                 VALUES (:article_id, :section, :first_seen_at, :last_seen_at, :last_batch_id)
                 ON CONFLICT(article_id, section) DO UPDATE SET
                    last_seen_at = excluded.last_seen_at,
                    last_batch_id = excluded.last_batch_id'
            );
            $tagStatement = $this->pdo->prepare(
                'INSERT INTO topic_tags (slug, label) VALUES (:slug, :label)
                 ON CONFLICT(slug) DO UPDATE SET label = excluded.label'
            );
            $articleTagStatement = $this->pdo->prepare(
                'INSERT INTO article_tags (article_id, tag_slug, assigned_at)
                 VALUES (:article_id, :tag_slug, :assigned_at)
                 ON CONFLICT(article_id, tag_slug) DO UPDATE SET assigned_at = excluded.assigned_at'
            );
            $clearArticleTags = $this->pdo->prepare('DELETE FROM article_tags WHERE article_id = :article_id');

            foreach ($items as $item) {
                $articleStatement->execute([
                    ':canonical_url' => $item['canonical_url'],
                    ':source_id' => $item['source_id'],
                    ':title' => $item['title'],
                    ':excerpt' => $item['excerpt'],
                    ':author' => $item['author'],
                    ':published_at' => $item['published_at'],
                    ':source_updated_at' => $item['source_updated_at'],
                    ':first_retrieved_at' => $completedAt,
                    ':last_retrieved_at' => $completedAt,
                    ':content_hash' => $item['content_hash'],
                    ':expires_at' => $expiresAt,
                ]);
                $articleIdStatement->execute([':canonical_url' => $item['canonical_url']]);
                $articleId = $articleIdStatement->fetchColumn();
                if ($articleId === false) {
                    continue;
                }
                $sections = array_values(array_unique([$displaySection, ...($item['extra_sections'] ?? [])]));
                foreach ($sections as $section) {
                    $sectionStatement->execute([
                        ':article_id' => (int) $articleId,
                        ':section' => $section,
                        ':first_seen_at' => $completedAt,
                        ':last_seen_at' => $completedAt,
                        ':last_batch_id' => $batchId,
                    ]);
                }
                $clearArticleTags->execute([':article_id' => (int) $articleId]);
                foreach ($item['topic_tags'] ?? [] as $tag) {
                    $tagStatement->execute([':slug' => $tag['slug'], ':label' => $tag['label']]);
                    $articleTagStatement->execute([
                        ':article_id' => (int) $articleId,
                        ':tag_slug' => $tag['slug'],
                        ':assigned_at' => $completedAt,
                    ]);
                }
            }

            $batchStatement = $this->pdo->prepare(
                'UPDATE refresh_batches
                 SET status = :status, completed_at = :completed_at, candidate_count = :candidate_count,
                     selected_count = :selected_count, error_summary = :error_summary
                 WHERE id = :id'
            );
            $batchStatement->execute([
                ':status' => $status,
                ':completed_at' => $completedAt,
                ':candidate_count' => count($items),
                ':selected_count' => count($items),
                ':error_summary' => $warning,
                ':id' => $batchId,
            ]);

            $stateStatement = $this->pdo->prepare(
                'INSERT INTO section_state (section, published_batch_id, last_success_at, last_attempt_at, status, warning)
                 VALUES (:section, :published_batch_id, :last_success_at, :last_attempt_at, :status, :warning)
                 ON CONFLICT(section) DO UPDATE SET
                    published_batch_id = CASE WHEN excluded.last_success_at IS NOT NULL THEN excluded.published_batch_id ELSE section_state.published_batch_id END,
                    last_success_at = COALESCE(excluded.last_success_at, section_state.last_success_at),
                    last_attempt_at = excluded.last_attempt_at,
                    status = excluded.status,
                    warning = excluded.warning'
            );
            $stateStatement->execute([
                ':section' => $stateKey,
                ':published_batch_id' => $successCount > 0 ? $batchId : null,
                ':last_success_at' => $successCount > 0 ? $completedAt : null,
                ':last_attempt_at' => $completedAt,
                ':status' => $status === 'success' ? 'ready' : $status,
                ':warning' => $warning,
            ]);

            $purge = $this->pdo->prepare('DELETE FROM articles WHERE expires_at IS NOT NULL AND expires_at < :now');
            $purge->execute([':now' => $completedAt]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'status' => $status,
            'candidate_count' => count($items),
            'source_success_count' => $successCount,
            'source_failure_count' => count($failedCalls),
            'warning' => $warning,
        ];
    }

    public function failRunningBatch(string $batchId, string $stateKey, string $message): void
    {
        $completedAt = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            "UPDATE refresh_batches SET status = 'failed', completed_at = :completed_at, error_summary = :message WHERE id = :id"
        );
        $statement->execute([':completed_at' => $completedAt, ':message' => $message, ':id' => $batchId]);
        $state = $this->pdo->prepare(
            "INSERT INTO section_state (section, last_attempt_at, status, warning)
             VALUES (:section, :last_attempt_at, 'failed', :warning)
             ON CONFLICT(section) DO UPDATE SET last_attempt_at = excluded.last_attempt_at, status = 'failed', warning = excluded.warning"
        );
        $state->execute([':section' => $stateKey, ':last_attempt_at' => $completedAt, ':warning' => $message]);
    }

    public function isFresh(string $stateKey, int $minutes): bool
    {
        $statement = $this->pdo->prepare('SELECT last_success_at FROM section_state WHERE section = :section');
        $statement->execute([':section' => $stateKey]);
        $lastSuccess = $statement->fetchColumn();
        return is_string($lastSuccess) && strtotime($lastSuccess . ' UTC') >= time() - ($minutes * 60);
    }

    public function latestSnapshot(
        string $displaySection,
        string $stateKey,
        int $cacheMinutes,
        int $limit,
        ?array $llmContext = null,
    ): array
    {
        $rankingStatement = $this->pdo->prepare(
            "SELECT rr.*
             FROM ranking_runs rr
             INNER JOIN refresh_batches rb ON rb.id = rr.batch_id
             WHERE rr.section = :display_section AND rb.section = :state_key AND rr.status = 'success'
             ORDER BY rr.completed_at DESC, rr.id DESC
             LIMIT 1"
        );
        $rankingStatement->execute([':display_section' => $displaySection, ':state_key' => $stateKey]);
        $rankingRun = $rankingStatement->fetch() ?: null;

        if ($rankingRun !== null) {
            $hasCurrentLlmRun = false;
            if ($llmContext !== null) {
                $llmRunStatement = $this->pdo->prepare(
                    "SELECT 1 FROM llm_runs
                     WHERE batch_id = :batch_id AND section = :section
                       AND model = :model AND prompt_version = :prompt_version
                       AND profile_hash = :profile_hash AND status IN ('success', 'skipped')
                     LIMIT 1"
                );
                $llmRunStatement->execute([
                    ':batch_id' => $rankingRun['batch_id'],
                    ':section' => $displaySection,
                    ':model' => $llmContext['model'],
                    ':prompt_version' => $llmContext['prompt_version'],
                    ':profile_hash' => $llmContext['profile_hash'],
                ]);
                $hasCurrentLlmRun = $llmRunStatement->fetchColumn() !== false;
            }
            $llmCurrentExpression = $llmContext === null
                ? '0'
                : '(ae.llm_prompt_version = :llm_prompt_version
                    AND ae.llm_profile_hash = :llm_profile_hash
                    AND ae.llm_requested_model = :llm_requested_model
                    AND ae.llm_model IS NOT NULL)';
            $selectionExpression = $hasCurrentLlmRun
                ? '(' . $llmCurrentExpression . ' AND ae.llm_selected = 1)'
                : '1 = 1';
            $statement = $this->pdo->prepare(
                'SELECT a.*, s.name AS source_name, s.source_type, s.trust_level, s.geography,
                        ae.deterministic_score, ae.importance_score, ae.relevance_score,
                        ae.evidence_confidence, ae.practical_impact_score, ae.novelty_score,
                        ae.summary AS evaluated_summary, ae.why_selected,
                        ae.deterministic_explanation, ae.business_angle, ae.llm_model,
                        ae.llm_selected, ae.llm_relevance_score, ae.llm_requested_model, ae.llm_evaluated_at,
                        ae.ranking_version, sc.id AS cluster_id,
                        ' . $llmCurrentExpression . ' AS llm_current,
                        COUNT(DISTINCT ca.article_id) AS cluster_article_count,
                        COUNT(DISTINCT related.source_id) AS cluster_source_count,
                        COUNT(DISTINCT CASE
                            WHEN related_source.trust_level >= 3
                             AND related_source.source_type NOT IN (\'signal\', \'contrarian\')
                            THEN related.source_id END) AS cluster_evidence_source_count,
                        GROUP_CONCAT(DISTINCT related_source.name) AS related_sources,
                        GROUP_CONCAT(DISTINCT CASE WHEN related.id <> a.id
                            THEN related_source.name || char(31) || related.canonical_url END) AS related_links_raw,
                        (SELECT GROUP_CONCAT(tt.label)
                         FROM article_tags art
                         INNER JOIN topic_tags tt ON tt.slug = art.tag_slug
                         WHERE art.article_id = a.id) AS topic_tags,
                        (SELECT fe.action FROM feedback_events fe
                         WHERE fe.article_id = a.id AND fe.section = sc.section
                         ORDER BY fe.id DESC LIMIT 1) AS feedback_action
                 FROM story_clusters sc
                 INNER JOIN articles a ON a.id = sc.representative_article_id
                 INNER JOIN sources s ON s.id = a.source_id
                 INNER JOIN article_evaluations ae
                    ON ae.article_id = a.id AND ae.batch_id = sc.batch_id AND ae.section = sc.section
                 LEFT JOIN cluster_articles ca ON ca.cluster_id = sc.id
                 LEFT JOIN articles related ON related.id = ca.article_id
                 LEFT JOIN sources related_source ON related_source.id = related.source_id
                 WHERE sc.batch_id = :batch_id AND sc.section = :section AND ae.selected = 1
                   AND ' . $selectionExpression . '
                   AND (a.expires_at IS NULL OR a.expires_at >= :now)
                 GROUP BY sc.id, a.id, ae.id
                 ORDER BY ae.deterministic_score DESC,
                          COALESCE(a.source_updated_at, a.published_at, a.first_retrieved_at) DESC
                 LIMIT :limit'
            );
            $statement->bindValue(':batch_id', $rankingRun['batch_id']);
            $statement->bindValue(':section', $displaySection);
            $statement->bindValue(':now', gmdate('Y-m-d H:i:s'));
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($llmContext !== null) {
                $statement->bindValue(':llm_prompt_version', $llmContext['prompt_version']);
                $statement->bindValue(':llm_profile_hash', $llmContext['profile_hash']);
                $statement->bindValue(':llm_requested_model', $llmContext['model']);
            }
            $statement->execute();
            $articles = $statement->fetchAll();
        } else {
            $statement = $this->pdo->prepare(
                'SELECT a.*, s.name AS source_name, s.source_type, s.trust_level, s.geography
                 FROM article_sections ars
                 INNER JOIN articles a ON a.id = ars.article_id
                 INNER JOIN sources s ON s.id = a.source_id
                 WHERE ars.section = :section AND (a.expires_at IS NULL OR a.expires_at >= :now)
                 ORDER BY COALESCE(a.source_updated_at, a.published_at, a.first_retrieved_at) DESC, a.id DESC
                 LIMIT :limit'
            );
            $statement->bindValue(':section', $displaySection);
            $statement->bindValue(':now', gmdate('Y-m-d H:i:s'));
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $articles = $statement->fetchAll();
        }

        $stateStatement = $this->pdo->prepare('SELECT * FROM section_state WHERE section = :section');
        $stateStatement->execute([':section' => $stateKey]);
        $state = $stateStatement->fetch() ?: null;
        $lastSuccess = $state['last_success_at'] ?? null;
        $stale = !is_string($lastSuccess) || strtotime($lastSuccess . ' UTC') < time() - ($cacheMinutes * 60);

        $archiveStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM article_sections ars
             INNER JOIN articles a ON a.id = ars.article_id
             WHERE ars.section = :section AND (a.expires_at IS NULL OR a.expires_at >= :now)'
        );
        $archiveStatement->execute([':section' => $displaySection, ':now' => gmdate('Y-m-d H:i:s')]);

        $latestBatch = $this->pdo->prepare(
            'SELECT id, status FROM refresh_batches WHERE section = :section ORDER BY started_at DESC LIMIT 1'
        );
        $latestBatch->execute([':section' => $stateKey]);

        return [
            'articles' => $articles,
            'state' => $state,
            'stale' => $stale,
            'has_data' => $articles !== [],
            'archive_count' => (int) $archiveStatement->fetchColumn(),
            'latest_attempt' => $latestBatch->fetch() ?: null,
            'ranking_ready' => $rankingRun !== null,
            'ranking_run' => $rankingRun,
        ];
    }

    public function sourceHealth(array $sourceIds): array
    {
        $health = [];
        $latest = $this->pdo->prepare(
            'SELECT status, http_status, item_count, completed_at, error_message
             FROM source_fetches WHERE source_id = :source_id ORDER BY id DESC LIMIT 1'
        );
        $success = $this->pdo->prepare(
            "SELECT MAX(completed_at) FROM source_fetches WHERE source_id = :source_id AND status = 'success'"
        );
        foreach ($sourceIds as $sourceId) {
            $latest->execute([':source_id' => $sourceId]);
            $row = $latest->fetch() ?: null;
            $success->execute([':source_id' => $sourceId]);
            $lastSuccess = $success->fetchColumn();
            $health[$sourceId] = [
                'status' => $row === null ? 'warning' : ($row['status'] === 'success' ? 'healthy' : (is_string($lastSuccess) ? 'warning' : 'down')),
                'last_success' => is_string($lastSuccess) ? $lastSuccess : null,
                'latest' => $row,
            ];
        }
        return $health;
    }
}
