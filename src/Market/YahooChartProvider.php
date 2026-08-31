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
        $needsEuroConversion = false;
        $euroConversionNeedsHistory = false;
        $historyHours = max(1, (int) (getenv('MARKET_HISTORY_REFRESH_HOURS') ?: 168));
        $period2 = time() + 86400;

        foreach ($instruments as $instrument) {
            $state = $states[$instrument['key']] ?? null;
            $lastHistoryCheck = !empty($state['history_checked_at']) ? strtotime($state['history_checked_at'] . ' UTC') : false;
            $needsHistory = $lastHistoryCheck === false || $lastHistoryCheck < time() - ($historyHours * 3600) || empty($state['highest_close']);
            $historyDue[$instrument['key']] = $needsHistory;
            if (($instrument['conversion'] ?? null) === 'usd_ounce_to_eur_gram') {
                $needsEuroConversion = true;
                $euroConversionNeedsHistory = $euroConversionNeedsHistory || $needsHistory;
            }
            $symbol = rawurlencode($instrument['provider_symbol']);
            $query = $needsHistory
                ? 'period1=0&period2=' . $period2 . '&interval=1d&events=history'
                : 'range=10d&interval=1d&events=history';
            $requests[$instrument['key']] = [
                'url' => 'https://query1.finance.yahoo.com/v8/finance/chart/' . $symbol . '?' . $query,
                'headers' => ['Accept: application/json'],
            ];
        }

        $euroConversionRequestKey = '__eurusd_conversion';
        if ($needsEuroConversion) {
            $query = $euroConversionNeedsHistory
                ? 'period1=0&period2=' . $period2 . '&interval=1d&events=history'
                : 'range=10d&interval=1d&events=history';
            $requests[$euroConversionRequestKey] = [
                'url' => 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode('EURUSD=X') . '?' . $query,
                'headers' => ['Accept: application/json'],
            ];
        }

        $responses = $this->httpClient->getMany($requests);
        $results = [];
        $calls = [];
        $errors = [];
        $euroConversionChart = null;

        if ($needsEuroConversion) {
            $fxResponse = $responses[$euroConversionRequestKey];
            $fxCall = [
                'source_id' => $this->key(),
                'request_kind' => 'api',
                'status' => $fxResponse['ok'] ? 'success' : 'failed',
                'http_status' => $fxResponse['status'] ?: null,
                'item_count' => $fxResponse['ok'] ? 1 : 0,
                'duration_ms' => $fxResponse['duration_ms'],
                'error' => $fxResponse['error'],
                'instrument_key' => $euroConversionRequestKey,
            ];
            if (!$fxResponse['ok']) {
                $message = sprintf('EUR/USD conversion failed with HTTP %s%s', $fxResponse['status'] ?: 'network error', $fxResponse['error'] ? ': ' . $fxResponse['error'] : '');
                $fxCall['error'] = $message;
                $errors[] = $message;
            } else {
                try {
                    $euroConversionChart = $this->parseChart($fxResponse['body']);
                } catch (Throwable $exception) {
                    $message = 'EUR/USD conversion: ' . $exception->getMessage();
                    $fxCall['status'] = 'failed';
                    $fxCall['item_count'] = 0;
                    $fxCall['error'] = $message;
                    $errors[] = $message;
                }
            }
            $calls[] = $fxCall;
        }

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
                $parsedChart = $this->parseChart($response['body']);
                $validCloses = $parsedChart['closes'];
                $meta = $parsedChart['meta'];
                $lastClose = $validCloses[array_key_last($validCloses)];
                $previousClose = $validCloses[array_key_last($validCloses) - 1];
                $latestValue = isset($meta['regularMarketPrice']) && is_numeric($meta['regularMarketPrice'])
                    ? (float) $meta['regularMarketPrice']
                    : $lastClose['value'];
                $referenceValue = $previousClose['value'];
                $comparisonCloses = $validCloses;
                $currency = (string) ($meta['currency'] ?? '');
                $providerTimestampUnix = $this->providerTimestamp($meta, $lastClose);

                if (($instrument['conversion'] ?? null) === 'usd_ounce_to_eur_gram') {
                    if ($euroConversionChart === null) {
                        throw new RuntimeException('EUR/USD conversion data is unavailable.');
                    }
                    $fxCloses = $euroConversionChart['closes'];
                    $fxMeta = $euroConversionChart['meta'];
                    $fxLastClose = $fxCloses[array_key_last($fxCloses)];
                    $latestFx = isset($fxMeta['regularMarketPrice']) && is_numeric($fxMeta['regularMarketPrice'])
                        ? (float) $fxMeta['regularMarketPrice']
                        : $fxLastClose['value'];
                    $previousFx = $this->valueAtOrBefore($fxCloses, $previousClose['timestamp']);
                    if ($latestFx <= 0 || $previousFx === null || $previousFx <= 0) {
                        throw new RuntimeException('EUR/USD conversion returned invalid comparison values.');
                    }

                    $latestValue = $this->usdOunceToEurGram($latestValue, $latestFx);
                    $referenceValue = $this->usdOunceToEurGram($previousClose['value'], $previousFx);
                    $comparisonCloses = [];
                    foreach ($validCloses as $dailyClose) {
                        $dailyFx = $this->valueAtOrBefore($fxCloses, $dailyClose['timestamp']);
                        if ($dailyFx === null || $dailyFx <= 0) {
                            continue;
                        }
                        $comparisonCloses[] = [
                            'value' => $this->usdOunceToEurGram($dailyClose['value'], $dailyFx),
                            'timestamp' => $dailyClose['timestamp'],
                        ];
                    }
                    if (count($comparisonCloses) < 2) {
                        throw new RuntimeException('Insufficient aligned Gold and EUR/USD history.');
                    }
                    $currency = 'EUR';
                    $providerTimestampUnix = min(
                        $providerTimestampUnix,
                        $this->providerTimestamp($fxMeta, $fxLastClose),
                    );
                }

                $state = $states[$key] ?? [];
                $highestClose = isset($state['highest_close']) ? (float) $state['highest_close'] : null;
                $highestCloseAt = $state['highest_close_at'] ?? null;
                if ($historyDue[$key]) {
                    $highestClose = null;
                    $highestCloseAt = null;
                    $completedCloses = array_slice($comparisonCloses, 0, -1);
                    foreach ($completedCloses as $dailyClose) {
                        if ($highestClose === null || $dailyClose['value'] > $highestClose) {
                            $highestClose = $dailyClose['value'];
                            $highestCloseAt = $dailyClose['timestamp'] ? gmdate('Y-m-d H:i:s', $dailyClose['timestamp']) : null;
                        }
                    }
                } elseif ($highestClose === null || $referenceValue > $highestClose) {
                    $highestClose = $referenceValue;
                    $highestCloseAt = $previousClose['timestamp'] ? gmdate('Y-m-d H:i:s', $previousClose['timestamp']) : null;
                }

                if ($highestClose === null || $highestClose <= 0 || $referenceValue <= 0) {
                    throw new RuntimeException('Provider returned invalid comparison values.');
                }

                $results[$key] = [
                    'instrument_key' => $key,
                    'provider' => $this->key(),
                    'provider_symbol' => $instrument['provider_symbol'],
                    'currency' => $currency,
                    'latest_value' => $latestValue,
                    'reference_value' => $referenceValue,
                    'change_percent' => (($latestValue - $referenceValue) / $referenceValue) * 100,
                    'highest_close' => $highestClose,
                    'highest_close_at' => $highestCloseAt,
                    'from_high_percent' => (($latestValue - $highestClose) / $highestClose) * 100,
                    'provider_timestamp' => gmdate('Y-m-d H:i:s', $providerTimestampUnix),
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

    private function parseChart(string $body): array
    {
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
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

        return ['meta' => $chart['meta'] ?? [], 'closes' => $validCloses];
    }

    private function valueAtOrBefore(array $closes, ?int $timestamp): ?float
    {
        for ($index = count($closes) - 1; $index >= 0; $index--) {
            if ($timestamp === null || $closes[$index]['timestamp'] === null || $closes[$index]['timestamp'] <= $timestamp) {
                return (float) $closes[$index]['value'];
            }
        }
        return null;
    }

    private function providerTimestamp(array $meta, array $lastClose): int
    {
        if (isset($meta['regularMarketTime']) && is_numeric($meta['regularMarketTime'])) {
            return (int) $meta['regularMarketTime'];
        }
        return $lastClose['timestamp'] ?: time();
    }

    private function usdOunceToEurGram(float $usdPerTroyOunce, float $usdPerEuro): float
    {
        return ($usdPerTroyOunce / $usdPerEuro) / 31.1034768;
    }
}
