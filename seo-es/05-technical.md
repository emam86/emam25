# 5. الجانب التقني

---

## 5.1 hreflang — الأولوية القصوى تقنيًا

موقعك يخدم `/en/` و`/es/` لكن الظهور موزّع على 12+ دولة ناطقة بالإسبانية.
بدون hreflang صحيح، جوجل يعرض النسخة الخاطئة ويُهدر الـ CTR.

**في `<head>` كل صفحة، على كلا النسختين:**

```html
<link rel="alternate" hreflang="en" href="https://www.luxorandaswantours.net/en/{slug}" />
<link rel="alternate" hreflang="es" href="https://www.luxorandaswantours.net/es/{slug-es}" />
<link rel="alternate" hreflang="x-default" href="https://www.luxorandaswantours.net/en/{slug}" />
```

**عند إطلاق صفحات «desde [بلد]» أضف الاستهداف الإقليمي:**

```html
<link rel="alternate" hreflang="es-ES" href=".../es/viajes-a-egipto-desde-espana" />
<link rel="alternate" hreflang="es-MX" href=".../es/viajes-a-egipto-desde-mexico" />
<link rel="alternate" hreflang="es-AR" href=".../es/viajes-a-egipto-desde-argentina" />
<link rel="alternate" hreflang="es-CO" href=".../es/viajes-a-egipto-desde-colombia" />
<link rel="alternate" hreflang="es"    href=".../es/paquetes-de-viaje-a-egipto" />
```

**القواعد الثلاث التي يخطئ فيها الجميع:**
1. **متبادلة**: إن أشارت EN إلى ES، يجب أن تشير ES إلى EN. وإلا يتجاهلها جوجل كليًا.
2. **مطلقة**: روابط كاملة بالـ `https://www.` — لا روابط نسبية.
3. **تُشير لنفسها**: كل صفحة تتضمن `hreflang` لنفسها ضمن القائمة.

---

## 5.2 توحيد النطاق (مشكلة مؤكدة من Search Console)

ظهر في البيانات نطاقان:
- `https://www.luxorandaswantours.net/...` (الغالبية)
- `https://luxorandaswantours.net/en/destinations` ← **بدون www**

هذا يُشتِّت الإشارات. الحل:
- 301 دائم من `luxorandaswantours.net` → `www.luxorandaswantours.net`
- `<link rel="canonical">` على كل صفحة بنسخة `www` فقط
- تحديث خريطة الموقع

---

## 5.3 البيانات المنظمة (Schema)

### صفحات البواخر والجولات — `Product` + `Offer`
هذا ما يُظهر **السعر والنجوم** في نتائج البحث ويضاعف الـ CTR:

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Crucero por el Nilo MS Amwaj — Luxor a Asuán",
  "image": ["https://www.luxorandaswantours.net/img/ms-amwaj-1.jpg"],
  "description": "Crucero de 5 días por el Nilo a bordo del MS Amwaj 5★...",
  "brand": { "@type": "Brand", "name": "Luxor and Aswan Tours" },
  "offers": {
    "@type": "Offer",
    "price": "850",
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock",
    "url": "https://www.luxorandaswantours.net/es/tour/ms-amwaj-crucero-por-el-nilo",
    "priceValidUntil": "2027-12-31"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "127"
  },
  "inLanguage": "es"
}
```

> ⚠️ `aggregateRating` يجب أن يعكس مراجعات حقيقية ظاهرة على الصفحة.
> البيانات غير الحقيقية تُعرِّض الموقع لعقوبة يدوية من جوجل.

### صفحات الفئات — `ItemList`
### المقالات — `Article` + `BreadcrumbList` + `FAQPage`
### الموقع كاملًا — `TravelAgency` مع `areaServed` و`availableLanguage: ["es","en","ar"]`

```json
{
  "@type": "TravelAgency",
  "name": "Luxor and Aswan Tours",
  "availableLanguage": ["es", "en", "ar"],
  "areaServed": ["EG"],
  "address": { "@type": "PostalAddress", "addressLocality": "Luxor", "addressCountry": "EG" },
  "sameAs": ["https://www.tripadvisor.com/...", "https://www.facebook.com/...", "https://www.instagram.com/..."]
}
```

`sameAs` مهمة جدًا — تربط ملفاتك الخارجية بالموقع وتدعم الكيان (Entity) في نظر جوجل.

---

## 5.4 خريطة الموقع

اجعل خريطة منفصلة للإسبانية مع إشارات hreflang داخلها:

```
/sitemap.xml            (index)
  ├── /sitemap-en.xml
  ├── /sitemap-es.xml   ← جديدة
  └── /sitemap-images.xml
```

في `/sitemap-es.xml` استخدم `xhtml:link` لكل URL للإشارة للنسخة الإنجليزية.

---

## 5.5 تحسين CTR — مكسب فوري بلا تغيير في الترتيب

الـ CTR الحالي **1.15%** في إسبانيا عند ترتيب 18.9. حتى في الصفحة الثانية،
هذا منخفض. عناوين ووصف أفضل يرفعه فورًا.

| قبل (نمط ضعيف) | بعد (نمط المنافسين الرابح) |
|---|---|
| `MS Zeina Nile Cruise` | `Crucero Nilo MS Zeina 5★ \| Luxor–Asuán 2026 · desde 850 €` |
| `Abu Simbel from Aswan` | `Abu Simbel desde Asuán: coche o avión · Precios y horarios 2026` |
| `Egypt Tour Packages` | `Paquetes a Egipto 2026/2027 \| Vuelos + Nilo + guía en español` |

**العناصر الخمسة في كل Title إسباني:** الكلمة المفتاحية · النجوم/المدة · المسار ·
السنة `2026/2027` · السعر `desde X €`

---

## 5.6 قائمة فحص تقنية سريعة

- [ ] hreflang متبادل على كل زوج EN/ES
- [ ] 301 من non-www إلى www + canonical موحَّد
- [ ] `<html lang="es">` على صفحات `/es/` (وليس `lang="en"`)
- [ ] خريطة موقع منفصلة `/sitemap-es.xml` مُرسَلة لـ Search Console
- [ ] Product + Offer schema على كل صفحة باخرة/جولة إسبانية
- [ ] FAQPage schema على صفحات الفئات والمقالات
- [ ] TravelAgency schema مع `sameAs` و`availableLanguage`
- [ ] Core Web Vitals: صور WebP، lazy-load، LCP < 2.5 ثانية
- [ ] كل مقال إسباني يحتوي 2–3 روابط داخلية لصفحات البيع
- [ ] لا صفحة `/es/` تحمل canonical يشير للنسخة `/en/` (خطأ شائع وقاتل)
