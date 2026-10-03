<?php

namespace Tests\Feature;

use App\Actions\CleanupStaleGuests;
use App\Events\ChatMessageSent;
use App\Livewire\LobbyPage;
use App\Livewire\RegisterPage;
use App\Models\User;
use App\Support\AnonymousClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('guest-register:'.AnonymousClient::fingerprint());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/auth')->assertOk();
    }

    public function test_layout_contains_csrf_token_for_broadcast_authentication(): void
    {
        $this->get('/auth')
            ->assertOk()
            ->assertSee('name="csrf-token"', false);
    }

    public function test_authenticated_user_can_authorize_lobby_presence_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => 'test-app',
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
            ],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'presence-lobby',
                'socket_id' => '123.456',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }

    public function test_guest_cannot_authorize_lobby_presence_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => 'test-app',
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => 8080,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
            ],
        ]);

        $this->post('/broadcasting/auth', [
            'channel_name' => 'presence-lobby',
            'socket_id' => '123.456',
        ])->assertForbidden();
    }

    public function test_guest_can_register_with_local_avatar_uuid_and_activity_timestamp(): void
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
        $this->assertNotNull($user->last_seen_at);
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

    public function test_registration_is_rate_limited_per_anonymous_client(): void
    {
        $key = 'guest-register:'.AnonymousClient::fingerprint();
        RateLimiter::clear($key);

        for ($index = 1; $index <= 5; $index++) {
            Livewire::test(RegisterPage::class)
                ->set('display_name', 'Guest '.$index)
                ->call('submit');
        }

        Livewire::test(RegisterPage::class)
            ->set('display_name', 'Guest 6')
            ->call('submit')
            ->assertHasErrors(['display_name']);
    }

    public function test_authenticated_user_message_is_broadcast_with_server_identity_and_message_id(): void
    {
        Event::fake([ChatMessageSent::class]);
        $user = User::factory()->create(['display_name' => 'Sender']);

        $component = Livewire::actingAs($user)
            ->test(LobbyPage::class)
            ->call('sendMessage', '<img src=x onerror=alert(1)>');

        $component->assertReturned(function (array $payload) use ($user): bool {
            return preg_match('/^[0-9a-f-]{36}$/i', $payload['message_id']) === 1
                && $payload['user']['uuid'] === $user->uuid
                && $payload['message'] === '<img src=x onerror=alert(1)>';
        });

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($user): bool {
            return $event->user['uuid'] === $user->uuid
                && $event->user['display_name'] === $user->display_name
                && $event->message === '<img src=x onerror=alert(1)>'
                && preg_match('/^[0-9a-f-]{36}$/i', $event->messageId) === 1;
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

    public function test_message_rate_limit_rejects_ninth_message_in_ten_seconds(): void
    {
        Event::fake([ChatMessageSent::class]);
        $user = User::factory()->create();
        $userKey = 'chat-message:user:'.$user->id;
        $clientKey = 'chat-message:client:'.AnonymousClient::fingerprint();
        RateLimiter::clear($userKey);
        RateLimiter::clear($clientKey);

        $component = Livewire::actingAs($user)->test(LobbyPage::class);

        for ($index = 1; $index <= 8; $index++) {
            $component->call('sendMessage', 'message '.$index)->assertHasNoErrors();
        }

        $component->call('sendMessage', 'message 9')->assertHasErrors(['message']);
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

    public function test_stale_guest_cleanup_keeps_recent_users_and_deletes_old_users(): void
    {
        $recent = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
        $stale = User::factory()->create(['last_seen_at' => now()->subDays(2)]);
        $neverSeen = User::factory()->create([
            'last_seen_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $deleted = app(CleanupStaleGuests::class)();

        $this->assertSame(2, $deleted);
        $this->assertDatabaseHas('users', ['id' => $recent->id]);
        $this->assertDatabaseMissing('users', ['id' => $stale->id]);
        $this->assertDatabaseMissing('users', ['id' => $neverSeen->id]);
    }

    public function test_chat_template_uses_text_content_and_message_deduplication(): void
    {
        $template = file_get_contents(resource_path('views/livewire/lobby-page.blade.php'));

        $this->assertStringNotContainsString('innerHTML', $template);
        $this->assertStringNotContainsString("listenForWhisper('new-message'", $template);
        $this->assertStringContainsString('text.textContent = message', $template);
        $this->assertStringContainsString('seenMessageIds.has(payload.message_id)', $template);
        $this->assertStringContainsString('realtime-status', $template);
    }
}
