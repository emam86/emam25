# 7. تدقيق الصفحات الإسبانية — تصحيح للتشخيص الأول

> ⚠️ **هذا الملف يصحّح خطأً في الملفات 01 و03 و04.**
> التشخيص الأول بُني على قائمة صفحات مقطوعة عند ١٠٠ صف، فاستنتجت أن الصفحات
> الإسبانية للبواخر **غير موجودة**. استعلام «كلمة ← الصفحة الظاهرة» أثبت أنها
> **موجودة فعلًا** — والمشكلة مختلفة تمامًا، وحلّها أرخص وأسرع.

## 7.1 الإجابة المباشرة

**لا، مش كل صفحة إنجليزية ليها مقابل إسباني — لكن الأغلبية ليها.**
والمشكلة الحقيقية مش النقص، المشكلة إن الصفحات الإسبانية الموجودة **مسمّاة بأسماء
لا يبحث بها أحد**.

## 7.2 الصفحات الإسبانية المؤكَّد وجودها

مصدر التأكيد: Search Console، بُعد `query + page` — أي أن جوجل أظهر هذه الصفحة
بالفعل لهذا الاستعلام، فوجودها وفهرستها مؤكدان.

### بواخر النيل — تطابق الاسم جيد ✅ (الترتيب 5–12)

| الصفحة | الترتيب |
|---|---:|
| `/es/tour/assouan-crucero-dahabiya-por-el-nilo` | **5.0** |
| `/es/tour/al-hambra-crucero-por-el-nilo` | **6.0** |
| `/es/tour/royal-viking-crucero-por-el-nilo` | **8.5** |
| `/es/tour/ms-zeina-crucero-por-el-nilo` | **9.4** |
| `/es/tour/moon-dance-crucero-por-el-nilo` | **11.9** |

**متوسط: 8.2**

### بواخر النيل — تطابق الاسم سيئ ❌ (الترتيب 19–85)

| الصفحة | الترتيب | الكلمة التي يبحث بها الناس | التشخيص |
|---|---:|---|---|
| `/es/tour/ms-amwaj-living-stone-crucero-por-el-nilo` | 24.0 | `crucero ms amwaj` | كلمتان زائدتان في الاسم |
| `/es/tour/royal-ruby-crucero-por-el-nilo` | 19.6 | `royal ruby crucero nilo` | قريب — يحتاج تحسين عنوان فقط |
| `/es/tour/steigenberger-minerva-crucero-por-el-nilo` | 32.8 | `crucero minerva` | `steigenberger` تسبق الاسم المطلوب |
| `/es/tour/iberotel-crown-empress-crucero-por-el-nilo` | 34.0 | `buque empress camarotes` | الاسم الشائع مدفون |
| `/es/tour/sonesta-st-george-i-crucero-por-el-nilo` | 37.0 | `crucero sonesta st. george` | الـ `i` الزائدة تُضعف التطابق |
| `/es/tour/le-fayan-crucero-por-el-nilo` | 37.3 | `crucero por el nilo le fayan ii` | الـ `ii` **ناقصة** |
| `/es/tour/blue-shadow-crucero-por-el-nilo` | 40.3 | `blue shadow crucero nilo` · `blue shadow ii` · `iii` | صفحة واحدة لثلاث بواخر |
| `/es/tour/princess-sarah-crucero-por-el-nilo` | 40.5 | `cadena sarah crucero nilo` | `princess` تسبق `sarah` |
| `/es/tour/radamis-ii-crucero-por-el-nilo` | 49.0 | `crucero ms radamis ii` | ينقصه `ms` |
| `/es/tour/acamar-crucero-por-el-nilo` | 50.0 | `acamar nile cruise` | النسخة EN ترتيبها **10.0** لنفس الكلمة |
| `/es/tour/ms-medea-crucero-por-el-nilo` | 51.0 | — | تنافس داخلي (انظر 7.3) |
| `/es/tour/ms-nile-goddess-crucero-por-el-nilo` | 53.6 | — | تنافس داخلي |
| `/es/tour/nour-el-nil-crucero-dahabiya-por-el-nilo` | 56.0 | `dahabiya crucero nilo` | — |
| `/es/tour/ms-esplanade-crucero-por-el-nilo` | 63.0 | — | تنافس داخلي |
| `/es/tour/ms-radamis-i-crucero-por-el-nilo` | 70.0 | `crucero ms radamis ii` | **الباخرة الغلط تمامًا** |
| `/es/tour/m-s-nile-dolphin-crucero-por-el-nilo` | 81.0 | — | تنافس داخلي + `m-s-` |
| `/es/tour/m-s-nile-style-crucero-por-el-nilo` | 84.5 | `ms nile style` | صيغة `m-s-` تكسر التطابق |
| `/es/tour/excursion-de-un-dia-a-abu-simbel-desde-asuan-en-autocar` | 63.0 | `bus aswan abu simbel` | رابط طويل جدًا |

