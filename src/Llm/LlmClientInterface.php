<?php

declare(strict_types=1);

interface LlmClientInterface
{
    public function configuredModel(): string;

    /**
     * @return array{model:string, content:string, prompt_tokens:?int, completion_tokens:?int, duration_ms:int}
     */
    public function complete(array $messages, array $jsonSchema): array;
}
