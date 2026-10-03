# laravel Chat

laravel Chat یک تالار گفت‌وگوی ناشناس و بلادرنگ بر پایه Laravel، Livewire، Reverb و Vite است.

Programmer: Miladjef

## وضعیت این نسخه

این نسخه در پنج فاز بازبینی و اصلاح شده است.

### فاز ۱: مسیر پیام و CSRF

- منطق Rate Limiter اصلاح شده و دیگر نتیجه `event()` معیار پذیرش پیام نیست.
- هر پیام یک `message_id` سروری و زمان ارسال دارد.
- پاسخ ارسال پیام به فرستنده برمی‌گردد تا نمایش پیام به رسیدن WebSocket وابسته نباشد.
- توکن CSRF در layout اضافه شده است.
- Echo توکن CSRF را صریح در درخواست `/broadcasting/auth` ارسال می‌کند.

### فاز ۲: امنیت و حساب‌های ناشناس

- محدودیت ارسال هم بر اساس کاربر و هم بر اساس HMAC آدرس کلاینت اعمال می‌شود. IP خام در کلید Cache ذخیره نمی‌شود.
- ثبت حساب ناشناس برای هر کلاینت محدود شده است.
- `last_seen_at` برای پاکسازی حساب‌های موقت اضافه شده است.
- پاکسازی حساب‌های قدیمی دیگر به Database Session وابسته نیست.
- کانال Broadcast بدون مصرف حذف شده است.
- HSTS در Production روی HTTPS، COOP و Headerهای امنیتی پایه اضافه شده‌اند.
- Session محلی به صورت پیش‌فرض روی File قرار گرفته تا جدول Session برای اجرای ساده پروژه ضروری نباشد.

### فاز ۳: وابستگی‌ها و Production

- Laravel به شاخه 13 ارتقا داده شده است.
- Reverb به شاخه پایدار 1.11 ارتقا داده شده و `@beta` حذف شده است.
- Livewire روی شاخه 3.7 نگه داشته شده تا مهاجرت UI کم‌ریسک بماند.
- PHP حداقل 8.3 است.
- Vite، Laravel Vite Plugin، Echo و Pusher به شاخه‌های جاری ارتقا یافته‌اند.
- فایل‌های lock قدیمی حذف شده‌اند چون نسخه‌های آسیب‌پذیر قدیمی را تثبیت می‌کردند. بعد از نصب وابستگی‌ها، lockهای تازه را در مخزن نگه دارید.
- نمونه تنظیمات Production و Docker هماهنگ با MySQL و Redis اضافه شده است.

### فاز ۴: پایداری Realtime و تست

- پیام‌های Broadcast و پاسخ Livewire با `message_id` deduplicate می‌شوند.
- وضعیت اتصال شامل اتصال، قطع، خطا و عدم دسترسی در رابط نمایش داده می‌شود.
- هنگام قطع ارتباط لحظه‌ای ارسال پیام غیرفعال است.
- خطاهای 401، 419، 422، 429 و خطاهای سرور پیام‌های جدا دارند.
- تست‌های ثبت نام، Rate Limit، Presence Channel، Broadcast، خروج، CSRF و پاکسازی حساب‌های قدیمی اضافه شده‌اند.

### فاز ۵: پاکسازی و بهینه‌سازی

- Session Serialization برای Laravel 13 روی JSON قرار گرفته است.
- Seeder آزمایشی حذف شده است.
- Assetهای بدون مصرف و favicon صفر بایت حذف شده‌اند.
- تصویر صفحه ورود به WebP سبک‌تر تبدیل شده است.
- فایل‌های `.env` واقعی، `public/hot`، log و build توسعه در بسته قرار ندارند.

## پیش‌نیازها

- PHP 8.3 یا بالاتر
- Composer 2
- Node.js سازگار با Vite 8، ترجیحاً Node.js 22.12 یا بالاتر
- npm
- SQLite برای توسعه ساده، یا MySQL و Redis برای محیط Production

## نصب توسعه

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install --ignore-scripts
npm run build
php artisan test
```

پس از نخستین نصب موفق، `composer.lock` و `package-lock.json` تولیدشده را در مخزن پروژه ثبت کنید تا Deploymentهای بعدی تکرارپذیر باشند.

اجرای توسعه:

```bash
php artisan serve
php artisan reverb:start
npm run dev
```

## Production

فایل `.env.production.example` را مبنا قرار دهید. مقادیر APP_KEY، دیتابیس و اطلاعات Reverb را با مقادیر واقعی سرور جایگزین کنید.

تنظیمات اصلی Production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chat.example.com
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SERIALIZATION=json
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
REVERB_HOST=chat.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=https://chat.example.com
```

پس از Deployment:

```bash
php artisan migrate --force
php artisan optimize
php artisan reverb:restart
```

Reverb را زیر Supervisor یا systemd اجرا کنید و Reverse Proxy وب‌سرور را برای WebSocket تنظیم کنید.

## Scheduler

برای حذف حساب‌های ناشناس قدیمی Scheduler لاراول باید فعال باشد:

```cron
* * * * * cd /path/to/laravel-chat && php artisan schedule:run >> /dev/null 2>&1
```

## Docker

ابتدا dependencyهای Composer را نصب کنید تا Laravel Sail در `vendor` وجود داشته باشد. سپس:

```bash
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install --ignore-scripts
./vendor/bin/sail npm run build
./vendor/bin/sail artisan test
```

Docker Compose برای اپلیکیشن از MySQL و Redis بهره می‌گیرد و مقادیر توسعه مناسب را به Container تزریق می‌کند.

## کنترل‌های امنیتی مهم

- داده‌های پیام با `textContent` وارد DOM می‌شوند.
- هویت پیام از Session سرور گرفته می‌شود.
- Presence Channel ایمیل یا داده خصوصی کاربر را منتشر نمی‌کند.
- آواتار داخل برنامه تولید می‌شود و درخواست Gravatar وجود ندارد.
- متن تایپ نشده روی WebSocket ارسال نمی‌شود.
- `REVERB_ALLOWED_ORIGINS` باید دامنه واقعی سایت باشد و نباید `*` باشد.
- روی سرور Production مقدار `APP_DEBUG=false` و `SESSION_SECURE_COOKIE=true` نگه داشته شود.
