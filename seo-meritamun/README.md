# مراجعة 404 والأرشفة — egypttours-meritamun.com

> **المصادر:** Google Search Console (ملكية `https://egypttours-meritamun.com/`)، آخر 30 يوم
> (2026-09-04 → 2026-10-04)، + فحص URL Inspection لـ 7 صفحات، + زحف كامل للموقع بتاريخ 2026-10-04
> (543 رابط: كل الـ sitemap الإنجليزي والألماني + كل رابط داخلي ظاهر في الصفحات).
>
> **حدود البيانات:** تقرير «Pages / Not found (404)» في Search Console **مالوش API** من جوجل،
> فقائمة الـ 404 بالظبط اللي جوجل شايفها مش متاحة لي. اللي تحت مبني على الزحف المباشر وعلى
> الـ URLs اللي جوجل بيعرضها في بيانات الأداء.

## الأرقام

| | |
|---|---|
| نقرات 30 يوم | **13** |
| ظهور 30 يوم | **1,159** |
| متوسط الترتيب اليومي | **40–85** (الصفحة 4–9) |
| أكبر صفحة ظهورًا | `top-5-star-nile-cruises-in-egypt…` — 554 ظهور، ترتيب **82.5**، نقرة واحدة |
| أكبر الدول | أمريكا 445 ظهور (ترتيب 76.6) · مصر 271 · ألمانيا 165 (ترتيب 46.1) |

الموقع **مش متشال من الفهرس**: كل الصفحات اللي فحصتها «Submitted and indexed». المشكلة إن جوجل
مفهرس الصفحات لكنه **مش واثق فيها**، فبيرتبها في الصفحة 5–9. الأسباب بالترتيب تحت.

---

## 1) الـ 404 — مصدرها إيه؟

**النتيجة المهمة: الموقع حاليًا مافيهوش ولا رابط داخلي بيودّي لـ 404.**
كل الـ 543 رابط رجّعوا 200 أو 301. ده معناه إن الـ 404 اللي في Search Console **روابط قديمة جوجل
لسه فاكرها**، مش روابط مكسورة جوه الموقع. مصادرها المرجّحة:

| المصدر | الدليل |
|---|---|
| **محتوى الديمو بتاع قالب Traveltour اتمسح** | `/portfolio/`، `/personnel/`، `/hello-world/`، `/sample-page/`، `/cart/`، `/shop/` كلها 404 دلوقتي. صفحة `portfolio_tag/adventure/` جوجل بيقول عليها «**Submitted**» مع إنها مش في الـ sitemap الحالي |
| **sitemaps قديمة لسه متسجلة في Search Console** | `portfolio_tag-sitemap.xml`، `category-sitemap.xml`، `post_tag-sitemap.xml`، `author-sitemap.xml`، `tour_tag-sitemap.xml` كلها 404. لو مسجلة في GSC هتفضل تولّد أخطاء |
| **روابط اتغيرت أسماؤها قبل ما يتعملها redirect** | فيه ~120 redirect يدوي متعمل بالفعل (`/4-day-nile-cruise/` → الرابط الطويل…). غالبًا الـ 404 دي **اتصلحت خلاص**، بس محدش داس «Validate fix» |

### المطلوب
1. **Search Console ← Sitemaps:** امسح أي sitemap غير `sitemap_index.xml`، وضيف
   **`https://egypttours-meritamun.com/de/sitemap_index.xml`** (موجود وشغال، 164 رابط ألماني).
2. **Search Console ← Pages ← Not found (404) ← Export.** ابعتلي الـ CSV وأنا أطلّع لكل رابط:
   يتعمله 301 لأنهي صفحة، ولا يتساب 404 (الديمو المحذوف **يتساب 404 عادي**، مش ضرر).
3. بعد الإصلاح: **Validate fix** في نفس التقرير.

---

## 2) 182 رابط داخلي بيعدّي على redirect (30 منهم سلسلة من خطوتين)

الملف الكامل: [`internal-redirect-links.csv`](internal-redirect-links.csv)، وفيه لكل رابط
الصفحة الصح وأمثلة للصفحات اللي فيها الرابط.

أهم 3:

| الرابط | المشكلة | الإصلاح |
|---|---|---|
| `tour/day-tour-luxor-west-bank/` في الميجا منيو («Popular right now») | سلسلة **خطوتين** → `…east-and-west-bank-2/` → `…east-and-west-bank/`. موجود في **كل صفحة** (184 + 125 بالألماني) | غيّر الرابط في المنيو للنهائي مباشرة: `/tour/day-tour-luxor-east-and-west-bank/` |
| **50 رابط بشرطتين** `https://egypttours-meritamun.com//slug/` | جوه **31 مقال**. كل واحد redirect، وأحيانًا اتنين | Search & Replace في الداتابيز: `egypttours-meritamun.com//` ← `egypttours-meritamun.com/` (باستخدام Better Search Replace، وخد backup الأول) |
| `author/egyptianemam25/` | لينك اسم الكاتب في 94 مقال بيعمل 301 للرئيسية، وجوجل بيعتبر ده **soft 404** | شيل لينك الكاتب من القالب، أو فعّل صفحة الكاتب |

