#!/usr/bin/env php
<?php
declare(strict_types=1);

/*
 | Standalone VirusTotal response tester.
 | Usage:
 |   VT_API_KEY=xxxx php ops/tools/vt-response-test.php 8.8.8.8
 |   VT_API_KEY=xxxx php ops/tools/vt-response-test.php 8.8.8.8 --raw-only
 */

function vtTestUsage(): void
{
    fwrite(STDERR, "Usage: VT_API_KEY=your_key php ops/tools/vt-response-test.php <ip> [--raw-only]\n");
    fwrite(STDERR, "Example: VT_API_KEY=xxxx php ops/tools/vt-response-test.php 8.8.8.8\n");
}

function vtTestPrintJson(mixed $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

function vtTestHttpGet(string $url, string $apiKey): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-apikey: ' . $apiKey,
            ],
            CURLOPT_USERAGENT => 'IPFeed-VirusTotal-Test/1.0',
        ]);

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = (string) curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['status_code' => $statusCode, 'headers' => '', 'body' => '', 'error' => $error];
        }

        return [
            'status_code' => $statusCode,
            'headers' => substr((string) $raw, 0, $headerSize),
            'body' => substr((string) $raw, $headerSize),
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n" .
                        "x-apikey: " . $apiKey . "\r\n" .
                        "User-Agent: IPFeed-VirusTotal-Test/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $statusCode = 0;
    $headers = isset($GLOBALS['http_response_header']) && is_array($GLOBALS['http_response_header'])
        ? implode("\n", $GLOBALS['http_response_header'])
        : '';

    if (preg_match('/\s(\d{3})\s/', (string) ($GLOBALS['http_response_header'][0] ?? ''), $matches) === 1) {
        $statusCode = (int) $matches[1];
    }

    return [
        'status_code' => $statusCode,
        'headers' => $headers,
        'body' => is_string($body) ? $body : '',
        'error' => is_string($body) ? '' : 'Unable to connect to VirusTotal.',
    ];
}

$args = array_values(array_slice($argv, 1));
$ip = '';
$rawOnly = false;

foreach ($args as $arg) {
    if ($arg === '--raw-only') {
        $rawOnly = true;
        continue;
    }

    if ($ip === '') {
        $ip = $arg;
    }
}

if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
    vtTestUsage();
    exit(2);
}

$apiKey = trim((string) getenv('VT_API_KEY'));

if ($apiKey === '') {
    fwrite(STDERR, "Missing VT_API_KEY environment variable.\n");
    vtTestUsage();
    exit(2);
}

$url = 'https://www.virustotal.com/api/v3/ip_addresses/' . rawurlencode($ip);
$response = vtTestHttpGet($url, $apiKey);
$body = (string) ($response['body'] ?? '');
$decoded = $body !== '' ? json_decode($body, true) : null;

if ($rawOnly) {
    echo $body, PHP_EOL;
    exit((int) ($response['status_code'] ?? 0) >= 200 && (int) ($response['status_code'] ?? 0) < 300 ? 0 : 1);
}

echo "VirusTotal IP response test\n";
echo "IP: " . $ip . PHP_EOL;
echo "URL: " . $url . PHP_EOL;
echo "HTTP status: " . (int) ($response['status_code'] ?? 0) . PHP_EOL;

if ((string) ($response['error'] ?? '') !== '') {
    echo "Transport error: " . (string) $response['error'] . PHP_EOL;
}

if (is_array($decoded)) {
    $attributes = $decoded['data']['attributes'] ?? [];
    $stats = is_array($attributes) ? ($attributes['last_analysis_stats'] ?? []) : [];

    echo PHP_EOL, "Parsed summary:\n";
    vtTestPrintJson([
        'id' => $decoded['data']['id'] ?? $ip,
        'type' => $decoded['data']['type'] ?? '',
        'last_analysis_stats' => is_array($stats) ? $stats : [],
        'reputation' => is_array($attributes) ? ($attributes['reputation'] ?? null) : null,
        'asn' => is_array($attributes) ? ($attributes['asn'] ?? null) : null,
        'as_owner' => is_array($attributes) ? ($attributes['as_owner'] ?? null) : null,
        'country' => is_array($attributes) ? ($attributes['country'] ?? null) : null,
        'last_analysis_date' => is_array($attributes) && isset($attributes['last_analysis_date'])
            ? date('Y-m-d H:i:s', (int) $attributes['last_analysis_date'])
            : null,
    ]);

    echo PHP_EOL, "Raw JSON:\n";
    vtTestPrintJson($decoded);
} else {
    echo PHP_EOL, "Raw body:\n", $body, PHP_EOL;
    echo PHP_EOL, "JSON error: ", json_last_error_msg(), PHP_EOL;
}

exit((int) ($response['status_code'] ?? 0) >= 200 && (int) ($response['status_code'] ?? 0) < 300 ? 0 : 1);
