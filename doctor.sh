#!/usr/bin/env bash
set -u

usage() {
  cat <<'EOF'
Usage: ./doctor.sh [options]

Options:
  --project-dir PATH     Project checkout path. Default: directory containing doctor.sh.
  --private-dir PATH     Runtime private directory. Default: IP_FEED_SETTINGS_DIR or /var/lib/ipfeed.
  --config-file PATH     Runtime config file. Default: PRIVATE_DIR/config.php.
  --feed-file PATH       Public FortiGate feed file. Default: IP_FEED_FEED_FILE or PROJECT_DIR/ipfeed/ips.txt.
  --web-user USER        Web server user. Default: auto-detect www-data/apache/nginx/_www.
  -h, --help             Show this help.

Example:
  sudo ./doctor.sh --project-dir /var/www/IPFeed --private-dir /var/lib/ipfeed --web-user www-data
EOF
}

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_dir="${IP_FEED_PROJECT_DIR:-$script_dir}"
private_dir="${IP_FEED_SETTINGS_DIR:-/var/lib/ipfeed}"
config_file=""
feed_file="${IP_FEED_FEED_FILE:-}"
web_user="${IP_FEED_WEB_USER:-}"
private_dir_explicit=0
config_file_explicit=0
failures=0
warnings=0

detect_web_user() {
  for candidate in www-data apache nginx _www; do
    if id "$candidate" >/dev/null 2>&1; then
      printf '%s\n' "$candidate"
      return
    fi
  done

  id -un
}

status_line() {
  local status="$1"
  local name="$2"
  local detail="${3:-}"

  printf '[%s] %s' "$status" "$name"
  if [[ -n "$detail" ]]; then
    printf ' - %s' "$detail"
  fi
  printf '\n'
}

ok() {
  status_line "OK" "$1" "${2:-}"
}

warn() {
  warnings=$((warnings + 1))
  status_line "WARN" "$1" "${2:-}"
}

fail() {
  failures=$((failures + 1))
  status_line "FAIL" "$1" "${2:-}"
}

check_cmd() {
  local cmd="$1"
  local label="$2"

  if command -v "$cmd" >/dev/null 2>&1; then
    ok "$label" "$(command -v "$cmd")"
  else
    fail "$label" "$cmd not found"
  fi
}

check_path_readable() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" && -r "$path" ]]; then
    ok "$label" "$path"
  elif [[ -e "$path" ]]; then
    fail "$label" "exists but is not readable: $path"
  else
    fail "$label" "missing: $path"
  fi
}

check_path_writable() {
  local path="$1"
  local label="$2"

  if [[ -e "$path" && -w "$path" ]]; then
    ok "$label" "$path"
  elif [[ -e "$path" ]]; then
    fail "$label" "exists but is not writable: $path"
  else
    fail "$label" "missing: $path"
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --project-dir)
      project_dir="$2"
      shift 2
      ;;
    --private-dir)
      private_dir="$2"
      private_dir_explicit=1
      shift 2
      ;;
    --config-file)
      config_file="$2"
      config_file_explicit=1
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

if [[ -d "$project_dir" ]]; then
  project_dir="$(cd "$project_dir" && pwd)"
fi
if [[ -d "$private_dir" ]]; then
  private_dir="$(cd "$private_dir" && pwd)"
fi
if [[ "$config_file_explicit" -eq 0 && "$private_dir_explicit" -eq 0 && -n "${IP_FEED_CONFIG_FILE:-}" ]]; then
  config_file="$IP_FEED_CONFIG_FILE"
fi
config_file="${config_file:-$private_dir/config.php}"
feed_file="${feed_file:-$project_dir/ipfeed/ips.txt}"
database_file="$private_dir/ip_feed.sqlite"
web_user="${web_user:-$(detect_web_user)}"

echo "IPFeed doctor"
echo "Project:     $project_dir"
echo "Private dir: $private_dir"
echo "Config:      $config_file"
echo "Feed:        $feed_file"
echo "Web user:    $web_user"
echo

check_cmd php "PHP binary"
check_cmd python3 "Python 3"
check_cmd sqlite3 "sqlite3 CLI"

