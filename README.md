# IP Feed Manager

IP Feed Manager is a PHP dashboard for maintaining an IPv4 blocklist feed for FortiGate. It keeps `ips.txt` as the public feed output while storing users, logs, geo cache, VirusTotal results, queue state, and management metadata in SQLite.

## Features

- FortiGate-compatible `ips.txt` output.
- Secure admin login with role-based users.
- Login attempt rate limiting and login event history.
- SQLite storage for users, logs, geo cache, VirusTotal results, queue state, IP metadata, and login events.
- VirusTotal queue with gradual background processing.
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
│   └── app/                   # Application modules and views
├── ip-feed-manager-private/
│   ├── config.php             # Main configuration
│   ├── ip_feed.sqlite         # SQLite database
│   ├── vt_worker.php          # Optional CLI queue worker
│   └── migrate_json_to_sqlite.py
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

## Quick Start

1. Put `ipfeed/` in the web-accessible directory.
2. Keep `ip-feed-manager-private/` outside the public web root when possible.
3. Configure paths in `ip-feed-manager-private/config.php`.
4. Make sure `pdo_sqlite` is enabled.
5. Open:

```text
ipfeed/index.php
```

6. Change the default `admin` password when prompted.
7. Use the generated `ips.txt` link as the FortiGate External Block List source.

## VirusTotal Queue

The web dashboard can process the queue gradually while it is open. For stable background operation, run the CLI worker with cron:

```cron
* * * * * php /path/to/ip-feed-manager-private/vt_worker.php --limit=1 >/dev/null 2>&1
```

Increase `--limit` only if your VirusTotal quota allows it.

## System Health

After logging in, open:

```text
ipfeed/index.php?page=health
```

This page checks file permissions, private directory placement, `.htaccess` protection, SQLite integrity, and VirusTotal queue state.

## Deployment Guide

See [DEPLOYMENT.md](DEPLOYMENT.md) for the full Arabic deployment and update guide.

## Security Note

This repository may contain private operational data if `ip-feed-manager-private/` is committed. Keep the repository private unless you remove secrets, logs, SQLite data, API keys, and user records first.
