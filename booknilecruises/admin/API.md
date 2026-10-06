# واجهة Book Nile Cruises والتكامل مع n8n وMake

الواجهة على `https://booknilecruises.net/api`؛ يمكن تغيير المسار في `api_path` داخل `bnc-config.php`. جميع الردود JSON، ولا تستخدم الواجهة جلسة لوحة التحكم أو CSRF. حجم جسم الطلب الأقصى ١ ميجابايت. لا توجد CORS؛ استفسارات الموقع تقبل Origin مطابقًا لـ `site_url` فقط أو بدون Origin، وOPTIONS يعيد 204 بدون ترويسات CORS.

## التثبيت والنشر

ارفع `public/api/` إلى `public_html/api/` مع تحديث `bnc-app/`، وطبّق تحديث قاعدة البيانات `002_phase5` من لوحة المالك أو `php app/bin/migrate.php`. لا تغيّر إعداداتك السرية عند تحديث الملفات.

في `bnc-config.php` اضبط `export_token` إلى رمز عشوائي لا يقل عن ٣٢ حرفًا، واضبط `github.token` و`github.repo` و`github.workflow` و`github.ref`. يجب أن يملك رمز GitHub صلاحية بدء Actions في المستودع. ضع نفس `export_token` في سر المستودع `BNC_EXPORT_TOKEN`؛ بيانات FTP تبقى في أسرار GitHub الخاصة بسير النشر الموجود. صفحة «النشر» تعرض حالة الإعداد فقط ولا تعرض رمز GitHub.

زر «نشر الموقع الآن» ينشئ طلبًا في الانتظار ويشغّل سير العمل مع `inputs.job_id` كنص. يعرض السجل آخر ٢٠ طلبًا، ويدخل الطلب حالة قيد التنفيذ ثم نجح أو فشل عند رد سير العمل. لا يبدأ طلب آخر أثناء وجود طلب في الانتظار أو قيد التنفيذ عمره أقل من ٢٠ دقيقة؛ يرجع 409. فشل GitHub يسجّل الطلب كفاشل ويرجع 502. لا يعني قبول الطلب اكتمال نشر الموقع.

واجهتا سير العمل تستخدمان **Authorization: Bearer EXPORT_TOKEN** فقط، وليس مفتاح n8n:

| الطريقة والمسار | الطلب والنتيجة |
|---|---|
| `GET /api/export` | تصدير المحتوى الموافق لـ `Content\Exporter`، بدون تخزين مؤقت. المقالات المجدولة لا تظهر حتى يحين موعدها. |
| `POST /api/publish/status` | `job_id` عدد صحيح، `status` أحد running/succeeded/failed، `run_url` رابط HTTPS على github.com، و`message` حتى ٢٥٥ حرفًا. النجاح يرجع `{"ok":true}`. |

```bash
curl -H "Authorization: Bearer $BNC_EXPORT_TOKEN" \
  https://booknilecruises.net/api/export

curl -X POST -H "Authorization: Bearer $BNC_EXPORT_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"job_id":42,"status":"succeeded","run_url":"https://github.com/owner/repo/actions/runs/123","message":"Published"}' \
  https://booknilecruises.net/api/publish/status
```

تكرار رد الحالة النهائية آمن ولا يكرر Webhook. محاولة إعادة فتح طلب انتهى ترجع 409.

## إنشاء مفتاح أتمتة

من حساب يملك `api.manage` افتح «مفاتيح API»، واكتب اسمًا واضحًا مثل n8n أو Make واختر أقل الصلاحيات المطلوبة. المفتاح بالشكل `bnc_` ثم ٤٠ خانة hex ويظهر **مرة واحدة فقط** بعد الإنشاء. خزّنه في مخزن Credentials الخاص بأداة الأتمتة؛ قاعدة البيانات تحفظ بصمة SHA-256 والبادئة فقط. إذا فقدته، أنشئ مفتاحًا جديدًا وألغِ القديم.

| الصلاحية | ما تسمح به |
|---|---|
| `trips.read` | قراءة الرحلات المنشورة |
| `posts.write` | إنشاء مقال وتعديل المقالات التي أُنشئت عبر API |
| `posts.publish` | طلب حالة published عند الإنشاء أو التعديل، مع posts.write |
| `enquiries.read` | قراءة الاستفسارات |
| `publish` | بدء نشر الموقع |

