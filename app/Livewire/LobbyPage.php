<?php

namespace App\Livewire;

use App\Events\ChatMessageSent;
use App\Support\AnonymousClient;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('تالار گفت‌وگوی اوکیو')]
class LobbyPage extends Component
{
    #[Computed]
    public function systemMessages(): array
    {
        return [
            'سلااام 👋',
            'به تالار گفت‌وگوی اوکیو خوش اومدید 🔥',
            'در این محیط به صورت ناشناس گفت‌وگو می‌کنیم 🍃',
            'برای حفظ امنیت و حریم خصوصی، اطلاعات حساس و شخصی خودت یا دیگران را منتشر نکن ❤️',
            'برای تغییر تم شخصی، «دارک» یا «لایت» را ارسال کن 🌚',
        ];
    }

    public function sendMessage(string $message): array
    {
        $this->skipRender();

        $user = auth()->user();
        abort_unless($user, 401);

        $message = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '');

        $userKey = 'chat-message:user:'.$user->id;
        $clientKey = 'chat-message:client:'.AnonymousClient::fingerprint();

        if (RateLimiter::tooManyAttempts($userKey, 8) || RateLimiter::tooManyAttempts($clientKey, 20)) {
            throw ValidationException::withMessages([
                'message' => 'تعداد پیام‌ها زیاد است. چند ثانیه بعد دوباره ارسال کن.',
            ]);
        }

        RateLimiter::hit($userKey, 10);
        RateLimiter::hit($clientKey, 10);

        validator(
            ['message' => $message],
            ['message' => ['required', 'string', 'max:500']],
            [
                'message.required' => 'پیام خالی است.',
                'message.max' => 'حداکثر طول پیام ۵۰۰ نویسه است.',
            ]
        )->validate();

        $event = ChatMessageSent::fromUser($user, $message);
        event($event);

        return $event->broadcastWith();
    }
}
