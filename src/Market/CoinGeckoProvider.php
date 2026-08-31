<?php

declare(strict_types=1);

final class CoinGeckoProvider implements MarketProviderInterface
{
    public function __construct(private readonly HttpClient $httpClient)
    {
    }

    public function key(): string
    {
        return 'coingecko';
    }

    public function fetch(array $instruments, array $states): array
    {
        $ids = implode(',', array_column($instruments, 'provider_symbol'));
        $url = 'https://api.coingecko.com/api/v3/coins/markets?' . http_build_query([
            'vs_currency' => 'usd',
            'ids' => $ids,
            'price_change_percentage' => '24h',
            'sparkline' => 'false',
        ]);
        $response = $this->httpClient->get($url, ['Accept: application/json']);
        $call = [
            'source_id' => $this->key(),
            'request_kind' => 'api',
            'status' => $response['ok'] ? 'success' : 'failed',
            'http_status' => $response['status'] ?: null,
            'item_count' => 0,
            'duration_ms' => $response['duration_ms'],
            'error' => $response['error'],
            'instrument_key' => null,
        ];

        if (!$response['ok']) {
            $message = sprintf('CoinGecko failed with HTTP %s%s', $response['status'] ?: 'network error', $response['error'] ? ': ' . $response['error'] : '');
            $call['error'] = $message;
            return ['results' => [], 'calls' => [$call], 'errors' => [$message]];
        }

        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            $byId = [];
            foreach ($payload as $coin) {
                if (isset($coin['id'])) {
                    $byId[$coin['id']] = $coin;
                }
            }

            $results = [];
            $errors = [];
            foreach ($instruments as $instrument) {
                $coin = $byId[$instrument['provider_symbol']] ?? null;
                if (!is_array($coin) || !is_numeric($coin['current_price'] ?? null) || !is_numeric($coin['ath'] ?? null)) {
                    $errors[] = $instrument['symbol'] . ': CoinGecko returned incomplete data.';
                    continue;
                }
                $current = (float) $coin['current_price'];
                $change = is_numeric($coin['price_change_percentage_24h'] ?? null) ? (float) $coin['price_change_percentage_24h'] : 0.0;
                $reference = $change > -100 ? $current / (1 + ($change / 100)) : null;
                $ath = (float) $coin['ath'];
                $providerTime = !empty($coin['last_updated']) ? gmdate('Y-m-d H:i:s', strtotime($coin['last_updated'])) : gmdate('Y-m-d H:i:s');
                $results[$instrument['key']] = [
                    'instrument_key' => $instrument['key'],
                    'provider' => $this->key(),
                    'provider_symbol' => $instrument['provider_symbol'],
                    'currency' => 'USD',
                    'latest_value' => $current,
                    'reference_value' => $reference,
                    'change_percent' => $change,
                    'highest_close' => $ath,
                    'highest_close_at' => !empty($coin['ath_date']) ? gmdate('Y-m-d H:i:s', strtotime($coin['ath_date'])) : null,
                    'from_high_percent' => is_numeric($coin['ath_change_percentage'] ?? null)
                        ? (float) $coin['ath_change_percentage']
                        : (($current - $ath) / $ath) * 100,
                    'provider_timestamp' => $providerTime,
                    'history_checked_at' => gmdate('Y-m-d H:i:s'),
                ];
            }
            $call['item_count'] = count($results);
            if ($errors !== []) {
                $call['status'] = $results === [] ? 'failed' : 'success';
                $call['error'] = implode(' ', $errors);
            }
            return ['results' => $results, 'calls' => [$call], 'errors' => $errors];
        } catch (Throwable $exception) {
            $message = 'CoinGecko: ' . $exception->getMessage();
            $call['status'] = 'failed';
            $call['error'] = $message;
            return ['results' => [], 'calls' => [$call], 'errors' => [$message]];
        }
    }
}

