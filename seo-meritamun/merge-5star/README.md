# دمج مقالات «5 star Nile cruise»: 19 مقال يبقوا 3 صفحات

> ## ✅ اتنفّذ على الموقع المباشر (2026-10-04)
> اتعمل عن طريق WordPress REST API (بالـ Application Password) وإضافة **Redirection** اللي متفعّلة
> على الموقع، فـ **ملف `.htaccess` ماتلمسش، ومش محتاج يتلمس** (`htaccess-redirects.txt` بقى مرجع بس).
>
> | الخطوة | النتيجة |
> |---|---|
> | تحديث الصفحات التلاتة (6988، 6987، 6986): العنوان + المحتوى + عنوان Yoast + الوصف + الكلمة المفتاحية | ✅ اتأكدت منها على الموقع |
> | Redirects في إضافة Redirection: **27 جديد + 10 اتعدّلوا** عشان يروحوا للصفحة الجديدة على طول | ✅ **70/70** اختبار (35 رابط، بالـ `/` في الآخر ومن غيرها): 301 واحد للصفحة الصح |
> | الـ 16 مقال بقوا **Draft** (ماتمسحوش) | ✅ الـ sitemap فيه 3 صفحات «5-star» بس، والنسخ الألماني `/de/` بتتحول هي كمان (16/16) |
> | تصليح اللينكات الداخلية جوه المحتوى | ✅ **284 تعديل في 45 مقال/صفحة**، منهم الـ `//` وكل لينك كان بيعدّي على redirect ([`link-replacements.csv`](link-replacements.csv)) |
> | redirect «Day Tour Luxor West Bank» | ✅ اتمسح بناءً على طلبك (التور لسه بيتباع)، والتور بقى بيفتح عادي |
>
> **النسخ الاحتياطية للرجوع:** [`backup/`](backup/) فيه محتوى الـ 19 مقال قبل أي تعديل (`post-<id>.json`)،
> وكل الـ redirects قبل التعديل (`redirects-before.json`)، والمحتوى الأصلي لكل مقال من الـ 45 اللي
> اتصلحت لينكاته (`linkfix/`).
>
> **فاضل عليك:**
> 1. Search Console ← URL Inspection ← **Request indexing** للصفحات التلاتة.
> 2. **امسح الـ Application Password** من Users ← Profile بعد ما نخلص.
> 3. ✅ **تور «Day Tour Luxor West Bank» (5680) رجع يشتغل** (لسه بيتباع): redirect رقم 2 اتمسح، والصفحة بقت
>    بترجّع 200 بالإنجليزي و`/de/`. واللينك اللي كان رايح له في مقال 7574 (`5-sterne-nilkreuzfahrt`) رجع زي ما كان.
>    **فاضل عليك:** التور عليه `noindex` من Yoast. افتح التور ← Yoast ← Advanced ← «Allow search engines to show
>    this content?» ← **Yes**، وبعدين Request indexing. (تور 6707 النسخة المكررة يفضل noindex زي ما هو.)
> 4. لينكات القالب (مش جوه المحتوى): لينك اسم الكاتب `author/egyptianemam25/` بيحوّل للرئيسية،
>    ولينكات الصور بتروح لصفحات المرفقات.

---

**الهدف:** 19 مقال بيتنافسوا على نفس الكلمة (كلهم مترتبين 50–90) يتحولوا لـ 3 صفحات قوية.
الـ 16 الباقيين يتعملهم 301 لأقرب صفحة منهم.

**ليه احتفظنا بالروابط دي بالذات:** كل صفحة من التلاتة هي اللي عندها أكبر ظهور في مجموعتها.
فإحنا بنحدّث محتوى صفحة جوجل عارفها أصلًا، ومش بنعمل رابط جديد يبدأ من الصفر.

| # | الصفحة اللي فاضلة (الرابط زي ما هو) | ID | الموضوع | بيندمج فيها |
|---|---|---|---|---|
| 1 | `/top-5-star-nile-cruises-in-egypt-compare-ships-routes-luxury-inclusions-fast/` | 6988 | الدليل الشامل: يعني إيه 5 نجوم، Standard وDeluxe وSuper Deluxe، المشمول، المسار، أحسن وقت | 9 مقالات |
| 2 | `/5-star-nile-cruise-rooms-choose-the-perfect-cabin-for-comfort-views-relaxation/` | 6987 | البواخر والكبائن: الأدوار، أنواع الغرف، المرافق، الأكل | 3 مقالات |
| 3 | `/book-a-5-star-aswan-luxor-nile-cruise-what-youll-see-whats-included-why-its-worth-it/` | 6986 | البرنامج يوم بيوم (أسوان↔الأقصر)، المشمول، الإضافات، الحجز | 4 مقالات |

**المحتوى اتكتب من جديد، مش لزق للقديم.** المقالات القديمة كلامها عام ومافيهاش أسعار ولا تفاصيل،
وفيها أخطاء، زي مقال `five-star-nile-cruise-aswan-luxor` اللي بيوصف برنامج **بحيرة ناصر**
على إنه أسوان→الأقصر، وبيستشهد بأسعار 2021. المحتوى الجديد مبني على رحلاتكم الحقيقية:
الـ 4 ليالي من $1,150 والـ 3 ليالي من $950، وكل لينك فيه شغال (200) ورايح للصفحة النهائية على طول.

