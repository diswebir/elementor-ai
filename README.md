# AI Elementor AG

افزونهٔ **AI Elementor AG** در نسخهٔ **۰.۳.۰** در «حالت سادهٔ Editor» قرار دارد. این نسخه فقط یک عملیات قابل‌مشاهده را در برگهٔ **Draft** انجام می‌دهد: افزودن یک block حاوی Heading به بوم Elementor. قابلیت‌های گفت‌وگو، تحلیل، Plan، Job، اجرای خودکار و Rollback موقتاً غیرفعال‌اند.

> این افزونه هیچ‌گاه به مدل اجازهٔ دست‌کاری مستقیم دیتابیس، JSON خام Elementor، PHP، SQL، JavaScript، shell، shortcode یا widgetهای خارج از allowlist را نمی‌دهد.

## قابلیت‌های پیاده‌سازی‌شده

| حوزه | قابلیت |
|---|---|
| پنل Elementor | رابط فارسی RTL با حالت‌های پرسش، برنامه‌ریزی و ساخت؛ وضعیت Job، Plan، تأیید، اجرای گام بعدی و rollback |
| Provider | اتصال OpenAI-compatible از سمت سرور، کلید رمزگذاری‌شده، timeout، خطایابی امن و اعتبارسنجی endpoint |
| Context | استخراج ساختار صفحه، انتخاب فعلی، رنگ‌ها و typographyهای global با redaction و budget محدود |
| Plan | خروجی JSON schema، allowlist ابزار، dependency check، risk level و کنترل freshness context |
| Execution | Job idempotent، task log، document hash، قفل کوتاه‌مدت، snapshot، validation و receipt برای هر گام؛ اجرای خودکار محدود به Plan کم‌ریسک در Draft |
| Elementor | ایجاد container و widgetهای Core، به‌روزرسانی settingهای allowlisted، جابه‌جایی، حذف و بازگردانی snapshot |
| Audit | جدول‌های مستقل برای conversations، plans، jobs، tasks، snapshots، usage و audit log |
| امنیت | capability اختصاصی، REST nonce، محدودیت Draft، بررسی مالکیت، endpoint guard، عدم نمایش secret و عدم اجرای مستقیم دستور مدل |

## حالت سادهٔ Editor

در این انتشار، پنل Elementor فقط فیلد «متن عنوان» و دکمهٔ «درگ و افزودن عنوان» را نمایش می‌دهد. پس از انتخاب دکمه، افزونه از فرمان رسمی ایجاد عنصر Elementor برای درج block استفاده می‌کند و یک حرکت نمایشی drag-and-drop به سمت بوم نشان می‌دهد. این روش نتیجهٔ قابل‌اعتماد drag-and-drop را در model داخلی Editor ثبت می‌کند؛ شبیه‌سازی رویدادهای mouse خام عمداً استفاده نمی‌شود، زیرا در مرورگر یک کاربر واقعی به‌عنوان تعامل مورداعتماد پذیرفته نمی‌شوند و در نسخه‌های Elementor شکننده‌اند.

| مورد | وضعیت در نسخهٔ ۰.۳.۰ |
|---|---|
| افزودن Heading | فعال؛ فقط روی برگهٔ Draft و با capability اجرای افزونه |
| گفت‌وگو، تحلیل context، Plan و Job | موقتاً غیرفعال در پنل و REST |
| اجرای خودکار و Rollback | موقتاً غیرفعال |
| Provider و کلید API | تنظیمات باقی می‌مانند اما در حالت ساده استفاده نمی‌شوند |

### استفاده

1. برگه را در وضعیت **Draft** باز کنید و وارد Elementor شوید.
2. روی «افزودن با هوش مصنوعی» بزنید و متن عنوان را وارد کنید.
3. «درگ و افزودن عنوان» را انتخاب کنید. block عنوان در بوم Elementor ظاهر می‌شود.
4. برای ثبت دائمی، دکمهٔ «به‌روزرسانی» Elementor را بزنید.

