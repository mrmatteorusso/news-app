<?php

declare(strict_types=1);

require_once '/var/www/src/News/FeedParser.php';
require_once '/var/www/src/News/NewsRepository.php';

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec((string) file_get_contents('/var/www/database/schema.sql'));
$repository = new NewsRepository($pdo);
$repository->seedSources([[
    'id' => 'test_feed',
    'name' => 'Test Feed',
    'url' => 'https://example.test/feed.xml',
    'categories' => ['breaking'],
    'geography' => 'global',
    'source_type' => 'primary',
    'trust_level' => 5,
    'enabled' => true,
    'refresh_method' => 'rss',
    'notes' => 'Test source',
]]);

$rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Test</title><item>
<title>A material test event</title>
<link>https://example.test/story?utm_source=test&amp;id=7</link>
<description><![CDATA[<p>A short feed excerpt.</p>]]></description>
<pubDate>Mon, 31 Aug 2026 10:00:00 GMT</pubDate>
</item></channel></rss>
XML;
$parser = new FeedParser(30, 600);
$items = $parser->parse($rss, 'https://example.test/feed.xml');
ensure(count($items) === 1, 'RSS item was not parsed.');
ensure($items[0]['canonical_url'] === 'https://example.test/story?id=7', 'Tracking parameters were not removed.');
ensure($items[0]['excerpt'] === 'A short feed excerpt.', 'Feed HTML was not reduced to plain text.');
$items[0]['source_id'] = 'test_feed';

$successCall = [[
    'source_id' => 'test_feed',
    'status' => 'success',
    'http_status' => 200,
    'item_count' => 1,
    'started_at' => gmdate('Y-m-d H:i:s'),
    'error' => null,
]];
$repository->startBatch('TEST-1', 'breaking', 'manual_section');
$repository->completeBatch('TEST-1', 'breaking', 'breaking', $items, $successCall, 90);

$repository->startBatch('TEST-2', 'breaking', 'manual_section');
$repository->completeBatch('TEST-2', 'breaking', 'breaking', $items, $successCall, 90);
ensure((int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn() === 1, 'Canonical URL deduplication failed.');
ensure((int) $pdo->query('SELECT COUNT(*) FROM article_sections')->fetchColumn() === 1, 'Section mapping deduplication failed.');

$repository->startBatch('TEST-FAIL', 'breaking', 'manual_section');
$repository->completeBatch('TEST-FAIL', 'breaking', 'breaking', [], [[
    'source_id' => 'test_feed',
    'status' => 'failed',
    'http_status' => 503,
    'item_count' => 0,
    'started_at' => gmdate('Y-m-d H:i:s'),
    'error' => 'Synthetic outage',
]], 90);
$snapshot = $repository->latestSnapshot('breaking', 'breaking', 15, 24);
ensure($snapshot['has_data'] === true, 'A failed refresh removed the previous successful article.');
ensure($snapshot['state']['status'] === 'failed', 'Failure state was not recorded.');
ensure($snapshot['state']['published_batch_id'] === 'TEST-2', 'Failed batch replaced the last published batch.');
ensure($pdo->query('SELECT raw_payload FROM articles LIMIT 1')->fetchColumn() === null, 'Raw article content should not be stored.');

echo "Stage 3 smoke test passed: parse, canonicalise, deduplicate, retain on failure, metadata-only storage.\n";
