# IP Feed Manager - دليل النشر والتحديث

## الفكرة الأساسية

لا تنشر ملفات التشغيل الحقيقية على GitHub. المستودع يجب أن يحتوي الكود، القوالب، migrations، وأمثلة التشغيل فقط.

ملفات التشغيل التي يجب أن تبقى على السيرفر:

- `config.php`
- `ip_feed.sqlite`
- `ips.txt`
- ملفات JSON القديمة إن وجدت قبل الترحيل
- `logs/`
- `backups/`

التخطيط المقترح:

```text
/var/www/IPFeed/                 نسخة الكود من GitHub release
/var/www/IPFeed/ipfeed/          المجلد المكشوف للويب
/var/www/IPFeed/ipfeed/ips.txt   الملف الوحيد المكشوف لـ FortiGate
/var/lib/ipfeed/                 مجلد التشغيل الخاص خارج web root
/var/lib/ipfeed/config.php       إعدادات الإنتاج
/var/lib/ipfeed/ip_feed.sqlite   قاعدة SQLite
/var/lib/ipfeed/logs/            السجلات
/var/lib/ipfeed/backups/         النسخ الاحتياطية
```

## المتطلبات

- PHP 8.1 أو أحدث.
- امتداد `pdo_sqlite`.
- Python 3.
- Apache أو Nginx مع PHP-FPM.
- Composer مفضل للإنتاج، ويوجد fallback autoload إذا لم يتوفر مؤقتا.
- systemd مفضل للـ worker والنسخ الاحتياطي، ويمكن استخدام cron كبديل.

## التثبيت عبر install.sh

بعد تنزيل GitHub release أو عمل clone:

```bash
cd /var/www/IPFeed
sudo ./install.sh \
  --project-dir /var/www/IPFeed \
  --private-dir /var/lib/ipfeed \
  --feed-file /var/www/IPFeed/ipfeed/ips.txt \
  --web-user www-data
```

السكربت يقوم بـ:

- إنشاء `/var/lib/ipfeed`.
- إنشاء `logs/` و `backups/`.
- إنشاء `config.php` داخل المجلد الخاص.
- إنشاء `ipfeed/ips.txt` فارغ.
- تشغيل migrations على SQLite.
- ضبط صلاحيات أساسية.

بعد التثبيت أو بعد أي نقل للسيرفر، شغل أداة التشخيص:

```bash
sudo ./doctor.sh \
  --project-dir /var/www/IPFeed \
  --private-dir /var/lib/ipfeed \
  --feed-file /var/www/IPFeed/ipfeed/ips.txt \
  --web-user www-data
```

إذا أردت إعادة توليد ملف الإعداد:

```bash
sudo ./install.sh --private-dir /var/lib/ipfeed --force-config
```

## متغيرات البيئة المهمة

استخدمها في Nginx/Apache/PHP-FPM/systemd:

```bash
IP_FEED_PROJECT_DIR=/var/www/IPFeed
IP_FEED_SETTINGS_DIR=/var/lib/ipfeed
IP_FEED_CONFIG_FILE=/var/lib/ipfeed/config.php
IP_FEED_FEED_FILE=/var/www/IPFeed/ipfeed/ips.txt
IP_FEED_HEALTH_TOKEN=change-this-long-random-token
```

## Apache

يوجد مثال جاهز:

```text
ops/apache/ipfeed.conf.example
```

تثبيت نموذجي:

