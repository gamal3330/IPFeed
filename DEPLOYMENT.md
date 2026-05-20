# IP Feed Manager - دليل النشر والتحديث

## المتطلبات

- PHP 8.1 أو أحدث.
- امتداد `pdo_sqlite`.
- Composer لتوليد autoload في بيئة الإنتاج. يوجد fallback داخلي إذا لم يتوفر Composer مؤقتا.
- صلاحية كتابة لمجلد الإعدادات الخاصة وملف `ipfeed/ips.txt`.
- خادم ويب يدعم PHP مثل Apache أو Nginx مع PHP-FPM.
- Python 3 لتشغيل migrations والنسخ الاحتياطي.
- systemd مفضل للتشغيل المستقر، ويمكن استخدام cron كبديل.

## هيكل الملفات

- `ipfeed/` هو مجلد الويب.
- `ipfeed/ips.txt` هو الملف الوحيد الذي يجب أن يكون مكشوفا لـ FortiGate.
- `ipfeed/index.php` هو لوحة الإدارة.
- `ipfeed/app/bootstrap.php` يقوم بتحميل Composer autoload أو fallback داخلي.
- `ipfeed/app/src/` يحتوي طبقات `Controllers`, `Services`, `Repositories`, و `Config`.
- `ip-feed-manager-private/` يحتوي الإعدادات وقاعدة SQLite والملفات الحساسة.
- `ip-feed-manager-private/migrations/` يحتوي migrations منظمة لقاعدة SQLite.
- `ip-feed-manager-private/logs/` يحتوي سجلات التشغيل.
- `ip-feed-manager-private/backups/` يحتوي نسخ SQLite و `ips.txt`.
- `ops/systemd/` يحتوي قوالب الخدمات والمؤقتات.
- `ops/cron/` يحتوي مثال cron بديل.

يفضل نقل `ip-feed-manager-private/` خارج مجلد الويب. إذا تعذر ذلك، تأكد أن ملف `.htaccess` يمنع الوصول المباشر.

## الإعداد

يمكن ضبط مسار الإعدادات عبر:

```bash
IP_FEED_SETTINGS_DIR=/path/to/ip-feed-manager-private
```

أو ضبط ملف إعداد مباشر:

```bash
IP_FEED_CONFIG_FILE=/path/to/config.php
```

ملف الإعداد الرئيسي:

```text
ip-feed-manager-private/config.php
```

## الصلاحيات المقترحة

```bash
chmod 755 ipfeed
chmod 644 ipfeed/index.php ipfeed/ips.txt
chmod 750 ipfeed/app
chmod 750 ip-feed-manager-private
chmod 640 ip-feed-manager-private/config.php
chmod 640 ip-feed-manager-private/ip_feed.sqlite
mkdir -p ip-feed-manager-private/logs ip-feed-manager-private/backups
chmod 750 ip-feed-manager-private/logs ip-feed-manager-private/backups
```

يجب أن يكون مستخدم خادم الويب قادرا على الكتابة في:

- `ipfeed/ips.txt`
- `ip-feed-manager-private/ip_feed.sqlite`
- `ip-feed-manager-private/vt_rate_limit.json`
- `ip-feed-manager-private/vt_settings.json`
- `ip-feed-manager-private/login_attempts.json`
- `ip-feed-manager-private/logs/`
- `ip-feed-manager-private/backups/`

## الترحيل إلى SQLite

لتطبيق migrations المنظمة:

```bash
python3 ip-feed-manager-private/run_migrations.py --database ip-feed-manager-private/ip_feed.sqlite
```

عند وجود ملفات JSON قديمة، شغل:

```bash
python3 ip-feed-manager-private/migrate_json_to_sqlite.py --storage-dir ip-feed-manager-private
```

للتحقق من قاعدة البيانات:

```bash
sqlite3 ip-feed-manager-private/ip_feed.sqlite 'pragma integrity_check;'
```

## VirusTotal Queue

الواجهة تعالج الطابور تدريجيا إذا كانت الصفحة مفتوحة. للتشغيل المستقر بالخلفية، استخدم systemd timer.

1. انسخ ملف البيئة وعدل المسار:

```bash
sudo mkdir -p /etc/ipfeed
sudo cp ops/systemd/ipfeed.env.example /etc/ipfeed/ipfeed.env
sudo nano /etc/ipfeed/ipfeed.env
```

تأكد أن:

```bash
IP_FEED_PROJECT_DIR=/var/www/IPFeed
IP_FEED_CONFIG_FILE=/var/www/IPFeed/ip-feed-manager-private/config.php
IP_FEED_VT_LIMIT=1
IP_FEED_VT_SLEEP=2
```

2. فعّل worker:

```bash
sudo cp ops/systemd/ipfeed-vt-worker.service /etc/systemd/system/
sudo cp ops/systemd/ipfeed-vt-worker.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ipfeed-vt-worker.timer
sudo systemctl status ipfeed-vt-worker.timer
```

بديل cron عند عدم استخدام systemd:

```cron
* * * * * cd /var/www/IPFeed && /usr/bin/php ip-feed-manager-private/vt_worker.php --limit=1 --sleep=2 >> ip-feed-manager-private/logs/vt_worker.cron.log 2>&1
```

يمكن رفع `--limit` عند وجود اشتراك VirusTotal يسمح بعدد أكبر من الطلبات.

## سجلات التشغيل

السجلات الافتراضية:

- `ip-feed-manager-private/logs/app.log`
- `ip-feed-manager-private/logs/vt_worker.log`
- `ip-feed-manager-private/logs/backup.log`

كل سطر JSON مستقل، مما يسهل قراءته أو إرساله لأي نظام مراقبة.

## النسخ الاحتياطي

تشغيل نسخة يدوية:

```bash
python3 ip-feed-manager-private/backup.py --retention-days=14
```

تفعيل النسخ اليومي عبر systemd:

```bash
sudo cp ops/systemd/ipfeed-backup.service /etc/systemd/system/
sudo cp ops/systemd/ipfeed-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ipfeed-backup.timer
sudo systemctl status ipfeed-backup.timer
```

النسخ تحفظ:

- نسخة SQLite سليمة بعد `PRAGMA integrity_check`.
- نسخة من `ipfeed/ips.txt`.
- ملف manifest يحتوي الحجم و SHA256.

لجدولة cron بديلة:

```cron
30 2 * * * cd /var/www/IPFeed && /usr/bin/python3 ip-feed-manager-private/backup.py --retention-days=14 >> ip-feed-manager-private/logs/backup.cron.log 2>&1
```

## صفحة صحة النظام

بعد تسجيل الدخول افتح:

```text
ipfeed/index.php?page=health
```

تتحقق الصفحة من:

- صلاحيات `ips.txt`.
- صلاحيات مجلد الإعدادات الخاصة.
- حماية `.htaccess`.
- اتصال SQLite وسلامة `integrity_check`.
- حالة مفتاح VirusTotal والطابور.
- حالة سجلات التشغيل.
- آخر نسخة احتياطية.

للمراقبة الخارجية استخدم:

```text
https://example.com/ipfeed/index.php?healthcheck=1
```

لحماية الرابط أضف token في `config.php` أو كمتغير بيئة:

```bash
IP_FEED_HEALTH_TOKEN=change-this-long-random-token
```

ثم أرسل الطلب بهذا الشكل:

```bash
curl -H 'X-IPFeed-Health-Token: change-this-long-random-token' https://example.com/ipfeed/index.php?healthcheck=1
```

يرجع الرابط HTTP 200 عند `ok` أو `warning`، و HTTP 503 عند وجود `error`. إذا أردت اعتبار التحذيرات فشلًا، اجعل `healthcheck.fail_on_warning` بقيمة `true`.

## التحديث

قبل أي تحديث:

1. انسخ `ip-feed-manager-private/` احتياطيا.
2. انسخ `ipfeed/ips.txt` احتياطيا.
3. استبدل ملفات التطبيق.
4. شغل `composer install --no-dev --optimize-autoloader` إذا كان Composer متاحا.
5. شغل `python3 ip-feed-manager-private/run_migrations.py --database ip-feed-manager-private/ip_feed.sqlite`.
6. شغل سكربت ترحيل JSON عند الحاجة.
7. شغل `python3 ip-feed-manager-private/backup.py --retention-days=14` للتأكد أن النسخ الاحتياطي يعمل.
8. افتح صفحة صحة النظام وتأكد من عدم وجود أخطاء.
9. تأكد أن systemd timers تعمل:

```bash
systemctl list-timers 'ipfeed-*'
```

## FortiGate

استخدم رابط `ips.txt` فقط كمصدر External Block List. لا تستخدم رابط لوحة الإدارة كمصدر للقائمة.
