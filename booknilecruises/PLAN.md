# خطة تحويل booknilecruises.net من WordPress إلى موقع مبرمج

**الهدف:** نقل الموقع من WordPress (Elementor + WP Travel Engine + WooCommerce) إلى موقع ثابت مبرمج سريع على نفس استضافة Hostinger، بنفس الروابط ونفس التقسيمات، بدون خسارة أي بيانات، وبهوية بصرية مأخوذة من ألوان اللوجو (كحلي + ذهبي + أزرق النيل).

**المعمارية:** موقع ثابت مبني بـ **Astro**: المحتوى كله (الرحلات والصفحات والتصنيفات والصور وبيانات السيو) يُصدَّر من WordPress إلى ملفات JSON داخل المشروع، وAstro يولّد منها صفحات HTML جاهزة تُرفع على Hostinger. هذا نفس أسلوب lesamisdegypte.com: HTML ثابت على سيرفر LiteSpeed، بدون قاعدة بيانات وبدون لوحة تحكم WordPress.

**الأدوات:** Astro 5، Node 22، CSS عادي بمتغيرات (tokens) من ألوان اللوجو، سكربت PHP صغير لنموذج الاستفسار (Hostinger يدعم PHP)، Playwright لاختبار الصفحات، GitHub Actions للبناء والرفع.

---

## ما تم بالفعل في هذه الجلسة (قراءة فقط، لم يُعدَّل أي شيء على الموقع الحي)

| البند | النتيجة |
|---|---|
| تصدير المحتوى عبر REST API | `data/wp-export/` — ‏60 صفحة، 102 رحلة، 99 رحلة بباقات أسعارها، 618 ملف ميديا (روابط + أبعاد + alt)، 8 منتجات، 1 مقال، كل التصنيفات |
| خريطة الروابط | `data/wp-export/sitemap-urls.json` — ‏211 رابط من sitemap الحالي، وهي مرجع اختبار تطابق الروابط |
| السكربت | `scripts/export-wp.mjs` — يُعاد تشغيله قبل الإطلاق مباشرةً لأخذ آخر نسخة من المحتوى |
| الألوان من اللوجو | كحلي `#1a3251` / `#162d4b`، ذهبي `#af9868` → `#e0c98c`، أزرق النيل `#2f5b7f` |
| 3 تصميمات مقترحة | `designs/design-a.html`، `design-b.html`، `design-c.html` (بصور ومحتوى حقيقي من الموقع) |

> **ملاحظة مهمة عن "عدم خسارة البيانات":** الـ API العام لا يُخرج الحجوزات ولا بيانات العملاء ولا الطلبات ولا رسائل النماذج ولا إعدادات الإضافات. هذه موجودة في قاعدة البيانات فقط، ولذلك **المرحلة 0 (نسخة احتياطية كاملة من Hostinger) شرط قبل أي خطوة أخرى**.

---

## اكتشافات تحتاج قرارك قبل التنفيذ

لن أعدّل أيًّا من هذه بصمت. كل بند سيُنقل كما هو إلا إذا وافقت على تغييره:

1. **نصوص قالب وهمية ظاهرة للزوار الآن:**
   - صفحة **FAQ** فيها أسئلة عقارات ("How do I start the process of buying a home?").
   - صفحة **About Us** فيها مرشدين وهميين (Michel Smith, Alex Pulak) وآراء عملاء وهمية عن "Ecoland Residence".
   - الصفحة الرئيسية فيها نص Lorem ("There are many variations of passages…")، و"News & Articles From Tourm"، و"Copyright 2025 Tourm"، وإيميل `mailinfo00@tourm.com`.
   - **الاقتراح:** نكتب لها محتوى حقيقي بدل نقل النص الوهمي.
