<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function normalizeUsername(string $username): string
{
    return strtolower(trim($username));
}

function isValidUsername(string $username): bool
{
    return preg_match('/^[a-z0-9_.-]{3,32}$/', $username) === 1;
}

function defaultAdminUser(): array
{
    $envHash = trim((string) (getenv('ADMIN_PASSWORD_HASH') ?: ($_SERVER['ADMIN_PASSWORD_HASH'] ?? '')));
    $hashInfo = $envHash !== '' ? password_get_info($envHash) : ['algo' => 0];
    $usingEnvHash = (($hashInfo['algo'] ?? 0) !== 0);
    $passwordHash = $usingEnvHash ? $envHash : password_hash('ChangeMe123!', PASSWORD_DEFAULT);

    return [
        'username' => 'admin',
        'display_name' => 'Administrator',
        'password_hash' => $passwordHash,
        'role' => 'admin',
        'active' => true,
        'must_change_password' => !$usingEnvHash,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_login' => '',
    ];
}

function normalizeUserRecord(string $fallbackUsername, mixed $record): ?array
{
    $username = $fallbackUsername;
    $displayName = $fallbackUsername;
    $passwordHash = '';
    $role = 'operator';
    $active = true;
    $mustChangePassword = false;
    $createdAt = date('Y-m-d H:i:s');
    $updatedAt = date('Y-m-d H:i:s');
    $lastLogin = '';

    if (is_string($record)) {
        $passwordHash = $record;
    } elseif (is_array($record)) {
        $username = normalizeUsername((string) ($record['username'] ?? $fallbackUsername));
        $displayName = trim((string) ($record['display_name'] ?? $record['full_name'] ?? $username));
        $passwordHash = (string) ($record['password_hash'] ?? $record['hash'] ?? $record['password'] ?? '');
        $role = (string) ($record['role'] ?? 'operator');
        $active = filter_var($record['active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $active = $active ?? true;
        $mustChangePassword = filter_var($record['must_change_password'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $mustChangePassword = $mustChangePassword ?? false;
        $createdAt = (string) ($record['created_at'] ?? $createdAt);
        $updatedAt = (string) ($record['updated_at'] ?? $updatedAt);
        $lastLogin = (string) ($record['last_login'] ?? '');
    }

    $username = normalizeUsername($username);

    if (!isValidUsername($username) || $passwordHash === '') {
        return null;
    }

    if (!in_array($role, ['admin', 'operator', 'viewer'], true)) {
        $role = 'operator';
    }

    if ($displayName === '') {
        $displayName = $username;
    }

    return [
        'username' => $username,
        'display_name' => $displayName,
        'password_hash' => $passwordHash,
        'role' => $role,
        'active' => (bool) $active,
        'must_change_password' => (bool) $mustChangePassword,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'last_login' => $lastLogin,
    ];
}

function readUsers(string $file): array
{
    $users = [];
    $changed = false;

    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        $stmt = $db->query('
            SELECT username, display_name, password_hash, role, active, must_change_password, created_at, updated_at, last_login
            FROM users
            ORDER BY username COLLATE NOCASE ASC
        ');

        foreach ($stmt->fetchAll() as $record) {
            $user = normalizeUserRecord((string) ($record['username'] ?? ''), [
                'username' => (string) ($record['username'] ?? ''),
                'display_name' => (string) ($record['display_name'] ?? ''),
                'password_hash' => (string) ($record['password_hash'] ?? ''),
                'role' => (string) ($record['role'] ?? 'operator'),
                'active' => (int) ($record['active'] ?? 1) === 1,
                'must_change_password' => (int) ($record['must_change_password'] ?? 0) === 1,
                'created_at' => (string) ($record['created_at'] ?? ''),
                'updated_at' => (string) ($record['updated_at'] ?? ''),
                'last_login' => (string) ($record['last_login'] ?? ''),
            ]);

            if ($user !== null) {
                $users[$user['username']] = $user;
            }
        }
    } elseif (file_exists($file)) {
        $json = file_get_contents($file);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : [];

        if (is_array($data)) {
            foreach ($data as $key => $record) {
                $fallback = is_string($key) ? normalizeUsername($key) : '';

                if (is_array($record) && isset($record['username'])) {
                    $fallback = normalizeUsername((string) $record['username']);
                }

                if ($fallback === '') {
                    continue;
                }

                $user = normalizeUserRecord($fallback, $record);

                if ($user !== null) {
                    $users[$user['username']] = $user;
                }
            }
        }
    }

    if (!isset($users['admin'])) {
        $users['admin'] = defaultAdminUser();
        $changed = true;
    }

    if (!hasActiveAdmin($users)) {
        $users['admin'] = defaultAdminUser();
        $users['admin']['active'] = true;
        $users['admin']['role'] = 'admin';
        $changed = true;
    }

    ksort($users, SORT_NATURAL);

    if ($changed && isSqliteStorage($file)) {
        saveUsers($file, $users);
    }

    return $users;
}

function saveUsers(string $file, array $users): void
{
    ksort($users, SORT_NATURAL);

    if (isSqliteStorage($file)) {
        $db = sqliteConnection($file);
        $stmt = $db->prepare('
            INSERT INTO users (
                username, display_name, password_hash, role, active, must_change_password, created_at, updated_at, last_login
            ) VALUES (
                :username, :display_name, :password_hash, :role, :active, :must_change_password, :created_at, :updated_at, :last_login
            )
        ');

        $db->beginTransaction();

        try {
            $db->exec('DELETE FROM users');

            foreach ($users as $username => $user) {
                $stmt->execute([
                    ':username' => normalizeUsername((string) ($user['username'] ?? $username)),
                    ':display_name' => (string) ($user['display_name'] ?? $username),
                    ':password_hash' => (string) ($user['password_hash'] ?? ''),
                    ':role' => (string) ($user['role'] ?? 'operator'),
                    ':active' => (bool) ($user['active'] ?? true) ? 1 : 0,
                    ':must_change_password' => (bool) ($user['must_change_password'] ?? false) ? 1 : 0,
                    ':created_at' => (string) ($user['created_at'] ?? date('Y-m-d H:i:s')),
                    ':updated_at' => (string) ($user['updated_at'] ?? date('Y-m-d H:i:s')),
                    ':last_login' => (string) ($user['last_login'] ?? ''),
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

    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function usersStorageError(string $usersFile): string
{
    if (isSqliteStorage($usersFile)) {
        return databaseStorageError($usersFile);
    }

    $baseDir = dirname($usersFile);

    if (!is_dir($baseDir)) {
        return 'المجلد غير موجود: ' . $baseDir;
    }

    if (!is_writable($baseDir)) {
        return 'المجلد غير قابل للكتابة لإنشاء users.json: ' . $baseDir;
    }

    if (file_exists($usersFile) && !is_writable($usersFile)) {
        return 'ملف users.json غير قابل للكتابة: ' . $usersFile;
    }

    return '';
}

function hasActiveAdmin(array $users): bool
{
    foreach ($users as $user) {
        if (($user['role'] ?? '') === 'admin' && (bool) ($user['active'] ?? false)) {
            return true;
        }
    }

    return false;
}

function countActiveUsers(array $users): int
{
    $count = 0;

    foreach ($users as $user) {
        if ((bool) ($user['active'] ?? false)) {
            $count++;
        }
    }

    return $count;
}

function currentUserRecord(array $users): ?array
{
    $username = normalizeUsername((string) ($_SESSION['user'] ?? ''));

    if ($username === '' || !isset($users[$username])) {
        return null;
    }

    return $users[$username];
}

function currentUserRole(array $users): string
{
    $user = currentUserRecord($users);

    return is_array($user) ? (string) ($user['role'] ?? 'viewer') : 'viewer';
}

function roleLabel(string $role): string
{
    return match ($role) {
        'admin' => 'مدير',
        'operator' => 'مشغّل',
        'viewer' => 'مشاهدة فقط',
        default => 'غير معروف',
    };
}

function roleDescription(string $role): string
{
    return match ($role) {
        'admin' => 'إدارة كاملة للمستخدمين و IPs و VirusTotal',
        'operator' => 'إضافة وحذف وفحص IPs بدون إدارة المستخدمين',
        'viewer' => 'عرض فقط بدون تعديل أو فحص',
        default => 'صلاحية غير معروفة',
    };
}

function canManageUsers(array $users): bool
{
    return isLoggedIn() && currentUserRole($users) === 'admin';
}

function canModifyIps(array $users): bool
{
    return isLoggedIn() && in_array(currentUserRole($users), ['admin', 'operator'], true);
}

function canCheckVirusTotal(array $users): bool
{
    return canModifyIps($users);
}

function adminMustChangeDefaultPassword(array $users, bool $forceDefaultChange): bool
{
    if (!$forceDefaultChange || !isLoggedIn()) {
        return false;
    }

    $sessionUser = normalizeUsername((string) ($_SESSION['user'] ?? ''));
    $admin = $users['admin'] ?? null;

    if ($sessionUser !== 'admin' || !is_array($admin)) {
        return false;
    }

    $passwordHash = (string) ($admin['password_hash'] ?? '');

    return (bool) ($admin['must_change_password'] ?? false)
        || ($passwordHash !== '' && password_verify('ChangeMe123!', $passwordHash));
}