**متوسط: 49.5**

> **الفارق الحقيقي: ٤١ مركزًا — بين صفحات إسبانية موجودة كلها.**
> الفرق الوحيد بينها هو **هل اسم الصفحة يطابق ما يكتبه الباحث أم لا.**

### صفحات الباقات الإسبانية — موجودة أيضًا

`/es/paquetes-egipto/11-dias` (62.5) · `/es/paquetes-egipto/4-dias` (56.0)
البنية موجودة — الترتيب هو الضعيف.

### مقالات إسبانية مؤكَّدة

`cuantos-dias-necesitas-en-egipto-una-guia-realista` · `abu-simbel-desde-asuan-por-carretera-o-en-avion` ·
`esfinge-egipto` · `luxor-ciudad-egipto` · `templo-de-karnak-vs-templo-de-luxor-cual-ver-primero` ·
`valle-de-los-reyes-vs-valle-de-las-reinas-cual-es-la-dife` · `gran-piramide-de-giza-guia-completa` ·
`great-pyramids-of-giza` ⚠️ · `itinerario-crucero-nilo-luxor-asuan` ·
`cruceros-de-5-estrellas-de-luxor-a-asuan-que-significa-re` · `tour-por-la-orilla-este-y-oeste-de-luxor-guia-completa`

---

## 7.3 تنافس داخلي مؤكَّد (Cannibalization) — أخطر من النقص

عندما تتنافس صفحتان من موقعك على نفس الكلمة، جوجل يُضعف الاثنتين.

| الكلمة | الصفحات المتنافسة | الترتيب |
|---|---|---:|
| `crucero ms dwa` | **٥ صفحات دفعة واحدة:** `m-s-nile-dolphin` · `m-s-nile-style` · `ms-esplanade` · `ms-medea` · `ms-nile-goddess` | 51–85 |
| `cadena sarah crucero nilo` | `princess-sarah` + `princess-sarah-ii` | 40.5 / 67 |
| `crucero ms radamis ii` | `ms-radamis-i` + `radamis-ii` | 70 / 49 |
| `gran piramide de giza` | `/es/blog/great-pyramids-of-giza` + `/es/blog/gran-piramide-de-giza-guia-completa` | 33.5 / 27 |
| `crucero sarah nilo` | `princess-sarah` + `princess-sarah-ii` | 43 / 57 |

> **`crucero ms dwa`:** خمس صفحات مختلفة تظهر لنفس الكلمة، ولا واحدة منها اسمها «MS Dwa».
> إما أن الباخرة غير موجودة على الموقع، أو مُسجَّلة باسم آخر. جوجل يخمّن — والنتيجة ترتيب 51–85.
> **تحقّق أولًا: هل تبيع هذه الباخرة فعلًا؟** لو نعم، أنشئ لها صفحة باسمها الصحيح.
> لو لا، لا تفعل شيئًا — الاستعلام ليس لك.

---

