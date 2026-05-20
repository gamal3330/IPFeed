# IP Feed Manager

IP Feed Manager is a PHP dashboard for maintaining an IPv4 blocklist feed for FortiGate. It keeps `ips.txt` as the public feed output while storing users, logs, geo cache, VirusTotal results, queue state, and management metadata in SQLite.

## Features

- FortiGate-compatible `ips.txt` output.
- Secure admin login with role-based users.
- Login attempt rate limiting and login event history.
- SQLite storage for users, logs, geo cache, VirusTotal results, queue state, IP metadata, and login events.
- VirusTotal queue with gradual background processing.
- systemd/cron templates for stable VirusTotal worker operation.
- JSONL operational logs for web/runtime, VirusTotal worker, and backups.
- Automated SQLite and `ips.txt` backups with retention cleanup.
- Public monitoring health check endpoint with optional token protection.
- Last-result caching to avoid repeated VirusTotal checks.
- Bulk IP management: scan, delete, export CSV/TXT, update category, and set expiration dates.
- IP categories such as Brute Force, Scanner, Spam, TOR, Malware, Botnet, Proxy, and Manual.
- Advanced filtering by country, VirusTotal status, ASN, user, date range, category, and expiration state.
- System health page for file permissions, SQLite status, and VirusTotal state.
- Responsive RTL dashboard with improved mobile table views.

## Project Structure

```text
.
├── ipfeed/
│   ├── index.php              # Web dashboard
│   ├── ips.txt                # Public FortiGate feed output
│   └── app/
│       ├── bootstrap.php      # Composer/fallback bootstrap
│       ├── controllers/       # HTTP controller entrypoints
│       ├── src/               # PSR-4 classes
│       └── views/             # RTL views
├── ip-feed-manager-private/
│   ├── config.php             # Main configuration
│   ├── ip_feed.sqlite         # SQLite database
│   ├── migrations/            # Ordered SQLite migrations
│   ├── backup.py              # SQLite + ips.txt backup runner
│   ├── vt_worker.php          # Optional CLI queue worker
│   ├── run_migrations.py      # Migration runner
│   └── migrate_json_to_sqlite.py
├── ops/
│   ├── cron/                  # Cron examples
│   └── systemd/               # systemd service/timer examples
├── composer.json
└── DEPLOYMENT.md              # Arabic deployment and update guide
```

## Requirements

- PHP 8.1 or newer.
- PHP `pdo_sqlite` extension.
- A PHP-capable web server such as Apache or Nginx with PHP-FPM.
- Writable access for the web server user to:
  - `ipfeed/ips.txt`
  - `ip-feed-manager-private/ip_feed.sqlite`
  - `ip-feed-manager-private/vt_rate_limit.json`
  - `ip-feed-manager-private/vt_settings.json`
  - `ip-feed-manager-private/login_attempts.json`
  - `ip-feed-manager-private/logs/`
  - `ip-feed-manager-private/backups/`

## Quick Start

1. Put `ipfeed/` in the web-accessible directory.
2. Keep `ip-feed-manager-private/` outside the public web root when possible.
3. Configure paths in `ip-feed-manager-private/config.php`.
4. Install Composer autoload when Composer is available:

```bash
composer install --no-dev --optimize-autoloader
```

If Composer is not available yet, the application keeps a small fallback autoloader in `ipfeed/app/bootstrap.php`.

5. Run SQLite migrations:

```bash
python3 ip-feed-manager-private/run_migrations.py --database ip-feed-manager-private/ip_feed.sqlite
```

6. Make sure `pdo_sqlite` is enabled.
7. Open:

```text
ipfeed/index.php
```

8. Change the default `admin` password when prompted.
9. Use the generated `ips.txt` link as the FortiGate External Block List source.

## VirusTotal Queue

The web dashboard can process the queue gradually while it is open. For stable background operation, use the included systemd timer:

```bash
sudo mkdir -p /etc/ipfeed
sudo cp ops/systemd/ipfeed.env.example /etc/ipfeed/ipfeed.env
sudo nano /etc/ipfeed/ipfeed.env
sudo cp ops/systemd/ipfeed-vt-worker.service /etc/systemd/system/
sudo cp ops/systemd/ipfeed-vt-worker.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ipfeed-vt-worker.timer
```

Cron is also supported:

```cron
* * * * * cd /path/to/IPFeed && php ip-feed-manager-private/vt_worker.php --limit=1 --sleep=2 >> ip-feed-manager-private/logs/vt_worker.cron.log 2>&1
```

Increase `--limit` only if your VirusTotal quota allows it.

## Backups

Create a backup manually:

```bash
python3 ip-feed-manager-private/backup.py --retention-days=14
```

Or enable the daily systemd timer:

```bash
sudo cp ops/systemd/ipfeed-backup.service /etc/systemd/system/
sudo cp ops/systemd/ipfeed-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ipfeed-backup.timer
```

Backups are written to `ip-feed-manager-private/backups/` and operational logs to `ip-feed-manager-private/logs/`.

## System Health

After logging in, open:

```text
ipfeed/index.php?page=health
```

This page checks file permissions, private directory placement, `.htaccess` protection, SQLite integrity, and VirusTotal queue state.

For external monitoring, use:

```text
ipfeed/index.php?healthcheck=1
```

If `healthcheck.token` or `IP_FEED_HEALTH_TOKEN` is set, send it as `?token=...`, `Authorization: Bearer ...`, or `X-IPFeed-Health-Token: ...`.

## Deployment Guide

See [DEPLOYMENT.md](DEPLOYMENT.md) for the full Arabic deployment and update guide.

## Security Note

This repository may contain private operational data if `ip-feed-manager-private/` is committed. Keep the repository private unless you remove secrets, logs, SQLite data, API keys, and user records first.