> اگر پنل اعلام کرد Editor آماده نیست، صفحهٔ Elementor را یک‌بار Refresh کنید. اگر وضعیت برگه Draft نباشد، عملیات افزونه عمداً غیرفعال می‌شود.

## پیش‌نیازها

| مورد | حداقل نسخه |
|---|---:|
| WordPress | 6.4 |
| PHP | 8.1 |
| Elementor | 3.20 |
| مرورگر | Chromium، Firefox، Safari یا Edge جدید |

برای ساخت Plan و پاسخ‌دهی، به یک provider **سازگار با OpenAI Chat Completions** نیاز دارید که JSON output را پشتیبانی کند.

## نصب از پنل WordPress

1. از خروجی release، فایل `ai-elementor-ag.zip` را دریافت کنید.
2. در WordPress به **افزونه‌ها ← افزودن ← بارگذاری افزونه** بروید.
3. فایل ZIP را انتخاب، نصب و فعال کنید.
4. مطمئن شوید Elementor نصب و فعال است.
5. به **AI Elementor AG** در منوی مدیریت بروید و provider را پیکربندی کنید.

### نصب با SSH یا Composer برای توسعه‌دهندگان

```bash
git clone https://github.com/diswebir/elementor-ai.git ai-elementor-ag
cd ai-elementor-ag
composer install
pnpm install
pnpm run build
```

سپس پوشه را در `wp-content/plugins/ai-elementor-ag/` قرار دهید و افزونه را فعال کنید. برای محیط production، پوشه‌های `node_modules` و فایل‌های آزمون ضروری نیستند؛ فقط assetهای build شده در `assets/build/` باید باقی بمانند.

## پیکربندی Provider

از صفحهٔ **AI Elementor AG** این مقادیر را وارد کنید.

| گزینه | توضیح |
|---|---|
| Provider alias | نام نمایشی امن؛ هرگز کلید را در این فیلد قرار ندهید. |
| Base URL | endpoint پایهٔ HTTPS مانند `https://provider.example/v1`. افزونه خودکار `/chat/completions` را اضافه می‌کند. |
| API key | فقط برای ذخیره وارد کنید. مقدار بعد از ذخیره نمایش داده نمی‌شود. |
| Default model | نام دقیق مدل provider برای Plan و گفتگو. |
| Timeout | بازهٔ ۵ تا ۱۲۰ ثانیه. |
| Context scope | `current`، `site` یا `project`. برای محیط production از `current` شروع کنید. |

کلید API با AES-256-GCM و کلیدی مشتق‌شده از salt وردپرس رمزگذاری می‌شود. برای مدیریت کلید از فایل پیکربندی، ثابت زیر را در `wp-config.php` تعریف کنید؛ در این حالت ثابت بر مقدار ذخیره‌شده اولویت دارد.

```php
define('AIEA_API_KEY', 'provider-secret');
// اختیاری اما توصیه‌شده برای جداسازی کلید رمزگذاری در محیط‌های production:
define('AIEA_ENCRYPTION_KEY', 'a-long-random-server-side-secret');
```

## عملیات فعال فعلی در Elementor

عملیات فعال فعلی فقط یک container ریشه با یک widget از نوع `heading` ایجاد می‌کند. متن widget از فیلد پنل گرفته می‌شود و سطح Heading روی `H2` قرار می‌گیرد. هیچ widget دیگری، هیچ تغییر استایل، جابه‌جایی، حذف یا ویرایش محتوایی توسط افزونه انجام نمی‌شود.

| مورد | سیاست فعلی |
|---|---|
| ساختار اضافه‌شده | یک container و یک widget `heading` |
| متن | حداکثر ۱۸۰ نویسه؛ فاصله‌های اضافی حذف می‌شود |
| ثبت تغییر | Elementor آن را به‌عنوان تغییر در Editor نگه می‌دارد؛ کاربر با «به‌روزرسانی» آن را منتشر یا ذخیره می‌کند |
| موارد مسدود | همهٔ widgetهای دیگر، style، move، delete، Chat، Plan، Job، Auto و Rollback |

