<?php

declare(strict_types=1);

interface MarketProviderInterface
{
    public function key(): string;

    /**
     * @return array{results: array<string, array>, calls: array<int, array>, errors: array<int, string>}
     */
    public function fetch(array $instruments, array $states): array;
}