كل طلب إلى `/api/v1/*` يحتاج `Authorization: Bearer bnc_…` أو `X-API-Key: bnc_…`. المفتاح غير المعروف أو الملغى يرجع 401، والصلاحية الناقصة ترجع 403. الحد ١٢٠ طلبًا في نافذة متحركة مدتها دقيقة لكل مفتاح، وبعدها 429. عند 429 انتظر دقيقة قبل المحاولة التالية. يمكن مشاهدة آخر استخدام وإلغاء أو حذف المفتاح من اللوحة.

## واجهات الأتمتة

| الطريقة والمسار | الصلاحية | النتيجة |
|---|---|---|
| `GET /api/v1/trips` | trips.read | `{"trips":[…]}`: id، slug، title، url، price، currency، duration_days، duration_nights، categories (id/slug/name/taxonomy)، cover_image_url. |
| `POST /api/v1/posts` | posts.write | 201: id، url، status، published_at، edit_url. |
| `PATCH /api/v1/posts/{id}` | posts.write | نفس حقول الإنشاء، كلها اختيارية؛ يرجع 200. مقالات اللوحة أو الاستيراد لا يمكن تعديلها هنا (403). |
| `GET /api/v1/enquiries?since=2026-10-01T00:00:00%2B03:00&status=new` | enquiries.read | `{"enquiries":[…]}` حتى ٢٠٠ سجل، من الأقدم للأحدث، بعد since حصريًا. كلا المرشّحين اختياري. |
| `POST /api/v1/publish` | publish | جسم `{}`، ويرجع 201 `{"job_id":42}` مع نفس حارس الـ٢٠ دقيقة الخاص بزر اللوحة. |

حقول المقال: `title` مطلوب حتى ٢٥٥ حرفًا؛ `content_html` مطلوب ويُنقّى من HTML الخطر؛ `excerpt` حتى ٥٠٠؛ `slug` اختياري (حروف إنجليزية صغيرة وأرقام وشرطات، حتى ١٩٠)، يُولّد من العنوان وتُضاف لاحقة عند التعارض؛ `status` draft أو published، والافتراضي draft؛ `published_at` تاريخ ISO 8601 اختياري، ويفضل مع المنطقة الزمنية؛ `seo_title` حتى ٢٥٥؛ `seo_description` حتى ٥٠٠؛ `image_url` اختياري HTTPS؛ `image_alt` حتى ٢٥٥.

حالة published تحتاج posts.publish؛ تاريخ النشر المستقبلي يجعل الرد `status: "scheduled"` وتبقى حالة التخزين published حتى يحين الموعد. تحرير محتوى مقال منشور دون إرسال status يحتاج posts.write فقط. تغيير رابط مقال منشور ينشئ تحويلًا تلقائيًا. يُحفظ مصدر المقال `api:<اسم المفتاح>`. ترسل المقالات المنشورة المستحقة حدث post.published بعد الحفظ؛ المقال المجدول لا يرسل هذا الحدث عند إنشائه. عند النشر الدوري للموقع يصل site.published فقط للطلبات التي لها job_id ورد حالة من سير العمل.

تنزيل صورة المقال: HTTPS فقط، حتى ١٥ ميجابايت، مهلة ٢٠ ثانية، دون اتباع تحويلات؛ تُرفض عناوين IP الخاصة والمحلية والمحجوزة وجميع DNS التي تحتوي عليها. يعاد التحقق من نوع الصورة وحجمها وأبعادها ثم إعادة ترميزها في مكتبة الصور. فشل الصورة يرجع 422 في `fields.image_url`.

```bash
curl -H "Authorization: Bearer $BNC_API_KEY" \
  https://booknilecruises.net/api/v1/trips

curl -X POST -H "Authorization: Bearer $BNC_API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"title":"رحلة نيلية جديدة","content_html":"<p>تفاصيل الرحلة</p>","status":"draft","seo_description":"دليل الرحلات النيلية"}' \
  https://booknilecruises.net/api/v1/posts

curl -X PATCH -H "Authorization: Bearer $BNC_API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"status":"published","published_at":"2026-12-01T10:00:00+02:00"}' \
  https://booknilecruises.net/api/v1/posts/42

curl -H "X-API-Key: $BNC_API_KEY" \
  'https://booknilecruises.net/api/v1/enquiries?since=2026-10-01T00:00:00Z&status=new'

curl -X POST -H "Authorization: Bearer $BNC_API_KEY" \
  -H 'Content-Type: application/json' -d '{}' \
  https://booknilecruises.net/api/v1/publish
```

## إعداد n8n

