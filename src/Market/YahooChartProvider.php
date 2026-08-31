<?php

declare(strict_types=1);

final class YahooChartProvider implements MarketProviderInterface
{
    public function __construct(private readonly HttpClient $httpClient)
    {
    }

    public function key(): string
    {
        return 'yahoo_chart';
    }

    public function fetch(array $instruments, array $states): array
    {
        $requests = [];
        $historyDue = [];
        $historyHours = max(1, (int) (getenv('MARKET_HISTORY_REFRESH_HOURS') ?: 168));
        $period2 = time() + 86400;

        foreach ($instruments as $instrument) {
            $state = $states[$instrument['key']] ?? null;
            $lastHistoryCheck = !empty($state['history_checked_at']) ? strtotime($state['history_checked_at'] . ' UTC') : false;
            $needsHistory = $lastHistoryCheck === false || $lastHistoryCheck < time() - ($historyHours * 3600) || empty($state['highest_close']);
            $historyDue[$instrument['key']] = $needsHistory;
            $symbol = rawurlencode($instrument['provider_symbol']);
            $query = $needsHistory
                ? 'period1=0&period2=' . $period2 . '&interval=1d&events=history'
                : 'range=10d&interval=1d&events=history';
            $requests[$instrument['key']] = [
                'url' => 'https://query1.finance.yahoo.com/v8/finance/chart/' . $symbol . '?' . $query,
                'headers' => ['Accept: application/json'],
            ];
        }

        $responses = $this->httpClient->getMany($requests);
        $results = [];
        $calls = [];
        $errors = [];

        foreach ($instruments as $instrument) {
            $key = $instrument['key'];
            $response = $responses[$key];
            $call = [
                'source_id' => $this->key(),
                'request_kind' => 'api',
                'status' => $response['ok'] ? 'success' : 'failed',
                'http_status' => $response['status'] ?: null,
                'item_count' => $response['ok'] ? 1 : 0,
                'duration_ms' => $response['duration_ms'],
                'error' => $response['error'],
                'instrument_key' => $key,
            ];

            if (!$response['ok']) {
                $message = sprintf('%s failed with HTTP %s%s', $instrument['symbol'], $response['status'] ?: 'network error', $response['error'] ? ': ' . $response['error'] : '');
                $call['error'] = $message;
                $calls[] = $call;
                $errors[] = $message;
                continue;
            }

            try {
                $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                $chart = $payload['chart']['result'][0] ?? null;
                if (!is_array($chart) || !empty($payload['chart']['error'])) {
                    throw new RuntimeException('Provider returned no chart data.');
                }

                $timestamps = $chart['timestamp'] ?? [];
                $closes = $chart['indicators']['quote'][0]['close'] ?? [];
                $validCloses = [];
                foreach ($closes as $index => $close) {
                    if ($close === null || !is_numeric($close)) {
                        continue;
                    }
                    $validCloses[] = [
                        'value' => (float) $close,
                        'timestamp' => isset($timestamps[$index]) ? (int) $timestamps[$index] : null,
                    ];
                }
                if (count($validCloses) < 2) {
                    throw new RuntimeException('Provider returned insufficient daily values.');
                }

                $meta = $chart['meta'] ?? [];
                $lastClose = $validCloses[array_key_last($validCloses)];
                $previousClose = $validCloses[array_key_last($validCloses) - 1];
                $latestValue = isset($meta['regularMarketPrice']) && is_numeric($meta['regularMarketPrice'])
                    ? (float) $meta['regularMarketPrice']
                    : $lastClose['value'];

                $state = $states[$key] ?? [];
                $highestClose = isset($state['highest_close']) ? (float) $state['highest_close'] : null;
                $highestCloseAt = $state['highest_close_at'] ?? null;
                if ($historyDue[$key]) {
                    $completedCloses = array_slice($validCloses, 0, -1);
                    foreach ($completedCloses as $dailyClose) {
                        if ($highestClose === null || $dailyClose['value'] > $highestClose) {
                            $highestClose = $dailyClose['value'];
                            $highestCloseAt = $dailyClose['timestamp'] ? gmdate('Y-m-d H:i:s', $dailyClose['timestamp']) : null;
                        }
                    }
                } elseif ($highestClose === null || $previousClose['value'] > $highestClose) {
                    $highestClose = $previousClose['value'];
                    $highestCloseAt = $previousClose['timestamp'] ? gmdate('Y-m-d H:i:s', $previousClose['timestamp']) : null;
                }

                if ($highestClose === null || $highestClose <= 0 || $previousClose['value'] <= 0) {
                    throw new RuntimeException('Provider returned invalid comparison values.');
                }

                $providerTimestamp = isset($meta['regularMarketTime']) && is_numeric($meta['regularMarketTime'])
                    ? gmdate('Y-m-d H:i:s', (int) $meta['regularMarketTime'])
                    : ($lastClose['timestamp'] ? gmdate('Y-m-d H:i:s', $lastClose['timestamp']) : gmdate('Y-m-d H:i:s'));

                $results[$key] = [
                    'instrument_key' => $key,
                    'provider' => $this->key(),
                    'provider_symbol' => $instrument['provider_symbol'],
                    'currency' => (string) ($meta['currency'] ?? ''),
                    'latest_value' => $latestValue,
                    'reference_value' => $previousClose['value'],
                    'change_percent' => (($latestValue - $previousClose['value']) / $previousClose['value']) * 100,
                    'highest_close' => $highestClose,
                    'highest_close_at' => $highestCloseAt,
                    'from_high_percent' => (($latestValue - $highestClose) / $highestClose) * 100,
                    'provider_timestamp' => $providerTimestamp,
                    'history_checked_at' => $historyDue[$key] ? gmdate('Y-m-d H:i:s') : ($state['history_checked_at'] ?? null),
                ];
                $calls[] = $call;
            } catch (Throwable $exception) {
                $message = $instrument['symbol'] . ': ' . $exception->getMessage();
                $call['status'] = 'failed';
                $call['item_count'] = 0;
                $call['error'] = $message;
                $calls[] = $call;
                $errors[] = $message;
            }
        }

        return ['results' => $results, 'calls' => $calls, 'errors' => $errors];
    }
}