## 7.4 خلل hreflang مؤكَّد — الدليل القاطع

| الاستعلام (إسباني) | الصفحة الظاهرة | الترتيب |
|---|---|---:|
| `luxor ciudad` | `/en/blog/luxor-city-egypt` 🇬🇧 | **9.0** |
| `luxor ciudad` | `/es/blog/luxor-ciudad-egipto` 🇪🇸 | 27.0 |

**صفحتك الإنجليزية تتفوق على صفحتك الإسبانية في استعلام إسباني.**
هذا بالضبط ما يحدث عندما يكون hreflang ناقصًا أو غير متبادل — راجع
[`05-technical.md`](05-technical.md) §5.1. نفس النمط في `acamar nile cruise`
(EN = 10.0 · ES = 50.0).

---

## 7.5 خطة العمل المصححة

### ❌ ما لا يجب فعله
**لا تبنِ ٢٠ صفحة باخرة جديدة.** أغلبها موجود بالفعل — وبناء نسخة ثانية
يضاعف التنافس الداخلي ويجعل الوضع أسوأ.

### ✅ ما يجب فعله — بالترتيب

**الخطوة ١ — جرد كامل (قبل أي تعديل)**
استخرج `/sitemap.xml` وقسّمه: كل `/en/tour/...` مقابل `/es/tour/...`.
هذا هو الجرد الوحيد الكامل — بيانات Search Console تُظهر الصفحات التي حصلت على
ظهور فقط، والخطة المجانية تقطع عند ١٠٠ صف.

**الخطوة ٢ — إصلاح العناوين (أرخص وأسرع مكسب)**
لا تغيّر الروابط في البداية — غيّر `<title>` و`<h1>` فقط، وضع **الاسم الشائع أولًا**
والاسم التجاري الكامل بعده:

| الصفحة | العنوان الحالي (المُستنتَج) | العنوان المقترح |
|---|---|---|
| `ms-amwaj-living-stone` | MS Amwaj Living Stone… | `Crucero por el Nilo MS Amwaj (Living Stone) 5★ \| Luxor–Asuán` |
| `steigenberger-minerva` | Steigenberger Minerva… | `Crucero Minerva por el Nilo (Steigenberger) 5★ \| Luxor–Asuán` |
| `le-fayan` | Le Fayan… | `Crucero por el Nilo Le Fayan II \| Luxor–Asuán 7 noches` |
| `sonesta-st-george-i` | Sonesta St George I… | `Crucero Sonesta St. George por el Nilo \| Luxor–Asuán` |
| `princess-sarah` | Princess Sarah… | `Crucero Sarah por el Nilo (Princess Sarah) \| Luxor–Asuán` |
| `m-s-nile-style` | M/S Nile Style… | `Crucero por el Nilo MS Nile Style 5★ \| Luxor–Asuán` |
| `radamis-ii` | Radamis II… | `Crucero por el Nilo MS Radamis II \| Luxor–Asuán` |

**الخطوة ٣ — حلّ التنافس الداخلي**
- `princess-sarah` + `princess-sarah-ii`: أبقِ الاثنتين لو الباخرتان مختلفتان فعلًا،
  لكن ميّزهما بوضوح في العنوان (`Sarah I` / `Sarah II`) واربط كلًا منهما بالأخرى.
  لو نفس الباخرة → 301 من الثانية للأولى.
- `ms-radamis-i` + `radamis-ii`: وحّد التسمية (`ms-radamis-i` و `ms-radamis-ii`).
- `great-pyramids-of-giza` + `gran-piramide-de-giza-guia-completa`:
  **301 من الإنجليزية للإسبانية** — لا معنى لرابط إنجليزي تحت `/es/`.

**الخطوة ٤ — توحيد صيغة الروابط**
`m-s-` → `ms-` في كل مكان، مع 301 من القديم للجديد.
غيّر الروابط **بعد** إصلاح العناوين وقياس الأثر، مش قبله.

