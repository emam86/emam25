# لوحة تحكم booknilecruises.net — الخطة

**الهدف:** لوحة تحكم على Hostinger (PHP + MySQL) لإدارة الرحلات والصور والمقالات والـ SEO والمستخدمين بصلاحيات، مع نشر تلقائي للموقع الثابت وربط n8n / Make.

**المعمارية:** الموقع العام يفضل HTML ثابت (Astro) عشان السرعة والـ SEO. اللوحة تطبيق PHP منفصل
(`/admin`) وواجهة برمجة (`/api`). البيانات في MySQL. زرار «نشر» يطلب من GitHub Actions يبني الموقع من
بيانات اللوحة ويرفعه على السيرفر عبر FTP. الصور تترفع مباشرة على السيرفر في `/images`.

**الأدوات:** PHP 8.2+ بدون framework (PDO، GD)، MySQL/MariaDB، Astro 5، GitHub Actions، lftp.

---

## شكل الملفات على السيرفر

```
domains/booknilecruises.net/
├── bnc-config.php        ← الإعدادات والأسرار (خارج public_html)
├── bnc-app/              ← كود اللوحة كله (خارج public_html)
└── public_html/
    ├── admin/index.php   ← نقطة دخول اللوحة فقط
    ├── api/index.php     ← نقطة دخول الـ API فقط
    ├── images/           ← الصور (المرفوعة من اللوحة تتحط هنا)
    └── … صفحات الموقع الثابتة (يكتبها النشر التلقائي)
```

PHP ممنوع في كل الموقع ما عدا `admin/index.php` و`api/index.php`.

## شكل الملفات في المشروع

```
booknilecruises/admin/
├── app/              ← bootstrap.php, src/ (الكلاسات), views/ (القوالب), migrations/, bin/ (أوامر CLI)
├── public/admin/     ← index.php + .htaccess + assets/
├── public/api/       ← index.php + .htaccess
├── tests/            ← اختبارات PHP (php tests/run.php)
└── config.sample.php
```

## البيانات (MySQL)

| الجدول | المحتوى |
|---|---|
| `users` | الاسم، الإيميل، كلمة السر (password_hash)، الدور، نشط/موقوف، آخر دخول |
| `roles` | اسم الدور + قائمة الصلاحيات (JSON). دور `owner` محمي |
| `trips` | كل حقول الرحلة الحالية + SEO + حالة (draft/published) |
| `terms` | الوجهات، الأنشطة، أنواع الرحلات (بنفس الروابط) + ربطها بالرحلات `trip_terms` |
| `posts` | المقالات (ومنها اللي ينشرها n8n / Make) |
| `media` | الصور: المسار، الأبعاد، النسخ المصغرة، alt |
| `seo_overrides` | عنوان/وصف/noindex لأي صفحة بالمسار |
| `redirects` | تحويلات 301 (تتعمل تلقائيًا لما رابط رحلة يتغير) |
| `settings` | التواصل، Google/Bing verification، Google Analytics، إعدادات الإشعارات |
| `enquiries` | الاستفسارات من الموقع + حالتها |
| `api_keys` | مفاتيح n8n / Make بصلاحيات محددة |
| `webhooks` | روابط تستقبل أحداث (استفسار جديد، نشر، مقال) |
| `publish_jobs` | سجل عمليات النشر |
| `audit_log` | مين عمل إيه وإمتى |

## الصلاحيات

`trips.view` `trips.create` `trips.edit` `trips.delete` · `posts.*` · `media.upload` `media.delete` ·
`seo.edit` `sitemap.edit` · `enquiries.view` `enquiries.manage` · `settings.edit` · `users.manage` ·
`api.manage` · `publish`

أدوار جاهزة: **Owner** (كل شيء)، **Admin**، **Editor** (رحلات ومقالات وصور)، **SEO**، **Sales** (الاستفسارات)،
وأي دور جديد بالصلاحيات اللي تختارها.

## المراحل

| # | المرحلة | المنفذ | المراجعة |
|---|---|---|---|
| 1 | الأساس: الجداول، الدخول، الأدوار والصلاحيات، المستخدمين، سجل العمليات، CSRF، حماية من التخمين | Claude | اختبارات + /code-review |
| 2 | الرحلات والتصنيفات: إضافة/تعديل/حذف، البرنامج اليومي، المعرض، SEO لكل رحلة، تحويل تلقائي عند تغيير الرابط | Codex | Claude |
| 3 | الصور: رفع (مع نسخ مصغرة)، حذف (مع منع حذف صورة مستخدمة)، مكتبة | Codex | Claude |
| 4 | المقالات، SEO الصفحات، التحويلات، الإعدادات (منها Google verification و Analytics) | Codex | Claude |
| 5 | الـ API: تصدير المحتوى للنشر، الاستفسارات، مقالات n8n/Make بمفاتيح، webhooks | Codex | Claude |
| 6 | النشر: Astro يقرأ من اللوحة، GitHub Action يبني ويرفع بـ FTP، زرار نشر بحالة | Claude | اختبارات build |
| 7 | فحص SEO تلقائي: تقرير في اللوحة + فحص أسبوعي بإيميل + IndexNow | Codex | Claude |
| 8 | الاستيراد: نقل الـ 102 رحلة والصور والمقال للوحة، اختبار شامل، /code-review، /security-review | Claude | — |

## الأمان

كلمات السر `password_hash`، جلسات `HttpOnly + Secure + SameSite=Lax` تنتهي بعد 4 ساعات خمول أو بتغيير كلمة السر، CSRF على كل نموذج،
قفل بعد 5 محاولات دخول غلط، كل استعلام بـ PDO prepared statements، الصور تتحقق من نوعها وتتعاد
كتابتها بـ GD (عشان أي كود مخفي يتشال)، مفاتيح الـ API محفوظة كـ hash، كل عملية في `audit_log`.

## اللي محتاجه منك وقت التركيب

1. قاعدة بيانات MySQL جديدة من hPanel (اسم، مستخدم، باسورد).
2. حساب FTP من hPanel (للنشر التلقائي من GitHub).
3. دمج الفرع في `main` على GitHub (GitHub Actions بيشتغل من الفرع الأساسي بس).
