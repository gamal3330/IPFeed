<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function getGeoInfo(string $ip): array
{
    $default = [
        'country' => 'Unknown',
        'city' => 'Unknown',
        'isp' => 'Unknown',
    ];

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,city,isp';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 4,
            'ignore_errors' => true,
            'header' => "User-Agent: IP-Feed-Manager/1.0\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return $default;
    }

    $data = json_decode($response, true);

    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return $default;
    }

    return [
        'country' => $data['country'] ?? 'Unknown',
        'city' => $data['city'] ?? 'Unknown',
        'isp' => $data['isp'] ?? 'Unknown',
    ];
}

function isKnownCountry(string $country): bool
{
    $country = trim($country);

    return $country !== '' && $country !== '-' && strcasecmp($country, 'Unknown') !== 0;
}

function readGeoCache(string $file): array
{
    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        $stmt = $db->query('
            SELECT ip, country, country_code, city, isp, updated_at
            FROM geo_cache
            ORDER BY ip ASC
        ');
        $cache = [];

        foreach ($stmt->fetchAll() as $row) {
            $ip = trim((string) ($row['ip'] ?? ''));

            if ($ip === '') {
                continue;
            }

            $entry = [
                'country' => (string) ($row['country'] ?? 'Unknown'),
                'city' => (string) ($row['city'] ?? 'Unknown'),
                'isp' => (string) ($row['isp'] ?? 'Unknown'),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];

            $countryCode = trim((string) ($row['country_code'] ?? ''));
            if ($countryCode !== '') {
                $entry['country_code'] = $countryCode;
            }

            $cache[$ip] = $entry;
        }

        return $cache;
    }

    if (!file_exists($file)) {
        return [];
    }

    $json = file_get_contents($file);

    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function saveGeoCache(string $file, array $cache): void
{
    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        $stmt = $db->prepare('
            INSERT INTO geo_cache (ip, country, country_code, city, isp, updated_at)
            VALUES (:ip, :country, :country_code, :city, :isp, :updated_at)
            ON CONFLICT(ip) DO UPDATE SET
                country = excluded.country,
                country_code = excluded.country_code,
                city = excluded.city,
                isp = excluded.isp,
                updated_at = excluded.updated_at
        ');

        $db->beginTransaction();

        try {
            foreach ($cache as $ip => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $ip = trim((string) $ip);

                if ($ip === '') {
                    continue;
                }

                $stmt->execute([
                    ':ip' => $ip,
                    ':country' => (string) ($entry['country'] ?? 'Unknown'),
                    ':country_code' => (string) ($entry['country_code'] ?? ''),
                    ':city' => (string) ($entry['city'] ?? 'Unknown'),
                    ':isp' => (string) ($entry['isp'] ?? 'Unknown'),
                    ':updated_at' => (string) ($entry['updated_at'] ?? date('Y-m-d H:i:s')),
                ]);
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return;
    }

    @file_put_contents(
        $file,
        json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function isPrivateOrReservedIp(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function getRequestClientIp(): string
{
    $candidates = [];

    // عند استخدام Cloudflare، هذا هو IP الزائر الحقيقي.
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    // في حالة عدم استخدام Cloudflare أو أثناء الاختبار المحلي.
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = (string) $_SERVER['REMOTE_ADDR'];
    }

    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return 'unknown';
}

function normalizeCountryCode(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?: '';

    return substr($code, 0, 2);
}

function getVisitorCountryFromCloudflare(): array
{
    $code = normalizeCountryCode((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));

    if ($code === '') {
        return [
            'country_code' => '',
            'country' => 'Unknown',
            'source' => 'none',
            'error' => 'Cloudflare country header is missing',
        ];
    }

    return [
        'country_code' => $code,
        'country' => $code,
        'source' => 'cloudflare',
        'error' => '',
    ];
}

function getVisitorCountryFromIpApi(string $ip, string $cacheFile, int $ttlSeconds): array
{
    $default = [
        'country_code' => 'XX',
        'country' => 'Unknown',
        'source' => 'ip-api',
        'error' => '',
    ];

    if ($ip === 'unknown' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return $default + ['error' => 'Visitor IP is unknown'];
    }

    if (isPrivateOrReservedIp($ip)) {
        return [
            'country_code' => 'LOCAL',
            'country' => 'Local/Private',
            'source' => 'local',
            'error' => '',
        ];
    }

    $cache = readGeoCache($cacheFile);
    $cached = $cache[$ip] ?? null;

    if (is_array($cached)) {
        $updatedAt = strtotime((string) ($cached['updated_at'] ?? ''));
        if ($updatedAt !== false && (time() - $updatedAt) < $ttlSeconds) {
            return [
                'country_code' => normalizeCountryCode((string) ($cached['country_code'] ?? 'XX')),
                'country' => (string) ($cached['country'] ?? 'Unknown'),
                'source' => 'cache',
                'error' => '',
            ];
        }
    }

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,countryCode,message';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return $default + ['error' => 'Unable to reach GeoIP lookup service'];
    }

    $data = json_decode((string) $response, true);

    if (!is_array($data) || (string) ($data['status'] ?? '') !== 'success') {
        return $default + ['error' => (string) ($data['message'] ?? 'GeoIP lookup failed')];
    }

    $result = [
        'country_code' => normalizeCountryCode((string) ($data['countryCode'] ?? 'XX')),
        'country' => (string) ($data['country'] ?? 'Unknown'),
        'source' => 'ip-api',
        'error' => '',
    ];

    $cache[$ip] = [
        'country_code' => $result['country_code'],
        'country' => $result['country'],
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    saveGeoCache($cacheFile, $cache);

    return $result;
}

function resolveVisitorCountryAccess(
    array $allowedCountries,
    string $cacheFile,
    bool $allowLocalAndPrivateVisitors,
    int $cacheTtlSeconds
): array {
    $ip = getRequestClientIp();
    $allowedCodes = array_map('strtoupper', array_keys($allowedCountries));

    if ($allowLocalAndPrivateVisitors && filter_var($ip, FILTER_VALIDATE_IP) && isPrivateOrReservedIp($ip)) {
        return [
            'allowed' => true,
            'ip' => $ip,
            'country_code' => 'LOCAL',
            'country' => 'Local/Private',
            'source' => 'local',
            'allowed_countries' => $allowedCountries,
            'error' => '',
        ];
    }

    $country = getVisitorCountryFromCloudflare();

    if (($country['country_code'] ?? '') === '' || ($country['country_code'] ?? '') === 'XX') {
        $country = getVisitorCountryFromIpApi($ip, $cacheFile, $cacheTtlSeconds);
    }

    $countryCode = normalizeCountryCode((string) ($country['country_code'] ?? 'XX'));
    $isAllowed = in_array($countryCode, $allowedCodes, true);

    return [
        'allowed' => $isAllowed,
        'ip' => $ip,
        'country_code' => $countryCode,
        'country' => (string) ($country['country'] ?? 'Unknown'),
        'source' => (string) ($country['source'] ?? 'unknown'),
        'allowed_countries' => $allowedCountries,
        'error' => (string) ($country['error'] ?? ''),
    ];
}

function renderCountryBlockedPage(array $visitorAccess): void
{
    http_response_code(403);
    $allowedNames = implode(' و ', array_values($visitorAccess['allowed_countries'] ?? []));
    $countryCode = (string) ($visitorAccess['country_code'] ?? 'XX');
    $country = (string) ($visitorAccess['country'] ?? 'Unknown');
    $ip = (string) ($visitorAccess['ip'] ?? 'unknown');
    $source = (string) ($visitorAccess['source'] ?? 'unknown');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'unknown-host');
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestedUrl = $host !== 'unknown-host' ? $scheme . '://' . $host . $requestUri : $requestUri;
    $blockedAt = date('Y-m-d H:i:s');

    ?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غير مسموح لك الدخول</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0, #0f172a 52%, #020617 100%);
            color: #0f172a;
        }
        .box {
            width: min(680px, calc(100% - 32px));
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 26px;
            padding: 34px;
            box-shadow: 0 28px 80px rgba(2, 6, 23, 0.36);
            text-align: center;
        }
        .icon {
            width: 68px;
            height: 68px;
            display: inline-grid;
            place-items: center;
            margin-bottom: 16px;
            border-radius: 22px;
            color: #b91c1c;
            background: #fee2e2;
            font-size: 34px;
            font-weight: 900;
        }
        h1 {
            margin: 0 0 10px;
            color: #b91c1c;
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 950;
        }
        p {
            margin: 8px 0;
            color: #475569;
            line-height: 1.8;
            font-size: 16px;
        }
        .meta {
            display: grid;
            gap: 10px;
            margin-top: 22px;
            padding: 16px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: right;
        }
        .meta-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px;
            align-items: start;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .meta-row:last-child { border-bottom: 0; }
        .meta-label {
            color: #64748b;
            font-weight: 900;
        }
        .meta-value {
            direction: ltr;
            text-align: left;
            font-family: Consolas, "Courier New", monospace;
            color: #0f172a;
            word-break: break-all;
        }
        @media (max-width: 640px) {
            .box { padding: 24px; }
            .meta-row { grid-template-columns: 1fr; gap: 4px; }
            .meta-value { text-align: right; }
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">!</div>
        <h1>غير مسموح لك الدخول</h1>
        <p>هذه اللوحة متاحة فقط للزوار من <?= e($allowedNames) ?>.</p>
        <p>تم تسجيل بيانات الطلب الظاهرة أدناه للمراجعة الأمنية.</p>

        <div class="meta" aria-label="تفاصيل محاولة الدخول">
            <div class="meta-row">
                <div class="meta-label">العنوان الذي حاول الدخول إليه</div>
                <div class="meta-value"><?= e($requestedUrl) ?></div>
            </div>
            <div class="meta-row">
                <div class="meta-label">IP الزائر</div>
                <div class="meta-value"><?= e($ip) ?></div>
            </div>
            <div class="meta-row">
                <div class="meta-label">الدولة</div>
                <div class="meta-value"><?= e($country) ?> / <?= e($countryCode) ?></div>
            </div>
            <div class="meta-row">
                <div class="meta-label">مصدر تحديد الدولة</div>
                <div class="meta-value"><?= e($source) ?></div>
            </div>
            <div class="meta-row">
                <div class="meta-label">وقت المحاولة</div>
                <div class="meta-value"><?= e($blockedAt) ?></div>
            </div>
        </div>
    </div>
</body>
</html><?php
    exit;
}

function countryMapFromLogForCurrentIps(array $log, array $currentIps): array
{
    $currentSet = array_flip($currentIps);
    $map = [];

    foreach ($log as $row) {
        $ip = trim((string) ($row['ip'] ?? ''));

        if (!isset($currentSet[$ip])) {
            continue;
        }

        $country = trim((string) ($row['country'] ?? ''));

        if (isKnownCountry($country)) {
            $map[$ip] = $country;
        }
    }

    return $map;
}

function topCountriesForCurrentIpList(array $currentIps, array $log, string $geoCacheFile, int $limit = 5, int $lookupLimit = 20): array
{
    $currentIps = array_values(array_unique(array_filter(array_map('trim', $currentIps), static function ($ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    })));

    $countryByIp = countryMapFromLogForCurrentIps($log, $currentIps);
    $cache = readGeoCache($geoCacheFile);
    $cacheChanged = false;

    foreach ($currentIps as $ip) {
        if (isset($countryByIp[$ip])) {
            continue;
        }

        $cachedCountry = trim((string) ($cache[$ip]['country'] ?? ''));

        if (isKnownCountry($cachedCountry)) {
            $countryByIp[$ip] = $cachedCountry;
        }
    }

    $lookups = 0;

    foreach ($currentIps as $ip) {
        if (isset($countryByIp[$ip])) {
            continue;
        }

        if ($lookups >= $lookupLimit) {
            break;
        }

        $geo = getGeoInfo($ip);
        $lookups++;

        $cache[$ip] = [
            'country' => $geo['country'] ?? 'Unknown',
            'city' => $geo['city'] ?? 'Unknown',
            'isp' => $geo['isp'] ?? 'Unknown',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $cacheChanged = true;

        if (isKnownCountry((string) ($geo['country'] ?? ''))) {
            $countryByIp[$ip] = (string) $geo['country'];
        }

        usleep(180000);
    }

    if ($cacheChanged) {
        saveGeoCache($geoCacheFile, $cache);
    }

    $countries = [];

    foreach ($currentIps as $ip) {
        $country = trim((string) ($countryByIp[$ip] ?? ''));

        if (!isKnownCountry($country)) {
            continue;
        }

        $countries[$country] = ($countries[$country] ?? 0) + 1;
    }

    arsort($countries);

    $meta = [
        'total' => count($currentIps),
        'known' => count($countryByIp),
        'unknown' => max(0, count($currentIps) - count($countryByIp)),
        'lookups' => $lookups,
        'source' => 'ips.txt الحالي',
    ];

    return [array_slice($countries, 0, $limit, true), $meta];
}
