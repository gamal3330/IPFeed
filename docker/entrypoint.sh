#!/usr/bin/env bash
set -euo pipefail

project_dir="${IP_FEED_PROJECT_DIR:-/opt/ipfeed}"
private_dir="${IP_FEED_SETTINGS_DIR:-/var/lib/ipfeed}"
config_file="${IP_FEED_CONFIG_FILE:-$private_dir/config.php}"
feed_file="${IP_FEED_FEED_FILE:-$private_dir/ips.txt}"
public_feed="$project_dir/ipfeed/ips.txt"

mkdir -p "$private_dir" "$(dirname "$feed_file")"
touch "$feed_file"

rm -f "$public_feed"
ln -s "$feed_file" "$public_feed"

"$project_dir/install.sh" \
  --project-dir "$project_dir" \
  --private-dir "$private_dir" \
  --feed-file "$feed_file" \
  --web-user www-data \
  --skip-composer

chown -h www-data:www-data "$public_feed"
chown -R www-data:www-data "$private_dir"

export IP_FEED_SETTINGS_DIR="$private_dir"
export IP_FEED_CONFIG_FILE="$config_file"
export IP_FEED_FEED_FILE="$feed_file"

exec "$@"