if command -v php >/dev/null 2>&1; then
  php_version="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
  if [[ -n "$php_version" ]]; then
    ok "PHP version" "$php_version"
  else
    fail "PHP version" "unable to execute php -r"
  fi

  if php -m 2>/dev/null | grep -qi '^pdo_sqlite$'; then
    ok "PHP pdo_sqlite extension"
  else
    fail "PHP pdo_sqlite extension" "install php-sqlite3 and restart the web server"
  fi

  if php -m 2>/dev/null | grep -qi '^sqlite3$'; then
    ok "PHP sqlite3 extension"
  else
    warn "PHP sqlite3 extension" "pdo_sqlite is required; sqlite3 is recommended"
  fi

  if php -i 2>/dev/null | grep -qi '^pcre.jit => On'; then
    warn "PCRE JIT" "disable with pcre.jit=0 if your VPS blocks executable memory"
  else
    ok "PCRE JIT" "not enabled or not reported as On"
  fi

  if [[ -d "$project_dir" ]]; then
    lint_output="$(find "$project_dir/ipfeed" "$project_dir/ip-feed-manager-private" -name '*.php' -not -path '*/vendor/*' -print0 2>/dev/null | xargs -0 -n1 php -l 2>&1)"
    if [[ $? -eq 0 ]]; then
      ok "PHP syntax"
    else
      fail "PHP syntax" "$lint_output"
    fi
  fi
fi

if [[ -d "$project_dir" && -f "$project_dir/ipfeed/index.php" ]]; then
  ok "Project structure" "$project_dir"
else
  fail "Project structure" "expected $project_dir/ipfeed/index.php"
fi

check_path_readable "$config_file" "Runtime config"
check_path_writable "$feed_file" "FortiGate feed file"

if [[ -d "$private_dir" ]]; then
  ok "Private directory exists" "$private_dir"
  if [[ -w "$private_dir" ]]; then
    ok "Private directory writable"
  else
    fail "Private directory writable" "$private_dir"
  fi
else
  fail "Private directory exists" "$private_dir"
fi

for dir in "$private_dir/logs" "$private_dir/backups"; do
  if [[ -d "$dir" && -w "$dir" ]]; then
    ok "$(basename "$dir") directory writable" "$dir"
  elif [[ -d "$dir" ]]; then
    fail "$(basename "$dir") directory writable" "$dir"
  else
    fail "$(basename "$dir") directory exists" "$dir"
  fi
done

if [[ -f "$database_file" ]]; then
  check_path_writable "$database_file" "SQLite database"
  if command -v sqlite3 >/dev/null 2>&1; then
    integrity="$(sqlite3 "$database_file" 'pragma integrity_check;' 2>&1 || true)"
    if [[ "$integrity" == "ok" ]]; then
      ok "SQLite integrity_check"
    else
      fail "SQLite integrity_check" "$integrity"
    fi

    schema="$(sqlite3 "$database_file" "select version || ':' || migration from schema_version where id = 1;" 2>/dev/null || true)"
    if [[ -n "$schema" ]]; then
      ok "SQLite schema_version" "$schema"
    else
      fail "SQLite schema_version" "missing row in schema_version"
    fi
  fi
else
  fail "SQLite database" "missing: $database_file"
fi

if [[ -d "$project_dir/ipfeed" && -w "$project_dir/ipfeed" ]]; then
  warn "Public web directory writable" "not required after v0.1.2; only ips.txt should be writable"
elif [[ -d "$project_dir/ipfeed" ]]; then
  ok "Public web directory not writable" "$project_dir/ipfeed"
fi

if [[ -e "$feed_file" && -n "$web_user" ]] && id "$web_user" >/dev/null 2>&1; then
  owner="$(stat -c '%U:%G' "$feed_file" 2>/dev/null || stat -f '%Su:%Sg' "$feed_file" 2>/dev/null || true)"
  ok "Feed file owner" "${owner:-unknown}"
fi

if [[ -L /var/www/html/ipfeed ]]; then
  resolved="$(readlink -f /var/www/html/ipfeed 2>/dev/null || true)"
  if [[ "$resolved" == "$project_dir/ipfeed" ]]; then
    ok "Apache /ipfeed symlink" "$resolved"
  else
    warn "Apache /ipfeed symlink" "points to ${resolved:-unknown}, expected $project_dir/ipfeed"
  fi
elif [[ -e /var/www/html/ipfeed ]]; then
  warn "Apache /ipfeed path" "/var/www/html/ipfeed is not a symlink"
fi

echo
echo "Summary: $failures failure(s), $warnings warning(s)"

if [[ "$failures" -gt 0 ]]; then
  exit 1
fi

exit 0
