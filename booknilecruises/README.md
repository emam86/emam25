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
