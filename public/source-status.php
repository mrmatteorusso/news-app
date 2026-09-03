<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/market_bootstrap.php';
require_once __DIR__ . '/../src/news_bootstrap.php';
require_once __DIR__ . '/../src/source_status_data.php';

function status_time(?string $timestamp): string
{
    if (!$timestamp) {
        return 'Not yet';
    }
    return (new DateTimeImmutable($timestamp, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('d M H:i');
}

function duration_label(?float $milliseconds): string
{
    if ($milliseconds === null) {
        return 'Inactive';
    }
    return $milliseconds >= 1000
        ? number_format($milliseconds / 1000, 1) . ' s'
        : number_format($milliseconds, 0) . ' ms';
}

$providerHealth = market_repository()->providerHealth();
$sourceStatuses = [];
$lastLiveSuccesses = [];
$providerDefinitions = [
    'yahoo_chart' => ['name' => 'Yahoo chart endpoint', 'type' => 'Public JSON · unofficial'],
    'coingecko' => ['name' => 'CoinGecko Public API', 'type' => 'Keyless public API'],
];
foreach ($providerDefinitions as $key => $definition) {
    $health = $providerHealth[$key] ?? null;
    $status = $health['status'] ?? 'warning';
    $sourceStatuses[] = [
        'status' => $status,
        'label' => $health === null ? 'Not checked' : match ($status) {
            'healthy' => 'Healthy',
            'down' => 'Unavailable',
            default => 'Partial',
        },
        'name' => $definition['name'],
        'geography' => 'Global',
        'section' => 'Finance',
        'type' => $definition['type'],
        'last_success' => status_time($health['last_success'] ?? null),
        'detail' => $health['detail'] ?? 'Waiting for the first live finance refresh.',
    ];
    if (is_string($health['last_success'] ?? null)) {
        $lastLiveSuccesses[] = $health['last_success'];
    }
}

$sectionNames = [
    'breaking' => 'Critical',
    'finance' => 'Finance',
    'crypto' => 'Crypto',
    'ai_business' => 'AI / Tech',
    'x_signals' => 'X Signals',
    'italy' => 'Italy',
    'local' => 'My Area',
];
$activeNewsSources = news_refresh_service()->activeSources();
$newsHealth = news_repository()->sourceHealth(array_column($activeNewsSources, 'id'));
foreach ($activeNewsSources as $source) {
    $health = $newsHealth[$source['id']];
    $latest = $health['latest'];
    $detail = 'Waiting for the first live feed check.';
    if (is_array($latest) && $latest['status'] === 'success') {
        $detail = sprintf('%d recent item(s) accepted · HTTP %s.', (int) $latest['item_count'], $latest['http_status'] ?: '—');
    } elseif (is_array($latest)) {
        $detail = $latest['error_message'] ?: 'The latest feed check failed.';
    }
    $sourceStatuses[] = [
        'status' => $health['status'],
        'label' => match ($health['status']) {
            'healthy' => 'Healthy',
            'down' => 'Unavailable',
            default => is_array($latest) ? 'Previous kept' : 'Not checked',
        },
        'name' => $source['name'],
        'geography' => ucwords(str_replace('_', ' ', $source['geography'])),
        'section' => implode(' / ', array_map(static fn (string $category): string => $sectionNames[$category] ?? $category, $source['categories'])),
        'type' => ucwords(str_replace('_', ' ', $source['source_type'])) . ' · ' . strtoupper($source['refresh_method']),
        'last_success' => status_time($health['last_success']),
        'detail' => $detail,
    ];
    if (is_string($health['last_success'])) {
        $lastLiveSuccesses[] = $health['last_success'];
    }
}

foreach (configured_sources() as $source) {
    if (($source['stage3_active'] ?? false) || in_array($source['id'], array_keys($providerDefinitions), true)) {
        continue;
    }
    $sourceStatuses[] = [
        'status' => 'warning',
        'label' => 'Planned',
        'name' => $source['name'],
        'geography' => ucwords(str_replace('_', ' ', $source['geography'])),
        'section' => implode(' / ', array_map(static fn (string $category): string => $sectionNames[$category] ?? $category, $source['categories'])),
        'type' => ucwords(str_replace('_', ' ', $source['source_type'])) . ' · ' . str_replace('_', ' ', $source['refresh_method']),
        'last_success' => 'Not active',
        'detail' => $source['notes'] ?? 'A source-specific adapter is planned.',
    ];
}

$sourceSummary = [
    'healthy' => count(array_filter($sourceStatuses, static fn (array $source): bool => $source['status'] === 'healthy')),
    'warning' => count(array_filter($sourceStatuses, static fn (array $source): bool => $source['status'] === 'warning')),
    'down' => count(array_filter($sourceStatuses, static fn (array $source): bool => $source['status'] === 'down')),
    'total' => count($sourceStatuses),
];

$activityMetrics = (new TelemetryRepository(Database::connection()))->metrics($analyticsSections);
$metricTotal = static fn (string $key): int => array_sum(array_column($activityMetrics, $key));
$localAiValues = static function (string $key) use ($activityMetrics): array {
    return array_values(array_filter(
        array_column($activityMetrics, $key),
        static fn (mixed $value): bool => $value !== null,
    ));
};
$average = static fn (array $values): ?float => $values === [] ? null : array_sum($values) / count($values);
$refreshTotal = $metricTotal('refresh_total');
$refresh7d = $metricTotal('refresh_7d');
$linksTotal = $metricTotal('links_total');
$links7d = $metricTotal('links_7d');
$scansTotal = $metricTotal('scans_total');
$scans7d = $metricTotal('scans_7d');
$apiTotal = $metricTotal('api_total');
$api7d = $metricTotal('api_7d');
$localAiTotal = $average($localAiValues('local_ai_total_ms'));
$localAi7d = $average($localAiValues('local_ai_7d_ms'));
$lastLiveCheck = $lastLiveSuccesses === [] ? null : max($lastLiveSuccesses);
$localAiSectionStatuses = [];
foreach (['breaking' => 'Critical', 'finance' => 'Finance', 'crypto' => 'Crypto', 'ai' => 'AI / Tech', 'italy' => 'Italy', 'local' => 'My Area'] as $key => $label) {
    $localAiSectionStatuses[$key] = ['label' => $label, ...llm_enrichment_service()->status($key)];
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Configured source health for Personal Briefing.">
    <title>Source Status · Personal Briefing</title>
    <script>
        try {
            const savedTheme = localStorage.getItem('personalBriefing.theme');
            const preferredTheme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = savedTheme || preferredTheme;
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/"><span class="brand__mark">PB</span><span>Personal Briefing</span></a>
        <nav class="topnav" aria-label="Primary navigation">
            <a class="topnav__link" href="/">Dashboard</a>
            <a class="topnav__link topnav__link--active" href="/source-status.php">Source status</a>
            <a class="topnav__link" href="/methodology.php">Ranking method</a>
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" aria-pressed="false">◐ <span>Theme</span></button>
        </nav>
    </header>

    <main class="shell shell--status">
        <section class="status-hero">
            <div>
                <p class="hero__kicker">Coverage and retrieval health</p>
                <h1>Source status</h1>
                <p>Distinguish “nothing important happened” from “the app could not check.” Previous successful dashboard data will remain visible after a failed refresh.</p>
            </div>
            <button class="button button--primary" type="button" id="check-sources"><span aria-hidden="true">↻</span> Refresh all live sources</button>
        </section>

        <div class="demo-banner" role="note">
            <strong>Stage 5 · Retrieval, ranking, and local-AI health</strong>
            <span>Market providers and free news feeds are live; PHP ranks event clusters before Gemma makes minimal keep/reject decisions. LM Studio failures never erase the previous valid briefing.</span>
        </div>

        <section class="status-summary" aria-label="Source summary">
            <div><strong><?= e($sourceSummary['healthy']) ?></strong><span>Healthy</span></div>
            <div><strong><?= e($sourceSummary['warning']) ?></strong><span>Partial / warning</span></div>
            <div><strong><?= e($sourceSummary['down']) ?></strong><span>Unavailable</span></div>
            <div><strong><?= e($sourceSummary['total']) ?></strong><span>Configured</span></div>
        </section>

        <section class="analytics-panel" data-activity-dashboard data-server-metrics="true">
            <div class="analytics-panel__header">
                <div>
                    <p class="overline">Activity and processing</p>
                    <h2>Usage telemetry</h2>
                </div>
                <p>SQLite stores refreshes, opened links, market API calls, per-feed PHP scans, and every local-model success or failure with timing.</p>
            </div>

            <div class="analytics-cards">
                <article><span>Total refreshes</span><strong><?= e($refreshTotal) ?></strong><small><?= e($refresh7d) ?> in last 7 days</small></article>
                <article><span>Refresh average</span><strong><?= e(number_format($refresh7d / 7, 1)) ?>/day</strong><small>Rolling last 7 days</small></article>
                <article><span>Links opened</span><strong><?= e($linksTotal) ?></strong><small><?= e($links7d) ?> in last 7 days</small></article>
                <article><span>PHP source scans</span><strong><?= e($scansTotal) ?></strong><small><?= e($scans7d) ?> in last 7 days</small></article>
                <article><span>External API calls</span><strong><?= e($apiTotal) ?></strong><small><?= e($api7d) ?> in last 7 days</small></article>
                <article><span>Gemma processing</span><strong><?= e(duration_label($localAiTotal)) ?></strong><small><?= e(duration_label($localAi7d)) ?> average in last 7 days</small></article>
            </div>

            <div class="source-table-wrap analytics-table-wrap">
                <table class="source-table analytics-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Refreshes<br>Total / 7d</th>
                            <th>Average<br>per day</th>
                            <th>Links opened<br>Total / 7d</th>
                            <th>PHP scans<br>Total / 7d</th>
                            <th>API calls<br>Total / 7d</th>
                            <th>Local-AI average<br>Total / 7d</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analyticsSections as $analyticsSection): ?>
                            <?php $metric = $activityMetrics[$analyticsSection['key']]; ?>
                            <tr data-analytics-section="<?= e($analyticsSection['key']) ?>">
                                <td><strong><?= e($analyticsSection['name']) ?></strong></td>
                                <td><?= e($metric['refresh_total']) ?> / <?= e($metric['refresh_7d']) ?></td>
                                <td><?= e(number_format($metric['refresh_7d'] / 7, 1)) ?></td>
                                <td><?= e($metric['links_total']) ?> / <?= e($metric['links_7d']) ?></td>
                                <td><?= e($metric['scans_total']) ?> / <?= e($metric['scans_7d']) ?></td>
                                <td><?= e($metric['api_total']) ?> / <?= e($metric['api_7d']) ?></td>
                                <td><?= e(duration_label($metric['local_ai_total_ms'])) ?> / <?= e(duration_label($metric['local_ai_7d_ms'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="analytics-footnote">Durable totals start with Stage 2 and are stored in the local SQLite database. Browser-only counts from Stage 1 are not imported.</p>
        </section>

        <section class="source-table-card">
            <div class="source-table__header">
                <div>
                    <p class="overline">Local model connection</p>
                    <h2>Gemma section status</h2>
                </div>
                <p>Requests run one section at a time to protect local memory.</p>
            </div>
            <div class="source-table-wrap">
                <table class="source-table">
                    <thead><tr><th>Status</th><th>Category</th><th>Model</th><th>Last run</th><th>Duration</th><th>Detail</th></tr></thead>
                    <tbody>
                        <?php foreach ($localAiSectionStatuses as $localAiStatus): ?>
                            <?php
                                $run = $localAiStatus['run'] ?? null;
                                $health = in_array($localAiStatus['state'], ['ready', 'empty'], true)
                                    ? 'healthy'
                                    : (in_array($localAiStatus['state'], ['failed'], true) ? 'down' : 'warning');
                            ?>
                            <tr>
                                <td><span class="health-pill health-pill--<?= e($health) ?>"><span></span><?= e(ucfirst($localAiStatus['state'])) ?></span></td>
                                <td><strong><?= e($localAiStatus['label']) ?></strong></td>
                                <td><?= e($run['resolved_model'] ?? $run['model'] ?? llm_config()['model']) ?></td>
                                <td><?= e(status_time($run['completed_at'] ?? null)) ?></td>
                                <td><?= e(duration_label(isset($run['duration_ms']) ? (float) $run['duration_ms'] : null)) ?></td>
                                <td><?= e($localAiStatus['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="source-table-card">
            <div class="source-table__header">
                <div>
                    <p class="overline">Configured coverage</p>
                    <h2>Feeds and public sources</h2>
                </div>
                <p>Last live source success: <strong data-source-check-time><?= e(status_time($lastLiveCheck)) ?></strong></p>
            </div>
            <div class="source-table-wrap">
                <table class="source-table">
                    <thead>
                        <tr><th>Status</th><th>Source</th><th>Section</th><th>Type</th><th>Last success</th><th>Detail</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sourceStatuses as $source): ?>
                            <tr data-source-row>
                                <td><span class="health-pill health-pill--<?= e($source['status']) ?>"><span></span><?= e($source['label']) ?></span></td>
                                <td><strong><?= e($source['name']) ?></strong><small><?= e($source['geography']) ?></small></td>
                                <td><?= e($source['section']) ?></td>
                                <td><?= e($source['type']) ?></td>
                                <td data-source-success><?= e($source['last_success']) ?></td>
                                <td><?= e($source['detail']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="failure-policy">
            <p class="overline">Failure policy</p>
            <h2>Old, valid data is safer than a misleading blank dashboard.</h2>
            <ol>
                <li>Record each refresh as a uniquely identified batch.</li>
                <li>Track success or failure separately for every source.</li>
                <li>Publish a new section only after a usable batch succeeds.</li>
                <li>On failure, retain the last successful stories and show a clear warning.</li>
            </ol>
        </section>
    </main>
    <div class="toast" id="refresh-toast" role="status" aria-live="polite"></div>
    <footer class="footer"><span>Personal Briefing · Source health</span><a href="/">← Return to briefing</a></footer>
</body>
</html>
