<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Livewire\LobbyPage;
use App\Livewire\RegisterPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/auth')->assertOk();
    }

    public function test_guest_can_register_with_local_avatar_and_uuid(): void
    {
        Livewire::test(RegisterPage::class)
            ->set('display_name', 'Milad Test')
            ->call('submit')
            ->assertRedirect(route('lobby'));

        $this->assertAuthenticated();

        $user = User::query()->firstOrFail();
        $this->assertSame('Milad Test', $user->display_name);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $user->uuid);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $user->avatar);
    }

    public function test_duplicate_display_name_gets_a_safe_suffix(): void
    {
        User::factory()->create(['display_name' => 'Same Name']);

        Livewire::test(RegisterPage::class)
            ->set('display_name', 'Same Name')
            ->call('submit');

        $created = User::query()->latest('id')->firstOrFail();
        $this->assertNotSame('Same Name', $created->display_name);
        $this->assertLessThanOrEqual(16, mb_strlen($created->display_name));
    }

    public function test_authenticated_user_message_is_broadcast_with_server_identity(): void
    {
        Event::fake([ChatMessageSent::class]);
        $user = User::factory()->create(['display_name' => 'Sender']);

        Livewire::actingAs($user)
            ->test(LobbyPage::class)
            ->call('sendMessage', '<img src=x onerror=alert(1)>');

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($user): bool {
            return $event->user['uuid'] === $user->uuid
                && $event->user['display_name'] === $user->display_name
                && $event->message === '<img src=x onerror=alert(1)>';
        });
    }

    public function test_message_length_is_limited(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(LobbyPage::class)
            ->call('sendMessage', str_repeat('a', 501))
            ->assertHasErrors(['message']);
    }

    public function test_logout_deletes_ephemeral_guest_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_chat_template_does_not_use_unsafe_message_inner_html_or_message_whispers(): void
    {
        $template = file_get_contents(resource_path('views/livewire/lobby-page.blade.php'));

        $this->assertStringNotContainsString('innerHTML', $template);
        $this->assertStringNotContainsString("listenForWhisper('new-message'", $template);
        $this->assertStringNotContainsString("whisper(\n                'new-message'", $template);
    }
}