```bash
sudo cp ops/apache/ipfeed.conf.example /etc/apache2/sites-available/ipfeed.conf
sudo nano /etc/apache2/sites-available/ipfeed.conf
sudo a2enmod proxy_fcgi setenvif rewrite headers
sudo a2ensite ipfeed.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

عدّل مسار PHP-FPM socket حسب إصدار PHP لديك، مثل `php8.2-fpm.sock` أو `php8.3-fpm.sock`.

## Nginx

يوجد مثال جاهز:

```text
ops/nginx/ipfeed.conf.example
```

تثبيت نموذجي:

```bash
sudo cp ops/nginx/ipfeed.conf.example /etc/nginx/sites-available/ipfeed
sudo nano /etc/nginx/sites-available/ipfeed
sudo ln -s /etc/nginx/sites-available/ipfeed /etc/nginx/sites-enabled/ipfeed
sudo nginx -t
sudo systemctl reload nginx
```

تأكد من تعديل:

- `server_name`
- مسار `/var/www/IPFeed`
- مسار `/var/lib/ipfeed`
- PHP-FPM socket

## Docker

للتجربة السريعة:

```bash
docker compose up -d --build
```

ثم افتح:

```text
http://localhost:8080/
```

Docker يستخدم volume باسم `ipfeed-data` ويحفظ داخله:

- SQLite
- `config.php`
- `ips.txt`
- logs
- backups

## الصلاحيات المقترحة

```bash
sudo chown -R www-data:www-data /var/lib/ipfeed /var/www/IPFeed/ipfeed/ips.txt
sudo chmod 750 /var/lib/ipfeed /var/lib/ipfeed/logs /var/lib/ipfeed/backups
sudo chmod 640 /var/lib/ipfeed/config.php /var/lib/ipfeed/ip_feed.sqlite
sudo chmod 644 /var/www/IPFeed/ipfeed/ips.txt
```

يجب أن يستطيع مستخدم الويب الكتابة في:

- `/var/www/IPFeed/ipfeed/ips.txt`
- `/var/lib/ipfeed/ip_feed.sqlite`
- `/var/lib/ipfeed/logs/`
- `/var/lib/ipfeed/backups/`

ابتداءً من `v0.1.2` لا يحتاج مجلد `/var/www/IPFeed/ipfeed` نفسه أن يكون قابلًا للكتابة. الأفضل أن يبقى `755`، ويكون الملف `ips.txt` فقط قابلًا للكتابة.

## SQLite و migrations

تشغيل migrations يدويا:

```bash
python3 ip-feed-manager-private/run_migrations.py \
  --database /var/lib/ipfeed/ip_feed.sqlite \
  --migrations-dir ip-feed-manager-private/migrations
```

أسماء migrations الحالية:

```text
ip-feed-manager-private/migrations/001_initial.sql
ip-feed-manager-private/migrations/002_vt_queue.sql
ip-feed-manager-private/migrations/003_ip_metadata.sql
```

التحقق:

```bash
sqlite3 /var/lib/ipfeed/ip_feed.sqlite 'pragma integrity_check;'
sqlite3 /var/lib/ipfeed/ip_feed.sqlite 'select * from schema_version;'
```

إذا كانت لديك ملفات JSON قديمة على السيرفر:

```bash
python3 ip-feed-manager-private/migrate_json_to_sqlite.py --storage-dir /var/lib/ipfeed
```

بعد الترحيل تصبح إعدادات VirusTotal وحدود الطلبات ومحاولات الدخول داخل جدول `app_state`.

## VirusTotal Worker

1. انسخ ملف البيئة:

```bash
sudo mkdir -p /etc/ipfeed
sudo cp ops/systemd/ipfeed.env.example /etc/ipfeed/ipfeed.env
sudo nano /etc/ipfeed/ipfeed.env
```

2. فعّل worker:

```bash
sudo cp ops/systemd/ipfeed-vt-worker.service /etc/systemd/system/
sudo cp ops/systemd/ipfeed-vt-worker.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ipfeed-vt-worker.timer
sudo systemctl status ipfeed-vt-worker.timer
```

بديل cron موجود في:

```text
ops/cron/ipfeed.cron.example
```

## النسخ الاحتياطي والاستعادة

تشغيل نسخة يدوية:

```bash
IP_FEED_DATABASE=/var/lib/ipfeed/ip_feed.sqlite \
IP_FEED_FEED_FILE=/var/www/IPFeed/ipfeed/ips.txt \
IP_FEED_BACKUP_DIR=/var/lib/ipfeed/backups \
IP_FEED_BACKUP_LOG=/var/lib/ipfeed/logs/backup.log \
python3 ip-feed-manager-private/backup.py --retention-days=14
```

استعادة نسخة:

```bash
IP_FEED_DATABASE=/var/lib/ipfeed/ip_feed.sqlite \
IP_FEED_FEED_FILE=/var/www/IPFeed/ipfeed/ips.txt \
IP_FEED_BACKUP_DIR=/var/lib/ipfeed/backups \
IP_FEED_BACKUP_LOG=/var/lib/ipfeed/logs/backup.log \
python3 ip-feed-manager-private/backup.py restore --manifest backup_YYYYMMDD_HHMMSS.json
```

يمكن أيضا إنشاء واستعادة النسخ من صفحة `Settings`. عند الاستعادة ينشئ النظام نسخة `pre_restore` تلقائيا.

تفعيل النسخ اليومي عبر systemd:

```bash
sudo cp ops/systemd/ipfeed-backup.service /etc/systemd/system/
sudo cp ops/systemd/ipfeed-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ipfeed-backup.timer
```

## صفحة صحة النظام

بعد تسجيل الدخول افتح:

```text
/ipfeed/index.php?page=health
```

للمراقبة الخارجية:

```text
/ipfeed/index.php?healthcheck=1
```

مع token:

```bash
curl -H 'X-IPFeed-Health-Token: change-this-long-random-token' \
  https://example.com/ipfeed/index.php?healthcheck=1
