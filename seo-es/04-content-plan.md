# 4. خطة المحتوى — ماذا نبني، بالترتيب

---

## المرحلة 1 (أسبوع 1–3) — حصاد بواخر النيل 🔥

### لماذا أولًا
الصفحات الإنجليزية موجودة ومُرتَّبة جيدًا (`blue-shadow` = 2.0، `farida` = 4.5).
النسخة الإسبانية = **ترجمة + توطين**، لا بحث ولا تصوير جديد.
البرهان الرقمي: 4 بواخر لها صفحة ES ← متوسط ترتيب **9.0**. الباقي بلا صفحة ES ← **38.8**.

### قالب صفحة الباخرة الإسبانية

```
URL:    /es/tour/{barco}-crucero-por-el-nilo
Title:  Crucero por el Nilo {Barco} 5★ | Luxor–Asuán 2026/2027 · desde {X} €
H1:     Crucero por el Nilo {Barco} — Luxor a Asuán
Meta:   Navega el Nilo a bordo del {Barco} 5★. Salidas semanales desde Luxor y
        Asuán, guía egiptólogo en español, pensión completa. Precio desde {X} €.
```

**الأقسام الإلزامية (بهذا الترتيب):**
1. `Precio desde {X} € / {Y} MXN` + زر «Solicitar presupuesto» — **فوق الطية**
2. `Itinerario día a día` (4 / 5 / 8 días) بأسماء المعابد الإسبانية:
   Karnak · Valle de los Reyes · Edfu · Kom Ombo · Templo de Filae · Alta Presa
3. `Camarotes y cubiertas` — المقاسات، الشرفة، الطابق
4. `Qué incluye / Qué no incluye` — قائمتان منفصلتان
5. `Fechas de salida 2026 / 2027` — جدول بتاريخ تحديث ظاهر
6. `Guía en español` — اذكرها صراحةً، هذه أهم نقطة بيع للسوق الإسباني
7. `Preguntas frecuentes` — 5 أسئلة + FAQPage schema
8. روابط داخلية: → `/es/cruceros-por-el-nilo` و→ باخرتان بديلتان

**الترتيب التنفيذي** (الأعلى ظهورًا أولًا):
`ms amwaj` → `le fayan ii` → `minerva` → `sonesta st. george` → `ms dwa` →
`royal ruby` → `blue shadow` → `nile premium` → `sarah` → `radamis ii` →
`salacia` → `nubian sea` → `movenpick (sunray / lotus / royal lily)` →
`nile style` → `sonesta nile goddess` → `iberotel crown emperor` →
`amoura dahabiya` → `assouan dahabiya`

> ⚠️ **ليست ترجمة آلية.** هذا بالضبط ما يفعله المنافسون وهو سبب ضعف صفحاتهم.
> اكتبها بإسبانية بشرية، وأضف تفاصيل تشغيلية حقيقية (رصيف الإقلاع، مواعيد الصعود،
> اسم المرشد الإسباني) — هذا ما لا يستطيع منافسوك تقليده.

---

## المرحلة 2 (أسبوع 3–5) — صفحات الفئات التجارية

| الصفحة | الكلمة الرئيسية | المحتوى |
|---|---|---|
| `/es/cruceros-por-el-nilo` | `cruceros por el nilo` | مركز يربط كل صفحات البواخر + جدول مقارنة (باخرة / نجوم / مدة / سعر من) + قسم «cómo elegir» |
| `/es/excursiones-en-luxor` | `excursiones en luxor` | كل جولات الأقصر + خريطة + «orilla este vs oeste» |
| `/es/excursiones-en-asuan` | `excursiones en asuán` | جولات أسوان + أبو سمبل + فيلة |
| `/es/paquetes-de-viaje-a-egipto` | `paquetes de viaje a egipto` | مركز يربط صفحات المدد |
| `/es/cruceros-por-el-nilo/dahabiya` | `crucero dahabiya` | تخصص عالي الهامش، منافسة ضعيفة |

**قاعدة صفحة الفئة:** 800–1200 كلمة نص فريد **فوق** شبكة المنتجات، لا تحتها.
جدول مقارنة حقيقي + FAQ + روابط داخلية لكل صفحة ابن.

---

## المرحلة 3 (أسبوع 4–6) — صفحات «desde [بلد]» 💰

أعلى نيّة شراء وأضعف منافسة. القالب:

```
URL:   /es/viajes-a-egipto-desde-mexico
Title: Viajes a Egipto desde México 2026/2027 | Paquetes con Crucero por el Nilo
H1:    Viajes a Egipto desde México — paquetes con vuelo, Nilo y guía en español
```

**الأقسام الإلزامية:**
1. أسعار بـ **MXN** (لا يورو فقط) + «a partir de»
2. `Vuelos desde CDMX / Guadalajara / Monterrey` — شركات الطيران، مدة الرحلة، التوقفات
3. `Visado para mexicanos` — النوع، السعر، هل عند الوصول
4. `Mejor época desde México` — مقارنة بالمواسم المكسيكية (Semana Santa, verano, diciembre)
5. `Diferencia horaria` México ↔ Egipto
6. شهادات عملاء **مكسيكيين بالاسم والمدينة**
7. `Formas de pago` المتاحة في المكسيك

