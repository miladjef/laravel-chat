<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\AnonymousClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('ورود به اوکیو چت')]
class RegisterPage extends Component
{
    #[Rule(['required', 'string', 'min:3', 'max:16'])]
    public string $display_name = '';

    public function submit(): void
    {
        $this->validate();

        $rateLimitKey = 'guest-register:'.AnonymousClient::fingerprint();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            throw ValidationException::withMessages([
                'display_name' => 'تعداد ورودهای جدید زیاد است. چند دقیقه بعد دوباره تلاش کن.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 600);

        $baseName = preg_replace('/\s+/u', ' ', trim($this->display_name)) ?: '';

        validator(
            ['display_name' => $baseName],
            ['display_name' => ['required', 'string', 'min:3', 'max:16', 'regex:/^[\p{L}\p{N}_\-\s]+$/u']],
            [
                'display_name.regex' => 'نام نمایشی شامل نویسه نامعتبر است.',
            ]
        )->validate();

        $user = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $suffix = $attempt === 0 ? '' : ' '.random_int(1000, 9999);
            $maxBaseLength = 16 - mb_strlen($suffix);
            $displayName = mb_substr($baseName, 0, max(1, $maxBaseLength)).$suffix;
            $uuid = (string) Str::uuid();

            try {
                $user = User::create([
                    'uuid' => $uuid,
                    'display_name' => $displayName,
                    'avatar' => avatar_data_uri($uuid),
                    'last_seen_at' => now(),
                ]);
                break;
            } catch (QueryException $exception) {
                if (! str_starts_with((string) $exception->getCode(), '23') || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'display_name' => 'ایجاد شناسه انجام نشد. دوباره تلاش کن.',
            ]);
        }

        auth()->login($user);
        request()->session()->regenerate();

        $this->redirectRoute('lobby', navigate: true);
    }
}