نفس الحكاية مع أرشيف التواريخ (`/2026/09/07/` → الرئيسية): 35 رابط داخلي بيعمل redirect
للرئيسية.

---

## 3) أسباب ضعف الأرشفة والترتيب — بالأولوية

### 🔴 أ. الموقع بطيء جدًا، ومافيش كاش صفحات
- **وقت الاستجابة (TTFB) 2.2–2.6 ثانية** لكل صفحة إنجليزية، و**7–8 ثواني** للصفحات الألمانية
  (`x-gt-cache-status: MISS` من GTranslate).
- السيرفر **LiteSpeed**، لكن مافيش header بتاع `x-litespeed-cache`، يعني **إضافة LiteSpeed Cache
  مش متفعلة**.
- السيرفر رجّع **503** لصفحة ألماني لما الزحف كان 12 طلب في نفس الوقت (الزحف اتعاد بعدها بـ 4).
  Googlebot لما يلاقي بطء أو 5xx **بيقلل معدل الزحف**، وده بالظبط «الأرشفة البطيئة».

**الإصلاح:** نزّل وفعّل **LiteSpeed Cache** (مجانية وبتشتغل native على LiteSpeed)، فعّل
Page Cache + Object Cache لو متاح، وفعّل كاش GTranslate. الهدف TTFB أقل من 600ms.

### 🔴 ب. 19 مقال بيتنافسوا على كلمة واحدة: «5 star nile cruise»
في الـ sitemap **85 مقال، 19 منهم** عن «5 star Nile cruise» بعناوين شبه متطابقة:
`5-star-nile-cruise-ships…`، `5-star-nile-cruise-boats…`، `5-star-nile-cruise-rooms…`،
`5-star-deluxe-nile-cruise-ships…`، `5-star-luxury-nile-cruise…`، `5-star-luxury-nile-cruises…`،
وغيرهم. ده واضح في الأداء: **كل** استعلامات «5 star nile cruise / best nile cruise» ترتيبها
**70–90**، وجوجل بيبدّل بين الصفحات من غير ما يثق في أي واحدة.

وفيه خطر أكبر: معدل النشر (85 مقال في ~4 شهور) والعناوين المتكررة والصور المولّدة
(`Soren_Malena_…_202606071436.webp`) بيطابقوا شكل **«scaled content»** اللي بتستهدفه
تحديثات جوجل الأساسية.

**الإصلاح:** دمج الـ 19 في **3 صفحات قوية** (مثلًا: دليل شامل لبواخر الخمس نجوم / مقارنة
البواخر والكبائن / الحجز والأسعار من أسوان للأقصر) + **301** من الباقي لأقرب واحدة منهم.
ووقّف المقالات الجديدة على نفس الكلمة.

### 🔴 ج. 33 مقال ألماني منشورين على أنهم إنجليزي، فبقى فيه نسختين من كل مقال
مقالات زي `private-nilkreuzfahrt-die-beste-wahl-2026` و`5-sterne-nilkreuzfahrt` منشورة على
الجذر (`/`) بـ `lang="en-US"` و`hreflang="en"`، وهي ألماني. وGTranslate بيعمل منها نسخة تانية
على `/de/` (ألماني → ألماني) **بنفس النص بالظبط**. والنتيجة:
- **محتوى مكرر**: النسختين مفهرسين كل واحدة لوحدها (اتأكدت من الاتنين بالـ URL Inspection).
- **إشارة لغة غلط**: جوجل متقالّه إن النص الألماني «إنجليزي».

**الإصلاح (جرّبه على مقال واحد الأول):** في Yoast لكل مقال من الـ 33، خلّي الـ canonical يشاور
على نسخة `/de/`، وبعدين افحص النسختين في URL Inspection. لو GTranslate بوّظ الـ canonical
في نسخة `/de/`، الحل البديل إن المقالات دي تتكتب بالإنجليزي على الجذر ويسيب GTranslate يترجمها.
القائمة الكاملة في الملحق.

### 🟠 د. صفحات أرشيف فاضية مفهرسة
`portfolio_tag/*` و`portfolio_category/*` و`tour-activity/*` (تصنيفات من القالب) مفهرسة ومافيهاش
محتوى (صفحة `portfolio_tag/adventure/` = عنوان + فوتر بس). ده بينزّل تقييم جودة الموقع كله.
**الإصلاح:** Yoast ← Settings ← Taxonomies: خلّي Portfolio Tag وPortfolio Category وTour
Activity على **noindex**، أو امسح التصنيفات دي لو مش مستخدمة.

### 🟠 هـ. ترقيم صفحات الرحلات بيعمل canonical للصفحة الأولى
`/tours/page/2/` و`/3/` و`/4/` كلهم canonical ← `/tours/`، وكذلك `/de/tours/page/N/`. كده جوجل
بيتجاهل الصفحات دي، والرحلات اللي ظاهرة فيها بس بتتعرف أصعب.
**الإصلاح:** كل صفحة ترقيم يكون الـ canonical بتاعها **لنفسها** (ده الافتراضي في Yoast، والقالب
غالبًا هو اللي بيغيّره).