كرِّر لـ: 🇪🇸 España · 🇦🇷 Argentina · 🇨🇴 Colombia · 🇨🇱 Chile
(إسبانيا أولًا — 781 ظهور/شهر).

> **مهم:** لا تكرّر نفس النص وتبدّل اسم البلد فقط. جوجل يعتبره محتوى رقيقًا مكرَّرًا.
> الطيران والتأشيرة والعملة والمواسم مختلفة فعليًا لكل بلد — استغل هذا الاختلاف.

---

## المرحلة 4 (أسبوع 6–7) — صفحات المدد

نسخة ES مباشرة من `/en/egypt-tour-packages/{n}-days`:
`/es/paquetes-de-viaje-a-egipto/{7|8|10|11|12|15}-dias`

النسخ الإنجليزية ترتيبها 40–50 (ضعيف) — أي أن المنافسة هنا ليست قوية،
والنسخة الإسبانية قد تتفوق على النسخة الإنجليزية نفسها.

---

## المرحلة 5 (مستمر) — المحتوى المعلوماتي + الربط الداخلي

### إصلاح فوري (يوم واحد عمل): متغيرات الكتابة
في `/es/blog/abu-simbel-desde-asuan-por-carretera-o-en-avion` أضف داخل النص:
> «La distancia entre **Asuán (Aswan)** y Abu Simbel es de 280 km…»

وأضف قسم `Distancia Aswan – Abu Simbel` بـ H2 صريح.
هذا وحده يعالج 5 استعلامات ترتيبها 34–63 اليوم.
كرِّر النمط: `Lúxor (Luxor)` · `El Cairo (Cairo)` · `Asuán (Aswan)`.

### مقالات جديدة بالأولوية
1. `cuánto cuesta viajar a egipto` — نيّة تجارية عالية، تربط بكل صفحات الباقات
2. `mejor época para viajar a egipto` — النسخة EN ترتيبها **4.3**، النسخة ES غير موجودة
3. `¿asuán o luxor primero?` — النسخة EN ترتيبها **3.0**
4. `crucero, dahabiya o faluca` — النسخة EN ترتيبها **6.4**
5. `cuántas horas de vuelo a egipto` (من مدريد، CDMX، بوينس آيرس)
6. `visado de egipto` لكل جنسية
7. `cuántos días en el cairo`

> **النمط الواضح:** كل مقال إنجليزي ترتيبه أقل من 7 لديه نسخة إسبانية غائبة أو ضعيفة.
> ترجمة هذه المقالات الأربعة = أرخص مكسب في الخطة.

### الربط الداخلي — الإصلاح الأهم على الإطلاق

**المشكلة الحالية:** مقالات المدونة الإسبانية تُرتَّب جيدًا (`cuantos-dias` = 14.1،
`abu-simbel` = 10.8) لكنها **لا تربط بأي صفحة بيع**. الزائر يقرأ ويخرج.

**القاعدة الإلزامية:** كل مقال إسباني يجب أن يحتوي:
- 2–3 روابط نصية داخل النص → صفحة جولة أو باخرة ذات صلة
- صندوق CTA في المنتصف: «¿Listo para reservar? Ver nuestros cruceros por el Nilo →»
- روابط breadcrumb → صفحة الفئة الأم

| من (المقال) | إلى (صفحة البيع) |
|---|---|
| `cuantos-dias-necesitas-en-egipto` | `/es/paquetes-de-viaje-a-egipto/{8,10,12}-dias` |
| `abu-simbel-desde-asuan-...` | `/es/tour/abu-simbel-desde-asuan` |
| `valle-de-los-reyes-vs-...` | `/es/excursiones-en-luxor` |
| `cruceros-de-5-estrellas-de-luxor-a-asuan` | `/es/cruceros-por-el-nilo` |
| `tour-por-la-orilla-este-y-oeste-de-luxor` | `/es/excursiones-en-luxor` |

---

## قواعد كتابة إسبانية عامة (تنطبق على كل صفحة)

| القاعدة | التفصيل |
|---|---|
| **إسبانية محايدة** | تجنّب `vosotros` (إسبانيا فقط) و`ustedes` الحصرية. استخدم صياغة تصلح للسوقين |
| **العملة** | € لإسبانيا، MXN للمكسيك، USD لباقي أمريكا اللاتينية |
| **التاريخ** | `DD/MM/AAAA` |
| **«guía en español»** | اذكرها في كل صفحة — هذه نقطة البيع رقم 1 لهذا السوق |
| **السعر في العنوان** | `desde 850 €` في الـ Title يرفع الـ CTR بشكل حاسم |
| **السنة** | `2026/2027` في العناوين — كل المنافسين يفعلونها |
| **علامات التشكيل** | `Asuán` `Lúxor` `Filae` — صحيحة في النص، بلا تشكيل في الـ URL |
