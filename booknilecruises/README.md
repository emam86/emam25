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
PUBLIC_ASSET_ORIGIN=https://booknilecruises.net npx astro build
node --test "tests/built/*.test.mjs"                         # تطابق الروابط والعناوين والمحتوى
```

- الصور لا تُنسخ للمشروع: الصفحات تستخدم `/wp-content/uploads/...` بنفس المسار، والملفات موجودة أصلًا على السيرفر.
  للمعاينة على دومين آخر نضع `PUBLIC_ASSET_ORIGIN=https://booknilecruises.net`.
- نسخة تجريبية (staging): `PUBLIC_NOINDEX=1` تضيف noindex في الصفحات و`robots.txt` و`.htaccess`.
- تصحيحات المحتوى تُكتب في `data/overrides.json` ولا يُعدَّل التصدير الخام أبدًا.

## النسخة الاحتياطية

على السيرفر (من جهازك، لأن SSH مقفول في بيئة Claude السحابية):

```bash
ssh -p 65002 USER@SERVER_IP 'bash -s' < scripts/backup-on-server.sh
```

تُحفَظ قاعدة البيانات والملفات في `~/backups/booknilecruises-<التاريخ>/` خارج `public_html`.