## الملفات

| الملف | الاستخدام |
|---|---|
| `page-1-guide.blocks.html` | محتوى الصفحة 1، جاهز للّزق في محرر الكود بتاع ووردبريس |
| `page-2-ships-cabins.blocks.html` | محتوى الصفحة 2 |
| `page-3-aswan-luxor.blocks.html` | محتوى الصفحة 3 |
| `page-*.html` (من غير `.blocks`) | نفس المحتوى HTML عادي. في أوله العنوان وعنوان Yoast والـ meta description والكلمة المفتاحية |
| `htaccess-redirects.txt` | 35 قاعدة 301: الـ 16 مقال + 19 رابط قديم قصير كانوا بيعدّوا عليهم |
| `posts-to-draft.csv` | الـ 16 مقال اللي يتحولوا Draft (بالـ ID) |
| `search-replace.csv` | الاستبدالات بتاعة اللينكات الداخلية، بالترتيب |

---

## خطوات التنفيذ (بالترتيب ده)

### 0) Backup
- **File Manager:** حمّل نسخة من `public_html/.htaccess`.
- **phpMyAdmin أو إضافة:** خد Export للداتابيز (أو Backup من cPanel).

### 1) حدّث الصفحات التلاتة (لوحة ووردبريس)
لكل صفحة من التلاتة:
1. Posts ← افتح المقال بالـ ID.
2. غيّر **العنوان** للعنوان المكتوب في أول ملف `page-N-….html`.
3. ⋮ (فوق على اليمين) ← **Code editor** ← امسح المحتوى كله ← الزق محتوى `page-N-….blocks.html` ← ارجع لـ Visual editor.
4. في Yoast: حط **SEO title** و**Meta description** و**Focus keyphrase** من أول الملف.
5. **ماتغيّرش الـ slug.**
6. Update.

### 2) حوّل الـ 16 مقال لـ Draft
من `posts-to-draft.csv`. Posts ← علّم عليهم ← Bulk actions ← Edit ← Status: **Draft**.
**بلاش تمسحهم.** الـ Draft بيشيلهم من الـ sitemap ومن الموقع، والمحتوى بيفضل موجود لو احتجته.

### 3) الـ Redirects (File Manager)
1. `public_html/.htaccess` ← Edit.
2. الزق محتوى `htaccess-redirects.txt` **في أول الملف خالص**، فوق `# BEGIN WordPress` وفوق أي بلوك
   بتاع GTranslate.
3. Save.
4. جرّب في المتصفح: `https://egypttours-meritamun.com/5-star-nile-cruise-boats-choose-your-ship-cabin-and-cruise-style/`
   لازم يحوّل لصفحة الكبائن. لو ظهر **500 Internal Server Error**، رجّع الـ backup فورًا وابعتلي.

**النسخ الألماني `/de/` مش محتاجة قواعد لوحدها.** GTranslate بيترجم الـ redirect أوتوماتيك، واتأكدت
من ده على الموقع: `/de//5-star-luxury-nile-cruises/` بيحوّل لنسخة `/de/` الصح.

### 4) صلّح اللينكات الداخلية
نزّل إضافة **Better Search Replace** ← Tools ← Better Search Replace.
نفّذ الاستبدالات بترتيب `search-replace.csv`. **أول واحد هو الأهم:**
`egypttours-meritamun.com//` ← `egypttours-meritamun.com/` (بيصلّح الـ 50 لينك اللي فيهم `//`).
- Tables: `wp_posts` و`wp_postmeta` بس.
- شغّل كل استبدال **Dry run** الأول، وبعدين نفّذه.

### 5) Search Console
1. URL Inspection للصفحات التلاتة ← **Request indexing**.
2. لو فيه كاش (LiteSpeed أو GTranslate)، امسحه.
3. ماتطلبش إزالة للروابط القديمة. الـ 301 هو اللي بينقل قيمتها.

### 6) ابعتلي «خلصت»
وأنا أفحص الـ 35 redirect والصفحات التلاتة وأطلّع أي مشكلة.

---

## حاجة لازم تتصلح في صفحة الرحلة نفسها

صفحة [4-Night Nile Cruise Luxor to Aswan](https://egypttours-meritamun.com/tour/nile-cruise-sail-along-the-nile-from-luxor-to-aswan-4-nights-5-days/)
مكتوبة 4 ليالي / 5 أيام، لكن البرنامج فيه **4 أيام بس**، و**اليوم التاني مكتوب عليه «Final Departure»**،
و**كوم أمبو مش موجودة**. الصفحة 3 الجديدة فيها البرنامج كامل (5 أيام، وفيه كوم أمبو)، فلازم صفحة
الرحلة تتعدّل عشان الاتنين يقولوا نفس الكلام.

## بعد الدمج: قيس الأثر
بعد 4–6 أسابيع، قارن ترتيب الصفحات التلاتة ونقراتها على استعلامات «5 star nile cruise» و«best nile cruise»
بالأرقام دي (30 يوم قبل الدمج):

| الصفحة | ظهور | نقرات | ترتيب |
|---|---|---|---|
| top-5-star… | 554 | 1 | 82.5 |
| 5-star-nile-cruise-rooms… | 32 | 1 | 64.3 |
| book-a-5-star-aswan-luxor… | 25 | 0 | 60.4 |
