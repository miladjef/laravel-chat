<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('ورود به اوکیو چت')]
class RegisterPage extends Component
{
    /**
     * User display name
     *
     * @var string $display_name
     */
    #[Rule(['required', 'string', 'min:3', 'max:16'])]
    public string $display_name = '';

    /**
     * Register a new user and redirect it to lobby page
     *
     * @return void
     */
    public function submit(): void
    {
        $this->validate();

        $address = md5(Str::random(8) . '-' . md5($this->display_name) . '-' . time());

        $exists = User::whereDisplayName(trim($this->display_name))->first();

        $user = User::create(
            [
                'display_name' => trim(trim($this->display_name) . ' ' . ($exists ? $this->randomId() : '')),
                'email'        => "$address@okkio.chat",
            ]
        );

        auth()->login($user);

        $user->update(
            [
                'avatar' => gravatar(),
            ]
        );

        $this->redirectRoute('lobby', navigate: true);
    }

    /**
     * Get random int id
     *
     * @return int
     */
    private function randomId(): int
    {
        try
        {
            return random_int(10000, 99999);
        }
        catch (Throwable)
        {
            return $this->randomId();
        }
    }
}
