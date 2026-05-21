#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage: ./install.sh [options]

Options:
  --project-dir PATH     Project checkout path. Default: directory containing install.sh.
  --private-dir PATH     Runtime private directory. Default: /var/lib/ipfeed as root, ../ipfeed-private otherwise.
  --feed-file PATH       Public FortiGate feed file. Default: PROJECT_DIR/ipfeed/ips.txt.
  --web-user USER        Web server user. Default: auto-detect www-data/apache/nginx/_www.
  --force-config         Rewrite PRIVATE_DIR/config.php if it already exists.
  --skip-composer        Do not run composer install.
  -h, --help             Show this help.

Examples:
  sudo ./install.sh --project-dir /var/www/IPFeed --private-dir /var/lib/ipfeed --web-user www-data
  ./install.sh --private-dir "$PWD/../ipfeed-private" --skip-composer
EOF
}

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_dir="${IP_FEED_PROJECT_DIR:-$script_dir}"
if [[ -n "${IP_FEED_SETTINGS_DIR:-}" ]]; then
  private_dir="$IP_FEED_SETTINGS_DIR"
elif [[ "$(id -u)" -eq 0 ]]; then
  private_dir="/var/lib/ipfeed"
else
  private_dir="$(dirname "$project_dir")/ipfeed-private"
fi
feed_file="${IP_FEED_FEED_FILE:-$project_dir/ipfeed/ips.txt}"
web_user="${IP_FEED_WEB_USER:-}"
force_config=0
skip_composer=0

detect_web_user() {
  for candidate in www-data apache nginx _www; do
    if id "$candidate" >/dev/null 2>&1; then
      printf '%s\n' "$candidate"
      return
    fi
  done

  id -un
}

php_string() {
  printf "%s" "$1" | sed "s/'/'\\\\''/g"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --project-dir)
      project_dir="$2"
      shift 2
      ;;
    --private-dir)
      private_dir="$2"
      shift 2
      ;;
    --feed-file)
      feed_file="$2"
      shift 2
      ;;
    --web-user)
      web_user="$2"
      shift 2
      ;;
    --force-config)
      force_config=1
      shift
      ;;
    --skip-composer)
      skip_composer=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

project_dir="$(cd "$project_dir" && pwd)"
private_dir="$(mkdir -p "$private_dir" && cd "$private_dir" && pwd)"
feed_dir="$(mkdir -p "$(dirname "$feed_file")" && cd "$(dirname "$feed_file")" && pwd)"
feed_file="$feed_dir/$(basename "$feed_file")"
web_user="${web_user:-$(detect_web_user)}"

if [[ ! -f "$project_dir/ipfeed/index.php" ]]; then
  echo "Project directory does not look like IPFeed: $project_dir" >&2
  exit 1
fi

mkdir -p "$private_dir/logs" "$private_dir/backups"
touch "$feed_file"

config_file="$private_dir/config.php"
if [[ ! -f "$config_file" || "$force_config" -eq 1 ]]; then
  private_php="$(php_string "$private_dir")"
  project_php="$(php_string "$project_dir")"
  feed_php="$(php_string "$feed_file")"

  cat > "$config_file" <<PHP
<?php
declare(strict_types=1);

\$storageDir = '$private_php';
\$projectDir = '$project_php';
\$feedFile = '$feed_php';

