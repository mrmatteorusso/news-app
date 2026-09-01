<?php

declare(strict_types=1);

require_once '/var/www/src/News/TopicTagger.php';

function topicEnsure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tagger = new TopicTagger(require '/var/www/config/topics.php');
$crypto = $tagger->classify(
    'Hackers breach two million Bitcoin wallets',
    'A major custody provider disclosed the incident.',
    'crypto',
);
$slugs = array_column($crypto['topics'], 'slug');
topicEnsure(in_array('bitcoin', $slugs, true), 'Bitcoin topic tag was not assigned.');
topicEnsure(in_array('cybersecurity', $slugs, true), 'Cybersecurity topic tag was not assigned.');
topicEnsure(in_array('breaking', $crypto['extra_sections'], true), 'A major cross-category security event was not promoted to Breaking.');

$routine = $tagger->classify('Bitcoin price moves two percent', 'Routine market commentary.', 'crypto');
topicEnsure($routine['extra_sections'] === [], 'Routine crypto coverage was incorrectly promoted to Breaking.');

echo "Topic tag smoke test passed: controlled topics and conservative cross-category assignment.\n";
