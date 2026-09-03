<?php

declare(strict_types=1);

return [
    'max_items_per_source' => 30,
    'excerpt_characters' => 600,
    'retention_days' => max(7, (int) (getenv('ARTICLE_RETENTION_DAYS') ?: 90)),
    'sections' => [
        'breaking' => [
            'state_key' => 'breaking',
            'source_category' => 'breaking',
            'cache_minutes' => 15,
            'ingest_max_age_hours' => 48,
            'display_limit' => 24,
        ],
        'finance' => [
            'state_key' => 'finance_news',
            'source_category' => 'finance',
            'cache_minutes' => 30,
            'ingest_max_age_hours' => 168,
            'display_limit' => 24,
        ],
        'crypto' => [
            'state_key' => 'crypto',
            'source_category' => 'crypto',
            'cache_minutes' => 45,
            'ingest_max_age_hours' => 168,
            'display_limit' => 24,
        ],
        'ai' => [
            'state_key' => 'ai',
            'source_category' => 'ai_business',
            'cache_minutes' => 45,
            'ingest_max_age_hours' => 336,
            'display_limit' => 30,
        ],
        'italy' => [
            'state_key' => 'italy',
            'source_category' => 'italy',
            'cache_minutes' => 60,
            'ingest_max_age_hours' => 336,
            'display_limit' => 24,
        ],
        'local' => [
            'state_key' => 'local',
            'source_category' => 'local',
            'cache_minutes' => 60,
            'ingest_max_age_hours' => 336,
            'display_limit' => 24,
        ],
    ],
];
