<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Market/MarketRepository.php';
require_once __DIR__ . '/../src/TelemetryRepository.php';

function assert_stage_two(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
assert_stage_two($schema !== false, 'The test could not read the SQLite schema.');
$pdo->exec($schema);

$repository = new MarketRepository($pdo);
$repository->seedSources([[
    'id' => 'test_provider',
    'name' => 'Test provider',
    'url' => 'https://example.invalid/',
    'categories' => ['finance'],
    'geography' => 'test',
    'source_type' => 'market_data',
    'trust_level' => 3,
    'enabled' => true,
    'refresh_method' => 'test',
    'notes' => 'In-memory Stage 2 smoke test.',
]]);

$instrument = [
    'key' => 'test_asset',
    'provider' => 'test_provider',
    'provider_symbol' => 'TEST',
];
$successfulResult = [
    'instrument_key' => 'test_asset',
    'provider' => 'test_provider',
    'provider_symbol' => 'TEST',
    'currency' => 'EUR',
    'latest_value' => 100.0,
    'reference_value' => 99.0,
    'change_percent' => 1.0101,
    'highest_close' => 105.0,
    'highest_close_at' => '2026-01-01 00:00:00',
    'from_high_percent' => -4.7619,
    'provider_timestamp' => '2026-08-31 12:00:00',
    'history_checked_at' => '2026-08-31 12:00:00',
];
$successfulCall = [
    'source_id' => 'test_provider',
    'request_kind' => 'api',
    'status' => 'success',
    'http_status' => 200,
    'item_count' => 1,
    'error' => null,
];

$repository->startBatch('TEST-SUCCESS', 'manual_section');
$repository->completeBatch('TEST-SUCCESS', ['test_asset' => $successfulResult], [$successfulCall], [], 1);

$failedCall = $successfulCall;
$failedCall['status'] = 'failed';
$failedCall['http_status'] = 503;
$failedCall['item_count'] = 0;
$failedCall['error'] = 'Simulated provider outage.';
$repository->startBatch('TEST-FAILURE', 'manual_section');
$repository->completeBatch('TEST-FAILURE', [], [$failedCall], ['Simulated provider outage.'], 1);

$snapshot = $repository->latestSnapshot([$instrument], 60);
assert_stage_two($snapshot['has_data'] === true, 'A failed refresh removed the previous quote.');
assert_stage_two((float) $snapshot['markets'][0]['quote']['latest_value'] === 100.0, 'The retained quote changed after failure.');
assert_stage_two($snapshot['state']['published_batch_id'] === 'TEST-SUCCESS', 'A failed batch replaced the published batch.');
assert_stage_two($snapshot['state']['status'] === 'failed', 'The failed attempt was not exposed in section status.');

$telemetry = new TelemetryRepository($pdo);
$telemetry->recordInteraction('section_refresh', 'finance');
$metrics = $telemetry->metrics([['key' => 'finance']]);
assert_stage_two($metrics['finance']['refresh_total'] === 1, 'Refresh telemetry was not stored.');
assert_stage_two($metrics['finance']['api_total'] === 2, 'Provider call telemetry was not counted.');

echo "Stage 2 smoke test passed: cached data survives provider failure and telemetry persists.\n";
