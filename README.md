# laravel Chat

laravel Chat یک تالار گفت‌وگوی ناشناس و بلادرنگ بر پایه Laravel، Livewire، Reverb و Vite است.

Programmer: Miladjef

## اصلاحات امنیتی و فنی این نسخه

- مسیر ارسال پیام از Whisper مرورگر به Broadcast سمت سرور منتقل شده است.
- هویت فرستنده پیام از نشست احراز هویت سمت سرور خوانده می‌شود و کلاینت قادر به تعیین نام یا UUID فرستنده نیست.
- درج پیام، نام کاربر و آواتار با DOM API انجام می‌شود و `innerHTML` برای داده‌های کاربران حذف شده است.
- محدودیت ارسال پیام روی ۸ پیام در ۱۰ ثانیه اعمال شده است.
- طول هر پیام به ۵۰۰ نویسه محدود شده است.
- Whisper مربوط به متن در حال تایپ حذف شده است و متن قبل از ارسال روی WebSocket منتشر نمی‌شود.
- قابلیت تغییر تم همگانی حذف شده است. فرمان‌های `دارک` و `لایت` فقط روی مرورگر همان کاربر اثر دارند.
- Gravatar و درخواست خارجی آواتار حذف شده‌اند. آواتار SVG به صورت محلی و بر اساس UUID تولید می‌شود.
- Presence Channel دیگر ایمیل داخلی کاربر را منتشر نمی‌کند.
- برای هر کاربر UUID مستقل ثبت می‌شود.
- روی نام نمایشی Unique Index قرار گرفته و برخورد همزمان نام‌های تکراری کنترل می‌شود.
- حساب ناشناس هنگام خروج حذف می‌شود و پاکسازی دوره‌ای حساب‌ها و نشست‌های منقضی نیز تعریف شده است.
- `allowed_origins` برای Reverb از متغیر محیطی `REVERB_ALLOWED_ORIGINS` خوانده می‌شود و wildcard حذف شده است.
- Headerهای امنیتی پایه شامل `nosniff`، `DENY`، Referrer Policy و Permissions Policy اضافه شده‌اند.
- رابط Lobby برای موبایل و دسکتاپ اصلاح شده است.
- فونت ناموجود پروژه با stack سیستمی جایگزین شده است.
- تصویر صفحه ورود دیگر به Vite manifest وابسته نیست.
- فایل `public/hot`، لاگ توسعه و `.env` از بسته نهایی حذف شده‌اند.
- دیتابیس SQLite قدیمی و ناسازگار پاک شده و فایل SQLite خالی برای اجرای migration قرار گرفته است.
- تست‌های Feature برای ورود، ثبت کاربر، نام تکراری، Broadcast پیام، محدودیت طول، خروج و جلوگیری از بازگشت `innerHTML` اضافه شده‌اند.

## پیش‌نیازها

- PHP 8.2 یا بالاتر برای نسخه فعلی dependency lock
- Composer
- Node.js و npm
- افزونه‌های PHP موردنیاز Laravel و SQLite یا یک پایگاه داده دیگر

## نصب

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
```

برای محیط Production، مقادیر دامنه و Reverb را در `.env` با مقادیر واقعی جایگزین کنید و `SESSION_SECURE_COOKIE=true` قرار دهید:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chat.example.com
SESSION_SECURE_COOKIE=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=chat.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=https://chat.example.com

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

اجرای محیط توسعه:

```bash
php artisan serve
php artisan reverb:start
npm run dev
```

برای Production، Reverb را زیر Supervisor یا systemd اجرا کنید و WebSocket Proxy را در Nginx یا وب‌سرور تنظیم کنید.

## Scheduler

برای پاکسازی کاربران ناشناس و نشست‌های منقضی، Scheduler لاراول باید فعال باشد:

```cron
* * * * * cd /path/to/Laravel-chat && php artisan schedule:run >> /dev/null 2>&1
```

