<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/news_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function news_respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$section = (string) ($_GET['section'] ?? '');
$allowedSections = array_keys(news_config()['sections']);
if (!in_array($section, $allowedSections, true)) {
    news_respond(['ok' => false, 'error' => 'Unsupported news section.'], 422);
}

try {
    $action = $_GET['action'] ?? 'status';
    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        news_respond(['ok' => true, 'section' => $section, 'snapshot' => news_snapshot($section)]);
    }

    if ($action !== 'refresh' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        news_respond(['ok' => false, 'error' => 'Unsupported news API request.'], 405);
    }

    $body = json_decode((string) file_get_contents('php://input'), true);
    $trigger = is_array($body) ? ($body['trigger'] ?? 'manual_section') : 'manual_section';
    if (!in_array($trigger, ['page_open', 'manual_section', 'manual_all'], true)) {
        news_respond(['ok' => false, 'error' => 'Invalid refresh trigger.'], 422);
    }

    set_time_limit(120);
    $storagePath = getenv('APP_STORAGE_PATH') ?: '/var/www/storage';
    $lockPath = rtrim($storagePath, '/\\') . '/news-' . $section . '-refresh.lock';
    $lockHandle = fopen($lockPath, 'c+');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }
        news_respond([
            'ok' => false,
            'busy' => true,
            'error' => 'This section is already checking its feeds.',
            'snapshot' => news_snapshot($section),
        ], 409);
    }

    try {
        $refresh = news_refresh_service()->refresh($section, $trigger);
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    $snapshot = NewsPresenter::presentSnapshot($refresh['snapshot']);
    $failed = ($refresh['result']['status'] ?? null) === 'failed';
    $rankingFailed = ($refresh['ranking']['reason'] ?? null) === 'ranking_failed';
    news_respond([
        'ok' => !$failed,
        'section' => $section,
        'skipped_cache' => $refresh['skipped_cache'],
        'batch_id' => $refresh['batch_id'] ?? $snapshot['batch_id'],
        'result' => $refresh['result'],
        'ranking' => $refresh['ranking'],
        'warning' => $rankingFailed ? 'New intake was stored, but ranking failed. The previous ranked briefing remains visible.' : null,
        'snapshot' => $snapshot,
        'error' => $failed ? 'Every configured feed failed. Previous stories were retained.' : null,
    ], $failed ? 503 : 200);
} catch (Throwable $exception) {
    error_log('News API error for ' . $section . ': ' . $exception->getMessage());
    $snapshot = null;
    try {
        $snapshot = news_snapshot($section);
    } catch (Throwable) {
    }
    news_respond([
        'ok' => false,
        'section' => $section,
        'error' => 'Feeds could not be refreshed. The last successful stories were retained.',
        'snapshot' => $snapshot,
    ], 503);
}
