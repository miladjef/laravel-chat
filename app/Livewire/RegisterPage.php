<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
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
                ]);
                break;
            } catch (QueryException $exception) {
                if (! str_starts_with((string) $exception->getCode(), '23') || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        auth()->login($user);
        request()->session()->regenerate();

        $this->redirectRoute('lobby', navigate: true);
    }
}
