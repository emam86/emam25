# 3. خريطة الكلمات المفتاحية الإسبانية

> ⛔ **تصحيح مهم — اقرأ [`07-es-page-audit.md`](07-es-page-audit.md) أولًا.**
> هذا الملف كُتب قبل استعلام «كلمة ← الصفحة الظاهرة»، وافترض أن الصفحات الإسبانية
> للبواخر غير موجودة. **هي موجودة فعلًا.** المشكلة الحقيقية هي تطابق الاسم والتنافس
> الداخلي، لا النقص. الأرقام والعناقيد أدناه صحيحة — الاستنتاج «أنشئ صفحات جديدة» خاطئ.



كل عنقود ↔ صفحة واحدة مستهدفة. **لا تُنشئ صفحتين لنفس العنقود** (تنافس داخلي).

الرموز: 🟢 لدينا ترتيب جيد · 🟡 لدينا ترتيب ضعيف (فرصة سريعة) · 🔴 لا وجود لنا

---

## العنقود 1 — بواخر النيل بالاسم 🟡 **الأولوية القصوى**

**النمط:** `/es/tour/{nombre-barco}-crucero-por-el-nilo`
**النية:** شراء مباشر · **المنافسة:** ضعيفة · **العائد:** الأسرع على الإطلاق

| الكلمة الرئيسية | الحالة | الترتيب الآن |
|---|---|---:|
| `crucero ms amwaj` / `crucero de lujo ms amwaj` | 🔴 لا صفحة ES | 24.0 / 20.8 |
| `crucero por el nilo le fayan ii` | 🔴 | 37.3 |
| `crucero minerva` | 🔴 | 32.8 |
| `crucero sonesta st. george` / `sonesta st george crucero nilo` | 🔴 | 37.0 / 39.3 |
| `crucero ms dwa` | 🔴 | 51.3 |
| `royal ruby crucero nilo` / `crucero royal ruby nilo 5 días 4 noches desde luxor a asuán` | 🔴 | 30.5 / 19.6 |
| `blue shadow crucero nilo` / `blue shadow ii` / `blue shadow iii` | 🔴 | 40.3 |
| `ms nile premium crucero de lujo` | 🔴 | 60.8 |
| `cadena sarah crucero nilo` / `crucero sarah nilo` | 🔴 | 49.3 / 50.0 |
| `crucero ms radamis ii` | 🔴 | 49.0 |
| `ms salacia` · `ms nubian sea` · `ms nile style` · `ms medea` · `ms tuya` | 🔴 | 34–47 |
| `movenpick ms sunray` · `ms movenpick lotus` · `ms movenpick royal lily` | 🔴 | 40–46 |
| `sonesta nile goddess` · `iberotel crown emperor` · `concerto` · `acamar` · `al kahila` | 🔴 | 36–67 |
| `amoura dahabiya` · `assouan dahabiya` · `dahabiya crucero nilo` | 🔴 | 33–72 |
| `ms zeina` | 🟢 صفحة ES موجودة | **9.0** |
| `royal viking` | 🟢 صفحة ES موجودة | **9.0** |
| `al hambra` | 🟢 صفحة ES موجودة | **6.0** |
| `moon dance` | 🟢 صفحة ES موجودة | **11.9** |

> **البرهان:** المتوسط مع صفحة ES = **9.0**. المتوسط بدونها = **38.8**.
> نسخ نموذج `ms-zeina` على باقي البواخر هو أعلى عائد/جهد في الخطة كلها.

---

## العنقود 2 — فئات تجارية رئيسية 🔴 **فجوة كاملة**

| الكلمة | الصفحة المطلوبة |
|---|---|
| `cruceros por el nilo` · `crucero por el nilo precio` · `crucero nilo luxor asuán` | `/es/cruceros-por-el-nilo` |
| `crucero por el nilo 5 días` · `4 noches` · `7 noches` | `/es/cruceros-por-el-nilo/{5-dias\|4-noches\|7-noches}` |
| `excursiones en luxor` · `excursiones desde luxor` · `tours en luxor` | `/es/excursiones-en-luxor` |
| `excursiones en asuán` · `excursiones desde asuán` | `/es/excursiones-en-asuan` |
| `paquetes de viaje a egipto` · `tours a egipto` | `/es/paquetes-de-viaje-a-egipto` |
| `dahabiya nilo` · `crucero dahabiya` | `/es/cruceros-por-el-nilo/dahabiya` |
| `felucca nilo` · `faluca luxor asuán` | `/es/cruceros-por-el-nilo/faluca` |

---

## العنقود 3 — «desde [بلد]» 🔴 **أعلى نيّة شراء · أضعف منافسة**

| الكلمة | الصفحة | السوق |
|---|---|---|
| `viajes a egipto desde méxico` · `paquetes a egipto desde méxico` · `egipto todo incluido desde méxico` | `/es/viajes-a-egipto-desde-mexico` | 🇲🇽 84 ظهور/شهر بالفعل |
| `viajes a egipto desde españa` · `circuitos organizados egipto` | `/es/viajes-a-egipto-desde-espana` | 🇪🇸 781 ظهور/شهر |
| `viajes a egipto desde argentina` | `/es/viajes-a-egipto-desde-argentina` | 🇦🇷 56 ظهور/شهر |
| `viajes a egipto desde colombia` | `/es/viajes-a-egipto-desde-colombia` | 🇨🇴 36 ظهور/شهر |
| `viajes a egipto desde chile` | `/es/viajes-a-egipto-desde-chile` | 🇨🇱 18 ظهور/شهر |

