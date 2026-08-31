<?php

declare(strict_types=1);

$sourceStatuses = [
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'BBC World RSS', 'geography' => 'Global', 'section' => 'Breaking', 'type' => 'Mainstream RSS', 'last_success' => mock_time('-9 minutes'), 'detail' => 'Mock feed parsed successfully.'],
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'ECB press releases', 'geography' => 'EU', 'section' => 'Finance', 'type' => 'Primary', 'last_success' => mock_time('-9 minutes'), 'detail' => 'Mock official source available.'],
    ['status' => 'warning', 'label' => 'Partial', 'name' => 'Market data provider', 'geography' => 'Global', 'section' => 'Markets', 'type' => 'Public API', 'last_success' => mock_time('-54 minutes'), 'detail' => 'Two ETF symbols need provider confirmation.'],
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'Crypto news collection', 'geography' => 'Global / EU', 'section' => 'Crypto News', 'type' => 'Primary + specialist', 'last_success' => 'Not active', 'detail' => 'Stage 3 will verify free Bitcoin, Ethereum, Cardano, specialist, and regulatory feeds.'],
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'AI company announcements', 'geography' => 'Global', 'section' => 'AI / Tech', 'type' => 'Primary collection', 'last_success' => mock_time('-2 hours'), 'detail' => 'Mock sources checked.'],
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'Reddit curated communities', 'geography' => 'Global', 'section' => 'AI / Tech', 'type' => 'Signal', 'last_success' => mock_time('-2 hours'), 'detail' => 'Discovery only; claims require verification.'],
    ['status' => 'warning', 'label' => 'Partial', 'name' => 'Curated X accounts', 'geography' => 'Global', 'section' => 'X Signals', 'type' => 'Embed / public page', 'last_success' => mock_time('-2 hours'), 'detail' => 'No paid API; availability may vary.'],
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'Italian Government', 'geography' => 'Italy', 'section' => 'Italy', 'type' => 'Primary', 'last_success' => mock_time('-3 hours'), 'detail' => 'Mock official source available.'],
    ['status' => 'healthy', 'label' => 'Healthy', 'name' => 'Regione Lombardia', 'geography' => 'Lombardy', 'section' => 'My Area', 'type' => 'Primary', 'last_success' => mock_time('-3 hours'), 'detail' => 'Mock official source available.'],
    ['status' => 'down', 'label' => 'Unavailable', 'name' => 'Municipal notice example', 'geography' => 'Alta Valtellina', 'section' => 'My Area', 'type' => 'Local website', 'last_success' => 'Yesterday 18:42', 'detail' => 'Mock timeout; previous data retained.'],
];

$sourceSummary = [
    'healthy' => count(array_filter($sourceStatuses, static fn (array $source): bool => $source['status'] === 'healthy')),
    'warning' => count(array_filter($sourceStatuses, static fn (array $source): bool => $source['status'] === 'warning')),
    'down' => count(array_filter($sourceStatuses, static fn (array $source): bool => $source['status'] === 'down')),
    'total' => count($sourceStatuses),
];

$analyticsSections = [
    ['key' => 'breaking', 'name' => 'Critical News'],
    ['key' => 'finance', 'name' => 'Finance & Markets'],
    ['key' => 'crypto', 'name' => 'Crypto News'],
    ['key' => 'ai', 'name' => 'AI / Tech / Business'],
    ['key' => 'x', 'name' => 'X Signals'],
    ['key' => 'italy', 'name' => 'Italy'],
    ['key' => 'local', 'name' => 'My Area'],
];