2. **ويدجت آراء Tripadvisor** يعرض آراء عن شركات أخرى (Tui، Riviera Travel، Travel Evasion) وليس عن Book Nile Cruises. **الاقتراح:** نربطه بصفحة Tripadvisor الخاصة بكم، أو نعرض آراء عملائكم فقط.
3. **رحلات مكررة:** `sonesta-amirat-dahabiya-nile-cruise` و`-2`، و`8-days-cairo-alexandria-nile-cruise-by-flight` و`-2`.
4. **رحلات بدون سعر:** Three Pyramids Dahabiya، Day Trip to Luxor from Cairo by Air، و8 Days Cairo Alexandria (النسخة 2).
5. **رحلات روابطها أرقام:** `/trip/7923/`، `/trip/7919/`، `/trip/7901/`، `/trip/7881/`. **الاقتراح:** نعطيها روابط بأسماء ونعمل تحويل 301 من الرقم.
6. **مدد غريبة:** "4 Days Cairo Tour Package" مسجلة 7 أيام، و"MS Royal Esadora" و"Princess Sarah II" مسجلة يوم واحد.
7. **أسعار الرئيسية لا تطابق الرحلة:** الرئيسية تعرض 8 Days Cairo & Alexandria بسعر $569 بينما الرحلة $1550، وSonesta Amirat تعرض $2600 بينما الرحلة $2500.
8. **صفحات زائدة من القالب** (لن تُنقل، وتُحوَّل 301 للصفحة المناسبة): `sample-page`، `tt`، `check`، `pricing-plan`، 4 نسخ من `my-account`، 3 من `checkout`، 3 من `cart`، `tourm-shop`، `wishlist`، `wishlist-2`، وغيرها.
9. **الإيميل:** المكتوب في الفوتر `info@booknilecruise.com` (بدون s) بينما الدومين `booknilecruises.net`. أيهما الصحيح؟
10. **أخطاء إملائية:** "Hurgahda"، "Sharm El shiekh"، "Wornderful"، "Abu Simble".
11. **إيميلك الشخصي ظاهر في كود الموقع:** اسم الكاتب في بيانات السيو (schema) لكل صفحة هو عنوان Gmail، ورابط الكاتب `/author/egyptianemam25gmail-com/`. في الموقع الجديد نستخدم اسم المؤسسة بدلًا منه. (ملفات التصدير في `data/wp-export/` نسخة طبق الأصل، لذلك تحتوي عليه أيضًا.)

## أسئلة تحدد التنفيذ

- **الحجز والدفع:** الموقع الحالي عليه سلة ودفع (WP Travel Engine + WooCommerce). الموقع المبرمج الثابت لا يحتوي سلة. البدائل:
  (أ) نموذج استفسار + واتساب فقط، مثل lesamisdegypte.com (الأبسط والأسرع، وهو اقتراحي)،
  (ب) زر "ادفع الآن" يفتح رابط دفع جاهز (Stripe/PayPal Payment Link) لكل رحلة،
  (ج) سلة ودفع كامل، وهذا يحتاج باك-إند ووقت أطول بكثير.
- **التصميم:** أي من الثلاثة (A / B / C)، أو خليط منها.
- **اللغات:** الموقع الآن إنجليزي فقط. هل نجهّز البنية لإضافة لغات لاحقًا (مثلًا `/es/` كما في خطة السوق الإسباني)؟

---

## عن Codex CLI وAntigravity (agy) والتوزيع

مهارات `codex-delegate` و`agy-delegate` تحتاج أن يكون `codex` و`agy` مثبتين ومسجّل الدخول عليهما على الجهاز. هذه الجلسة تعمل في حاوية سحابية ليس فيها أيٌّ منهما (تم التحقق: `which codex agy` لا يعطي نتيجة)، ولا يوجد مفتاح OpenAI أو حساب Google لتسجيل الدخول.