return [
    'timezone' => (string) (getenv('IP_FEED_TIMEZONE') ?: 'Asia/Aden'),
    'storage_dir' => \$storageDir,
    'database' => \$storageDir . '/ip_feed.sqlite',

    'files' => [
        'feed' => \$feedFile,
        'users' => \$storageDir . '/ip_feed.sqlite',
        'log' => \$storageDir . '/ip_feed.sqlite',
        'geo_cache' => \$storageDir . '/ip_feed.sqlite',
        'visitor_geo_cache' => \$storageDir . '/ip_feed.sqlite',
        'vt_settings' => \$storageDir . '/ip_feed.sqlite',
        'vt_rate_limit' => \$storageDir . '/ip_feed.sqlite',
        'login_rate_limit' => \$storageDir . '/ip_feed.sqlite',
    ],

    'operations' => [
        'logs_dir' => \$storageDir . '/logs',
        'app_log' => \$storageDir . '/logs/app.log',
        'worker_log' => \$storageDir . '/logs/vt_worker.log',
        'backup_log' => \$storageDir . '/logs/backup.log',
    ],

    'backup' => [
        'dir' => \$storageDir . '/backups',
        'retention_days' => 14,
        'max_age_hours' => 30,
    ],

    'healthcheck' => [
        'enabled' => true,
        'token' => (string) (getenv('IP_FEED_HEALTH_TOKEN') ?: ''),
        'fail_on_warning' => false,
    ],

    'legacy_json' => [
        'users' => \$storageDir . '/users.json',
        'log' => \$storageDir . '/ips_log.json',
        'geo_cache' => \$storageDir . '/ip_geo_cache.json',
        'visitor_geo_cache' => \$storageDir . '/visitor_geo_cache.json',
        'vt_settings' => \$storageDir . '/vt_settings.json',
        'vt_rate_limit' => \$storageDir . '/vt_rate_limit.json',
        'login_rate_limit' => \$storageDir . '/login_attempts.json',
    ],

    'ui' => [
        'max_log_rows_on_screen' => 300,
        'rows_per_page' => 10,
        'add_progress_threshold' => 20,
        'add_progress_chunk_size' => 10,
    ],

    'virustotal' => [
        'bulk_queue_limit' => 1000,
        'public_api_safe_mode' => true,
        'requests_per_minute' => 4,
        'min_interval_seconds' => 16,
        'daily_quota' => 500,
        'max_server_wait_seconds' => 20,
        'result_fresh_ttl_seconds' => 86400,
    ],

    'visitor_country_restriction' => [
        'enabled' => false,
        'allowed_countries' => [
            'JO' => 'الأردن',
            'YE' => 'اليمن',
        ],
        'allow_local_private' => true,
        'cache_ttl_seconds' => 86400,
    ],

    'security' => [
        'force_default_admin_password_change' => true,
        'login_rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'window_seconds' => 900,
            'lock_seconds' => 900,
        ],
    ],
];
PHP
else
  echo "Keeping existing config: $config_file"
fi

if [[ "$skip_composer" -eq 0 && -f "$project_dir/composer.json" && -x "$(command -v composer || true)" ]]; then
  (cd "$project_dir" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader)
elif [[ "$skip_composer" -eq 0 ]]; then
  echo "Composer not found; using built-in fallback autoloader."
fi

python3 "$project_dir/ip-feed-manager-private/run_migrations.py" \
  --database "$private_dir/ip_feed.sqlite" \
  --migrations-dir "$project_dir/ip-feed-manager-private/migrations"

chmod 755 "$project_dir/ipfeed"
chmod 644 "$project_dir/ipfeed/index.php" "$feed_file"
chmod 750 "$private_dir" "$private_dir/logs" "$private_dir/backups"
chmod 640 "$config_file" "$private_dir/ip_feed.sqlite"

if [[ "$(id -u)" -eq 0 ]] && id "$web_user" >/dev/null 2>&1; then
  web_group="$(id -gn "$web_user")"
  chown -R "$web_user:$web_group" "$private_dir" "$feed_file"
fi

cat <<EOF

IPFeed install complete.

Project:       $project_dir
Private dir:   $private_dir
Config:        $config_file
Feed file:     $feed_file
Web user:      $web_user

Set these in Nginx/Apache/PHP-FPM/systemd:
  IP_FEED_SETTINGS_DIR=$private_dir
  IP_FEED_CONFIG_FILE=$config_file
  IP_FEED_PROJECT_DIR=$project_dir
  IP_FEED_FEED_FILE=$feed_file

Open the dashboard, then change the default admin password:
  /ipfeed/index.php

Use this file as the FortiGate external block list:
  /ipfeed/ips.txt
EOF
