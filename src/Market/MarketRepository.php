<?php

declare(strict_types=1);

final class MarketRepository
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
                name = excluded.name,
                url = excluded.url,
                category = excluded.category,
                geography = excluded.geography,
                source_type = excluded.source_type,
                trust_level = excluded.trust_level,
                enabled = excluded.enabled,
                refresh_method = excluded.refresh_method,
                notes = excluded.notes'
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

    public function instrumentStates(): array
    {
        $states = [];
        foreach ($this->pdo->query('SELECT * FROM market_instrument_state')->fetchAll() as $row) {
            $states[$row['instrument_key']] = $row;
        }
        return $states;
    }

    public function startBatch(string $batchId, string $triggerType): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO refresh_batches (id, section, trigger_type, status, started_at)
             VALUES (:id, 'finance', :trigger_type, 'running', :started_at)"
        );
        $statement->execute([
            ':id' => $batchId,
            ':trigger_type' => $triggerType,
            ':started_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function completeBatch(string $batchId, array $results, array $calls, array $errors, int $expectedCount): array
    {
        $completedAt = gmdate('Y-m-d H:i:s');
        $selectedCount = count($results);
        $status = $selectedCount === 0 ? 'failed' : ($selectedCount < $expectedCount ? 'partial' : 'success');
        $warning = $errors !== [] ? implode(' ', array_unique($errors)) : null;

        $this->pdo->beginTransaction();
        try {
            $fetchStatement = $this->pdo->prepare(
                'INSERT INTO source_fetches
                    (batch_id, source_id, request_kind, status, http_status, item_count, started_at, completed_at, error_message)
                 VALUES
                    (:batch_id, :source_id, :request_kind, :status, :http_status, :item_count, :started_at, :completed_at, :error_message)'
            );
            foreach ($calls as $call) {
                $fetchStatement->execute([
                    ':batch_id' => $batchId,
                    ':source_id' => $call['source_id'],
                    ':request_kind' => $call['request_kind'],
                    ':status' => $call['status'],
                    ':http_status' => $call['http_status'],
                    ':item_count' => $call['item_count'],
                    ':started_at' => $completedAt,
                    ':completed_at' => $completedAt,
                    ':error_message' => $call['error'],
                ]);
            }

            $stateStatement = $this->pdo->prepare(
                'INSERT INTO market_instrument_state
                    (instrument_key, provider, provider_symbol, currency, highest_close, highest_close_at, history_checked_at, last_provider_timestamp, last_success_at, last_error, updated_at)
                 VALUES
                    (:instrument_key, :provider, :provider_symbol, :currency, :highest_close, :highest_close_at, :history_checked_at, :provider_timestamp, :last_success_at, NULL, :updated_at)
                 ON CONFLICT(instrument_key) DO UPDATE SET
                    provider = excluded.provider,
                    provider_symbol = excluded.provider_symbol,
                    currency = excluded.currency,
                    highest_close = excluded.highest_close,
                    highest_close_at = excluded.highest_close_at,
                    history_checked_at = COALESCE(excluded.history_checked_at, market_instrument_state.history_checked_at),
                    last_provider_timestamp = excluded.last_provider_timestamp,
                    last_success_at = excluded.last_success_at,
                    last_error = NULL,
                    updated_at = excluded.updated_at'
            );
            $quoteStatement = $this->pdo->prepare(
                'INSERT INTO market_quotes
                    (batch_id, instrument_key, provider, symbol, currency, latest_value, reference_value, change_percent, highest_close, from_high_percent, provider_timestamp, retrieved_at)
                 VALUES
                    (:batch_id, :instrument_key, :provider, :symbol, :currency, :latest_value, :reference_value, :change_percent, :highest_close, :from_high_percent, :provider_timestamp, :retrieved_at)'
            );
            foreach ($results as $result) {
                $stateStatement->execute([
                    ':instrument_key' => $result['instrument_key'],
                    ':provider' => $result['provider'],
                    ':provider_symbol' => $result['provider_symbol'],
                    ':currency' => $result['currency'],
                    ':highest_close' => $result['highest_close'],
                    ':highest_close_at' => $result['highest_close_at'],
                    ':history_checked_at' => $result['history_checked_at'],
                    ':provider_timestamp' => $result['provider_timestamp'],
                    ':last_success_at' => $completedAt,
                    ':updated_at' => $completedAt,
                ]);
                $quoteStatement->execute([
                    ':batch_id' => $batchId,
                    ':instrument_key' => $result['instrument_key'],
                    ':provider' => $result['provider'],
                    ':symbol' => $result['provider_symbol'],
                    ':currency' => $result['currency'],
                    ':latest_value' => $result['latest_value'],
                    ':reference_value' => $result['reference_value'],
                    ':change_percent' => $result['change_percent'],
                    ':highest_close' => $result['highest_close'],
                    ':from_high_percent' => $result['from_high_percent'],
                    ':provider_timestamp' => $result['provider_timestamp'],
                    ':retrieved_at' => $completedAt,
                ]);
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
                ':candidate_count' => $expectedCount,
                ':selected_count' => $selectedCount,
                ':error_summary' => $warning,
                ':id' => $batchId,
            ]);

            $sectionStatement = $this->pdo->prepare(
                'INSERT INTO section_state (section, published_batch_id, last_success_at, last_attempt_at, status, warning)
                 VALUES (\'finance\', :published_batch_id, :last_success_at, :last_attempt_at, :status, :warning)
                 ON CONFLICT(section) DO UPDATE SET
                    published_batch_id = CASE WHEN excluded.last_success_at IS NOT NULL THEN excluded.published_batch_id ELSE section_state.published_batch_id END,
                    last_success_at = COALESCE(excluded.last_success_at, section_state.last_success_at),
                    last_attempt_at = excluded.last_attempt_at,
                    status = excluded.status,
                    warning = excluded.warning'
            );
            $sectionStatement->execute([
                ':published_batch_id' => $selectedCount > 0 ? $batchId : null,
                ':last_success_at' => $selectedCount > 0 ? $completedAt : null,
                ':last_attempt_at' => $completedAt,
                ':status' => $status === 'success' ? 'ready' : $status,
                ':warning' => $warning,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return ['status' => $status, 'selected_count' => $selectedCount, 'warning' => $warning];
    }

    public function failRunningBatch(string $batchId, string $message): void
    {
        $completedAt = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            "UPDATE refresh_batches SET status = 'failed', completed_at = :completed_at, error_summary = :message WHERE id = :id"
        );
        $statement->execute([':completed_at' => $completedAt, ':message' => $message, ':id' => $batchId]);
        $sectionStatement = $this->pdo->prepare(
            "INSERT INTO section_state (section, last_attempt_at, status, warning)
             VALUES ('finance', :last_attempt_at, 'failed', :warning)
             ON CONFLICT(section) DO UPDATE SET last_attempt_at = excluded.last_attempt_at, status = 'failed', warning = excluded.warning"
        );
        $sectionStatement->execute([':last_attempt_at' => $completedAt, ':warning' => $message]);
    }

    public function isFinanceFresh(int $minutes): bool
    {
        $statement = $this->pdo->query("SELECT last_success_at FROM section_state WHERE section = 'finance'");
        $lastSuccess = $statement->fetchColumn();
        return is_string($lastSuccess) && strtotime($lastSuccess . ' UTC') >= time() - ($minutes * 60);
    }

    public function latestSnapshot(array $instruments, int $cacheMinutes): array
    {
        $rows = $this->pdo->query(
            'SELECT mq.*
             FROM market_quotes mq
             INNER JOIN (
                SELECT instrument_key, MAX(id) AS latest_id
                FROM market_quotes
                GROUP BY instrument_key
             ) latest ON latest.latest_id = mq.id'
        )->fetchAll();
        $quotes = [];
        foreach ($rows as $row) {
            $quotes[$row['instrument_key']] = $row;
        }

        $stateStatement = $this->pdo->query("SELECT * FROM section_state WHERE section = 'finance'");
        $sectionState = $stateStatement->fetch() ?: null;
        $lastSuccess = $sectionState['last_success_at'] ?? null;
        $stale = !is_string($lastSuccess) || strtotime($lastSuccess . ' UTC') < time() - ($cacheMinutes * 60);

        $ordered = [];
        foreach ($instruments as $instrument) {
            $ordered[] = [
                'config' => $instrument,
                'quote' => $quotes[$instrument['key']] ?? null,
            ];
        }

        return [
            'markets' => $ordered,
            'state' => $sectionState,
            'stale' => $stale,
            'has_data' => $quotes !== [],
        ];
    }

    public function providerHealth(): array
    {
        $providers = [];
        foreach (['yahoo_chart', 'coingecko'] as $sourceId) {
            $batchStatement = $this->pdo->prepare(
                'SELECT batch_id FROM source_fetches WHERE source_id = :source_id ORDER BY id DESC LIMIT 1'
            );
            $batchStatement->execute([':source_id' => $sourceId]);
            $batchId = $batchStatement->fetchColumn();
            if (!is_string($batchId)) {
                $providers[$sourceId] = null;
                continue;
            }
            $fetchStatement = $this->pdo->prepare(
                'SELECT status, completed_at, error_message FROM source_fetches WHERE source_id = :source_id AND batch_id = :batch_id'
            );
            $fetchStatement->execute([':source_id' => $sourceId, ':batch_id' => $batchId]);
            $fetches = $fetchStatement->fetchAll();
            $failed = array_values(array_filter($fetches, static fn (array $fetch): bool => $fetch['status'] === 'failed'));
            $successStatement = $this->pdo->prepare(
                "SELECT MAX(completed_at) FROM source_fetches WHERE source_id = :source_id AND status = 'success'"
            );
            $successStatement->execute([':source_id' => $sourceId]);
            $lastSuccess = $successStatement->fetchColumn();
            $providers[$sourceId] = [
                'status' => $failed === [] ? 'healthy' : (count($failed) < count($fetches) ? 'warning' : 'down'),
                'last_success' => is_string($lastSuccess) ? $lastSuccess : null,
                'detail' => $failed === [] ? sprintf('%d API call(s) succeeded in the latest batch.', count($fetches)) : implode(' ', array_filter(array_column($failed, 'error_message'))),
            ];
        }
        return $providers;
    }
}
