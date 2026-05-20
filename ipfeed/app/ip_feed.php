<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function cleanIps(string $input): array
{
    $items = preg_split('/[\s,;]+/', $input) ?: [];
    $valid = [];

    foreach ($items as $ip) {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $valid[] = $ip;
        }
    }

    return array_values(array_unique($valid));
}

function positivePageParam(string $name): int
{
    $value = filter_input(INPUT_GET, $name, FILTER_VALIDATE_INT);

    if (!is_int($value) || $value < 1) {
        return 1;
    }

    return $value;
}

function pageUrl(string $param, int $page): string
{
    $query = $_GET;
    unset($query['logout']);
    $query[$param] = max(1, $page);

    return '?' . http_build_query($query);
}

function currentUrlWithout(array $keys): string
{
    $query = $_GET;
    $keys[] = 'logout';

    foreach ($keys as $key) {
        unset($query[$key]);
    }

    return empty($query) ? '?' : '?' . http_build_query($query);
}

function normalizeIpSearchQuery(string $query): string
{
    $query = trim($query);
    $query = preg_replace('/[^0-9a-fA-F:\.\/\s,;*_-]+/', '', $query) ?? '';
    $query = preg_replace('/\s+/', ' ', $query) ?? '';

    return substr(trim($query), 0, 120);
}

function filterIpsBySearchQuery(array $ips, string $query): array
{
    $query = normalizeIpSearchQuery($query);

    if ($query === '') {
        return array_values($ips);
    }

    $terms = preg_split('/[\s,;]+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $terms = array_values(array_unique(array_map(static function (string $term): string {
        return trim($term);
    }, $terms)));

    if (empty($terms)) {
        return array_values($ips);
    }

    return array_values(array_filter($ips, static function (string $ip) use ($terms): bool {
        foreach ($terms as $term) {
            if ($term !== '' && stripos($ip, $term) !== false) {
                return true;
            }
        }

        return false;
    }));
}

function domId(string $prefix, string $value): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $value) ?: 'item';

    return $prefix . '_' . $safe;
}

function allowedIpSort(string $value): string
{
    $allowed = ['natural', 'severity_desc', 'severity_asc', 'malicious_desc', 'malicious_asc'];

    return in_array($value, $allowed, true) ? $value : 'natural';
}

function ipSortLabel(string $sort): string
{
    return match ($sort) {
        'severity_desc' => 'الأعلى خطورة أولاً',
        'severity_asc' => 'الأقل خطورة أولاً',
        'malicious_desc' => 'أعلى VT Score أولاً',
        'malicious_asc' => 'أقل VT Score أولاً',
        default => 'الترتيب الطبيعي حسب IP',
    };
}

function compareIpNatural(string $a, string $b): int
{
    $longA = ip2long($a);
    $longB = ip2long($b);

    if ($longA !== false && $longB !== false) {
        $unsignedA = (int) sprintf('%u', $longA);
        $unsignedB = (int) sprintf('%u', $longB);

        return $unsignedA <=> $unsignedB;
    }

    return strnatcmp($a, $b);
}

function vtSeverityRankForRow(?array $row): int
{
    if ($row === null) {
        return 0;
    }

    $status = (string) ($row['vt_status'] ?? '');
    $malicious = (int) ($row['vt_malicious'] ?? 0);
    $suspicious = (int) ($row['vt_suspicious'] ?? 0);

    if ($status === 'خطير' || $malicious >= 3) {
        return 4;
    }

    if ($status === 'مشبوه' || $malicious > 0 || $suspicious > 0) {
        return 3;
    }

    if ($status === 'نظيف') {
        return 1;
    }

    return 0;
}

function vtThreatScoreForRow(?array $row): int
{
    if ($row === null) {
        return 0;
    }

    $rank = vtSeverityRankForRow($row);
    $malicious = (int) ($row['vt_malicious'] ?? 0);
    $suspicious = (int) ($row['vt_suspicious'] ?? 0);
    $total = (int) ($row['vt_total'] ?? 0);

    return ($rank * 1000000) + ($malicious * 10000) + ($suspicious * 100) + $total;
}

