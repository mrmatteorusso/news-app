<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/news_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST is required.']);
    exit;
}

try {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        throw new InvalidArgumentException('A JSON request body is required.');
    }
    $articleId = filter_var($body['article_id'] ?? null, FILTER_VALIDATE_INT);
    $section = is_string($body['section'] ?? null) ? $body['section'] : '';
    $action = is_string($body['action'] ?? null) ? $body['action'] : '';
    $note = is_string($body['note'] ?? null) ? $body['note'] : null;
    if ($articleId === false || $articleId < 1) {
        throw new InvalidArgumentException('A valid article identifier is required.');
    }
    if (!in_array($section, array_keys(news_config()['sections']), true)) {
        throw new InvalidArgumentException('Unsupported feedback section.');
    }
    $saved = feedback_repository()->save($articleId, $section, $action, $note);
    echo json_encode(['ok' => true, 'feedback' => $saved]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Feedback API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Feedback could not be stored.']);
}
