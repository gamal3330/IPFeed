# IPFeed Release Notes

## v0.1.0 - Deployment Release

- Added `install.sh` for repeatable production setup.
- Added Docker support with `Dockerfile`, `docker-compose.yml`, and Apache container config.
- Added Nginx and Apache production configuration examples.
- Moved real runtime artifacts out of the Git release surface:
  - SQLite databases
  - legacy JSON state files
  - generated `ips.txt`
  - logs and backups
- Added `config.example.php` and `ips.txt.example` templates.
- Documented the recommended `/var/lib/ipfeed` private runtime layout.
- Updated systemd and cron examples for private runtime paths.