function sortIpsForDisplay(array $ips, array $latestVtByIp, string $sort): array
{
    $ips = array_values($ips);
    $sort = allowedIpSort($sort);

    usort($ips, static function (string $a, string $b) use ($latestVtByIp, $sort): int {
        $rowA = $latestVtByIp[$a] ?? null;
        $rowB = $latestVtByIp[$b] ?? null;
        $scoreA = vtThreatScoreForRow($rowA);
        $scoreB = vtThreatScoreForRow($rowB);
        $rankA = vtSeverityRankForRow($rowA);
        $rankB = vtSeverityRankForRow($rowB);
        $maliciousA = (int) (($rowA['vt_malicious'] ?? 0));
        $maliciousB = (int) (($rowB['vt_malicious'] ?? 0));
        $suspiciousA = (int) (($rowA['vt_suspicious'] ?? 0));
        $suspiciousB = (int) (($rowB['vt_suspicious'] ?? 0));
        $natural = compareIpNatural($a, $b);

        return match ($sort) {
            'severity_desc' => ($rankB <=> $rankA)
                ?: ($maliciousB <=> $maliciousA)
                ?: ($suspiciousB <=> $suspiciousA)
                ?: ($scoreB <=> $scoreA)
                ?: $natural,
            'severity_asc' => ($rankA <=> $rankB)
                ?: ($maliciousA <=> $maliciousB)
                ?: ($suspiciousA <=> $suspiciousB)
                ?: ($scoreA <=> $scoreB)
                ?: $natural,
            'malicious_desc' => ($scoreB <=> $scoreA) ?: $natural,
            'malicious_asc' => ($scoreA <=> $scoreB) ?: $natural,
            default => $natural,
        };
    });

    return $ips;
}

function readExistingIps(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $rows = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_values(array_filter(array_map('trim', $rows), static function ($ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }));
}

function saveIps(string $file, array $ips): void
{
    $ips = array_values(array_unique(array_filter(array_map('trim', $ips), static function ($ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    })));

    sort($ips, SORT_NATURAL);

    $content = empty($ips) ? '' : implode(PHP_EOL, $ips) . PHP_EOL;
    file_put_contents($file, $content, LOCK_EX);
}