**الحل المقترح:** الخطة مقسّمة لمهام مستقلة صغيرة، كل مهمة لها ملفات محددة واختبار واضح. بعد اعتمادك:
- إما أنفّذها أنا هنا مباشرةً،
- أو تفتح الجلسة على جهازك (Claude Code desktop/CLI) حيث `codex` و`agy` مثبتين، وأوزّع: **Codex** لمهام القوالب والمكوّنات (المهام 3.x)، و**agy** لمهام البيانات والسكربتات (المهام 2.x)، وأنا أراجع كل diff وأشغّل الاختبارات قبل الدمج.

**بوابة المراجعة (قبل أي تعديل على الموقع الحي):** كل مهمة لا تُدمج إلا بعد: (1) نجاح `npm test`، (2) نجاح اختبار تطابق الروابط، (3) مراجعة الكود، (4) معاينتك على رابط staging.

---

## هيكل الملفات

```
booknilecruises/
├── PLAN.md                     هذه الخطة
├── scripts/
│   ├── export-wp.mjs           تصدير المحتوى من WordPress (تم)
│   ├── normalize.mjs           تحويل التصدير الخام إلى بيانات نظيفة للموقع
│   └── download-media.mjs      تنزيل كل الصور بنفس مساراتها
├── data/
│   ├── wp-export/              التصدير الخام كما هو (لا يُعدَّل يدويًا أبدًا)
│   └── site/                   البيانات النظيفة: trips.json, pages.json, taxonomies.json, redirects.json
├── designs/                    التصميمات الثلاثة المقترحة
├── site/                       مشروع Astro
│   ├── astro.config.mjs
│   ├── src/styles/tokens.css   ألوان وخطوط اللوجو
│   ├── src/layouts/Base.astro  الهيدر + الفوتر + بيانات السيو
│   ├── src/components/         TripCard, SearchBar, Breadcrumbs, Itinerary, Faq, EnquiryForm, WhatsAppButton
│   ├── src/pages/              index, [page].astro, trip/[slug].astro, destination/[slug].astro, activities/…, trip-types/…
│   ├── public/wp-content/uploads/  الصور بنفس المسار القديم
│   ├── public/.htaccess        تحويلات 301 + كاش + ضغط
│   ├── public/enquiry.php      استقبال نموذج الاستفسار وإرساله بالإيميل
│   └── tests/                  url-parity.test.mjs, seo-parity.test.mjs, e2e/*.spec.ts
└── .github/workflows/deploy.yml
```

---

## المرحلة 0: الحماية والنسخ الاحتياطي (قبل أي شيء)

### المهمة 0.1: نسخة احتياطية كاملة من Hostinger
- [ ] من hPanel ← Websites ← booknilecruises.net ← Files ← Backups: إنشاء backup جديد للملفات وقاعدة البيانات، وتنزيله على جهازك وعلى Google Drive.
- [ ] تصدير قاعدة البيانات SQL منفصلة من phpMyAdmin (Export ← Quick ← SQL).
- [ ] من لوحة WordPress: Tools ← Export ← All content (ملف XML)، وتصدير حجوزات WP Travel Engine وطلبات WooCommerce كـ CSV.
- [ ] التحقق: فتح ملف SQL والتأكد من وجود جداول `wp_posts` و`wp_postmeta` و`wp_wte_*` (إن وجدت) و`wp_wc_orders`.

### المهمة 0.2: تجميد المحتوى
- [ ] الاتفاق على تاريخ تجميد: بعده لا تُضاف رحلات على WordPress، وأي إضافة تتم في المشروع الجديد.
- [ ] إعادة تشغيل `node scripts/export-wp.mjs` يوم التجميد وعمل commit بالنتيجة.

---

## المرحلة 1: تأكيد التصميم (هذه الخطوة الآن)
- [ ] تختار A أو B أو C أو خليط.
- [ ] تجاوب على الأسئلة العشرة في "اكتشافات تحتاج قرارك" وأسئلة الحجز واللغة.
- [ ] بعد الاختيار أكتب خطة تنفيذ تفصيلية للمرحلة 3 (القوالب) بالكود الكامل لكل مكوّن حسب التصميم المختار.

