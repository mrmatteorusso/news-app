<?php

declare(strict_types=1);

return [
    'topics' => [
        'bitcoin' => ['label' => 'Bitcoin', 'terms' => ['bitcoin', 'btc']],
        'ethereum' => ['label' => 'Ethereum', 'terms' => ['ethereum', 'ether', 'eth']],
        'cardano' => ['label' => 'Cardano', 'terms' => ['cardano', 'ada']],
        'ukraine-war' => ['label' => 'Ukraine war', 'terms' => ['ukraine', 'ukrainian', 'kyiv', 'zelensky']],
        'tesla' => ['label' => 'Tesla', 'terms' => ['tesla', 'elon musk']],
        'openai' => ['label' => 'OpenAI', 'terms' => ['openai', 'chatgpt']],
        'google-deepmind' => ['label' => 'Google / DeepMind', 'terms' => ['deepmind', 'google ai', 'gemini']],
        'open-models' => ['label' => 'Open models', 'terms' => ['open source model', 'open-source model', 'qwen', 'gemma', 'llama', 'mistral']],
        'ai-infrastructure' => ['label' => 'AI infrastructure', 'terms' => ['ai infrastructure', 'data centre', 'data center', 'gpu', 'semiconductor', 'inference']],
        'cybersecurity' => ['label' => 'Cybersecurity', 'terms' => ['cybersecurity', 'cyberattack', 'cyber attack', 'hacked', 'hackers', 'data breach', 'security breach', 'wallet breach']],
    ],
    // Conservative cross-display rule: only large or explicitly major security events
    // are promoted into Breaking. The original category association is always kept.
    'cross_sections' => [
        'breaking' => [
            'all_groups' => [
                ['hacked', 'hackers', 'breach', 'cyberattack', 'cyber attack', 'exploit'],
                ['million', 'billion', 'major', 'massive', 'largest', 'critical'],
            ],
        ],
    ],
];
