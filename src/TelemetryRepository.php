<?php

declare(strict_types=1);

final class TelemetryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordInteraction(string $eventType, string $section, ?string $targetUrl = null): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO interaction_events (event_type, section, target_url, created_at)
             VALUES (:event_type, :section, :target_url, :created_at)'
        );
        $statement->execute([
            ':event_type' => $eventType,
            ':section' => $section,
            ':target_url' => $targetUrl,
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function metrics(array $sections): array
    {
        $metrics = [];
        foreach ($sections as $section) {
            $key = $section['key'];
            $metrics[$key] = [
                'refresh_total' => $this->countInteractions('section_refresh', $key),
                'refresh_7d' => $this->countInteractions('section_refresh', $key, true),
                'links_total' => $this->countInteractions('link_opened', $key),
                'links_7d' => $this->countInteractions('link_opened', $key, true),
                'scans_total' => $this->countFetches($key, 'scan'),
                'scans_7d' => $this->countFetches($key, 'scan', true),
                'api_total' => $this->countFetches($key, 'api'),
                'api_7d' => $this->countFetches($key, 'api', true),
                'qwen_total_ms' => $this->averageQwen($key),
                'qwen_7d_ms' => $this->averageQwen($key, true),
            ];
        }
        return $metrics;
    }

    private function countInteractions(string $type, string $section, bool $lastSevenDays = false): int
    {
        $sql = 'SELECT COUNT(*) FROM interaction_events WHERE event_type = :event_type AND section = :section';
        if ($lastSevenDays) {
            $sql .= " AND created_at >= datetime('now', '-7 days')";
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute([':event_type' => $type, ':section' => $section]);
        return (int) $statement->fetchColumn();
    }

    private function countFetches(string $section, string $kind, bool $lastSevenDays = false): int
    {
        $sql = 'SELECT COUNT(*)
                FROM source_fetches sf
                INNER JOIN refresh_batches rb ON rb.id = sf.batch_id
                WHERE rb.section = :section AND sf.request_kind = :request_kind';
        if ($lastSevenDays) {
            $sql .= " AND sf.completed_at >= datetime('now', '-7 days')";
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute([':section' => $section, ':request_kind' => $kind]);
        return (int) $statement->fetchColumn();
    }

    private function averageQwen(string $section, bool $lastSevenDays = false): ?float
    {
        $sql = "SELECT AVG(duration_ms) FROM llm_runs WHERE section = :section AND status = 'success'";
        if ($lastSevenDays) {
            $sql .= " AND completed_at >= datetime('now', '-7 days')";
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute([':section' => $section]);
        $value = $statement->fetchColumn();
        return $value === null ? null : (float) $value;
    }
}

