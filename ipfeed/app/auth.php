<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && is_string($_SESSION['user']) && $_SESSION['user'] !== '';
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(ensureCsrfToken()) . '">';
}

function verifyCsrfToken(): bool
{
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    return is_string($posted) && is_string($stored) && $stored !== '' && hash_equals($stored, $posted);
}

function recordLoginEvent(string $databaseFile, string $username, bool $success, string $sourceIp, string $reason): void
{
    try {
        $db = sqliteConnection($databaseFile);
        $stmt = $db->prepare('
            INSERT INTO login_events (username, success, source_ip, user_agent, reason, created_at)
            VALUES (:username, :success, :source_ip, :user_agent, :reason, :created_at)
        ');
        $stmt->execute([
            ':username' => normalizeUsername($username),
            ':success' => $success ? 1 : 0,
            ':source_ip' => $sourceIp,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ':reason' => substr($reason, 0, 500),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        $db->exec('
            DELETE FROM login_events
            WHERE id NOT IN (
                SELECT id FROM login_events ORDER BY id DESC LIMIT 5000
            )
        ');
    } catch (Throwable) {
        // لا نريد أن يمنع تعذر تسجيل الحدث عملية الدخول نفسها.
    }
}

function recentLoginEvents(string $databaseFile, int $limit = 30): array
{
    try {
        $db = sqliteConnection($databaseFile);
        $stmt = $db->prepare('
            SELECT username, success, source_ip, user_agent, reason, created_at
            FROM login_events
            ORDER BY id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function updateLoginAttemptState(string $file, callable $callback): mixed
{
    if (isSqliteStorage($file)) {
        try {
            return sqliteUpdateJsonState($file, 'auth', 'login_attempts', $callback);
        } catch (Throwable) {
            $state = [];
            return $callback($state, false);
        }
    }

    $dir = dirname($file);

    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        $state = [];
        return $callback($state, false);
    }

    $fp = @fopen($file, 'c+');

    if (!$fp) {
        $state = [];
        return $callback($state, false);
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        $state = [];
        return $callback($state, false);
    }

    rewind($fp);
    $raw = stream_get_contents($fp);
    $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
    $state = is_array($decoded) ? $decoded : [];
    $result = $callback($state, true);

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    @chmod($file, 0640);

    return $result;
}

function loginAttemptKey(string $username, string $sourceIp): string
{
    return hash('sha256', normalizeUsername($username) . '|' . trim($sourceIp));
}

function pruneLoginAttemptState(array &$state, int $now, int $windowSeconds): void
{
    foreach ($state as $key => $record) {
        if (!is_array($record)) {
            unset($state[$key]);
            continue;
        }

        $lockedUntil = (int) ($record['locked_until'] ?? 0);
        $firstAttemptAt = (int) ($record['first_attempt_at'] ?? 0);

        if ($lockedUntil <= $now && $firstAttemptAt > 0 && ($now - $firstAttemptAt) > ($windowSeconds * 2)) {
            unset($state[$key]);
        }
    }
}

function loginRateLimitStatus(
    string $file,
    string $username,
    string $sourceIp,
    bool $enabled,
    int $maxAttempts,
    int $windowSeconds,
    int $lockSeconds
): array {
    if (!$enabled) {
        return ['allowed' => true, 'message' => '', 'wait_seconds' => 0];
    }

    $now = time();
    $key = loginAttemptKey($username, $sourceIp);

    return updateLoginAttemptState($file, function (array &$state, bool $persistent) use ($key, $now, $maxAttempts, $windowSeconds, $lockSeconds): array {
        pruneLoginAttemptState($state, $now, $windowSeconds);

        $record = is_array($state[$key] ?? null) ? $state[$key] : [
            'attempts' => 0,
            'first_attempt_at' => 0,
            'locked_until' => 0,
        ];

        $lockedUntil = (int) ($record['locked_until'] ?? 0);
        if ($lockedUntil > $now) {
            $waitSeconds = $lockedUntil - $now;

            return [
                'allowed' => false,
                'message' => 'تم إيقاف محاولات الدخول مؤقتاً لهذا الحساب من هذا العنوان. حاول بعد ' . secondsToHumanArabic($waitSeconds) . '.',
                'wait_seconds' => $waitSeconds,
            ];
        }

        $firstAttemptAt = (int) ($record['first_attempt_at'] ?? 0);
        if ($firstAttemptAt <= 0 || ($now - $firstAttemptAt) > $windowSeconds) {
            $state[$key] = [
                'attempts' => 0,
                'first_attempt_at' => 0,
                'locked_until' => 0,
            ];
        }

        return [
            'allowed' => true,
            'message' => $persistent ? '' : 'تعذر حفظ حالة حد محاولات الدخول.',
            'wait_seconds' => 0,
        ];
    });
}

function recordLoginAttempt(
    string $file,
    string $username,
    string $sourceIp,
    bool $success,
    bool $enabled,
    int $maxAttempts,
    int $windowSeconds,
    int $lockSeconds
): array {
    if (!$enabled) {
        return ['locked' => false, 'message' => ''];
    }

    $now = time();
    $key = loginAttemptKey($username, $sourceIp);

    return updateLoginAttemptState($file, function (array &$state, bool $persistent) use ($key, $now, $success, $maxAttempts, $windowSeconds, $lockSeconds): array {
        pruneLoginAttemptState($state, $now, $windowSeconds);

        if ($success) {
            unset($state[$key]);
            return ['locked' => false, 'message' => ''];
        }

        $record = is_array($state[$key] ?? null) ? $state[$key] : [
            'attempts' => 0,
            'first_attempt_at' => $now,
            'locked_until' => 0,
        ];

        $firstAttemptAt = (int) ($record['first_attempt_at'] ?? $now);
        if (($now - $firstAttemptAt) > $windowSeconds) {
            $record = [
                'attempts' => 0,
                'first_attempt_at' => $now,
                'locked_until' => 0,
            ];
        }

        $record['attempts'] = ((int) ($record['attempts'] ?? 0)) + 1;
        $record['first_attempt_at'] = (int) ($record['first_attempt_at'] ?? $now);
        $record['last_attempt_at'] = $now;
        $record['updated_at'] = date('Y-m-d H:i:s');

        if ((int) $record['attempts'] >= $maxAttempts) {
            $record['locked_until'] = $now + $lockSeconds;
            $state[$key] = $record;

            return [
                'locked' => true,
                'message' => 'تم إيقاف محاولات الدخول مؤقتاً بعد ' . $maxAttempts . ' محاولات فاشلة. حاول بعد ' . secondsToHumanArabic($lockSeconds) . '.',
            ];
        }

        $state[$key] = $record;
        $remaining = max(0, $maxAttempts - (int) $record['attempts']);

        return [
            'locked' => false,
            'message' => $persistent && $remaining > 0 ? 'المحاولات المتبقية قبل القفل المؤقت: ' . $remaining . '.' : '',
        ];
    });
}
