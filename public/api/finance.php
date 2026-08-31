<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/market_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $action = $_GET['action'] ?? 'status';
    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        respond(['ok' => true, 'snapshot' => market_snapshot()]);
    }

    if ($action !== 'refresh' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['ok' => false, 'error' => 'Unsupported finance API request.'], 405);
    }

    $body = json_decode((string) file_get_contents('php://input'), true);
    $trigger = is_array($body) ? ($body['trigger'] ?? 'manual_section') : 'manual_section';
    $allowedTriggers = ['page_open', 'manual_section', 'manual_all'];
    if (!in_array($trigger, $allowedTriggers, true)) {
        respond(['ok' => false, 'error' => 'Invalid refresh trigger.'], 422);
    }

    set_time_limit(180);
    $storagePath = getenv('APP_STORAGE_PATH') ?: '/var/www/storage';
    $lockHandle = fopen(rtrim($storagePath, '/\\') . '/finance-refresh.lock', 'c+');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }
        respond([
            'ok' => false,
            'busy' => true,
            'error' => 'A finance refresh is already running.',
            'snapshot' => market_snapshot(),
        ], 409);
    }

    try {
        $refresh = market_refresh_service()->refresh($trigger);
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    $snapshot = MarketPresenter::presentSnapshot($refresh['snapshot']);
    respond([
        'ok' => true,
        'skipped_cache' => $refresh['skipped_cache'],
        'batch_id' => $refresh['batch_id'] ?? $snapshot['batch_id'],
        'result' => $refresh['result'],
        'snapshot' => $snapshot,
    ]);
} catch (Throwable $exception) {
    error_log('Finance API error: ' . $exception->getMessage());
    $snapshot = null;
    try {
        $snapshot = market_snapshot();
    } catch (Throwable) {
    }
    respond([
        'ok' => false,
        'error' => 'Finance data could not be refreshed. The last successful values were retained.',
        'snapshot' => $snapshot,
    ], 503);
}
