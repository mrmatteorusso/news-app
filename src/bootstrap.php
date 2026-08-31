<?php

declare(strict_types=1);

$timezone = getenv('APP_TIMEZONE') ?: 'Europe/Rome';
date_default_timezone_set($timezone);

require_once __DIR__ . '/view_helpers.php';

