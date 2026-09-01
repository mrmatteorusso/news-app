<?php

declare(strict_types=1);

final class RankingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function candidates(string $section, int $maxAgeHours, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.*, s.name AS source_name, s.source_type, s.trust_level, s.geography
             FROM article_sections ars
             INNER JOIN articles a ON a.id = ars.article_id
             INNER JOIN sources s ON s.id = a.source_id
             WHERE ars.section = :section
               AND (a.expires_at IS NULL OR a.expires_at >= :now)
               AND (
                    COALESCE(a.source_updated_at, a.published_at, a.last_retrieved_at) >= :oldest
                    OR (a.source_updated_at IS NULL AND a.published_at IS NULL)
               )
             ORDER BY COALESCE(a.source_updated_at, a.published_at, a.last_retrieved_at) DESC, a.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':section', $section);
        $statement->bindValue(':now', gmdate('Y-m-d H:i:s'));
        $statement->bindValue(':oldest', gmdate('Y-m-d H:i:s', time() - ($maxAgeHours * 3600)));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function publishedBatchId(string $stateKey): ?string
    {
        $statement = $this->pdo->prepare('SELECT published_batch_id FROM section_state WHERE section = :section');
        $statement->execute([':section' => $stateKey]);
        $batchId = $statement->fetchColumn();
        return is_string($batchId) ? $batchId : null;
    }

    public function hasSuccessfulRun(string $batchId, string $section, string $version): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM ranking_runs
             WHERE batch_id = :batch_id AND section = :section AND ranking_version = :version AND status = 'success'"
        );
        $statement->execute([':batch_id' => $batchId, ':section' => $section, ':version' => $version]);
        return $statement->fetchColumn() !== false;
    }

    public function save(
        string $batchId,
        string $section,
        string $version,
        array $evaluations,
        array $clusters,
        int $selectedCount,
        int $durationMs,
    ): void {
        $evaluatedAt = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $deleteClusters = $this->pdo->prepare('DELETE FROM story_clusters WHERE batch_id = :batch_id AND section = :section');
            $deleteClusters->execute([':batch_id' => $batchId, ':section' => $section]);
            $deleteEvaluations = $this->pdo->prepare('DELETE FROM article_evaluations WHERE batch_id = :batch_id AND section = :section');
            $deleteEvaluations->execute([':batch_id' => $batchId, ':section' => $section]);

            $evaluationStatement = $this->pdo->prepare(
                'INSERT INTO article_evaluations
                    (article_id, batch_id, section, deterministic_score, importance_score, relevance_score,
                     evidence_confidence, practical_impact_score, novelty_score, selected, summary,
                     why_selected, business_angle, llm_model, deterministic_explanation,
                     ranking_version, evaluated_at)
                 VALUES
                    (:article_id, :batch_id, :section, :deterministic_score, :importance_score, :relevance_score,
                     :evidence_confidence, :practical_impact_score, :novelty_score, :selected, :summary,
                     :why_selected, NULL, NULL, :deterministic_explanation,
                     :ranking_version, :evaluated_at)'
            );
            foreach ($evaluations as $evaluation) {
                $scores = $evaluation['scores'];
                $evaluationStatement->execute([
                    ':article_id' => $evaluation['article']['id'],
                    ':batch_id' => $batchId,
                    ':section' => $section,
                    ':deterministic_score' => $evaluation['deterministic_score'],
                    ':importance_score' => $scores['importance'],
                    ':relevance_score' => $scores['relevance'],
                    ':evidence_confidence' => $scores['evidence'],
                    ':practical_impact_score' => $scores['practical_impact'],
                    ':novelty_score' => $scores['novelty'],
                    ':selected' => $evaluation['selected'] ? 1 : 0,
                    ':summary' => $evaluation['article']['excerpt'],
                    ':why_selected' => $evaluation['why'],
                    ':deterministic_explanation' => $evaluation['why'],
                    ':ranking_version' => $version,
                    ':evaluated_at' => $evaluatedAt,
                ]);
            }

            $clusterStatement = $this->pdo->prepare(
                'INSERT INTO story_clusters (batch_id, section, representative_article_id, cluster_key, created_at)
                 VALUES (:batch_id, :section, :representative_article_id, :cluster_key, :created_at)'
            );
            $memberStatement = $this->pdo->prepare(
                'INSERT INTO cluster_articles (cluster_id, article_id) VALUES (:cluster_id, :article_id)'
            );
            foreach ($clusters as $cluster) {
                $clusterStatement->execute([
                    ':batch_id' => $batchId,
                    ':section' => $section,
                    ':representative_article_id' => $cluster['representative_id'],
                    ':cluster_key' => $cluster['cluster_key'],
                    ':created_at' => $evaluatedAt,
                ]);
                $clusterId = (int) $this->pdo->lastInsertId();
                foreach ($cluster['article_ids'] as $articleId) {
                    $memberStatement->execute([':cluster_id' => $clusterId, ':article_id' => $articleId]);
                }
            }

            $batchStatement = $this->pdo->prepare('UPDATE refresh_batches SET selected_count = :selected_count WHERE id = :batch_id');
            $batchStatement->execute([':selected_count' => $selectedCount, ':batch_id' => $batchId]);

            $runStatement = $this->pdo->prepare(
                'INSERT INTO ranking_runs
                    (batch_id, section, ranking_version, candidate_count, cluster_count, selected_count,
                     duration_ms, status, error_message, completed_at)
                 VALUES
                    (:batch_id, :section, :ranking_version, :candidate_count, :cluster_count, :selected_count,
                     :duration_ms, \'success\', NULL, :completed_at)
                 ON CONFLICT(batch_id, section, ranking_version) DO UPDATE SET
                    candidate_count = excluded.candidate_count,
                    cluster_count = excluded.cluster_count,
                    selected_count = excluded.selected_count,
                    duration_ms = excluded.duration_ms,
                    status = excluded.status,
                    error_message = NULL,
                    completed_at = excluded.completed_at'
            );
            $runStatement->execute([
                ':batch_id' => $batchId,
                ':section' => $section,
                ':ranking_version' => $version,
                ':candidate_count' => count($evaluations),
                ':cluster_count' => count($clusters),
                ':selected_count' => $selectedCount,
                ':duration_ms' => $durationMs,
                ':completed_at' => $evaluatedAt,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function recordFailure(string $batchId, string $section, string $version, int $durationMs, string $message): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ranking_runs
                (batch_id, section, ranking_version, candidate_count, cluster_count, selected_count,
                 duration_ms, status, error_message, completed_at)
             VALUES (:batch_id, :section, :version, 0, 0, 0, :duration_ms, \'failed\', :error, :completed_at)
             ON CONFLICT(batch_id, section, ranking_version) DO UPDATE SET
                duration_ms = excluded.duration_ms, status = \'failed\', error_message = excluded.error_message,
                completed_at = excluded.completed_at'
        );
        $statement->execute([
            ':batch_id' => $batchId,
            ':section' => $section,
            ':version' => $version,
            ':duration_ms' => $durationMs,
            ':error' => $message,
            ':completed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function latestStats(): array
    {
        $rows = $this->pdo->query(
            "SELECT rr.* FROM ranking_runs rr
             INNER JOIN (
                SELECT section, MAX(id) AS latest_id FROM ranking_runs WHERE status = 'success' GROUP BY section
             ) latest ON latest.latest_id = rr.id"
        )->fetchAll();
        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['section']] = $row;
        }
        return $stats;
    }
}