في عقدة **HTTP Request** اختر الطريقة GET أو POST أو PATCH والمسار من الجدول. اختر Authentication: Generic Credential Type ثم Header Auth، واجعل اسم الترويسة `Authorization` وقيمتها `Bearer <المفتاح>` داخل Credential؛ أو استخدم `X-API-Key`.

لإنشاء مقال فعّل Send Body، واختر Body Content Type: JSON، واربط حقلي title وcontent_html ببيانات العقدة السابقة. لإرسال المقال للنشر أضف status: published وتأكد من وجود posts.publish. اختر Response Format: JSON؛ الحقول id وedit_url في الرد تساعدك على المراجعة. استخدم IF لمعالجة الخطأ بدل إعادة المحاولة غير المحدودة، ولا تكرر POST تلقائيًا بعد انقطاع اتصال غامض قبل التأكد هل المقال أو طلب النشر أُنشئ بالفعل.

## إعداد Make

في **HTTP → Make a request** أدخل URL والطريقة؛ أضف Authorization بالقيمة Bearer متبوعة بالمفتاح المخزن بأمان. اختر Body type: Raw وContent type: application/json، واربط القيم داخل JSON (يفضل باستخدام وحدة JSON لتفادي أخطاء الاقتباس). فعّل Parse response. GET لا يحتاج جسمًا، وPOST نشر الموقع يرسل `{}`. عند الخطأ عالج 401/403 بتصحيح المفتاح والصلاحية، و422 بتصحيح المدخلات، و429 بالانتظار.

## استفسارات الموقع وصندوق الوارد

`POST /api/enquiries` عام بدون مفتاح، يقبل JSON (بما فيه Blob من navigator.sendBeacon) أو application/x-www-form-urlencoded. يجب أن يكون `website` فارغًا؛ إذا امتلأ يرجع 201 دون تخزين.

| الحقل | التحقق |
|---|---|
| name | مطلوب، حتى ١٩٠ حرفًا |
| email / phone | البريد صالح إن وُجد؛ الهاتف حتى ٦٠ حرفًا من `+0-9 ()-` |
| trip | slug اختياري؛ يُربط بالرحلة وعنوانها إذا وُجدت |
| travel_date | تاريخ Y-m-d اختياري، اليوم أو المستقبل |
| adults / children | اختياري، عدد صحيح من ٠ إلى ٩٩ |
| message | حتى ٥٠٠٠ حرف |
| page_url | مسار داخل الموقع فقط |
| channel | form (الافتراضي)، whatsapp، email |

form يحتاج البريد أو الهاتف. في whatsapp/email كلاهما اختياري؛ قيمة التواصل غير الصحيحة تُضاف كما هي للرسالة بدل رفض الاستفسار. الموقع يقسم حقل التواصل الاختياري إلى email إن احتوى @ وإلا إلى phone قبل الإرسال. الحد ٥ طلبات لكل IP خلال ١٠ دقائق و٢٠٠ إجمالًا خلال ٢٤ ساعة، ثم 429؛ يسجّل IP الفعلي من REMOTE_ADDR. النجاح 201 `{"ok":true}`.

```bash
curl -X POST -H 'Origin: https://booknilecruises.net' \
  -H 'Content-Type: application/json' \
  -d '{"name":"أحمد","phone":"+201234567890","adults":2,"children":0,"message":"أريد تفاصيل الحجز","page_url":"/trip/","channel":"form","website":""}' \
  https://booknilecruises.net/api/enquiries
```

إشعار البريد نص عادي بكل الحقول ورابط اللوحة. المستلم من إعداد enquiry_notify_email القابل للتعديل في صفحة الاستفسارات، وافتراضيه mail.notify؛ المرسل mail.from. فشل الإشعار يسجّل ولا يظهر للزائر، وبعد التخزين يصل Webhook enquiry.created. على FastCGI تتم الإشعارات بعد إنهاء الرد. على خادم PHP العادي قد يتأخر إنهاء الاتصال أثناء الإشعارات.

حساب المبيعات يملك عرض الاستفسارات وتعديل الحالة والملاحظات وحذفها. الحالات: جديد، تم التواصل، تم الحجز، مغلق، مزعج. القائمة ٥٠ في الصفحة مع البحث والترشيح. CSV يتضمن UTF-8 BOM ويمنع تنفيذ صيغ الجداول بإضافة علامة اقتباس مفردة للخلايا التي تبدأ بـ = أو + أو - أو @. شريط اللوحة والرئيسية يعرضان عدد الاستفسارات الجديدة.

