<?php

declare(strict_types=1);

final class HttpClient
{
    public function get(string $url, array $headers = []): array
    {
        $responses = $this->getMany(['request' => ['url' => $url, 'headers' => $headers]]);
        return $responses['request'];
    }

    public function getMany(array $requests): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($requests as $key => $request) {
            $handle = curl_init();
            curl_setopt_array($handle, [
                CURLOPT_URL => $request['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_USERAGENT => 'Personal-News-Dashboard/0.2 (+local personal use)',
                CURLOPT_HTTPHEADER => $request['headers'] ?? [],
                CURLOPT_ENCODING => '',
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[$key] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $active);
            if ($active) {
                $selected = curl_multi_select($multiHandle, 1.0);
                if ($selected === -1) {
                    usleep(10000);
                }
            }
        } while ($active && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $key => $handle) {
            $body = curl_multi_getcontent($handle);
            $responses[$key] = [
                'ok' => curl_errno($handle) === 0 && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) >= 200 && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) < 300,
                'status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'body' => is_string($body) ? $body : '',
                'error' => curl_error($handle) ?: null,
                'duration_ms' => (int) round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000),
            ];
            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);
        return $responses;
    }
}

