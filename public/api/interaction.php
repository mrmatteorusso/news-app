<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/market_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST is required.']);
    exit;
}

try {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $eventType = is_array($body) ? ($body['event_type'] ?? '') : '';
    $section = is_array($body) ? ($body['section'] ?? '') : '';
    $targetUrl = is_array($body) && is_string($body['target_url'] ?? null)
        ? substr($body['target_url'], 0, 2048)
        : null;

    if (!in_array($eventType, ['section_refresh', 'link_opened'], true)) {
        throw new InvalidArgumentException('Invalid interaction type.');
    }
    if (!in_array($section, ['breaking', 'finance', 'crypto', 'ai', 'x', 'italy', 'local'], true)) {
        throw new InvalidArgumentException('Invalid dashboard section.');
    }

    $telemetry = new TelemetryRepository(Database::connection());
    $telemetry->recordInteraction($eventType, $section, $targetUrl);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Interaction API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Interaction could not be stored.']);
}
