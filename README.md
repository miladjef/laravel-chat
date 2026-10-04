# Larvel Chat

Larvel Chat یک تالار گفت‌وگوی بلادرنگ با شناسه موقت بر پایه Laravel 13، Livewire 3، Reverb، Redis و Vite است.

Programmer: Miladjef

## وضعیت فعلی

این نسخه پس از بازبینی امنیت، معماری Realtime، استقرار و تست اصلاح شده است.

### امنیت پیام و هویت

- پیام ابتدا در Laravel اعتبارسنجی می‌شود و هویت فرستنده از Session سرور گرفته می‌شود.
- HTML پیام با `textContent` وارد DOM می‌شود و مسیر قدیمی `innerHTML` وجود ندارد.
- پیام‌ها `message_id` و `sent_at` سروری دارند.
- Rate Limit هم برای شناسه کاربر و هم برای اثرانگشت HMAC کلاینت اعمال می‌شود.
- ثبت شناسه موقت Rate Limit و Honeypot دارد.
- نام نمایشی قبل از ذخیره Normalize می‌شود. تفاوت‌های رایج «ي/ی»، «ك/ک»، فاصله، نیم‌فاصله و ارقام هم‌شکل در کنترل نام تکراری لحاظ می‌شوند.
- Presence Channel فقط `uuid` و `display_name` را منتشر می‌کند.
- آواتار از دیتابیس و payload حذف شده و نشان کاربر در مرورگر از UUID و حرف اول نام تولید می‌شود.

### حریم خصوصی و عمر داده

- حساب‌ها موقت هستند و خروج کاربر رکورد حساب را حذف می‌کند.
- `last_seen_at` و Heartbeat دوره‌ای از حذف کاربر فعال جلوگیری می‌کنند.
- Cleanup ساعتی حساب‌های غیرفعال قدیمی را حذف می‌کند.
- ۵۰ پیام آخر به شکل موقت در Cache نگهداری می‌شوند و TTL پیش‌فرض آن‌ها ۳۰ دقیقه است.
- صفحه‌ها `noindex` هستند و `robots.txt` خزیدن را مسدود می‌کند.

### پایداری Realtime

- Broadcast از `ShouldBroadcast` عبور می‌کند و در Production روی Redis Queue قرار می‌گیرد.
- پاسخ Livewire همان payload پیام را به فرستنده برمی‌گرداند تا نمایش پیام خودش به WebSocket وابسته نباشد.
- `message_id` جلوی نمایش تکراری پیام را می‌گیرد.
- وضعیت اتصال Reverb در رابط نمایش داده می‌شود.
- هنگام قطع Realtime، دکمه ارسال غیرفعال می‌شود.
- Heartbeat پیش‌فرض هر ۱۸۰ ثانیه اجرا می‌شود.
- DOM حداکثر ۳۰۰ پیام پویا را نگه می‌دارد.
- ساعت هر پیام از `sent_at` سرور نمایش داده می‌شود.

### مرز شبکه و Headerهای امنیتی

- Trusted Hosts فعال است.
- Trusted Proxies از `TRUSTED_PROXIES` خوانده می‌شود.
- HSTS در Production روی HTTPS فعال است.
- CSP سازگار با Livewire 3 در Production فعال است.
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `COOP` و `X-Robots-Tag` تنظیم شده‌اند.

نکته: Livewire 3 برای evaluator سمت کلاینت به سیاست CSP سازگار با `unsafe-eval` نیاز دارد. مهاجرت به CSP سخت‌گیرانه بدون `unsafe-eval` بهتر است همراه ارتقای مستقل به Livewire 4 و تست مرورگر انجام شود.

## پیش‌نیازها

- PHP 8.3 یا بالاتر
- Composer 2
- Node.js 22 یا بالاتر
- npm
- SQLite برای توسعه سبک
- MySQL و Redis برای Production