function readLog(string $file): array
{
    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        $stmt = $db->query('
            SELECT action, ip, country, city, isp, reason, user, time, source_ip,
                   vt_status, vt_malicious, vt_suspicious, vt_harmless, vt_undetected,
                   vt_timeout, vt_total, vt_reputation, vt_asn, vt_as_owner,
                   vt_last_analysis_date, vt_link, vt_error
            FROM logs
            ORDER BY id ASC
        ');

        return array_map('normalizeLogRowFromStorage', $stmt->fetchAll());
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

function saveLog(string $file, array $log): void
{
    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        $db->beginTransaction();

        try {
            $db->exec('DELETE FROM logs');

            foreach ($log as $row) {
                insertLogRow($db, is_array($row) ? $row : []);
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

    file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function addLog(string $file, array $row): void
{
    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        insertLogRow($db, $row);
        $db->exec('
            DELETE FROM logs
            WHERE id NOT IN (
                SELECT id FROM logs ORDER BY id DESC LIMIT 10000
            )
        ');

        return;
    }

    $log = readLog($file);
    $log[] = $row;

    // منع تضخم ملف السجل بشكل مبالغ فيه مع الاحتفاظ بآخر 10000 عملية.
    if (count($log) > 10000) {
        $log = array_slice($log, -10000);
    }

    saveLog($file, $log);
}

function normalizeLogRowFromStorage(array $row): array
{
    return [
        'action' => (string) ($row['action'] ?? 'add'),
        'ip' => (string) ($row['ip'] ?? ''),
        'country' => (string) ($row['country'] ?? 'Unknown'),
        'city' => (string) ($row['city'] ?? 'Unknown'),
        'isp' => (string) ($row['isp'] ?? 'Unknown'),
        'reason' => (string) ($row['reason'] ?? ''),
        'user' => (string) ($row['user'] ?? ''),
        'time' => (string) ($row['time'] ?? ($row['added_at'] ?? '')),
        'source_ip' => (string) ($row['source_ip'] ?? ''),
        'vt_status' => (string) ($row['vt_status'] ?? '-'),
        'vt_malicious' => (int) ($row['vt_malicious'] ?? 0),
        'vt_suspicious' => (int) ($row['vt_suspicious'] ?? 0),
        'vt_harmless' => (int) ($row['vt_harmless'] ?? 0),
        'vt_undetected' => (int) ($row['vt_undetected'] ?? 0),
        'vt_timeout' => (int) ($row['vt_timeout'] ?? 0),
        'vt_total' => (int) ($row['vt_total'] ?? 0),
        'vt_reputation' => (int) ($row['vt_reputation'] ?? 0),
        'vt_asn' => (int) ($row['vt_asn'] ?? 0),
        'vt_as_owner' => (string) ($row['vt_as_owner'] ?? ''),
        'vt_last_analysis_date' => (string) ($row['vt_last_analysis_date'] ?? ''),
        'vt_link' => (string) ($row['vt_link'] ?? ''),
        'vt_error' => (string) ($row['vt_error'] ?? ''),
    ];
}

function insertLogRow(PDO $db, array $row): void
{
    $row = normalizeLogRowFromStorage($row);
    $stmt = $db->prepare('
        INSERT INTO logs (
            action, ip, country, city, isp, reason, user, time, source_ip,
            vt_status, vt_malicious, vt_suspicious, vt_harmless, vt_undetected,
            vt_timeout, vt_total, vt_reputation, vt_asn, vt_as_owner,
            vt_last_analysis_date, vt_link, vt_error
        ) VALUES (
            :action, :ip, :country, :city, :isp, :reason, :user, :time, :source_ip,
            :vt_status, :vt_malicious, :vt_suspicious, :vt_harmless, :vt_undetected,
            :vt_timeout, :vt_total, :vt_reputation, :vt_asn, :vt_as_owner,
            :vt_last_analysis_date, :vt_link, :vt_error
        )
    ');

    $stmt->execute([
        ':action' => $row['action'],
        ':ip' => $row['ip'],
        ':country' => $row['country'],
        ':city' => $row['city'],
        ':isp' => $row['isp'],
        ':reason' => $row['reason'],
        ':user' => $row['user'],
        ':time' => $row['time'],
        ':source_ip' => $row['source_ip'],
        ':vt_status' => $row['vt_status'],
        ':vt_malicious' => $row['vt_malicious'],
        ':vt_suspicious' => $row['vt_suspicious'],
        ':vt_harmless' => $row['vt_harmless'],
        ':vt_undetected' => $row['vt_undetected'],
        ':vt_timeout' => $row['vt_timeout'],
        ':vt_total' => $row['vt_total'],
        ':vt_reputation' => $row['vt_reputation'],
        ':vt_asn' => $row['vt_asn'],
        ':vt_as_owner' => $row['vt_as_owner'],
        ':vt_last_analysis_date' => $row['vt_last_analysis_date'],
        ':vt_link' => $row['vt_link'],
        ':vt_error' => $row['vt_error'],
    ]);
}

function auditUserManagement(string $logFile, string $action, string $targetUsername, string $actorUsername): void
{
    if (isSqliteStorage($logFile) && databaseStorageError($logFile) !== '') {
        return;
    }

    if (!isSqliteStorage($logFile)) {
        $dir = dirname($logFile);

        if (!is_dir($dir) || !is_writable($dir) || (file_exists($logFile) && !is_writable($logFile))) {
            return;
        }
    }

    addLog($logFile, [
        'action' => $action,
        'ip' => '-',
        'country' => '-',
        'city' => '-',
        'isp' => '-',
        'reason' => 'إدارة مستخدم: ' . $targetUsername,
        'user' => $actorUsername,
        'time' => date('Y-m-d H:i:s'),
        'source_ip' => getRequestClientIp(),
        'vt_status' => '-',
        'vt_malicious' => 0,
        'vt_suspicious' => 0,
        'vt_harmless' => 0,
        'vt_undetected' => 0,
        'vt_timeout' => 0,
        'vt_total' => 0,
        'vt_reputation' => 0,
        'vt_asn' => 0,
        'vt_as_owner' => '',
        'vt_last_analysis_date' => '',
        'vt_link' => '',
        'vt_error' => '',
    ]);
}

function storageError(string $ipsFile, string $logFile): string
{
    $baseDir = dirname($ipsFile);

    if (!is_dir($baseDir)) {
        return 'المجلد غير موجود: ' . $baseDir;
    }

    if (!is_writable($baseDir)) {
        return 'المجلد غير قابل للكتابة: ' . $baseDir;
    }

    if (file_exists($ipsFile) && !is_writable($ipsFile)) {
        return 'ملف ips.txt غير قابل للكتابة: ' . $ipsFile;
    }

    if (isSqliteStorage($logFile)) {
        return databaseStorageError($logFile);
    }

    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        return 'مجلد السجل غير موجود: ' . $logDir;
    }

    if (!is_writable($logDir)) {
        return 'مجلد السجل غير قابل للكتابة: ' . $logDir;
    }

    if (file_exists($logFile) && !is_writable($logFile)) {
        return 'ملف السجل ips_log.json غير قابل للكتابة: ' . $logFile;
    }

    return '';
}

function countActions(array $log, string $action): int
{
    $count = 0;

    foreach ($log as $row) {
        if (($row['action'] ?? 'add') === $action) {
            $count++;
        }
    }

    return $count;
}

function allowedIpCategories(): array
{
    return [
        'manual' => 'Manual',
        'brute_force' => 'Brute Force',
        'scanner' => 'Scanner',
        'spam' => 'Spam',
        'tor' => 'TOR',
        'malware' => 'Malware',
        'botnet' => 'Botnet',
        'proxy' => 'Proxy',
        'other' => 'Other',
    ];
}

function normalizeIpCategory(string $category): string
{
    $category = strtolower(trim($category));
    $category = preg_replace('/[^a-z0-9_]+/', '_', $category) ?? '';
    $category = trim($category, '_');

    return array_key_exists($category, allowedIpCategories()) ? $category : 'manual';
}

function ipCategoryLabel(string $category): string
{
    $allowed = allowedIpCategories();
    $category = normalizeIpCategory($category);

    return $allowed[$category] ?? 'Manual';
}

function ipCategoryBadgeClass(string $category): string
{
    return match (normalizeIpCategory($category)) {
        'brute_force' => 'badge-category-danger',
        'scanner' => 'badge-category-warning',
        'spam' => 'badge-category-spam',
        'tor' => 'badge-category-tor',
        'malware', 'botnet' => 'badge-vt-danger',
        'proxy' => 'badge-check',
        default => 'badge-vt-muted',
    };
}

function normalizeDateOnly(string $date): string
{
    $date = trim($date);

    if ($date === '') {
        return '';
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date ? $date : '';
}

function expirationState(string $expiresAt): string
{
    $expiresAt = normalizeDateOnly($expiresAt);

    if ($expiresAt === '') {
        return 'permanent';
    }

    $today = date('Y-m-d');

    if ($expiresAt < $today) {
        return 'expired';
    }

    if ($expiresAt === $today) {
        return 'today';
    }

    return 'temporary';
}

function expirationLabel(string $expiresAt): string
{
    return match (expirationState($expiresAt)) {
        'expired' => 'منتهي',
        'today' => 'ينتهي اليوم',
        'temporary' => 'مؤقت حتى ' . normalizeDateOnly($expiresAt),
        default => 'دائم',
    };
}

function expirationBadgeClass(string $expiresAt): string
{
    return match (expirationState($expiresAt)) {
        'expired' => 'badge-vt-danger',
        'today' => 'badge-vt-warning',
        'temporary' => 'badge-check',
        default => 'badge-vt-muted',
    };
}

function normalizeIpMetadataRow(string $ip, array $row): array
{
    return [
        'ip' => $ip,
        'category' => normalizeIpCategory((string) ($row['category'] ?? 'manual')),
        'expires_at' => normalizeDateOnly((string) ($row['expires_at'] ?? '')),
        'note' => (string) ($row['note'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'updated_by' => (string) ($row['updated_by'] ?? ''),
    ];
}

function readIpMetadataByIp(string $databaseFile): array
{
    $db = sqliteConnection($databaseFile);
    $rows = $db->query('SELECT * FROM ip_metadata')->fetchAll();
    $metadata = [];

    foreach ($rows as $row) {
        $ip = trim((string) ($row['ip'] ?? ''));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $metadata[$ip] = normalizeIpMetadataRow($ip, $row);
        }
    }

    return $metadata;
}

function upsertIpMetadataBatch(
    string $databaseFile,
    array $ips,
    string $category,
    string $expiresAt,
    string $note,
    string $username
): int {
    $ips = array_values(array_unique(array_filter(array_map('trim', $ips), static function (string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    })));

    if (empty($ips)) {
        return 0;
    }

    $db = sqliteConnection($databaseFile);
    $category = normalizeIpCategory($category);
    $expiresAt = normalizeDateOnly($expiresAt);
    $note = substr(trim($note), 0, 500);
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('
        INSERT INTO ip_metadata (ip, category, expires_at, note, created_at, updated_at, updated_by)
        VALUES (:ip, :category, :expires_at, :note, :created_at, :updated_at, :updated_by)
        ON CONFLICT(ip) DO UPDATE SET
            category = excluded.category,
            expires_at = excluded.expires_at,
            note = excluded.note,
            updated_at = excluded.updated_at,
            updated_by = excluded.updated_by
    ');

    $db->beginTransaction();

    try {
        foreach ($ips as $ip) {
            $stmt->execute([
                ':ip' => $ip,
                ':category' => $category,
                ':expires_at' => $expiresAt,
                ':note' => $note,
                ':created_at' => $now,
                ':updated_at' => $now,
                ':updated_by' => $username,
            ]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return count($ips);
}

function deleteIpMetadataBatch(string $databaseFile, array $ips): void
{
    $ips = array_values(array_unique(array_filter(array_map('trim', $ips), static function (string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    })));

    if (empty($ips)) {
        return;
    }

    $db = sqliteConnection($databaseFile);
    $stmt = $db->prepare('DELETE FROM ip_metadata WHERE ip = :ip');

    $db->beginTransaction();

    try {
        foreach ($ips as $ip) {
            $stmt->execute([':ip' => $ip]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function latestIpContextByIp(array $log): array
{
    $context = [];

    foreach ($log as $row) {
        if (!is_array($row)) {
            continue;
        }

        $ip = trim((string) ($row['ip'] ?? ''));

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            continue;
        }

        $action = (string) ($row['action'] ?? '');
        $hasGeo = isKnownCountry((string) ($row['country'] ?? ''));

        if ($action === 'add' || !isset($context[$ip]) || $hasGeo) {
            $context[$ip] = [
                'country' => (string) ($row['country'] ?? 'Unknown'),
                'city' => (string) ($row['city'] ?? 'Unknown'),
                'isp' => (string) ($row['isp'] ?? 'Unknown'),
                'reason' => (string) ($row['reason'] ?? ''),
                'user' => (string) ($row['user'] ?? ''),
                'time' => (string) ($row['time'] ?? ''),
                'source_ip' => (string) ($row['source_ip'] ?? ''),
            ];
        }
    }

    return $context;
}

function buildIpAdminRows(array $ips, array $latestVtByIp, array $metadataByIp, array $geoCache, array $contextByIp): array
{
    $rows = [];

    foreach ($ips as $ip) {
        $ip = trim((string) $ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            continue;
        }

        $metadata = $metadataByIp[$ip] ?? normalizeIpMetadataRow($ip, []);
        $context = $contextByIp[$ip] ?? [];
        $geo = $geoCache[$ip] ?? [];
        $vt = $latestVtByIp[$ip] ?? null;

        $rows[] = [
            'ip' => $ip,
            'category' => (string) ($metadata['category'] ?? 'manual'),
            'expires_at' => (string) ($metadata['expires_at'] ?? ''),
            'note' => (string) ($metadata['note'] ?? ''),
            'updated_by' => (string) ($metadata['updated_by'] ?? ''),
            'updated_at' => (string) ($metadata['updated_at'] ?? ''),
            'country' => (string) (($geo['country'] ?? '') ?: ($context['country'] ?? 'Unknown')),
            'city' => (string) (($geo['city'] ?? '') ?: ($context['city'] ?? 'Unknown')),
            'isp' => (string) (($geo['isp'] ?? '') ?: ($context['isp'] ?? 'Unknown')),
            'reason' => (string) ($context['reason'] ?? ''),
            'user' => (string) (($context['user'] ?? '') ?: ($metadata['updated_by'] ?? '')),
            'added_at' => (string) ($context['time'] ?? ''),
            'vt_status' => (string) ($vt['vt_status'] ?? ''),
            'vt_malicious' => (int) ($vt['vt_malicious'] ?? 0),
            'vt_total' => (int) ($vt['vt_total'] ?? 0),
            'vt_asn' => (int) ($vt['vt_asn'] ?? 0),
            'vt_as_owner' => (string) ($vt['vt_as_owner'] ?? ''),
            'vt_row' => $vt,
        ];
    }

    return $rows;
}

function ipFiltersFromArray(array $source, string $prefix = ''): array
{
    $expiry = (string) ($source[$prefix . 'expiry'] ?? '');
    $allowedExpiry = ['permanent', 'temporary', 'expired', 'today', 'expiring_7'];

    return [
        'query' => normalizeIpSearchQuery((string) ($source[$prefix . 'ip_query'] ?? '')),
        'country' => substr(trim((string) ($source[$prefix . 'country'] ?? '')), 0, 80),
        'vt_status' => substr(trim((string) ($source[$prefix . 'vt_status'] ?? '')), 0, 40),
        'asn' => substr(trim((string) ($source[$prefix . 'asn'] ?? '')), 0, 20),
        'user' => substr(trim((string) ($source[$prefix . 'user'] ?? '')), 0, 80),
        'category' => trim((string) ($source[$prefix . 'category'] ?? '')) === '' ? '' : normalizeIpCategory((string) ($source[$prefix . 'category'] ?? '')),
        'expiry' => in_array($expiry, $allowedExpiry, true) ? $expiry : '',
        'date_from' => normalizeDateOnly((string) ($source[$prefix . 'date_from'] ?? '')),
        'date_to' => normalizeDateOnly((string) ($source[$prefix . 'date_to'] ?? '')),
    ];
}

function filterIpAdminRows(array $rows, array $filters): array
{
    $query = normalizeIpSearchQuery((string) ($filters['query'] ?? ''));
    $terms = $query === '' ? [] : (preg_split('/[\s,;]+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $today = date('Y-m-d');
    $sevenDays = date('Y-m-d', strtotime('+7 days') ?: time());

    return array_values(array_filter($rows, static function (array $row) use ($filters, $terms, $today, $sevenDays): bool {
        if (!empty($terms)) {
            $matched = false;

            foreach ($terms as $term) {
                if ($term !== '' && stripos((string) ($row['ip'] ?? ''), $term) !== false) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return false;
            }
        }

        if (($filters['country'] ?? '') !== '' && (string) ($row['country'] ?? '') !== (string) $filters['country']) {
            return false;
        }

        if (($filters['vt_status'] ?? '') !== '') {
            $status = (string) ($row['vt_status'] ?? '');

            if ($filters['vt_status'] === '__unscanned') {
                if ($status !== '') {
                    return false;
                }
            } elseif ($status !== (string) $filters['vt_status']) {
                return false;
            }
        }

        if (($filters['asn'] ?? '') !== '' && (string) ((int) ($row['vt_asn'] ?? 0)) !== (string) $filters['asn']) {
            return false;
        }

        if (($filters['user'] ?? '') !== '' && (string) ($row['user'] ?? '') !== (string) $filters['user'] && (string) ($row['updated_by'] ?? '') !== (string) $filters['user']) {
            return false;
        }

        if (($filters['category'] ?? '') !== '' && (string) ($row['category'] ?? 'manual') !== (string) $filters['category']) {
            return false;
        }

        $expiresAt = normalizeDateOnly((string) ($row['expires_at'] ?? ''));
        $state = expirationState($expiresAt);

        if (($filters['expiry'] ?? '') === 'permanent' && $expiresAt !== '') {
            return false;
        }

        if (($filters['expiry'] ?? '') === 'temporary' && ($expiresAt === '' || $expiresAt < $today)) {
            return false;
        }

        if (($filters['expiry'] ?? '') === 'expired' && $state !== 'expired') {
            return false;
        }

        if (($filters['expiry'] ?? '') === 'today' && $state !== 'today') {
            return false;
        }

        if (($filters['expiry'] ?? '') === 'expiring_7' && ($expiresAt === '' || $expiresAt < $today || $expiresAt > $sevenDays)) {
            return false;
        }

        $addedDate = substr((string) ($row['added_at'] ?? ''), 0, 10);

        if (($filters['date_from'] ?? '') !== '' && ($addedDate === '' || $addedDate < (string) $filters['date_from'])) {
            return false;
        }

        if (($filters['date_to'] ?? '') !== '' && ($addedDate === '' || $addedDate > (string) $filters['date_to'])) {
            return false;
        }

        return true;
    }));
}

function uniqueRowValues(array $rows, string $key): array
{
    $values = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $value = trim((string) ($row[$key] ?? ''));

        if ($value !== '' && $value !== '0' && $value !== '-' && strcasecmp($value, 'Unknown') !== 0) {
            $values[$value] = $value;
        }
    }

    natcasesort($values);

    return array_values($values);
}

function ipRowsToIps(array $rows): array
{
    return array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['ip'] ?? ''), $rows)));
}

function sendIpExport(array $rows, string $format): void
{
    $format = strtolower(trim($format));
    $filename = 'ip-feed-export-' . date('Ymd-His') . ($format === 'csv' ? '.csv' : '.txt');

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');

        if ($out !== false) {
            fputcsv($out, ['ip', 'category', 'expires_at', 'country', 'vt_status', 'vt_malicious', 'vt_total', 'asn', 'as_owner', 'user', 'added_at', 'reason']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) ($row['ip'] ?? ''),
                    ipCategoryLabel((string) ($row['category'] ?? 'manual')),
                    (string) ($row['expires_at'] ?? ''),
                    (string) ($row['country'] ?? ''),
                    (string) ($row['vt_status'] ?? 'لم يتم الفحص'),
                    (int) ($row['vt_malicious'] ?? 0),
                    (int) ($row['vt_total'] ?? 0),
                    (int) ($row['vt_asn'] ?? 0),
                    (string) ($row['vt_as_owner'] ?? ''),
                    (string) ($row['user'] ?? ''),
                    (string) ($row['added_at'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                ]);
            }
        }

        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo implode(PHP_EOL, ipRowsToIps($rows));

    if (!empty($rows)) {
        echo PHP_EOL;
    }

    exit;
}

function deleteIpsFromFeed(string $ipsFile, string $logFile, string $databaseFile, array $ips, string $username, string $sourceIp, string $reason): array
{
    $existingIps = readExistingIps($ipsFile);
    $targets = array_values(array_unique(array_filter(array_map('trim', $ips), static function (string $ip) use ($existingIps): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && in_array($ip, $existingIps, true);
    })));

    if (empty($targets)) {
        return ['deleted' => 0, 'requested' => count($ips)];
    }

    $targetMap = array_flip($targets);
    $remaining = array_values(array_filter($existingIps, static fn (string $ip): bool => !isset($targetMap[$ip])));
    saveIps($ipsFile, $remaining);
    deleteIpMetadataBatch($databaseFile, $targets);

    foreach ($targets as $ip) {
        addLog($logFile, [
            'action' => 'bulk_delete',
            'ip' => $ip,
            'country' => '-',
            'city' => '-',
            'isp' => '-',
            'reason' => $reason,
            'user' => $username,
            'time' => date('Y-m-d H:i:s'),
            'source_ip' => $sourceIp,
            'vt_status' => '-',
            'vt_malicious' => 0,
            'vt_suspicious' => 0,
            'vt_harmless' => 0,
            'vt_undetected' => 0,
            'vt_timeout' => 0,
            'vt_total' => 0,
            'vt_reputation' => 0,
            'vt_asn' => 0,
            'vt_as_owner' => '',
            'vt_last_analysis_date' => '',
            'vt_link' => '',
            'vt_error' => '',
        ]);
    }

    return ['deleted' => count($targets), 'requested' => count($ips)];
}

function updateIpMetadataForFeed(
    string $databaseFile,
    string $logFile,
    array $ips,
    string $category,
    string $expiresAt,
    string $note,
    string $username,
    string $sourceIp
): int {
    $updated = upsertIpMetadataBatch($databaseFile, $ips, $category, $expiresAt, $note, $username);
    $categoryLabel = ipCategoryLabel($category);
    $expirationText = normalizeDateOnly($expiresAt) === '' ? 'دائم' : normalizeDateOnly($expiresAt);
    $logNote = trim($note);
    $logIps = array_values(array_unique(array_filter(array_map('trim', $ips), static function (string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    })));

    foreach (array_slice($logIps, 0, $updated) as $ip) {
        addLog($logFile, [
            'action' => 'metadata_update',
            'ip' => $ip,
            'country' => '-',
            'city' => '-',
            'isp' => '-',
            'reason' => 'تحديث تصنيف: ' . $categoryLabel . '، انتهاء: ' . $expirationText . ($logNote !== '' ? '، ملاحظة: ' . $logNote : ''),
            'user' => $username,
            'time' => date('Y-m-d H:i:s'),
            'source_ip' => $sourceIp,
            'vt_status' => '-',
            'vt_malicious' => 0,
            'vt_suspicious' => 0,
            'vt_harmless' => 0,
            'vt_undetected' => 0,
            'vt_timeout' => 0,
            'vt_total' => 0,
            'vt_reputation' => 0,
            'vt_asn' => 0,
            'vt_as_owner' => '',
            'vt_last_analysis_date' => '',
            'vt_link' => '',
            'vt_error' => '',
        ]);
    }

    return $updated;
}

function addIpBatchToFeed(
    string $ipsFile,
    string $logFile,
    string $geoCacheFile,
    array $candidateIps,
    string $reason,
    string $username,
    string $sourceIp,
    bool $checkVirusTotal,
    string $vtApiKey,
    string $databaseFile = '',
    int $vtFreshTtlSeconds = 86400,
    string $category = 'manual',
    string $expiresAt = '',
    string $metadataNote = ''
): array {
    $validIps = array_values(array_unique(array_filter(array_map('trim', $candidateIps), static function ($ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    })));

    $existingIps = readExistingIps($ipsFile);
    $added = [];

    foreach ($validIps as $ip) {
        if (!in_array($ip, $existingIps, true)) {
            $existingIps[] = $ip;
            $added[] = $ip;
        }
    }

    if (!empty($added)) {
        saveIps($ipsFile, $existingIps);
    }

    $geoCache = readGeoCache($geoCacheFile);
    $geoCacheChanged = false;
    $vtChecked = 0;
    $vtFailed = 0;

    foreach ($added as $ip) {
        if ($databaseFile !== '') {
            upsertIpMetadataBatch($databaseFile, [$ip], $category, $expiresAt, $metadataNote, $username);
        }

        $geo = getGeoInfo($ip);

        $geoCache[$ip] = [
            'country' => $geo['country'] ?? 'Unknown',
            'city' => $geo['city'] ?? 'Unknown',
            'isp' => $geo['isp'] ?? 'Unknown',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $geoCacheChanged = true;

        $vt = defaultVirusTotalInfo($ip, $vtApiKey === '' ? 'غير مفعل' : 'لم يتم الفحص');

        if ($checkVirusTotal && $databaseFile !== '' && $vtApiKey !== '') {
            $queued = enqueueVirusTotalScan(
                $databaseFile,
                $ip,
                'فحص VirusTotal عند الإضافة: ' . $reason,
                $username,
                $sourceIp,
                $vtFreshTtlSeconds,
                'vt_check'
            );
            $queueStatus = (string) ($queued['status'] ?? '');

            if ($queueStatus === 'queued') {
                $vt = defaultVirusTotalInfo($ip, 'في الطابور', '');
                $vtChecked++;
            } elseif ($queueStatus === 'skipped_recent') {
                $vt = defaultVirusTotalInfo($ip, 'نتيجة حديثة', (string) ($queued['checked_at'] ?? ''));
            } elseif ($queueStatus === 'already_queued') {
                $vt = defaultVirusTotalInfo($ip, 'في الطابور', 'العنوان موجود بالفعل في طابور VirusTotal.');
            } else {
                $vt = defaultVirusTotalInfo($ip, 'غير معروف', (string) ($queued['message'] ?? 'تعذر إضافة الفحص إلى الطابور.'));
                $vtFailed++;
            }
        }

        addLog($logFile, array_merge([
            'action' => 'add',
            'ip' => $ip,
            'country' => $geo['country'] ?? 'Unknown',
            'city' => $geo['city'] ?? 'Unknown',
            'isp' => $geo['isp'] ?? 'Unknown',
            'reason' => $reason,
            'user' => $username,
            'time' => date('Y-m-d H:i:s'),
            'source_ip' => $sourceIp,
        ], virusTotalLogFields($vt)));

        usleep(250000);
    }

    if ($geoCacheChanged) {
        saveGeoCache($geoCacheFile, $geoCache);
    }

    return [
        'received' => count($candidateIps),
        'valid' => count($validIps),
        'added' => count($added),
        'skipped' => max(0, count($validIps) - count($added)),
        'invalid' => max(0, count($candidateIps) - count($validIps)),
        'vt_queued' => $vtChecked,
        'vt_failed' => $vtFailed,
    ];
}
