<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/source_status_data.php';
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
            <button class="button button--primary" type="button" id="check-sources"><span aria-hidden="true">↻</span> Check all sources</button>
        </section>

        <div class="demo-banner" role="note">
            <strong>Stage 1 · Status demonstration</strong>
            <span>These states illustrate the future monitoring interface; they are not live source checks.</span>
        </div>

        <section class="status-summary" aria-label="Source summary">
            <div><strong><?= e($sourceSummary['healthy']) ?></strong><span>Healthy</span></div>
            <div><strong><?= e($sourceSummary['warning']) ?></strong><span>Partial / warning</span></div>
            <div><strong><?= e($sourceSummary['down']) ?></strong><span>Unavailable</span></div>
            <div><strong><?= e($sourceSummary['total']) ?></strong><span>Configured</span></div>
        </section>

        <section class="analytics-panel" data-activity-dashboard>
            <div class="analytics-panel__header">
                <div>
                    <p class="overline">Activity and processing</p>
                    <h2>Usage telemetry</h2>
                </div>
                <p>Refreshes and opened links are counted locally now. Backend scans, API calls, and Qwen timing activate in later stages.</p>
            </div>

            <div class="analytics-cards">
                <article><span>Total refreshes</span><strong data-summary="refresh-total">0</strong><small><span data-summary="refresh-7d">0</span> in last 7 days</small></article>
                <article><span>Refresh average</span><strong><span data-summary="refresh-daily">0.0</span>/day</strong><small>Rolling last 7 days</small></article>
                <article><span>Links opened</span><strong data-summary="links-total">0</strong><small><span data-summary="links-7d">0</span> in last 7 days</small></article>
                <article><span>PHP source scans</span><strong>0</strong><small>Begins with live ingestion</small></article>
                <article><span>External API calls</span><strong>0</strong><small>Begins with live providers</small></article>
                <article><span>Qwen processing</span><strong>Inactive</strong><small>Timing begins in Stage 5</small></article>
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
                            <th>Qwen average<br>Total / 7d</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analyticsSections as $analyticsSection): ?>
                            <tr data-analytics-section="<?= e($analyticsSection['key']) ?>">
                                <td><strong><?= e($analyticsSection['name']) ?></strong></td>
                                <td><span data-metric="refresh-total">0</span> / <span data-metric="refresh-7d">0</span></td>
                                <td><span data-metric="refresh-daily">0.0</span></td>
                                <td><span data-metric="links-total">0</span> / <span data-metric="links-7d">0</span></td>
                                <td><span class="metric-inactive">0 / 0</span></td>
                                <td><span class="metric-inactive">0 / 0</span></td>
                                <td><span class="metric-inactive">Not active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="analytics-footnote">Stage 1 interaction counts are stored only in this browser. From Stage 2 onward, durable totals and seven-day statistics will be calculated from SQLite.</p>
        </section>

        <section class="source-table-card">
            <div class="source-table__header">
                <div>
                    <p class="overline">Configured coverage</p>
                    <h2>Feeds and public sources</h2>
                </div>
                <p>Last mock check: <strong data-source-check-time><?= e(mock_time('-9 minutes')) ?></strong></p>
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