> این محدودسازی موقتی است و با تنظیم `simple_editor_mode` در سمت سرور نیز اعمال می‌شود؛ بنابراین endpointهای Agent در این نسخهٔ ساده پاسخ اجرای عملیات نمی‌دهند.

## معماری امنیت

- همهٔ فراخوانی‌های browser به REST شامل `X-WP-Nonce` هستند و server nonce را دوباره بررسی می‌کند.
- endpoint provider فقط HTTPS می‌پذیرد و localhost، شبکه‌های private/reserved و hostهای داخلی را رد می‌کند.
- کلید API، cookie، token و patternهای حساس از context و logها redacted می‌شوند.
- هر Plan با schema validator، tool allowlist، widget allowlist و dependency check بررسی می‌شود.
- هر job به یک کاربر، صفحه، document hash و idempotency key مقید است.
- پیش از تغییر، snapshot فشرده ذخیره می‌شود؛ پس از تغییر، ساختار سند اعتبارسنجی و diff ثبت می‌شود.
- خطاهای اجرای action به وضعیت `needs_review` می‌روند و اجرای ادامهٔ job متوقف می‌شود.

## REST API داخلی

همهٔ مسیرها زیر `wp-json/ai-elementor/v1/` هستند و فقط برای کاربران مجاز و nonce معتبر فعال می‌شوند.

| مسیر | هدف |
|---|---|
| `GET /context` | دریافت context redacted صفحه |
| `POST /sessions` | ایجاد گفت‌وگوی مقید به page و context hash |
| `POST /chat` | پرسش بدون mutation |
| `POST /plans` | ساخت Plan ساخت‌یافته |
| `POST /plans/{id}/approve` | تأیید actionها و ایجاد Job idempotent |
| `GET /jobs/{id}` | وضعیت Job و taskها |
| `POST /jobs/{id}/next` | اجرای یک action تأییدشده |
| `POST /jobs/{id}/rollback` | بازگردانی snapshot مشخص |
| `POST /providers/test` | آزمون اتصال provider توسط مدیر |

## توسعه و آزمون

```bash
composer install
pnpm install
composer run test
composer run lint
composer run phpcs
composer run analyse
pnpm run check
pnpm run build
```

آزمون‌های واحد فعلی redaction، state transition، schema validation و structural diff را پوشش می‌دهند. برای انتشار production باید در یک سایت staging واقعی، این سناریوها را نیز بررسی کنید:

1. Provider سالم، نامعتبر، timeout و rate limit.
2. صفحهٔ Draft خالی، Draft دارای containerهای تو در تو و صفحهٔ Published.
3. deny شدن کاربر بدون capability و nonce منقضی.
4. تغییر دستی document بین ساخت Plan و تأیید آن.
5. توقف mutation، receipt، snapshot و rollback.
6. responsive layout در دسکتاپ، تبلت و موبایل.

## وضعیت انتشار و محدودیت‌ها

این repository یک **MVP امن و قابل‌گسترش** است. اجرای واقعی باید ابتدا در staging انجام شود. Providerهای Anthropic و Gemini در UI به‌عنوان انتخاب نمایشی دیده می‌شوند اما adapter اجرایی نسخهٔ فعلی OpenAI-compatible است؛ پیش از فعال‌سازی آن providerها باید adapter و contract اختصاصی آن‌ها افزوده و test شود.

پخش incremental token-by-token به‌دلیل تفاوت transportهای WordPress HTTP API به حالت پاسخ تکمیل‌شدهٔ امن fallback می‌کند. اجرای Plan همچنان از طریق Jobهای backend و با ثبت audit انجام می‌شود.

## مجوز

GPL-2.0-or-later.
