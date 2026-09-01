<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/news_bootstrap.php';

$ranking = ranking_config();
$llm = llm_config();
$latestStats = ranking_repository()->latestStats();
$latestLlmStats = llm_repository()->latestStats();
$sectionLabels = [
    'breaking' => 'Critical',
    'finance' => 'Finance',
    'crypto' => 'Crypto',
    'ai' => 'AI / Tech',
];
$componentDescriptions = [
    'importance' => 'Consequence and magnitude: crises, official decisions, major security events, systemic effects, and other high-impact changes.',
    'relevance' => 'Fit with the category profile. A topical match alone is not enough; each category also has minimum importance and relevance scores.',
    'evidence' => 'Configured source trust plus corroboration from distinct publishers. This is an evidence-strength score, not a truth probability.',
    'practical_impact' => 'Likely effect on decisions, costs, work, security, investment context, access, or deadlines.',
    'novelty' => 'Recency of the source timestamp, with a lower score as an item ages.',
];

function method_time(?string $value): string
{
    if (!$value) {
        return 'Not run';
    }
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('d M Y H:i');
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Transparent ranking, corroboration, and clustering rules for Personal Briefing.">
    <title>Ranking and AI Method · Personal Briefing</title>
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
            <a class="topnav__link" href="/source-status.php">Source status</a>
            <a class="topnav__link topnav__link--active" href="/methodology.php">Ranking method</a>
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" aria-pressed="false">◐ <span>Theme</span></button>
        </nav>
    </header>

    <main class="shell shell--status">
        <section class="status-hero">
            <div>
                <p class="hero__kicker">Transparent by design · <?= e($ranking['version']) ?> + <?= e($llm['prompt_version']) ?></p>
                <h1>Ranking and AI method</h1>
                <p>PHP produces reproducible event clusters and a shortlist first. Gemma then applies your Markdown profiles as a minimal final selector; it does not rewrite the news.</p>
            </div>
            <a class="button button--primary" href="/">Open briefing</a>
        </section>

        <section class="analytics-panel">
            <div class="analytics-panel__header">
                <div>
                    <p class="overline">Stage 5 · Minimal local selector</p>
                    <h2>What Gemma receives</h2>
                </div>
                <p>The model has no browser role. It receives stored metadata only and must satisfy a strict JSON response contract.</p>
            </div>
            <div class="analytics-cards methodology-cards">
                <article><span>Configured model</span><strong><?= e($llm['model']) ?></strong><small>Resolved against the models visible to LM Studio.</small></article>
                <article><span>Profiles per request</span><strong>2 Markdown</strong><small>GENERAL.md plus exactly one category profile; private files override templates.</small></article>
                <article><span>Maximum input</span><strong><?= e($llm['max_candidates']) ?> event clusters</strong><small>Only deterministic representatives; processed <?= e($llm['chunk_size']) ?> at a time.</small></article>
                <article><span>Model output</span><strong>Keep / reject</strong><small>Plus one restricted reason code. Titles and excerpts always remain publisher text.</small></article>
                <article><span>Feedback context</span><strong><?= e($llm['feedback_examples']) ?> examples</strong><small>Recent category feedback is soft guidance and cannot override evidence rules.</small></article>
                <article><span>Failure behaviour</span><strong>Keep Stage 4</strong><small>No partial AI output is published when a chunk or schema validation fails.</small></article>
            </div>
        </section>

        <div class="demo-banner" role="note">
            <strong>What the score means</strong>
            <span>The total is a weighted prioritisation score, not a claim that an article is true. Selection also requires category-specific minimum importance and relevance scores.</span>
        </div>

        <section class="analytics-panel">
            <div class="analytics-panel__header">
                <div>
                    <p class="overline">Five visible components</p>
                    <h2>Weighted score contract</h2>
                </div>
                <p>Each component is scored from 0 to 100. The weights below always sum to 100%.</p>
            </div>
            <div class="analytics-cards methodology-cards">
                <?php foreach ($ranking['weights'] as $key => $weight): ?>
                    <article>
                        <span><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
                        <strong><?= e(number_format($weight * 100, 0)) ?>%</strong>
                        <small><?= e($componentDescriptions[$key]) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="source-table-card">
            <div class="source-table__header">
                <div>
                    <p class="overline">Selection gates</p>
                    <h2>Rules by category</h2>
                </div>
                <p>No fixed article quota: every qualifying distinct story may appear.</p>
            </div>
            <div class="source-table-wrap">
                <table class="source-table">
                    <thead>
                        <tr><th>Category</th><th>Total threshold</th><th>Minimum importance</th><th>Minimum relevance</th><th>Cluster window</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking['sections'] as $key => $rules): ?>
                            <tr>
                                <td><strong><?= e($sectionLabels[$key] ?? $key) ?></strong></td>
                                <td><?= e($rules['selection_threshold']) ?>/100</td>
                                <td><?= e($rules['minimum_importance']) ?>/100</td>
                                <td><?= e($rules['minimum_relevance']) ?>/100</td>
                                <td><?= e($rules['cluster_hours']) ?> hours</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="source-table-card">
            <div class="source-table__header">
                <div>
                    <p class="overline">Latest local-model runs</p>
                    <h2>Local-AI processing ledger</h2>
                </div>
                <p>Every success, skip, and failure is retained for diagnostics and timing.</p>
            </div>
            <div class="source-table-wrap">
                <table class="source-table">
                    <thead><tr><th>Category</th><th>Status</th><th>Model</th><th>Reviewed</th><th>Kept</th><th>Chunks</th><th>Duration</th><th>Completed</th></tr></thead>
                    <tbody>
                        <?php foreach ($sectionLabels as $key => $label): ?>
                            <?php $stats = $latestLlmStats[$key] ?? null; ?>
                            <tr>
                                <td><strong><?= e($label) ?></strong></td>
                                <td><?= e($stats['status'] ?? 'Not run') ?></td>
                                <td><?= e($stats['resolved_model'] ?? $stats['model'] ?? $llm['model']) ?></td>
                                <td><?= e($stats['candidate_count'] ?? 0) ?></td>
                                <td><?= e($stats['selected_count'] ?? 0) ?></td>
                                <td><?= e($stats['chunk_count'] ?? 0) ?></td>
                                <td><?= $stats ? e(number_format((int) $stats['duration_ms'])) . ' ms' : 'Not run' ?></td>
                                <td><?= e(method_time($stats['completed_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="source-table-card">
            <div class="source-table__header">
                <div>
                    <p class="overline">Latest durable runs</p>
                    <h2>What Stage 4 processed</h2>
                </div>
                <p>Runs and article-level score breakdowns persist in the local SQLite database.</p>
            </div>
            <div class="source-table-wrap">
                <table class="source-table">
                    <thead>
                        <tr><th>Category</th><th>Candidates</th><th>Story clusters</th><th>Selected</th><th>Duration</th><th>Completed</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionLabels as $key => $label): ?>
                            <?php $stats = $latestStats[$key] ?? null; ?>
                            <tr>
                                <td><strong><?= e($label) ?></strong></td>
                                <td><?= e($stats['candidate_count'] ?? 0) ?></td>
                                <td><?= e($stats['cluster_count'] ?? 0) ?></td>
                                <td><?= e($stats['selected_count'] ?? 0) ?></td>
                                <td><?= $stats ? e(number_format((int) $stats['duration_ms'])) . ' ms' : 'Not run' ?></td>
                                <td><?= e(method_time($stats['completed_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="failure-policy">
            <p class="overline">Corroboration and clustering</p>
            <h2>Different reports strengthen a story; repeated headlines do not multiply it.</h2>
            <ol>
                <li>Titles are normalised into meaningful tokens and compared only within the category time window.</li>
                <li>Similar reports are grouped without deleting any article from the archive.</li>
                <li>Corroboration counts distinct publishers, not the number of articles from one publisher.</li>
                <li>A primary source is preferred as the representative when its score is close to the strongest report.</li>
                <li>Community signals and contrarian analysis cannot be promoted as verified facts without a reputable report in the same cluster.</li>
                <li>If intake or ranking fails, the last successful ranked batch remains visible.</li>
                <li>If LM Studio is closed, Gemma returns malformed data, or any chunk fails, no partial AI decision is published.</li>
                <li>Feedback is saved as an append-only history and becomes soft guidance on the next category review.</li>
            </ol>
        </section>
    </main>
    <footer class="footer"><span>Personal Briefing · <?= e($ranking['version']) ?> · <?= e($llm['prompt_version']) ?></span><a href="/">← Return to briefing</a></footer>
</body>
</html>
