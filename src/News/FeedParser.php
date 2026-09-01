<?php

declare(strict_types=1);

final class FeedParser
{
    public function __construct(
        private readonly int $maxItems = 30,
        private readonly int $excerptCharacters = 600,
    ) {
    }

    public function parse(string $body, string $sourceUrl): array
    {
        if ($body === '' || strlen($body) > 5_000_000) {
            throw new RuntimeException('Feed response is empty or larger than the 5 MB safety limit.');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml instanceof SimpleXMLElement) {
            $detail = isset($errors[0]) ? trim($errors[0]->message) : 'unknown XML error';
            throw new RuntimeException('Feed XML could not be parsed: ' . $detail);
        }

        $nodes = $xml->xpath('//*[local-name()="item"]') ?: [];
        if ($nodes === []) {
            $nodes = $xml->xpath('//*[local-name()="entry"]') ?: [];
        }
        if ($nodes === []) {
            throw new RuntimeException('Feed contains no RSS items or Atom entries.');
        }

        $items = [];
        foreach (array_slice($nodes, 0, $this->maxItems) as $node) {
            $title = $this->cleanText($this->firstText($node, ['./*[local-name()="title"]']));
            $url = $this->canonicalUrl($this->entryUrl($node), $sourceUrl);
            if ($title === '' || $url === null) {
                continue;
            }

            $published = $this->normaliseDate($this->firstText($node, [
                './*[local-name()="pubDate"]',
                './*[local-name()="published"]',
                './*[local-name()="date"]',
                './*[local-name()="updated"]',
            ]));
            $updated = $this->normaliseDate($this->firstText($node, [
                './*[local-name()="updated"]',
                './*[local-name()="modified"]',
                './*[local-name()="pubDate"]',
            ])) ?? $published;
            $excerpt = $this->cleanText($this->firstText($node, [
                './*[local-name()="description"]',
                './*[local-name()="summary"]',
                './*[local-name()="encoded"]',
                './*[local-name()="content"]',
            ]), $this->excerptCharacters);
            $author = $this->cleanText($this->firstText($node, [
                './*[local-name()="author"]/*[local-name()="name"]',
                './*[local-name()="creator"]',
                './*[local-name()="author"]',
            ]), 160);

            $items[] = [
                'canonical_url' => $url,
                'title' => $title,
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'author' => $author !== '' ? $author : null,
                'published_at' => $published,
                'source_updated_at' => $updated,
                'content_hash' => hash('sha256', implode('|', [$title, $excerpt, $updated ?? $published ?? ''])),
                'raw_payload' => null,
            ];
        }

        if ($items === []) {
            throw new RuntimeException('Feed items did not contain usable titles and HTTP links.');
        }

        return $items;
    }

    private function firstText(SimpleXMLElement $node, array $paths): string
    {
        foreach ($paths as $path) {
            $matches = $node->xpath($path) ?: [];
            if (isset($matches[0])) {
                $value = trim((string) $matches[0]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    private function entryUrl(SimpleXMLElement $node): string
    {
        foreach ($node->xpath('./*[local-name()="link"]') ?: [] as $link) {
            $attributes = $link->attributes();
            $href = trim((string) ($attributes['href'] ?? ''));
            $rel = trim((string) ($attributes['rel'] ?? ''));
            if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                return $href;
            }
            $text = trim((string) $link);
            if ($text !== '') {
                return $text;
            }
        }

        return $this->firstText($node, [
            './*[local-name()="guid"]',
            './*[local-name()="id"]',
        ]);
    }

    private function cleanText(string $value, int $limit = 300): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value);
        return mb_strlen($value) > $limit ? rtrim(mb_substr($value, 0, $limit - 1)) . '…' : $value;
    }

    private function normaliseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function canonicalUrl(string $value, string $sourceUrl): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '/')) {
            $source = parse_url($sourceUrl);
            if (!isset($source['scheme'], $source['host'])) {
                return null;
            }
            $value = $source['scheme'] . '://' . $source['host'] . $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (array_keys($query) as $key) {
                $normalised = strtolower((string) $key);
                if (str_starts_with($normalised, 'utm_') || in_array($normalised, ['fbclid', 'gclid', 'mc_cid', 'mc_eid'], true)) {
                    unset($query[$key]);
                }
            }
            ksort($query);
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $canonical = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . $port . $path;
        if ($query !== []) {
            $canonical .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $canonical;
    }
}
