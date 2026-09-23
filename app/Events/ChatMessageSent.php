<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $user,
        public readonly string $message,
    ) {
    }

    public static function fromUser(User $user, string $message): self
    {
        return new self(
            user: [
                'uuid' => $user->uuid,
                'display_name' => $user->display_name,
                'avatar' => $user->avatar,
            ],
            message: $message,
        );
    }

    public function broadcastOn(): array
    {
        return [new PresenceChannel('lobby')];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => $this->user,
            'message' => $this->message,
        ];
    }
}
