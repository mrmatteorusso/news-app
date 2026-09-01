<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/market_bootstrap.php';
require_once __DIR__ . '/../src/news_bootstrap.php';
require_once __DIR__ . '/../src/dashboard_data.php';

$pageTitle = 'Personal Briefing';
$dashboardConfig = require __DIR__ . '/../config/dashboard.php';

try {
    $liveMarketSnapshot = market_snapshot();
} catch (Throwable $exception) {
    error_log('Market snapshot error: ' . $exception->getMessage());
    $liveMarketSnapshot = MarketPresenter::presentSnapshot([
        'markets' => array_map(
            static fn (array $instrument): array => ['config' => $instrument, 'quote' => null],
            market_instruments(),
        ),
        'state' => null,
        'stale' => true,
        'has_data' => false,
    ]);
    $liveMarketSnapshot['warning'] = 'The local finance database could not be opened.';
    $liveMarketSnapshot['status'] = 'failed';
}

$markets = $liveMarketSnapshot['markets'];
$financeStatus = match ($liveMarketSnapshot['status']) {
    'ready' => 'ready',
    'partial' => 'partial',
    'failed' => 'error',
    default => 'working',
};
$financeState = match ($liveMarketSnapshot['status']) {
    'ready' => $liveMarketSnapshot['stale'] ? 'Live cache ready · refresh recommended' : 'Live data ready',
    'partial' => 'Live data · some providers unavailable',
    'failed' => $liveMarketSnapshot['has_data'] ? 'Refresh failed · previous live data retained' : 'Live refresh unavailable',
    default => 'Waiting for first live refresh',
};
$sections['finance'] = array_replace($sections['finance'], [
    'status' => $financeStatus,
    'state' => $financeState,
    'updated' => $liveMarketSnapshot['last_success_display'],
    'batch' => $liveMarketSnapshot['batch_id'] ?? 'NOT-RUN',
]);

$liveNewsSnapshots = [];
foreach (['breaking', 'finance', 'crypto', 'ai'] as $newsSection) {
    try {
        $liveNewsSnapshots[$newsSection] = news_snapshot($newsSection);
    } catch (Throwable $exception) {
        error_log('News snapshot error for ' . $newsSection . ': ' . $exception->getMessage());
        $liveNewsSnapshots[$newsSection] = [
            'stories' => [],
            'status' => 'failed',
            'stale' => true,
            'has_data' => false,
            'archive_count' => 0,
            'batch_id' => null,
            'last_success_display' => 'Not yet',
            'warning' => 'The local news database could not be opened.',
        ];
    }
}

$newsStateLabel = static function (array $snapshot): string {
    $countLabel = !empty($snapshot['ranking_ready'])
        ? sprintf(
            '%d selected / %d evaluated · %d clusters · %d archived',
            (int) ($snapshot['selected_count'] ?? 0),
            (int) ($snapshot['candidate_count'] ?? 0),
            (int) ($snapshot['cluster_count'] ?? 0),
            (int) ($snapshot['archive_count'] ?? 0),
        )
        : sprintf('%d stored · ranking pending', (int) ($snapshot['archive_count'] ?? 0));
    if ($snapshot['status'] === 'failed') {
        return ($snapshot['archive_count'] ?? 0) > 0 ? 'Feed check failed · previous ranked briefing retained · ' . $countLabel : 'Feed check unavailable';
    }
    if ($snapshot['status'] === 'partial') {
        return 'Ranked briefing ready · some feeds unavailable · ' . $countLabel;
    }
    if ($snapshot['status'] === 'ready') {
        return ($snapshot['stale'] ? 'Ranked cache ready · background check due · ' : 'Ranked briefing ready · ') . $countLabel;
    }
    return 'Waiting for first feed check';
};
$newsStatusLabel = static fn (string $status): string => match ($status) {
    'ready' => 'ready',
    'partial' => 'partial',
    'failed' => 'error',
    default => 'working',
};

foreach (['breaking', 'crypto', 'ai'] as $newsSection) {
    $snapshot = $liveNewsSnapshots[$newsSection];
    $sections[$newsSection] = array_replace($sections[$newsSection], [
        'status' => $newsStatusLabel($snapshot['status']),
        'state' => $newsStateLabel($snapshot),
        'updated' => $snapshot['last_success_display'],
        'batch' => $snapshot['batch_id'] ?? 'NOT-RUN',
    ]);
}

