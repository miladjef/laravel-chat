<?php

namespace Tests\Feature;

use App\Actions\CleanupStaleGuests;
use App\Events\ChatMessageSent;
use App\Livewire\LobbyPage;
use App\Livewire\RegisterPage;
use App\Models\User;
use App\Support\AnonymousClient;
use App\Support\ChatHistory;
use App\Support\DisplayNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        app(ChatHistory::class)->clear();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/auth')->assertOk();
    }

    public function test_layout_contains_csrf_and_noindex_metadata(): void
    {
        $this->get('/auth')
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertSee('name="robots" content="noindex,nofollow,noarchive"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Cache-Control', 'no-store, private, max-age=0');
    }


    public function test_untrusted_host_is_rejected(): void
    {
        $this->withHeader('Host', 'evil.example')
            ->get('/auth')
            ->assertStatus(400);
    }

    public function test_readiness_endpoint_checks_database_and_cache(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'checks' => [
                    'database' => 'ok',
                    'cache' => 'ok',
                ],
            ]);
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

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'presence-lobby',
                'socket_id' => '123.456',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);

        $channelData = json_decode((string) $response->json('channel_data'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($user->uuid, $channelData['user_info']['uuid']);
        $this->assertSame($user->display_name, $channelData['user_info']['display_name']);
        $this->assertArrayNotHasKey('avatar', $channelData['user_info']);
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

    public function test_guest_can_register_with_uuid_normalized_name_and_activity_timestamp(): void
    {
        $component = Livewire::test(RegisterPage::class)
            ->set('display_name', 'Milad Test');

        $this->travel(2)->seconds();

        $component->call('submit')
            ->assertRedirect(route('lobby'));

        $this->assertAuthenticated();

        $user = User::query()->firstOrFail();
        $this->assertSame('Milad Test', $user->display_name);
        $this->assertSame('milad test', $user->normalized_name);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $user->uuid);
        $this->assertNotNull($user->last_seen_at);
        $this->assertFalse(array_key_exists('avatar', $user->getAttributes()));
    }

    public function test_arabic_and_persian_equivalent_names_do_not_collide_visually(): void
    {
        User::factory()->create(['display_name' => 'علي']);

        $component = Livewire::test(RegisterPage::class)
            ->set('display_name', 'علی');

        $this->travel(2)->seconds();
        $component->call('submit');

        $created = User::query()->latest('id')->firstOrFail();
        $this->assertNotSame('علی', $created->display_name);
        $this->assertStringStartsWith('علی ', $created->display_name);
        $this->assertNotSame(DisplayNameNormalizer::normalize('علي'), $created->normalized_name);
    }

    public function test_registration_honeypot_rejects_bot_submission(): void
    {
        $component = Livewire::test(RegisterPage::class)
            ->set('display_name', 'Human Name')
            ->set('website', 'https://spam.example');

        $this->travel(2)->seconds();

        $component->call('submit')
            ->assertHasErrors(['display_name']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_rate_limited_per_anonymous_client(): void
    {
        $key = 'guest-register:'.AnonymousClient::fingerprint();
        RateLimiter::clear($key);

        for ($index = 0; $index < 5; $index++) {
            RateLimiter::hit($key, 600);
        }

        $component = Livewire::test(RegisterPage::class)
            ->set('display_name', 'Guest Six');

        $this->travel(2)->seconds();

        $component->call('submit')
            ->assertHasErrors(['display_name']);
    }

    public function test_authenticated_user_message_is_cached_and_broadcast_with_server_identity(): void
    {
        Event::fake([ChatMessageSent::class]);
        $user = User::factory()->create(['display_name' => 'Sender']);

        $component = Livewire::actingAs($user)
            ->test(LobbyPage::class)
            ->call('sendMessage', '<img src=x onerror=alert(1)>');

        $component->assertReturned(function (array $payload) use ($user): bool {
            return preg_match('/^[0-9a-f-]{36}$/i', $payload['message_id']) === 1
                && $payload['user']['uuid'] === $user->uuid
                && $payload['user']['display_name'] === $user->display_name
                && ! array_key_exists('avatar', $payload['user'])
                && $payload['message'] === '<img src=x onerror=alert(1)>';
        });

        $history = app(ChatHistory::class)->recent();
        $this->assertCount(1, $history);
        $this->assertSame('<img src=x onerror=alert(1)>', $history[0]['message']);

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($user): bool {
            return $event->user['uuid'] === $user->uuid
                && $event->user['display_name'] === $user->display_name
                && ! array_key_exists('avatar', $event->user)
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

    public function test_heartbeat_refreshes_activity_and_presence_marker(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subHour()]);

        Livewire::actingAs($user)
            ->test(LobbyPage::class)
            ->call('heartbeat');

        $user->refresh();
        $this->assertTrue($user->last_seen_at->greaterThan(now()->subMinute()));
        $this->assertTrue(Cache::has('chat:presence:'.$user->uuid));
    }

    public function test_logout_deletes_ephemeral_guest_account_and_presence_marker(): void
    {
        $user = User::factory()->create();
        Cache::put('chat:presence:'.$user->uuid, now()->timestamp, 600);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertFalse(Cache::has('chat:presence:'.$user->uuid));
    }

    public function test_stale_guest_cleanup_keeps_recent_and_heartbeat_active_users(): void
    {
        $recent = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
        $activeButOld = User::factory()->create(['last_seen_at' => now()->subDays(2)]);
        $stale = User::factory()->create(['last_seen_at' => now()->subDays(2)]);
        $neverSeen = User::factory()->create([
            'last_seen_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        Cache::put('chat:presence:'.$activeButOld->uuid, now()->timestamp, 600);

        $deleted = app(CleanupStaleGuests::class)();

        $this->assertSame(2, $deleted);
        $this->assertDatabaseHas('users', ['id' => $recent->id]);
        $this->assertDatabaseHas('users', ['id' => $activeButOld->id]);
        $this->assertDatabaseMissing('users', ['id' => $stale->id]);
        $this->assertDatabaseMissing('users', ['id' => $neverSeen->id]);
    }

    public function test_chat_history_has_a_short_ttl(): void
    {
        config(['chat.history_ttl_minutes' => 1]);

        app(ChatHistory::class)->push([
            'message_id' => '12345678-1234-1234-1234-123456789abc',
            'user' => ['uuid' => '12345678-1234-1234-1234-123456789abc', 'display_name' => 'Test'],
            'message' => 'hello',
            'sent_at' => now()->toIso8601String(),
        ]);

        $this->assertCount(1, app(ChatHistory::class)->recent());
        $this->travel(2)->minutes();
        $this->assertSame([], app(ChatHistory::class)->recent());
    }

    public function test_chat_template_uses_text_content_deduplication_heartbeat_and_dom_cap(): void
    {
        $template = file_get_contents(resource_path('views/livewire/lobby-page.blade.php'));

        $this->assertStringNotContainsString('innerHTML', $template);
        $this->assertStringNotContainsString("listenForWhisper('new-message'", $template);
        $this->assertStringContainsString('text.textContent = message', $template);
        $this->assertStringContainsString('seenMessageIds.has(payload.message_id)', $template);
        $this->assertStringContainsString('$wire.heartbeat()', $template);
        $this->assertStringContainsString('trimMessageDom()', $template);
        $this->assertStringContainsString('formatMessageTime', $template);
        $this->assertStringNotContainsString('data:image/svg+xml;base64,', $template);
    }
}
