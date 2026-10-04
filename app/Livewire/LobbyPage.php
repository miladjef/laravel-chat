<?php

namespace App\Livewire;

use App\Events\ChatMessageSent;
use App\Support\AnonymousClient;
use App\Support\ChatHistory;
use Illuminate\Support\Facades\Cache;
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
            'در این محیط با شناسه موقت گفت‌وگو می‌کنیم 🍃',
            'برای حفظ امنیت و حریم خصوصی، اطلاعات حساس و شخصی خودت یا دیگران را منتشر نکن ❤️',
            'برای تغییر تم شخصی، «دارک» یا «لایت» را ارسال کن 🌚',
        ];
    }

    public function recentMessages(): array
    {
        return app(ChatHistory::class)->recent();
    }

    public function heartbeat(): void
    {
        $this->skipRender();

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        abort_unless($user, 401);

        Cache::put(
            'chat:presence:'.$user->uuid,
            now()->getTimestamp(),
            now()->addSeconds(max(60, (int) config('chat.heartbeat_seconds', 180) * 3)),
        );

        if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(2))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    public function sendMessage(string $message): array
    {
        $this->skipRender();

        /** @var \App\Models\User|null $user */
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

        validator(
            ['message' => $message],
            ['message' => ['required', 'string', 'max:500']],
            [
                'message.required' => 'پیام خالی است.',
                'message.max' => 'حداکثر طول پیام ۵۰۰ نویسه است.',
            ]
        )->validate();

        RateLimiter::hit($userKey, 10);
        RateLimiter::hit($clientKey, 10);

        $event = ChatMessageSent::fromUser($user, $message);
        $payload = $event->broadcastWith();

        event($event);
        app(ChatHistory::class)->push($payload);
        $this->heartbeat();

        return $payload;
    }
}
