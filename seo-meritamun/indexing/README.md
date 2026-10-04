# الصفحات غير المفهرسة: «Discovered» و«Crawled – currently not indexed»

> المصدر: تصدير Search Console (Pages ← Coverage drilldown)، والبيانات لحد **2026-09-21**، يعني **قبل** تعديلات
> 2026-10-04. كل رابط اتفحص على الموقع المباشر يوم 2026-10-04.
> القايمة الكاملة بالقرار لكل رابط: [`not-indexed-actions.csv`](not-indexed-actions.csv) (171 رابط).

## الصورة باختصار

| التقرير | العدد | الاتجاه |
|---|---|---|
| Crawled – currently not indexed | **34** | زايد (كان 25 في يوليو) |
| Discovered – currently not indexed | **137** | ثابت تقريبًا (كان 132) |

**أخطر حاجة:** من الـ 137 «Discovered»، فيه **67 صفحة رحلات وتصنيفات رحلات** (صفحات البيع نفسها)، زي
رحلة الـ 3 ليالي والـ 4 ليالي والدهبية وأبو سمبل والأهرامات، و**جوجل ماحاولش يزحفها أصلًا** (Last crawled = 1970).
ده مش عيب في الصفحات، ده معناه إن جوجل **مش بيصرف وقت زحف كفاية على الموقع**.

## ليه جوجل مش بيزحف؟

1. **الموقع بطيء ومافيش كاش:** إضافة **SpeedyCache Pro متفعّلة بس مش بتكاشّي** (مافيش أي header أو علامة كاش
   في الصفحات، وكل صفحة بتتبني من الأول في 1–2.5 ثانية، والألماني 7–8 ثواني). والسيرفر رجّع 503 تحت ضغط بسيط.
   جوجل بيقلل الزحف على الموقع البطيء.
2. **روابط كتير مالهاش لازمة بتاكل من الزحف:** كل صفحة ليها نسختين `?currency=eur` و`?currency=gbp`
   (618 رابط)، و`/register/?redirect=…` (92)، وروابط `/feed/`.
3. **كل محتوى متكرر:** 33 مقال ألماني موجودين مرتين (على الجذر كإنجليزي، وعلى `/de/`)، وكل صفحة إنجليزي ليها
   ترجمة أوتوماتيك على `/de/`. يعني موقع صغير بقى بمئات الروابط.

## القرار لكل مجموعة

| المجموعة | العدد | إيه هي | المطلوب |
|---|---|---|---|
| **A** | 28 | روابط بقت بتعمل 301 (منها مقالات الـ 5 نجوم اللي اتدمجت، وروابط تورز قديمة، و`/feed/`) | **ولا حاجة**، جوجل هيشيلها لوحده |
| **B** | 3 | `register/?redirect=` و`/category/uncategorized/` (عليهم noindex أصلًا) | تتقفل في robots.txt (تحت) |
| **C** | 3 | صفحات `personnel` (Dalida Serhan، وAwad Saady بالألماني) | **راجعها:** لو Dalida مش موظفة حقيقية تتمسح |
| **D** | 4 | تورز اتزحفت **واترفضت**: East & West Bank، وإدفو وكوم أمبو، وإسكندرية Spiritual وGraeco-Roman | محتوى أقوى. **التورين بتوع إسكندرية نصهم متشابه بنسبة 69%**، فلازم كل واحد يبقى مختلف بوضوح |
| **E** | 67 | تورز وتصنيفات **ماتزحفتش** | الكاش أولًا، وبعدين Request indexing (تحت) |
| **F** | 31 | نسخ `/de/` للمقالات الألماني | **تفضل**، دي النسخة الصح |
| **G** | 4 | ترجمة أوتوماتيك لصفحات إنجليزي | أولوية منخفضة |
| **H** | 28 | **مقالات ألماني منشورة على الجذر كإنجليزي** (`lang="en-US"`) | canonical للنسخة `/de/` (تحت) |
| **I** | 3 | `/about-our-team/`، و`/private-egypt-tours-egyptologist-…`، و`/10-day-egypt-escape-…` | تحسين محتوى ولينكات داخلية |

## خطة التنفيذ بالترتيب

### 1. فعّل الكاش ← أكبر أثر (عليك، 10 دقايق)
لوحة ووردبريس ← **SpeedyCache** ← Settings:
- فعّل **Cache System**.
- فعّل **Preload** (عشان الصفحات تتجهز قبل ما جوجل يطلبها).
- Save.

ولما تخلص قولّي، وأنا أقيس السرعة وأتأكد إن الكاش شغال.

### 2. اقفل الروابط اللي مالهاش لازمة (عليك، دقيقتين)
Yoast SEO ← Tools ← **File editor** ← robots.txt، وضيف تحت `User-agent: *`:
```
Disallow: /*?currency=
Disallow: /*?redirect=
Disallow: /register/
```

### 3. Request indexing لصفحات البيع (عليك، 10 في اليوم)
Search Console ← URL Inspection ← Request indexing. ابدأ بدول:
1. `/tour/3-nights-4-days-nile-cruise-from-aswan-to-luxor/`
2. `/tour/nile-cruise-sail-along-the-nile-from-luxor-to-aswan-4-nights-5-days/`
3. `/tour/dahabiya-nile-cruise-luxor-to-aswan-5-nights-sailing/`
4. `/tour/day-tour-abu-simbel-by-coach-from-aswan/`
5. `/tour/day-tour-to-giza-pyramids-memphis-and-sakkara/`
6. `/tour/day-tour-to-the-egyptian-museum-giza-pyramids-and-sphinx/`
7. `/tour/philae-temple-sound-light-show-aswan-evening-tour/`
8. `/tour/st-catherines-monastery-day-tour-sinai-sacred-mountain/`
9. `/tour/ras-mohammed-snorkeling-diving-trip-from-sharm-el-sheikh/`
10. `/tour-category/dahabia/`

وفي اليوم اللي بعده كمّل بباقي المجموعة E من الملف.

### 4. المقالات الألماني المتكررة (المجموعة H)
الـ canonical مش متاح من الـ API. فيه طريقتين:
- **يدوي:** في كل مقال ← Yoast ← Advanced ← **Canonical URL** ← حط رابط `/de/` بتاعه. **جرّب على مقال واحد الأول**،
  وافحص النسختين في URL Inspection: لازم نسخة `/de/` تفضل canonical لنفسها. GTranslate بيعيد كتابة الـ canonical
  في نسخة `/de/`، ولازم نتأكد إنه مايبوّظوش.
- **كود صغير** يعمل ده للـ 28 مقال مرة واحدة (بعد التجربة على مقال واحد).

### 5. المحتوى (المجموعات D وI)
- التورين بتوع إسكندرية: كل واحد يتكتب له برنامج ووصف مختلف بوضوح، أو يندمجوا في تور واحد بخيارين.
- مقالات «Private tours» الكتير بالإنجليزي والألماني بتتنافس على نفس الكلمة، زي مقالات الـ 5 نجوم بالظبط.
  تستاهل دمج بنفس الطريقة.