---

## المرحلة 2: البيانات

### المهمة 2.1: سكربت التنظيف `scripts/normalize.mjs`
**الملفات:** إنشاء `scripts/normalize.mjs`، `scripts/normalize.test.mjs`

يقرأ `data/wp-export/*.json` ويكتب `data/site/*.json` بالشكل التالي لكل رحلة:

```js
{
  id: 7519, slug: 'blue-shadow-nile-cruise', url: '/trip/blue-shadow-nile-cruise/',
  title: 'Blue Shadow Nile Cruise',
  price: 490, salePrice: null, currency: 'USD',
  duration: { days: 4, nights: 3 },
  destinations: ['aswan', 'luxor'], activities: ['deluxe-nile-cruises'], tripTypes: ['nile-cruise'],
  descriptionHtml: '…', itinerary: [{ title, html }], includes: ['…'], excludes: ['…'], faqs: [{ q, a }],
  image: { src: '/wp-content/uploads/2025/12/Blue-Shadow-Nile-Cruise9.jpg', width: 903, height: 603, alt: '…' },
  gallery: [/* نفس الشكل */],
  packages: [/* من trip-packages.json */],
  seo: { title, description, canonical, ogImage, breadcrumbs }
}
```

- [ ] **الخطوة 1: اختبار فاشل**
```js
// scripts/normalize.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeTrip } from './normalize.mjs';
import trips from '../data/wp-export/trips.json' with { type: 'json' };

test('every exported trip survives normalization with its URL, price and itinerary', () => {
  for (const raw of trips) {
    const t = normalizeTrip(raw, { media: new Map(), terms: new Map(), packages: {} });
    assert.equal(t.url, new URL(raw.link).pathname);
    assert.equal(t.itinerary.length, raw.itineraries.length);
    assert.equal(t.price, raw.price === '' ? null : Number(raw.price));
  }
});
```
- [ ] **الخطوة 2:** `node --test scripts/` ← يفشل بـ `normalizeTrip is not a function`.
- [ ] **الخطوة 3:** كتابة `normalizeTrip` و`normalizePage` و`normalizeTerm` و`main()`.
- [ ] **الخطوة 4:** `node --test scripts/` ← ينجح.
- [ ] **الخطوة 5:** commit.

### المهمة 2.2: تنزيل الصور `scripts/download-media.mjs`
- [ ] يقرأ `media.json` ويُنزّل كل ملف أصلي + المقاسات المستخدمة إلى `site/public/wp-content/uploads/` **بنفس المسار** (حتى لا تنكسر روابط جوجل للصور ولا الروابط الخارجية).
- [ ] اختبار: عدد الملفات المنزّلة = عدد عناصر `media.json`، وكل ملف حجمه > 0.
- [ ] يُنشئ نسخة WebP بجانب كل صورة لاستخدامها في `<picture>`.

### المهمة 2.3: خريطة التحويلات `data/site/redirects.json`
- [ ] كل رابط في `sitemap-urls.json` إما له صفحة في الموقع الجديد أو له تحويل 301 هنا. لا يوجد رابط ثالث.
- [ ] الصفحات الزائدة (بند 8) تُحوَّل للأقرب: `my-account*`، `checkout*`، `cart*` ← `/contact-us/`، و`sample-page`، `tt`، `check` ← `/`.

---

## المرحلة 3: الموقع (Astro)
تُكتب تفاصيلها بالكود الكامل بعد اختيار التصميم. المهام:

