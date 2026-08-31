<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'stage' => 1,
    'time' => (new DateTimeImmutable())->format(DATE_ATOM),
], JSON_THROW_ON_ERROR);