### 🟡 و. روابط العملة بتضيّع ميزانية الزحف
كل صفحة فيها `?currency=eur` و`?currency=gbp` (لقيت **618** رابط من النوع ده)، و`/register/?redirect=ID`
(**92**). الـ canonical فيها سليم، لكن Googlebot بيزحف 3 نسخ من كل صفحة على سيرفر بطيء أصلًا.
**الإصلاح:** ضيف في `robots.txt` (Yoast ← Tools ← File editor):
```
Disallow: /*?currency=
Disallow: /*?redirect=
```

### 🟡 ز. رابط رحلة مضلِّل
`/tour/cairo-siwa-oasis-tour-8-days-in-egypts-western-desert-2/` عنوانها الحقيقي
«Cairo, Fayoum & White Desert Tour»، يعني رحلة مختلفة متنسوخة من رحلة سيوة ومتغيرش الـ slug.
**الإصلاح:** slug جديد (مثلًا `cairo-fayoum-white-desert-tour-8-days`) + 301 من القديم.

---

## الترتيب المقترح للتنفيذ

| # | المهمة | الوقت | الأثر |
|---|---|---|---|
| 1 | تفعيل LiteSpeed Cache + كاش GTranslate | ساعة | زحف أسرع، أرشفة أسرع |
| 2 | Sitemaps في GSC: امسح القديم، ضيف `/de/sitemap_index.xml` | 5 دقايق | يوقف أخطاء 404 من sitemaps ميتة |
| 3 | Search & Replace لـ `//` + رابط الميجا منيو | 30 دقيقة | يشيل 182 redirect داخلي |
| 4 | noindex للتصنيفات الفاضية + robots.txt للعملة | 15 دقيقة | يقلل الصفحات الضعيفة |
| 5 | Export تقرير 404 من GSC ← خريطة redirects | بعد ما توصلني القائمة | يقفل الـ 404 |
| 6 | canonical للمقالات الألمانية الـ 33 | ساعتين | يشيل التكرار |
| 7 | دمج الـ 19 مقال «5 star» في 3 ← **جاهز:** [`merge-5star/`](merge-5star/README.md) | يومين | **أكبر أثر على الترتيب** |

---

## ملحق: المقالات الألمانية المنشورة على الجذر

```
private-aegypten-touren-aegyptologe-luxor-kairo-assuan
private-aegypten-rundreisen-aegyptologe-luxor-kairo-assuan
5-sterne-nilkreuzfahrt
5-sterne-vs-luxus-nilkreuzfahrt-der-ultimative-vergleich-2026
abu-simbel-nilkreuzfahrt
sicherheit-in-agypten-2026-ein-guide-fur-deutsche-reisende
private-luxor-tour-aegyptologe-fuehrung-fahrer
private-nilkreuzfahrt-die-beste-wahl-2026
schaetze-aegyptens-tour
transport-aegypten-praktischer-reisefuehrer
nilkreuzfahrt-abu-simbel-route-dauer-kabinen-und-leistungen
nilkreuzfahrt-von-kairo-nach-luxor-praktischer-reisefuehrer
die-beste-reisezeit-fur-eine-nilkreuzfahrt-der-ultimative-guide-2026
private-luxor-tour-aegyptologe-eigener-fahrer
private-nilkreuzfahrt-luxor-aegyptologe
agypten-rundreise-privat-7-10-tage-mit-kairo-luxor-assuan-und-nilkreuz
nilkreuzfahrt-guide-2026-der-ultimative-ratgeber
private-nilkreuzfahrt-luxor
luxor-nach-assuan-die-ultimative-4-tage-route
private-aegypten-touren-eigener-guide-fahrer-luxor-kairo-assuan
aegypten-zeitlose-schaetze-tour-planung
belmond-nilkreuzfahrt
ms-nile-excellence-kabinen
ms-nile-crown-1-kabinen
nilkreuzfahrt-vergleich-route-dauer-stil
luxus-nilkreuzfahrt
merit-egypt-tours-reiseplanung
luxus-gold-eleganz-auf-dem-nil
nilkreuzfahrt-2026-so-planen-sie-die-richtige-reise-auf-dem-nil
nilinsel-bei-assuan-kreuzwortraetsel-elephantine
merit-in-der-aegyptischen-mythologie-bedeutung-schreibungen-und-reisebezug
aegypten-westliche-wueste-fayoum-bahariya-weisse-wueste
dahabiya-nilkreuzfahrt-5-7-oder-8-naechte
```

ملاحظة: بعض المقالات دي متكررة في الموضوع (`private-luxor-tour-aegyptologe-fuehrung-fahrer`
و`private-luxor-tour-aegyptologe-eigener-fahrer`، و3 نسخ من `private-nilkreuzfahrt-*`)، وتستاهل
دمج هي كمان.
