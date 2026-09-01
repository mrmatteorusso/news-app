<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(getenv('LLM_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL),
    'base_url' => rtrim(getenv('LLM_BASE_URL') ?: 'http://host.docker.internal:1234/v1', '/'),
    'model' => trim(getenv('LLM_MODEL') ?: 'gemma-3-4b-it'),
    'prompt_version' => 'profile-selector-v7-gemma-minimal',
    'connect_timeout_seconds' => max(1, (int) (getenv('LLM_CONNECT_TIMEOUT_SECONDS') ?: 3)),
    'request_timeout_seconds' => max(30, (int) (getenv('LLM_REQUEST_TIMEOUT_SECONDS') ?: 180)),
    'retry_after_minutes' => max(1, (int) (getenv('LLM_RETRY_AFTER_MINUTES') ?: 10)),
    'temperature' => 0.1,
    'max_output_tokens' => max(128, (int) (getenv('LLM_MAX_OUTPUT_TOKENS') ?: 420)),
    'chunk_size' => max(1, (int) (getenv('LLM_CHUNK_SIZE') ?: 8)),
    'max_candidates' => max(1, (int) (getenv('LLM_MAX_CANDIDATES') ?: 10)),
    'max_excerpt_characters' => 420,
    'feedback_examples' => 20,
    'profile_map' => [
        'breaking' => 'BREAKING.md',
        'finance' => 'FINANCE.md',
        'crypto' => 'CRYPTO.md',
        'ai' => 'AI_BUSINESS.md',
    ],
];