## Webhooks والتحقق من التوقيع

من «Webhooks» (صلاحية api.manage) أضف اسمًا ورابط HTTPS واختر enquiry.created أو post.published أو site.published. يمكن تعطيل الإرسال، تعديل البيانات، الحذف، وتجديد السر أو «إرسال اختبار» بحدث ping. نتيجة آخر إرسال ووقته تظهران في اللوحة.

ينشأ سر عشوائي بطول ٣٢ بايت ممثل بـ٦٤ خانة hex. **يظهر على صفحة التعديل مرة واحدة عند الإنشاء أو التجديد** احترامًا لقاعدة عدم إعادة عرض الأسرار؛ احفظه في جهة الاستقبال. التجديد يغيّر التوقيع فورًا ويحتاج تحديث جهة الاستقبال.

الطلب POST JSON، بمهلة ٥ ثوانٍ ودون تحويلات؛ ترفض عناوين IP الخاصة والمحلية والمحجوزة. `webhooks_allow_private` إعداد اختبارات فقط، افتراضيه false ويجب أن يبقى false في الإنتاج. لا توجد إعادة محاولة تلقائية؛ تُعرض آخر نتيجة مثل 200 أو timeout أو blocked: private address. فشل Webhook لا يلغي الاستفسار أو المقال أو نتيجة النشر.

```json
{"event":"enquiry.created","sent_at":"2026-10-06T12:00:00Z","data":{"id":42,"name":"أحمد"}}
```

الترويسات: `Content-Type: application/json`، `X-BNC-Event`، و`X-BNC-Signature: sha256=<hex>`. التوقيع HMAC-SHA256 على **بايتات الجسم الخام**، باستخدام نص السر hex كما ظهر في اللوحة، وليس تحويله إلى بايتات. لا تستخدم JSON.stringify على جسم محلّل للتحقق؛ قد يغيّر ترتيب المفاتيح أو المسافات.

في Webhook الخاص بـn8n فعّل Raw Body واحتفظ بالنص الخام قبل التحليل. في Code node مرّر `rawBody` من الجسم الخام (أو اقرأ binary.data عندما يضعه إصدارك هناك)، والسر من متغير بيئة محمي. يحتاج Code node السماح بوحدة Node المدمجة crypto حسب إعداد استضافتك:

```javascript
const crypto = require('crypto');
const item = $input.first();
const raw = item.binary?.data
  ? await this.helpers.getBinaryDataBuffer(0, 'data')
  : Buffer.from(item.json.rawBody, 'utf8');
const expected = Buffer.from('sha256=' + crypto.createHmac('sha256', $env.BNC_WEBHOOK_SECRET).update(raw).digest('hex'));
const received = Buffer.from(item.json.headers['x-bnc-signature'] || '');
if (received.length !== expected.length || !crypto.timingSafeEqual(received, expected)) throw new Error('Invalid signature');
return [{ json: JSON.parse(raw.toString('utf8')) }];
```

ارفض الطلب قبل تنفيذ أية أتمتة إن كان التوقيع خطأ، وطابق event مع X-BNC-Event. استخدم event وdata.id لمنع معالجة الحدث نفسه مرتين إن أعادت جهة وسيطة إرسال الطلب.

## الأخطاء

الخطأ دائمًا `{"error":"English message"}`؛ أخطاء التحقق 422 تضيف `fields` مثل `{"error":"Validation failed","fields":{"email":"Invalid value"}}`. الرموز: 400 JSON غير صالح، 401 رمز غائب/خاطئ، 403 صلاحية ناقصة أو Origin غير مسموح، 404 غير موجود، 405 طريقة غير مسموحة، 409 نشر جارٍ أو تعارض، 413 جسم أكبر من الحد، 422 تحقق، 429 تجاوز الحد، 502 فشل بدء سير GitHub، 500 خطأ داخلي بدون تفاصيل أو stack trace. سجّل رمز الخطأ ورقم الكيان فقط في أدواتك ولا تسجّل المفاتيح أو الأسرار.

للاستطلاع الدوري في n8n وMake استخدم `GET /api/v1/enquiries?since_id=0`، ثم احفظ `next_since_id` واستخدمه في الطلب التالي. النتائج مرتبة حسب المعرّف وبحد أقصى 200؛ كرّر الطلب حتى تصبح القائمة فارغة. يبقى `since` متاحاً ويشمل الاستفسارات عند نفس الوقت (`>=`).