كل صفحة تحتوي: أسعار بالعملة المحلية، مدة الطيران من عاصمة البلد، متطلبات التأشيرة
لحاملي جواز ذلك البلد، أفضل موسم، وشهادات عملاء من نفس البلد.

---

## العنقود 4 — المدد الزمنية 🔴

| الكلمة | الصفحة |
|---|---|
| `egipto 8 días` · `viaje a egipto 8 días` | `/es/paquetes-de-viaje-a-egipto/8-dias` |
| `egipto 10 días` · `egipto 11 días` (ترتيبك الآن 62.5) | `/es/paquetes-de-viaje-a-egipto/{10\|11}-dias` |
| `egipto 12 días` · `15 días` | `/es/paquetes-de-viaje-a-egipto/{12\|15}-dias` |
| `egipto 7 días` · `una semana en egipto` | `/es/paquetes-de-viaje-a-egipto/7-dias` |

نسخة إسبانية مباشرة من `/en/egypt-tour-packages/{n}-days` الموجودة أصلًا.

---

## العنقود 5 — تخطيط الرحلة 🟢 **نحن أقوياء — نوسّع ونربط**

| الكلمة | الحالة |
|---|---|
| `cuántos días en egipto` | 🟢 **#1 في كولومبيا** · 5.6 عالميًا |
| `cuántos días ir a egipto` | 🟢 #1 كولومبيا · #5 المكسيك |
| `cuántos días en el cairo` | 🟡 58.0 |
| `cuántos días para visitar egipto` / `para ver egipto` | 🟡 68–70 |
| `cuántas horas de vuelo son a egipto` | 🟡 63.0 |
| `cuánto cuesta un viaje a egipto` | 🔴 صفحة جديدة `/es/blog/cuanto-cuesta-viajar-a-egipto` |
| `mejor época para viajar a egipto` | 🔴 (النسخة EN ترتيبها 4.3!) |
| `¿es seguro viajar a egipto?` | 🔴 |
| `visado egipto para mexicanos / argentinos / españoles` | 🔴 |

---

## العنقود 6 — المقارنات 🟢 (نموذج ناجح — كرّره)

| الكلمة | الحالة |
|---|---|
| `valle de los reyes vs valle de las reinas` | 🟢 15.3 (النسخة EN = **1.5**) |
| `templos de luxor y karnak` · `karnak y luxor` | 🟡 12–13 |
| `abu simbel en coche o en avión` | 🟢 **10.8** |
| `luxor orilla este vs oeste` | 🟢 8.0 |
| `¿asuán o luxor primero?` | 🔴 (النسخة EN = 3.0) |
| `crucero vs dahabiya vs faluca` | 🔴 (النسخة EN = 6.4) |

---

## العنقود 7 — اللوجستيات وأبو سمبل 🟡

| الكلمة | الترتيب |
|---|---:|
| `distancia aswan abu simbel` (بكل صيغها) | 34–35 |
| `bus aswan abu simbel` | 63.0 |
| `visitar abu simbel` | 56.0 |
| `abu simbel desde asuán` | 🟢 10.8 |

⚠️ **غطِّ الصيغتين: `asuán` و `aswan`** — الجمهور يكتب الاثنين.

---

## العنقود 8 — المدن والمعالم 🔴/🟡

| الكلمة | الصفحة المطلوبة | الترتيب |
|---|---|---:|
| `ciudad de luxor` · `qué ver en luxor` | `/es/destinos/luxor` | 28–31 |
| `asuán` · `qué ver en asuán` | `/es/destinos/asuan` | 9.0 |
| `el valle de las reinas` | صفحة جولة ES | 69.0 |
| `esfinge egipcia` · `esfinge de giza` | `/es/blog/la-esfinge-de-giza` | 29–32 |
| `gran pirámide de giza` | مقال ES | 33.5 |

---

## ترتيب التنفيذ حسب العائد/الجهد

| # | العنقود | الجهد | العائد | المدة |
|---|---|---|---|---|
| 1 | بواخر النيل بالاسم | منخفض | **مرتفع جدًا** | أسبوع 1–3 |
| 2 | فئات تجارية رئيسية | متوسط | مرتفع | أسبوع 3–5 |
| 3 | «desde [بلد]» | متوسط | **مرتفع جدًا** | أسبوع 4–6 |
| 4 | المدد الزمنية | منخفض | متوسط | أسبوع 6–7 |
| 5 | توسيع تخطيط الرحلة + الربط الداخلي | منخفض | متوسط-مرتفع | مستمر |
| 6 | المقارنات | منخفض | متوسط | أسبوع 7–9 |
| 7 | اللوجستيات + متغيرات الكتابة | منخفض جدًا | متوسط | أسبوع 2 |
| 8 | المدن والمعالم | متوسط | متوسط | أسبوع 9–12 |
