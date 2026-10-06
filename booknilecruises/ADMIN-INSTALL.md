# تركيب لوحة التحكم على Hostinger

الخطوات دي بتتعمل مرة واحدة. محتاج: hPanel وبيانات قاعدة MySQL.

> ⚠️ متضغطش أبدًا على «Uninstall WordPress» في hPanel، ومتمسحش فولدر `images`.

---

## 1) قاعدة البيانات

1. hPanel ← **Websites** ← booknilecruises.net ← **Databases** ← **Management**.
2. اعمل قاعدة جديدة: اكتب اسم (مثلًا `bnc`) واسم مستخدم (مثلًا `bnc`) وباسورد قوي.
3. اكتب عندك الأسماء الكاملة زي ما هتظهر (بتبدأ بـ `u857861630_`) والباسورد.

## 2) رفع ملفات اللوحة

1. hPanel ← **File Manager**.
2. افتح فولدر `domains/booknilecruises.net` (الفولدر اللي **فيه** `public_html`، مش جواه).
3. ارفع ملف `admin-bundle.zip` اللي هبعتهولك، وبعدين كليك يمين عليه ← **Extract** في نفس المكان.
4. هتلاقي ظهر:
   - `bnc-app` (كود اللوحة، برا الموقع العام)
   - `bnc-config.php` (الإعدادات والأسرار)
   - جوا `public_html`: `admin` و`api`، وملفات الموقع `index.php` و`.htaccess` و`assets` و`img`
5. امسح ملف `admin-bundle.zip` بعد الفك.

## 3) ملف الإعدادات

افتح `domains/booknilecruises.net/bnc-config.php` بـ **Edit** وغيّر الجزء ده بس:

```php
'dsn'  => 'mysql:host=127.0.0.1;port=3306;dbname=u857861630_bnc;charset=utf8mb4',
'user' => 'u857861630_bnc',
'pass' => 'باسورد قاعدة البيانات',
```

باقي الملف جاهز: الرموز السرية (`install_token` و`export_token`) اتولدت عشوائي، ومسار الصور مظبوط.

## 4) التثبيت وإنشاء حسابك

1. افتح `https://booknilecruises.net/admin/install`
2. انسخ قيمة `install_token` من `bnc-config.php` في خانة «رمز التثبيت»، واكتب اسمك وإيميلك وباسورد (10 حروف على الأقل).
3. بعد ما تدخل: ارجع لـ `bnc-config.php` وخلّي `install_token` فاضي `''` واحفظ.

## 5) نقل محتوى الموقع الحالي للوحة

من اللوحة افتح **استيراد محتوى الموقع الحالي** (`/admin/import`) واضغط «ابدأ الاستيراد». هيتنقل 102 رحلة و27 تصنيف و618 صورة والمقال. الصور نفسها مش بتتنقل، هي موجودة بالفعل في `images`.

---

## 6) تشغيل الموقع المباشر

انسخ الملفات بهذه المسارات؛ فعّل إظهار الملفات المخفية لرفع `.htaccess`:

| الملف في المشروع | مكانه على Hostinger |
|---|---|
| `admin/app/` | `domains/booknilecruises.net/bnc-app/` |
| `admin/public/admin/` | `domains/booknilecruises.net/public_html/admin/` |
| `admin/public/api/` | `domains/booknilecruises.net/public_html/api/` |
| `admin/public/site/index.php` | `domains/booknilecruises.net/public_html/index.php` |
| `admin/public/site/.htaccess` | `domains/booknilecruises.net/public_html/.htaccess` |
| `admin/public/site/assets/` | `domains/booknilecruises.net/public_html/assets/` |
| `admin/public/site/img/` | `domains/booknilecruises.net/public_html/img/` |

حافظ على `bnc-config.php` خارج `public_html` وعلى مجلد `public_html/images/` الموجود. احذف `public_html/index.html` القديم لكي تُفتح صفحة PHP الرئيسية. الموقع يقرأ نفس قاعدة MySQL الخاصة باللوحة، والتعديلات تظهر بعد الحفظ دون زر نشر.

أنشئ `domains/booknilecruises.net/bnc-app/cache/site/` واجعله قابلًا للكتابة بواسطة مستخدم PHP. الإعداد `'site_cache_dir' => null` يستخدم هذا المسار تلقائيًا؛ أو ضع مسارًا مطلقًا خارج `public_html` في `site_cache_dir`. اترك `'site_noindex' => false` للإنتاج، واستخدم `true` في نسخة التجربة. المقالات المجدولة تظهر عند حلول موعدها حتى قبل تشغيل Cron.

من hPanel ← Advanced ← Cron Jobs اختر **كل ١٥ دقيقة**: الدقائق `*/15` وباقي حقول الوقت `*`. ضع هذا الأمر في خانة الأمر مع استبدال `<user>` باسم مستخدم الاستضافة:

```sh
php /home/<user>/domains/booknilecruises.net/bnc-app/bin/cron.php
```

صيغة crontab الكاملة:

```cron
*/15 * * * * php /home/<user>/domains/booknilecruises.net/bnc-app/bin/cron.php
```

المهمة ترسل إشعار `post.published` مرة واحدة للمقالات المستحقة في أول تشغيل بعد موعدها. ولّد مفتاح IndexNow من فحص SEO؛ الموقع يعرض ملف المفتاح تلقائيًا، والإرسال يتم بعد نجاح الحفظ. راجع [دليل API](admin/API.md).

## 7) فحص SEO أسبوعي بالإيميل (اختياري)
hPanel ← **Advanced** ← **Cron Jobs** ← أسبوعي ← الأمر:
```
php /home/u857861630/domains/booknilecruises.net/bnc-app/bin/seo-check.php --email
```

## 8) ربط n8n أو Make
التفاصيل في `admin/API.md`: إزاي تعمل مفتاح API من اللوحة، وتنشر مقالات تلقائي، وتستقبل الاستفسارات الجديدة.

---

## الأمان
- `bnc-config.php` فيه أسرار: متشاركهوش ومترفعهوش على GitHub.
- غيّر باسورد SSH اللي اتبعت في المحادثة قبل كده.
- كل عملية في اللوحة متسجلة في **سجل العمليات**.
