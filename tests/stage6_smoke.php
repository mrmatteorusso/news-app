<?php

declare(strict_types=1);

function stage6Ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$news = require '/var/www/config/news.php';
$ranking = require '/var/www/config/ranking.php';
$llm = require '/var/www/config/llm.php';
$sources = require '/var/www/config/sources.php';

foreach (['italy', 'local'] as $section) {
    stage6Ensure(isset($news['sections'][$section]), "Missing {$section} news configuration.");
    stage6Ensure(isset($ranking['sections'][$section]), "Missing {$section} ranking rules.");
    stage6Ensure(isset($llm['profile_map'][$section]), "Missing {$section} Markdown profile mapping.");
}

$activeRss = array_values(array_filter(
    $sources,
    static fn (array $source): bool => ($source['stage3_active'] ?? false)
        && ($source['refresh_method'] ?? null) === 'rss',
));
$italySources = array_filter($activeRss, static fn (array $source): bool => in_array('italy', $source['categories'] ?? [], true));
$localSources = array_filter($activeRss, static fn (array $source): bool => in_array('local', $source['categories'] ?? [], true));

stage6Ensure(count($italySources) >= 1, 'Italy needs at least one active official RSS feed.');
stage6Ensure(count($localSources) >= 6, 'My Area needs the six validated Alta Valtellina municipal feeds.');
stage6Ensure($news['sections']['local']['ingest_max_age_hours'] === 336, 'Local display freshness must remain 14 days.');

echo "Stage 6 smoke test passed: live Italy/local sections, ranking profiles, and official RSS sources configured.\n";