**الخطوة ٥ — hreflang**
الخطوة دي وحدها ممكن تحل نصف المشكلة. راجع [`05-technical.md`](05-technical.md) §5.1.

**الخطوة ٦ — بعد الجرد فقط**
أنشئ صفحات ES للبواخر اللي فعلًا ملهاش مقابل إسباني.
من البيانات المتاحة، المرشحون: `alyssa` · `concerto` · `farida` · `esmeralda` ·
`abundance-dahabiya` · `jasmine-dahabiya` · `al-kahila` · `amoura-dahabiya` ·
`sanctuary-sun-boat-iv` · `iberotel-crown-emperor` — **لكن تأكد من الخريطة أولًا.**

---

## 7.6 لماذا لا أستطيع إعطاء الجرد الكامل من هنا

| الطريق | النتيجة |
|---|---|
| قراءة `/sitemap.xml` من الموقع | ❌ سياسة الشبكة في هذه الجلسة تحجب الاتصال بالنطاق (403) |
| Search Console — بُعد `page` | ⚠️ الخطة المجانية: ١٠٠ صف فقط، والقائمة تنقطع عند حرف `j` |
| Search Console — بُعد `query+page` | ✅ المصدر المستخدم هنا — لكنه يُظهر الصفحات التي حصلت على ظهور فقط |
| Ahrefs Site Audit | ❌ `Insufficient plan` |

### ⚠️ «الخريطة موجودة في Search Console» لا تكفي — وهذا سبب تقني لا حاجز صلاحيات

Search Console **لا يُرجع أبدًا قائمة الروابط الموجودة داخل خريطة الموقع** — لا في
الواجهة ولا في الـ API، ولا حتى في الخطط المدفوعة. كل ما يُرجعه تقرير Sitemaps هو:
مسار ملف الخريطة، تاريخ آخر قراءة، وعدّاد «مُرسَل / مُفهرَس». العدّاد رقم، لا قائمة.

بالإضافة إلى أن أدوات الـ Sitemaps في هذه الجلسة تتطلب خطة STARTER أصلًا
(الخطة الحالية FREE)، و SEO Gets يتطلب اشتراكًا.

### ثلاث طرق لإيصال البيانات — مرتّبة بالأسهل

| # | الطريقة | الوقت |
|---|---|---|
| 1 | افتح `https://www.luxorandaswantours.net/sitemap.xml` في المتصفح → `Ctrl+A` → `Ctrl+C` → الصقها في المحادثة (أو ارفع الملف) | ٣٠ ثانية |
| 2 | Search Console → **الفهرسة → الصفحات** → «عرض بيانات الصفحات المُفهرسة» → **تصدير CSV** → ارفع الملف | دقيقتان |
| 3 | Screaming Frog (مجاني حتى ٥٠٠ رابط — قد لا يكفي) → زحف → `Export → Internal → HTML` | ١٠ دقائق |

> لو الخريطة فهرس (`sitemapindex`) يكفي الجزء الخاص بالجولات:
> `sitemap-tours.xml` أو ما يعادله. ولو كبيرة جدًا، `/en/tour/` و`/es/tour/` وحدهما يكفيان للبدء.

### وعندها؟ أمر واحد

جهّزت السكربت في [`tools/es-gap.py`](tools/es-gap.py) — يقرأ الخريطة ويُخرج مباشرةً:
كل صفحة EN بلا مقابل ES، وكل صفحة ES يتيمة، ومشاكل التسمية (`m-s-` مقابل `ms-`)،
وأزواج التنافس الداخلي بالأرقام الرومانية.

```bash
python3 seo-es/tools/es-gap.py sitemap.xml
# أو مباشرة من الموقع، من جهازك:
curl -s https://www.luxorandaswantours.net/sitemap.xml | python3 seo-es/tools/es-gap.py -
```

تقدر تشغّله بنفسك دلوقتي وتبعتلي الناتج — أو ابعت الخريطة وأنا أشغّله.
