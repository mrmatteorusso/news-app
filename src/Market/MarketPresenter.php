<?php

declare(strict_types=1);

final class MarketPresenter
{
    public static function presentSnapshot(array $snapshot): array
    {
        $markets = [];
        foreach ($snapshot['markets'] as $item) {
            $markets[] = self::presentMarket($item['config'], $item['quote']);
        }
        $state = $snapshot['state'];

        return [
            'markets' => $markets,
            'stale' => $snapshot['stale'],
            'has_data' => $snapshot['has_data'],
            'status' => $state['status'] ?? 'never',
            'warning' => $state['warning'] ?? null,
            'batch_id' => $state['published_batch_id'] ?? null,
            'last_success_at' => $state['last_success_at'] ?? null,
            'last_success_display' => self::formatTimestamp($state['last_success_at'] ?? null),
        ];
    }

    private static function presentMarket(array $config, ?array $quote): array
    {
        if ($quote === null) {
            return [
                'key' => $config['key'],
                'name' => $config['name'],
                'symbol' => $config['symbol'],
                'identifier' => $config['identifier'],
                'currency' => '—',
                'value' => '—',
                'change' => '—',
                'from_high' => '—',
                'high' => '—',
                'direction' => 'flat',
                'retrieved' => 'Waiting for first live refresh',
                'provider_updated' => '—',
                'provider' => self::providerName($config['provider']),
                'change_basis' => $config['change_basis'],
                'has_data' => false,
            ];
        }

        $currency = $quote['currency'] ?: '';
        $change = (float) $quote['change_percent'];
        return [
            'key' => $config['key'],
            'name' => $config['name'],
            'symbol' => $config['symbol'],
            'identifier' => $config['identifier'],
            'currency' => $currency,
            'value' => self::formatValue((float) $quote['latest_value'], $currency, (int) $config['decimals'], $config['value_style'], $config['value_suffix'] ?? ''),
            'change' => self::formatPercent($change),
            'from_high' => self::formatPercent((float) $quote['from_high_percent']),
            'high' => self::formatValue((float) $quote['highest_close'], $currency, (int) $config['decimals'], $config['value_style'], $config['value_suffix'] ?? ''),
            'direction' => $change > 0.00001 ? 'up' : ($change < -0.00001 ? 'down' : 'flat'),
            'retrieved' => self::formatTimestamp($quote['retrieved_at']),
            'provider_updated' => self::formatTimestamp($quote['provider_timestamp']),
            'provider' => self::providerName($quote['provider']),
            'change_basis' => $config['change_basis'],
            'has_data' => true,
        ];
    }

    private static function formatValue(float $value, string $currency, int $decimals, string $style, string $suffix = ''): string
    {
        $number = number_format($value, $decimals, '.', ',');
        if ($style === 'number') {
            return $number . $suffix;
        }
        $formatted = match ($currency) {
            'USD' => '$' . $number,
            'EUR' => '€' . $number,
            'GBP' => '£' . $number,
            default => trim($number . ' ' . $currency),
        };
        return $formatted . $suffix;
    }

    private static function formatPercent(float $value): string
    {
        return ($value > 0 ? '+' : '') . number_format($value, 2) . '%';
    }

    private static function formatTimestamp(?string $timestamp): string
    {
        if (!$timestamp) {
            return '—';
        }
        try {
            $date = new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d M H:i');
        } catch (Throwable) {
            return $timestamp;
        }
    }

    private static function providerName(string $provider): string
    {
        return match ($provider) {
            'coingecko' => 'CoinGecko Public API',
            'yahoo_chart' => 'Yahoo chart endpoint',
            default => $provider,
        };
    }
}
