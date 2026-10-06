# booknilecruises.net: من WordPress إلى موقع مبرمج

| الملف | المحتوى |
|---|---|
| [`PLAN.md`](PLAN.md) | الخطة الكاملة، والاكتشافات التي تحتاج قرارك، والأسئلة قبل التنفيذ |
| [`designs/`](designs/) | 3 تصميمات مقترحة للصفحة الرئيسية (`design-a.html`، `design-b.html`، `design-c.html`) وصفحة مقارنة (`index.html`) |
| [`data/wp-export/`](data/wp-export/) | نسخة من كل المحتوى العام للموقع الحالي (صفحات، رحلات، صور، تصنيفات، روابط sitemap) |
| [`scripts/export-wp.mjs`](scripts/export-wp.mjs) | سكربت التصدير (قراءة فقط من الموقع الحي) |

## إعادة التصدير

```bash
cd booknilecruises
NODE_USE_ENV_PROXY=1 node scripts/export-wp.mjs   # NODE_USE_ENV_PROXY مطلوب فقط خلف بروكسي
```

## الموقع الجديد (`site/`)

```bash
cd site
npm install
npm test                                                     # اختبارات البيانات
PUBLIC_IMAGES_BASE=https://booknilecruises.net/wp-content/uploads npx astro build
node --test "tests/built/*.test.mjs"                         # تطابق الروابط والعناوين والمحتوى
```

- الصور لا تُنسخ للمشروع: الصفحات تستخدم `/images/...`، ويُنقل مجلد `public_html/wp-content/uploads/` إلى `public_html/images/` عند إطلاق الموقع مع الحفاظ على مجلدات السنة والشهر وأسماء الملفات.
  للمعاينة على دومين آخر نضع `PUBLIC_IMAGES_BASE=https://booknilecruises.net/wp-content/uploads`.
- نسخة تجريبية (staging): `PUBLIC_NOINDEX=1` تضيف noindex في الصفحات و`robots.txt` و`.htaccess`.
- تصحيحات المحتوى تُكتب في `data/overrides.json` ولا يُعدَّل التصدير الخام أبدًا.

## النسخة الاحتياطية

أمر واحد من جهازك (يشتغل في PowerShell و cmd و Mac و Linux)، السكربت بيتنزّل على السيرفر مباشرة من GitHub:

```
ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/backup-on-server.sh | bash"
```

تُحفَظ قاعدة البيانات والملفات في `~/backups/booknilecruises-<التاريخ>/` خارج `public_html`.

## نقل الموقع الجديد على booknilecruises.net

`deploy/site.tar.gz` هو الموقع جاهز للرفع (يتبني بـ `npx astro build` من `site/`، ومعاه `site.tar.gz.sha256`).

| الأمر | ماذا يفعل |
|---|---|
| `scripts/cutover.sh` | Backup جديد ← تنزيل الموقع والتحقق من الـ checksum ← نقل WordPress خارج `public_html` ← نقل الصور إلى `/images` ← تركيب الموقع ← فحص الصفحات والصور والتحويلات، **ويرجّع WordPress تلقائيًا لو أي فحص فشل** |
| `scripts/rollback.sh` | يرجّع WordPress زي ما كان (الموقع الجديد يتنقل جنب، ما يتمسحش) |
| `scripts/purge-wordpress.sh` | يمسح ملفات WordPress نهائيًا، فقط مع `CONFIRM=DELETE-WORDPRESS` وبعد ما يتأكد من وجود Backup سليم |

كلها تتشغّل على السيرفر بنفس الطريقة:

```
ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/cutover.sh | bash"
```
