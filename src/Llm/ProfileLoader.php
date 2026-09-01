<?php

declare(strict_types=1);

final class ProfileLoader
{
    public function __construct(
        private readonly string $templateDirectory,
        private readonly string $privateDirectory,
        private readonly array $profileMap,
    ) {
    }

    public function load(string $section): array
    {
        $categoryFile = $this->profileMap[$section] ?? null;
        if (!is_string($categoryFile)) {
            throw new InvalidArgumentException('No Markdown profile is configured for this section.');
        }
        $general = $this->readProfile('GENERAL.md');
        $category = $this->readProfile($categoryFile);
        $combined = "--- GENERAL PROFILE ---\n{$general['content']}\n\n--- CATEGORY PROFILE ---\n{$category['content']}";
        return [
            'content' => $combined,
            'hash' => hash('sha256', $combined),
            'files' => [$general['path'], $category['path']],
            'uses_private_override' => $general['private'] || $category['private'],
        ];
    }

    private function readProfile(string $filename): array
    {
        $privatePath = rtrim($this->privateDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $templatePath = rtrim($this->templateDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $path = is_file($privatePath) && filesize($privatePath) > 0 ? $privatePath : $templatePath;
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            throw new RuntimeException('Unable to read the required profile: ' . $filename);
        }
        return [
            'content' => mb_substr(trim($content), 0, 16000),
            'path' => $path,
            'private' => $path === $privatePath,
        ];
    }
}