## نصب توسعه

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan test
```

برای اجرای محلی:

```bash
php artisan serve
php artisan reverb:start
npm run dev
```

در تنظیم توسعه `QUEUE_CONNECTION=sync` است تا Broadcast بدون Worker جدا اجرا شود.

## Lock files

پس از نخستین نصب موفق روی سیستمی که به Packagist و npm دسترسی دارد، این دو فایل را در مخزن ثبت کنید:

```text
composer.lock
package-lock.json
```

پس از ثبت lockها، Deployment با `composer install` و `npm ci` انجام شود.

## Production

`.env.production.example` را مبنا قرار دهید و Secretها را با مقادیر واقعی جایگزین کنید.

موارد اصلی:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chat.example.com
TRUSTED_HOSTS=chat.example.com
TRUSTED_PROXIES=REMOTE_ADDR

SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis

BROADCAST_CONNECTION=reverb
REVERB_HOST=chat.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=https://chat.example.com

HEALTH_CHECK_REVERB=true
HEALTH_REVERB_HOST=127.0.0.1
HEALTH_REVERB_PORT=8080
```

`TRUSTED_PROXIES=REMOTE_ADDR` زمانی مناسب است که PHP فقط از Reverse Proxy داخلی درخواست دریافت کند. اگر Cloudflare یا Load Balancer مستقیماً به PHP دسترسی دارد، CIDRهای Proxy مورد اعتماد را صریح در `TRUSTED_PROXIES` قرار دهید. از `*` روی سرویس در دسترس عمومی استفاده نکنید.

پس از Deployment:

```bash
php artisan migrate --force
php artisan optimize
php artisan reverb:restart
```

Reverb، Queue Worker و Scheduler باید به شکل Processهای مستقل اجرا شوند.

نمونه Worker:

```bash
php artisan queue:work redis --queue=broadcasts,default --sleep=1 --tries=3 --timeout=60
```

Scheduler:

```bash
php artisan schedule:work
```

## Docker

Docker Compose شامل این سرویس‌هاست:

- `laravel.test`
- `reverb`
- `worker`
- `scheduler`
- `mysql`
- `redis`

پس از نصب Composer و ایجاد `.env`:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
./vendor/bin/sail artisan test
```

## Health checks

`/up` سلامت Boot لاراول را نشان می‌دهد.

`/ready` دیتابیس و Cache را بررسی می‌کند. اگر `HEALTH_CHECK_REVERB=true` باشد، پورت داخلی Reverb نیز بررسی می‌شود. پاسخ سالم HTTP 200 و پاسخ ناسالم HTTP 503 است.

## تست و کیفیت کد

تست PHP:

```bash
php artisan test
```

فرمت کد:

```bash
vendor/bin/pint --test
```

تحلیل ایستا:

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

تست E2E با Playwright:

```bash
npx playwright install chromium
npm run test:e2e
```

تست E2E دو Session مستقل ایجاد می‌کند، Presence را بررسی می‌کند، پیام را بین دو کاربر ردوبدل می‌کند و یک payload HTML را برای جلوگیری از XSS آزمایش می‌کند.

Workflow موجود در `.github/workflows/ci.yml` مراحل PHP، Larastan، Pint، PHPUnit، build فرانت، audit وابستگی‌ها و Playwright را اجرا می‌کند.

## تنظیمات Chat

```env
CHAT_HISTORY_LIMIT=50
CHAT_HISTORY_TTL_MINUTES=30
CHAT_DOM_MESSAGE_LIMIT=300
CHAT_HEARTBEAT_SECONDS=180
CHAT_GUEST_STALE_HOURS=24
```

## نکات عملیاتی

- Secretهای Reverb و `APP_KEY` را در مخزن ثبت نکنید.
- `APP_DEBUG` در Production باید `false` باشد.
- `REVERB_ALLOWED_ORIGINS` باید دامنه واقعی باشد.
- دسترسی مستقیم به پورت داخلی Reverb را در Firewall محدود کنید و WebSocket را از Reverse Proxy عبور دهید.
- Queue Worker و Reverb را زیر Supervisor، systemd یا Container orchestrator نگه دارید.
- در استقرار چند سروری، Redis مشترک برای Cache، Queue و Reverb Scaling ضروری است.