| المهمة | المحتوى | المنفذ المقترح |
|---|---|---|
| 3.1 | إنشاء مشروع Astro + `tokens.css` من ألوان اللوجو + الخطوط | أنا |
| 3.2 | `Base.astro`: الهيدر بنفس قائمة الموقع الحالي، الفوتر، meta/OG/canonical/JSON-LD من بيانات AIOSEO | Codex |
| 3.3 | الصفحة الرئيسية بنفس أقسامها الحالية (Hero، بحث، الوجهات، الباقات الأشهر، التجارب، الآراء، المقالات، النشرة) | Codex |
| 3.4 | صفحة الرحلة `/trip/[slug]/`: الصور، المدة، السعر، البرنامج يومًا بيوم، يشمل/لا يشمل، FAQ، نموذج استفسار، JSON-LD `TouristTrip` | Codex |
| 3.5 | صفحات الأقسام بنفس روابطها: `/nile-cruise/`، `/standard-5-star-nile-cruises/`، `/deluxe-nile-cruises/`، `/luxury-nile-cruises/`، `/luxury-dahabiya-nile-cruise-packages/`، `/lake-nasser-nile-cruises/`، `/day-tours/`، `/luxor-day-tours/`، `/cairo-day-tours/`، `/hurghada-day-tours/`، `/egypt-tour-packages/`، `/transfers/` | agy |
| 3.6 | صفحات التصنيفات: `/destination/…`، `/activities/…`، `/trip-types/…` | agy |
| 3.7 | الصفحات الثابتة: About، Contact، FAQ، Terms، Travellers Information، المدونة والمقال | أنا |
| 3.8 | نموذج الاستفسار + `enquiry.php` + زر واتساب | أنا |
| 3.9 | `sitemap.xml` + `robots.txt` + `.htaccess` (301 + كاش + gzip) | agy |

---

## المرحلة 4: الاختبارات (بوابة المراجعة)

### اختبار تطابق الروابط (الأهم للسيو)
```js
// site/tests/url-parity.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import urls from '../../data/wp-export/sitemap-urls.json' with { type: 'json' };
import redirects from '../../data/site/redirects.json' with { type: 'json' };

test('every old URL is either built or redirected', () => {
  const missing = [];
  for (const { url } of urls) {
    const path = new URL(url).pathname;
    const built = existsSync(`dist${path}index.html`) || existsSync(`dist${path}`);
    if (!built && !redirects[path]) missing.push(path);
  }
  assert.deepEqual(missing, []);
});
```

- [ ] **اختبار السيو:** لكل رحلة وصفحة، `<title>` و`meta description` و`canonical` تطابق `aioseo_head_json` القديم.
- [ ] **Playwright:** فتح كل صفحة بعرض موبايل وديسكتوب: لا أخطاء console، لا scroll أفقي، كل الصور تُحمَّل.
- [ ] **فحص الروابط الداخلية:** لا يوجد رابط داخلي يرجع 404.
- [ ] **Lighthouse:** أداء ≥ 90 على الموبايل للرئيسية وصفحة رحلة.
- [ ] **مقارنة المحتوى:** سكربت يقارن عدد الرحلات والصفحات والصور بين التصدير والموقع المبني (102 / 60 ناقص الزائد / 618).

---

## المرحلة 5: النشر بدون توقف
- [ ] رفع الموقع على subdomain تجريبي (مثلًا `new.booknilecruises.net`) مع `noindex`.
- [ ] مراجعتك الكاملة على الموبايل والكمبيوتر.
- [ ] يوم الإطلاق: آخر تصدير ← بناء ← نقل ملفات WordPress إلى مجلد `_wp_backup/` محمي بكلمة سر (لا تُحذف) ← رفع الموقع الجديد في `public_html` ← تفعيل `.htaccess`.
- [ ] Google Search Console: إرسال sitemap الجديد، ومراقبة أخطاء 404 والتغطية يوميًا أسبوعين.
- [ ] الإبقاء على نسخة WordPress وقاعدة بياناتها 3 أشهر على الأقل قبل أي حذف.

## خطة الرجوع
لو ظهرت مشكلة بعد الإطلاق: إرجاع محتوى `_wp_backup/` إلى `public_html` يعيد WordPress كما كان خلال دقائق، لأن قاعدة البيانات لم تُلمس.
