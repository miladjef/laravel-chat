<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $messageId,
        public readonly array $user,
        public readonly string $message,
        public readonly string $sentAt,
    ) {
    }

    public static function fromUser(User $user, string $message): self
    {
        return new self(
            messageId: (string) Str::uuid(),
            user: [
                'uuid' => $user->uuid,
                'display_name' => $user->display_name,
            ],
            message: $message,
            sentAt: now()->toIso8601String(),
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

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'user' => $this->user,
            'message' => $this->message,
            'sent_at' => $this->sentAt,
        ];
    }
}
