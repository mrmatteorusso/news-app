<?php

declare(strict_types=1);

final class LlmRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function latestRankedBatch(string $section): ?string
    {
        $statement = $this->pdo->prepare(
            "SELECT batch_id FROM ranking_runs
             WHERE section = :section AND status = 'success'
             ORDER BY completed_at DESC, id DESC LIMIT 1"
        );
        $statement->execute([':section' => $section]);
        $batchId = $statement->fetchColumn();
        return is_string($batchId) ? $batchId : null;
    }

    public function candidates(string $batchId, string $section, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id AS article_id, a.title, a.excerpt, a.published_at, a.source_updated_at,
                    s.name AS source_name, s.source_type, s.trust_level,
                    ae.deterministic_score, ae.importance_score, ae.relevance_score,
                    ae.evidence_confidence, ae.practical_impact_score, ae.novelty_score,
                    ae.deterministic_explanation,
                    COUNT(DISTINCT ca.article_id) AS related_report_count,
                    COUNT(DISTINCT related.source_id) AS distinct_source_count,
                    COUNT(DISTINCT CASE
                        WHEN related_source.trust_level >= 3
                         AND related_source.source_type NOT IN (\'signal\', \'contrarian\')
                        THEN related.source_id END) AS reputable_source_count
             FROM story_clusters sc
             INNER JOIN articles a ON a.id = sc.representative_article_id
             INNER JOIN sources s ON s.id = a.source_id
             INNER JOIN article_evaluations ae
                ON ae.article_id = a.id AND ae.batch_id = sc.batch_id AND ae.section = sc.section
             LEFT JOIN cluster_articles ca ON ca.cluster_id = sc.id
             LEFT JOIN articles related ON related.id = ca.article_id
             LEFT JOIN sources related_source ON related_source.id = related.source_id
             WHERE sc.batch_id = :batch_id AND sc.section = :section AND ae.selected = 1
             GROUP BY sc.id, a.id, ae.id
             ORDER BY ae.deterministic_score DESC
             LIMIT :limit'
        );
        $statement->bindValue(':batch_id', $batchId);
        $statement->bindValue(':section', $section);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function latestAttempt(string $batchId, string $section, string $model, string $promptVersion, string $profileHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM llm_runs
             WHERE batch_id = :batch_id AND section = :section AND model = :model
               AND prompt_version = :prompt_version AND profile_hash = :profile_hash
             ORDER BY id DESC LIMIT 1'
        );
        $statement->execute([
            ':batch_id' => $batchId,
            ':section' => $section,
            ':model' => $model,
            ':prompt_version' => $promptVersion,
            ':profile_hash' => $profileHash,
        ]);
        return $statement->fetch() ?: null;
    }

    public function latestSuccessfulRun(string $batchId, string $section, string $model, string $promptVersion, string $profileHash): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM llm_runs
             WHERE batch_id = :batch_id AND section = :section AND model = :model
               AND prompt_version = :prompt_version AND profile_hash = :profile_hash
               AND status IN ('success', 'skipped')
             ORDER BY id DESC LIMIT 1"
        );
        $statement->execute([
            ':batch_id' => $batchId,
            ':section' => $section,
            ':model' => $model,
            ':prompt_version' => $promptVersion,
            ':profile_hash' => $profileHash,
        ]);
        return $statement->fetch() ?: null;
    }

    public function saveSuccess(
        string $batchId,
        string $section,
        array $context,
        string $resolvedModel,
        array $outputs,
        int $candidateCount,
        int $chunkCount,
        ?int $promptTokens,
        ?int $completionTokens,
        int $durationMs,
        string $startedAt,
    ): void {
        $completedAt = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $clear = $this->pdo->prepare(
                'UPDATE article_evaluations
                 SET summary = NULL, business_angle = NULL, llm_model = NULL, llm_selected = NULL,
                     llm_relevance_score = NULL, llm_requested_model = NULL,
                     llm_prompt_version = NULL, llm_profile_hash = NULL, llm_evaluated_at = NULL,
                     why_selected = deterministic_explanation
                 WHERE batch_id = :batch_id AND section = :section AND selected = 1'
            );
            $clear->execute([':batch_id' => $batchId, ':section' => $section]);

            $update = $this->pdo->prepare(
                'UPDATE article_evaluations
                 SET summary = NULL, why_selected = :why_selected, business_angle = NULL,
                     llm_model = :llm_model, llm_selected = :llm_selected,
                     llm_relevance_score = NULL,
                     llm_requested_model = :llm_requested_model,
                     llm_prompt_version = :llm_prompt_version, llm_profile_hash = :llm_profile_hash,
                     llm_evaluated_at = :llm_evaluated_at
                 WHERE article_id = :article_id AND batch_id = :batch_id AND section = :section AND selected = 1'
            );
            $selectedCount = 0;
            foreach ($outputs as $output) {
                $selectedCount += $output['keep'] ? 1 : 0;
                $update->execute([
                    ':why_selected' => $output['reason_code'],
                    ':llm_model' => $resolvedModel,
                    ':llm_selected' => $output['keep'] ? 1 : 0,
                    ':llm_requested_model' => $context['model'],
                    ':llm_prompt_version' => $context['prompt_version'],
                    ':llm_profile_hash' => $context['profile_hash'],
                    ':llm_evaluated_at' => $completedAt,
                    ':article_id' => $output['article_id'],
                    ':batch_id' => $batchId,
                    ':section' => $section,
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('An AI result did not match its deterministic evaluation.');
                }
            }
            $this->insertRun([
                'batch_id' => $batchId,
                'section' => $section,
                'model' => $context['model'],
                'resolved_model' => $resolvedModel,
                'prompt_version' => $context['prompt_version'],
                'profile_hash' => $context['profile_hash'],
                'candidate_count' => $candidateCount,
                'selected_count' => $selectedCount,
                'chunk_count' => $chunkCount,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'duration_ms' => $durationMs,
                'status' => 'success',
                'error_message' => null,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function recordRun(
        string $batchId,
        string $section,
        array $context,
        string $status,
        int $candidateCount,
        int $durationMs,
        string $startedAt,
        ?string $error = null,
    ): void {
        $this->insertRun([
            'batch_id' => $batchId,
            'section' => $section,
            'model' => $context['model'],
            'resolved_model' => null,
            'prompt_version' => $context['prompt_version'],
            'profile_hash' => $context['profile_hash'],
            'candidate_count' => $candidateCount,
            'selected_count' => 0,
            'chunk_count' => 0,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'duration_ms' => $durationMs,
            'status' => $status,
            'error_message' => $error === null ? null : mb_substr($error, 0, 1000),
            'started_at' => $startedAt,
            'completed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function latestStats(): array
    {
        $rows = $this->pdo->query(
            'SELECT lr.* FROM llm_runs lr
             INNER JOIN (SELECT section, MAX(id) AS latest_id FROM llm_runs GROUP BY section) latest
                ON latest.latest_id = lr.id'
        )->fetchAll();
        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['section']] = $row;
        }
        return $stats;
    }

    private function insertRun(array $run): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO llm_runs
                (batch_id, section, model, resolved_model, prompt_version, profile_hash,
                 candidate_count, selected_count, chunk_count, prompt_tokens, completion_tokens,
                 duration_ms, status, error_message, started_at, completed_at)
             VALUES
                (:batch_id, :section, :model, :resolved_model, :prompt_version, :profile_hash,
                 :candidate_count, :selected_count, :chunk_count, :prompt_tokens, :completion_tokens,
                 :duration_ms, :status, :error_message, :started_at, :completed_at)'
        );
        $statement->execute(array_combine(
            array_map(static fn (string $key): string => ':' . $key, array_keys($run)),
            array_values($run),
        ));
    }
}