```

## تشخيص الأعطال

أول أمر تشغله عند ظهور 500 أو مشكلة صلاحيات:

```bash
cd /var/www/IPFeed
sudo ./doctor.sh --project-dir /var/www/IPFeed --private-dir /var/lib/ipfeed --web-user www-data
```

أمثلة إصلاحات شائعة:

```bash
sudo apt install -y php-sqlite3 sqlite3 python3
sudo phpenmod pdo_sqlite sqlite3 || true
sudo chown -R www-data:www-data /var/lib/ipfeed
sudo chown www-data:www-data /var/www/IPFeed/ipfeed/ips.txt
sudo chmod 755 /var/www/IPFeed/ipfeed
sudo chmod 644 /var/www/IPFeed/ipfeed/ips.txt
sudo systemctl restart apache2
```

## GitHub Release بدل ملفات التشغيل

قبل إنشاء release:

```bash
git status -sb
git diff --check
```

كل push على `main` يشغل GitHub Actions لفحص:

- PHP syntax وامتدادات SQLite.
- Python scripts.
- `install.sh` و `doctor.sh`.
- Docker build.
- عدم رجوع ملفات التشغيل الحقيقية إلى Git.

تأكد أن هذه الملفات غير موجودة في Git:

```text
ip-feed-manager-private/config.php
ip-feed-manager-private/ip_feed.sqlite
ip-feed-manager-private/*.json
ip-feed-manager-private/logs/
ip-feed-manager-private/backups/
ipfeed/ips.txt
```

إنشاء release:

```bash
git tag -a vX.Y.Z -m "IPFeed vX.Y.Z"
git push origin vX.Y.Z
gh release create vX.Y.Z --title "IPFeed vX.Y.Z" --notes-file RELEASE_NOTES.md
```

على السيرفر، حدّث من release وليس من مجلد تشغيل حي:

```bash
cd /var/www/IPFeed
git fetch --tags
git checkout vX.Y.Z
sudo ./install.sh --project-dir /var/www/IPFeed --private-dir /var/lib/ipfeed --web-user www-data
```

## التحديث

قبل أي تحديث:

1. أنشئ backup من الواجهة أو CLI.
2. نزّل GitHub release الجديد.
3. لا تستبدل `/var/lib/ipfeed`.
4. شغل `./install.sh` بنفس المسارات.
5. شغل migrations إن لم يشغلها install.
6. افتح صفحة Health وتأكد أن SQLite و `ips.txt` والنسخ الاحتياطي سليمة.

## FortiGate

استخدم رابط `ips.txt` فقط كمصدر External Block List:

```text
https://example.com/ipfeed/ips.txt
```

لا تستخدم رابط لوحة الإدارة كمصدر للقائمة.
