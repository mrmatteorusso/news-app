<?php

declare(strict_types=1);

final class LmStudioClient implements LlmClientInterface
{
    private ?string $resolvedModel = null;

    public function __construct(private readonly array $config)
    {
    }

    public function configuredModel(): string
    {
        return $this->config['model'];
    }

    public function complete(array $messages, array $jsonSchema): array
    {
        $started = microtime(true);
        $model = $this->resolveModel();
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->config['temperature'],
            'max_tokens' => $this->config['max_output_tokens'],
            'stream' => false,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'news_profile_review',
                    'strict' => true,
                    'schema' => $jsonSchema,
                ],
            ],
        ];

        $response = $this->request('POST', '/chat/completions', $payload);
        if (!$response['ok'] && in_array($response['status'], [400, 422], true)) {
            unset($payload['response_format']);
            $response = $this->request('POST', '/chat/completions', $payload);
        }
        if (!$response['ok']) {
            throw new RuntimeException($this->errorMessage($response));
        }

        $decoded = json_decode($response['body'], true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('LM Studio returned no assistant content.');
        }

        return [
            'model' => (string) ($decoded['model'] ?? $model),
            'content' => $content,
            'prompt_tokens' => isset($decoded['usage']['prompt_tokens']) ? (int) $decoded['usage']['prompt_tokens'] : null,
            'completion_tokens' => isset($decoded['usage']['completion_tokens']) ? (int) $decoded['usage']['completion_tokens'] : null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function resolveModel(): string
    {
        if ($this->resolvedModel !== null) {
            return $this->resolvedModel;
        }
        $response = $this->request('GET', '/models');
        if (!$response['ok']) {
            throw new RuntimeException($this->errorMessage($response, 'LM Studio is not reachable.'));
        }
        $decoded = json_decode($response['body'], true);
        $rows = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $models = array_values(array_filter(array_map(
            static fn (mixed $row): ?string => is_array($row) && is_string($row['id'] ?? null) ? $row['id'] : null,
            $rows,
        )));
        if ($models === []) {
            throw new RuntimeException('LM Studio is running, but no model is visible to its API server.');
        }

        $requested = $this->configuredModel();
        foreach ($models as $model) {
            if ($model === $requested) {
                return $this->resolvedModel = $model;
            }
        }
        $normalisedRequested = $this->normaliseModelName($requested);
        foreach ($models as $model) {
            $normalisedModel = $this->normaliseModelName($model);
            if (str_contains($normalisedModel, $normalisedRequested)
                || str_contains($normalisedRequested, $normalisedModel)) {
                return $this->resolvedModel = $model;
            }
        }
        if (count($models) === 1) {
            return $this->resolvedModel = $models[0];
        }
        throw new RuntimeException(sprintf(
            'Configured model "%s" was not found. Visible LM Studio models: %s.',
            $requested,
            implode(', ', $models),
        ));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $handle = curl_init($this->config['base_url'] . $path);
        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $apiKey = trim((string) (getenv('LLM_API_KEY') ?: ''));
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->config['connect_timeout_seconds'],
            CURLOPT_TIMEOUT => $this->config['request_timeout_seconds'],
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
        }
        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return [
            'ok' => is_string($body) && $error === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => $error,
        ];
    }

    private function errorMessage(array $response, string $fallback = 'LM Studio rejected the request.'): string
    {
        if ($response['error'] !== '') {
            return $fallback . ' ' . $response['error'];
        }
        $decoded = json_decode($response['body'], true);
        $message = $decoded['error']['message'] ?? $decoded['error'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return sprintf('LM Studio HTTP %d: %s', $response['status'], trim($message));
        }
        return sprintf('%s HTTP %d.', $fallback, $response['status']);
    }

    private function normaliseModelName(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }
}
