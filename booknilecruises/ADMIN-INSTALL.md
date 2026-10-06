# تركيب لوحة التحكم على Hostinger

الخطوات دي بتتعمل مرة واحدة. محتاج: hPanel، وحساب GitHub بتاعك.

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
   - جوا `public_html`: فولدرين جداد `admin` و`api`
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

## 6) تشغيل زرار «نشر الموقع»

الموقع العام صفحات ثابتة سريعة. لما تعدل في اللوحة وتضغط «نشر»، GitHub بيبني الموقع من بيانات اللوحة ويرفعه على السيرفر. ده بيتظبط مرة واحدة:

### أ. حساب FTP مخصص للنشر
hPanel ← **Files** ← **FTP Accounts** ← اعمل حساب جديد:
- اسم المستخدم: مثلًا `deploy`
- الفولدر: `public_html` بتاع booknilecruises.net
- باسورد قوي

اكتب عندك: اسم المستخدم الكامل (زي `u857861630.deploy`)، والـ FTP host (بيظهر في نفس الصفحة، غالبًا `ftp.booknilecruises.net`).

### ب. خلّي `main` الفرع الأساسي على GitHub (مرة واحدة)
الفرع `main` اتعمل وفيه كل الشغل. GitHub Actions (زرار النشر والنشر كل ساعة) بيشتغل من الفرع الأساسي بس:
GitHub ← المستودع `emam86/emam25` ← **Settings** ← **General** ← **Default branch** ← اضغط أيقونة التبديل ⇄ ← اختار `main` ← **Update** ← أكّد.

### ج. أسرار GitHub
GitHub ← المستودع `emam86/emam25` ← **Settings** ← **Secrets and variables** ← **Actions** ← **New repository secret**، وضيف 4:

| الاسم | القيمة |
|---|---|
| `BNC_EXPORT_TOKEN` | قيمة `export_token` من `bnc-config.php` |
| `FTP_HOST` | الـ FTP host |
| `FTP_USER` | اسم مستخدم FTP الكامل |
| `FTP_PASS` | باسورد FTP |

### د. رمز GitHub للوحة (عشان الزرار يشغّل النشر)
1. GitHub ← صورتك ← **Settings** ← **Developer settings** ← **Personal access tokens** ← **Fine-grained tokens** ← **Generate new token**.
2. Repository access: **Only select repositories** ← `emam86/emam25`.
3. Permissions ← Repository ← **Actions: Read and write**.
4. انسخ الرمز وحطه في `bnc-config.php`:
   ```php
   'github' => ['token' => 'الرمز هنا', 'repo' => 'emam86/emam25', 'workflow' => 'publish-site.yml', 'ref' => 'main'],
   ```

### هـ. جرّب
من اللوحة ← **النشر** ← «نشر الموقع الآن». الحالة بتتحدث لوحدها، وفيه لينك لسجل التشغيل على GitHub.

> كمان كل ساعة GitHub بيبص: لو فيه تغيير ما اتنشرش (مثلًا مقال مجدول جه معاده) بينشره لوحده.

---

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
