# IP Feed Manager - دليل النشر والتحديث

## المتطلبات

- PHP 8.1 أو أحدث.
- امتداد `pdo_sqlite`.
- صلاحية كتابة لمجلد الإعدادات الخاصة وملف `ipfeed/ips.txt`.
- خادم ويب يدعم PHP مثل Apache أو Nginx مع PHP-FPM.

## هيكل الملفات

- `ipfeed/` هو مجلد الويب.
- `ipfeed/ips.txt` هو الملف الوحيد الذي يجب أن يكون مكشوفا لـ FortiGate.
- `ipfeed/index.php` هو لوحة الإدارة.
- `ip-feed-manager-private/` يحتوي الإعدادات وقاعدة SQLite والملفات الحساسة.

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
```

يجب أن يكون مستخدم خادم الويب قادرا على الكتابة في:

- `ipfeed/ips.txt`
- `ip-feed-manager-private/ip_feed.sqlite`
- `ip-feed-manager-private/vt_rate_limit.json`
- `ip-feed-manager-private/vt_settings.json`
- `ip-feed-manager-private/login_attempts.json`

## الترحيل إلى SQLite

عند وجود ملفات JSON قديمة، شغل:

```bash
python3 ip-feed-manager-private/migrate_json_to_sqlite.py --storage-dir ip-feed-manager-private
```

للتحقق من قاعدة البيانات:

```bash
sqlite3 ip-feed-manager-private/ip_feed.sqlite 'pragma integrity_check;'
```

## VirusTotal Queue

الواجهة تعالج الطابور تدريجيا إذا كانت الصفحة مفتوحة. للتشغيل المستقر بالخلفية، أضف cron عند توفر PHP CLI:

```cron
* * * * * php /path/to/ip-feed-manager-private/vt_worker.php --limit=1 >/dev/null 2>&1
```

يمكن رفع `--limit` عند وجود اشتراك VirusTotal يسمح بعدد أكبر من الطلبات.

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

## التحديث

قبل أي تحديث:

1. انسخ `ip-feed-manager-private/` احتياطيا.
2. انسخ `ipfeed/ips.txt` احتياطيا.
3. استبدل ملفات التطبيق.
4. شغل سكربت الترحيل مرة أخرى.
5. افتح صفحة صحة النظام وتأكد من عدم وجود أخطاء.

## FortiGate

استخدم رابط `ips.txt` فقط كمصدر External Block List. لا تستخدم رابط لوحة الإدارة كمصدر للقائمة.