$financeNews = $liveNewsSnapshots['finance'];
$financeStories = $financeNews['stories'];
$breakingStories = $liveNewsSnapshots['breaking']['stories'];
$cryptoStories = $liveNewsSnapshots['crypto']['stories'];
$aiStories = $liveNewsSnapshots['ai']['stories'];
$sections['finance']['state'] .= ' · News: ' . $newsStateLabel($financeNews);
$sections['finance']['status'] = in_array('error', [$sections['finance']['status'], $newsStatusLabel($financeNews['status'])], true)
    ? 'error'
    : (in_array('partial', [$sections['finance']['status'], $newsStatusLabel($financeNews['status'])], true) ? 'partial' : $sections['finance']['status']);
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A five-minute personal news intelligence dashboard.">
    <title><?= e($pageTitle) ?></title>
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
        <a class="brand" href="/" aria-label="Personal Briefing home">
            <span class="brand__mark">PB</span>
            <span>Personal Briefing</span>
        </a>
        <nav class="topnav" aria-label="Primary navigation">
            <a class="topnav__link topnav__link--active" href="/">Dashboard</a>
            <a class="topnav__link" href="/source-status.php">Source status</a>
            <a class="topnav__link" href="/methodology.php">Ranking method</a>
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" aria-pressed="false">◐ <span>Theme</span></button>
        </nav>
    </header>

    <main class="shell">
        <section class="dashboard-toolbar" aria-labelledby="page-title">
            <div>
                <p class="system-label">LOCAL BRIEF // <?= e(strtoupper((new DateTimeImmutable())->format('D d M Y'))) ?></p>
                <h1 id="page-title">Personal Briefing</h1>
            </div>
            <button class="button button--primary" type="button" id="refresh-all">
                <span aria-hidden="true">↻</span> Refresh all
            </button>
        </section>

        <div class="demo-banner" role="note">
            <strong>Stage 4 · Deterministic briefing</strong>
            <span>Breaking, finance, crypto, and AI are scored against transparent category rules, clustered into distinct stories, and strengthened only by corroboration from different publishers. Qwen is still inactive; X, Italy, and local remain demonstration sections.</span>
        </div>

        <section class="briefing-meta" aria-label="Briefing status">
            <div>
                <span class="meta-label">Last opened</span>
                <strong id="last-opened">Just now</strong>
            </div>
            <div>
                <span class="meta-label">Data status</span>
                <strong><span class="status-dot status-dot--<?= e($financeStatus) ?>"></span> Markets + live feed intake</strong>
            </div>
            <div>
                <span class="meta-label">Reading target</span>
                <strong>Flexible · all qualifying items</strong>
            </div>
            <div>
                <span class="meta-label">Background preparation</span>
                <strong id="background-state">Checking live caches…</strong>
            </div>
        </section>

        <section class="dashboard-section dashboard-section--breaking" data-section="breaking">
            <?php render_section_header($sections['breaking']); ?>
            <p class="section-note"><strong>Strict threshold:</strong> only unusually consequential events are selected. There is no quota, so this section may legitimately be empty.</p>
            <div class="story-grid story-grid--featured" data-news-grid>
                <?php foreach ($breakingStories as $story) { render_story($story, 'story-card--breaking'); } ?>
            </div>
            <p class="empty-state" data-news-empty<?= $breakingStories !== [] ? ' hidden' : '' ?>>No major breaking event currently passes the deterministic threshold. Stored candidates remain in the archive.</p>
        </section>

        <section class="dashboard-section" data-section="finance">
            <?php render_section_header($sections['finance']); ?>
            <div class="subsection-heading">
                <div>
                    <p class="overline">Market snapshot</p>
                    <h3>Movement first, price second</h3>
                </div>
                <p>Compared with the previous close or 24-hour reference</p>
            </div>
            <div class="market-grid">
                <?php foreach ($markets as $market): ?>
                    <article class="market-card" data-market-key="<?= e($market['key']) ?>">
                        <div class="market-card__top">
                            <div>
                                <h4><?= e($market['name']) ?></h4>
                                <span><?= e($market['symbol']) ?> · <?= e($market['identifier']) ?></span>
                            </div>
                            <span class="currency" data-market-field="currency"><?= e($market['currency']) ?></span>
                        </div>
                        <p class="market-card__value" data-market-field="value"><?= e($market['value']) ?></p>
                        <dl>
                            <div>
                                <dt data-market-field="basis"><?= e(ucfirst($market['change_basis'])) ?></dt>
                                <dd class="change change--<?= e($market['direction']) ?>" data-market-field="change"><?= e($market['change']) ?></dd>
                            </div>
                            <div>
                                <dt>From highest close</dt>
                                <dd data-market-field="from-high"><?= e($market['from_high']) ?></dd>
                            </div>
                            <div>
                                <dt>Highest close</dt>
                                <dd data-market-field="high"><?= e($market['high']) ?></dd>
                            </div>
                        </dl>
                        <p class="retrieval-time">Retrieved <span data-market-field="retrieved"><?= e($market['retrieved']) ?></span></p>
                        <p class="retrieval-time">Provider updated <span data-market-field="provider-updated"><?= e($market['provider_updated']) ?></span> · <span data-market-field="provider"><?= e($market['provider']) ?></span></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="subsection-heading subsection-heading--stories">
                <div>
                    <p class="overline">Important financial news</p>
                    <h3>Only decisions and movements with broad consequences</h3>
                </div>
            </div>
            <div class="story-grid" data-news-grid>
                <?php foreach ($financeStories as $story) { render_story($story); } ?>
            </div>
            <p class="empty-state" data-news-empty<?= $financeStories !== [] ? ' hidden' : '' ?>>No stored finance candidate currently passes the ranking threshold. Market cards remain independent.</p>
        </section>

        <section class="dashboard-section dashboard-section--crypto" data-section="crypto">
            <?php render_section_header($sections['crypto']); ?>
            <p class="section-note"><strong>Focus:</strong> consequential Bitcoin, Ethereum, and ADA developments plus industry-wide regulation, security, infrastructure, and adoption changes. Routine price commentary is excluded.</p>
            <div class="story-grid" data-news-grid>
                <?php foreach ($cryptoStories as $story) { render_story($story); } ?>
            </div>
            <p class="empty-state" data-news-empty<?= $cryptoStories !== [] ? ' hidden' : '' ?>>No stored crypto candidate currently passes the ranking threshold.</p>
        </section>

        <section class="dashboard-section" data-section="ai">
            <?php render_section_header($sections['ai']); ?>
            <p class="section-note"><strong>Evidence policy:</strong> official announcements, field experts, community discovery, and Chinese open-model updates remain visibly distinct. Signals cannot be promoted as fact without a reputable source.</p>
            <div class="story-grid" data-news-grid>
                <?php foreach ($aiStories as $story) { render_story($story); } ?>
            </div>
            <p class="empty-state" data-news-empty<?= $aiStories !== [] ? ' hidden' : '' ?>>No stored AI / technology candidate currently passes the ranking threshold.</p>
        </section>

        <section class="dashboard-section dashboard-section--signals" data-section="x">
            <?php render_section_header($sections['x']); ?>
            <p class="section-note"><strong>Signal ≠ verified fact.</strong> These five posts are discovery leads. Important claims must be confirmed through a primary or reputable source.</p>
            <div class="signal-list">
                <?php foreach (array_slice($xSignals, 0, $dashboardConfig['x_signal_count']) as $signal): ?>
                    <article class="signal-card">
                        <div>
                            <span class="signal-card__account"><?= e($signal['account']) ?></span>
                            <span class="signal-card__topic"><?= e($signal['topic']) ?></span>
                        </div>
                        <p><?= e($signal['text']) ?></p>
                        <a href="<?= e($signal['url']) ?>" target="_blank" rel="noreferrer noopener" data-track-link>Open account example →</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="dashboard-section" data-section="italy">
            <?php render_section_header($sections['italy']); ?>
            <div class="story-grid">
                <?php foreach ($italyStories as $story) { render_story($story); } ?>
            </div>
        </section>

        <section class="dashboard-section" data-section="local">
            <?php render_section_header($sections['local']); ?>
            <div class="local-scope" aria-label="Local geographic priorities">
                <span>Lombardy</span><span>Sondrio</span><span>Alta Valtellina</span><span>Bormio</span><span>Sondalo</span><span>Valdisotto</span><span>Valdidentro</span><span>Valfurva</span><span>Livigno</span>
            </div>
            <div class="story-grid">
                <?php foreach ($localStories as $story) { render_story($story); } ?>
            </div>
        </section>

        <aside class="method-note">
            <div>
                <p class="overline">Current pipeline</p>
                <h2>Safe intake. Explainable ranking. Qwen later.</h2>
            </div>
            <p>Stage 4 reads stored titles and short feed excerpts, scores importance, relevance, evidence, practical impact, and novelty, then shows one representative per story cluster. Every candidate and score stays in SQLite. In Stage 5, Qwen3.5 4B will read only these survivors plus the relevant Markdown profile to refine summaries and explanations.</p>
        </aside>
    </main>

    <div class="toast" id="refresh-toast" role="status" aria-live="polite"></div>

    <footer class="footer">
        <span>Personal Briefing · Local-first MVP</span>
        <a href="/methodology.php">Inspect the ranking method →</a>
    </footer>
</body>
</html>
