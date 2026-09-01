<?php

declare(strict_types=1);

final class TopicTagger
{
    public function __construct(private readonly array $config)
    {
    }

    /** @return array{topics:list<array{slug:string,label:string}>,extra_sections:list<string>} */
    public function classify(string $title, ?string $excerpt, string $currentSection): array
    {
        $text = $this->normalise($title . ' ' . ($excerpt ?? ''));
        $topics = [];
        foreach ($this->config['topics'] ?? [] as $slug => $definition) {
            if ($this->containsAny($text, $definition['terms'] ?? [])) {
                $topics[] = ['slug' => (string) $slug, 'label' => (string) ($definition['label'] ?? $slug)];
            }
        }

        $extraSections = [];
        foreach ($this->config['cross_sections'] ?? [] as $section => $rule) {
            if ($section === $currentSection) {
                continue;
            }
            $groups = $rule['all_groups'] ?? [];
            if ($groups !== [] && count(array_filter(
                $groups,
                fn (array $terms): bool => $this->containsAny($text, $terms),
            )) === count($groups)) {
                $extraSections[] = (string) $section;
            }
        }

        return ['topics' => $topics, 'extra_sections' => $extraSections];
    }

    private function containsAny(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            $needle = trim($this->normalise((string) $term));
            if ($needle !== '' && str_contains($text, ' ' . $needle . ' ')) {
                return true;
            }
        }
        return false;
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        return ' ' . trim(preg_replace('/\s+/u', ' ', $value) ?? '') . ' ';
    }
}
