<?php

namespace App\Livewire;

use App\Events\ChatMessageSent;
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

    public function sendMessage(string $message): void
    {
        $this->skipRender();

        $user = auth()->user();
        abort_unless($user, 401);

        $message = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '');

        validator(
            ['message' => $message],
            ['message' => ['required', 'string', 'max:500']],
            [
                'message.required' => 'پیام خالی است.',
                'message.max' => 'حداکثر طول پیام ۵۰۰ نویسه است.',
            ]
        )->validate();

        $rateLimitKey = 'chat-message:'.$user->id;
        $accepted = RateLimiter::attempt(
            $rateLimitKey,
            8,
            fn () => event(ChatMessageSent::fromUser($user, $message)),
            10,
        );

        if (! $accepted) {
            throw ValidationException::withMessages([
                'message' => 'تعداد پیام‌ها زیاد است. چند ثانیه بعد دوباره ارسال کن.',
            ]);
        }
    }
}
