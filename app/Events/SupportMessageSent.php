<?php

namespace App\Events;

use App\Models\SupportMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportMessage $message,
        public string $ownerPublicId,
    ) {}

    /**
     * Channel privat per-thread bantuan (diidentifikasi oleh public_id pemilik).
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('support.'.$this->ownerPublicId);
    }

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return [
            'message' => [
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toISOString(),
                'sender' => [
                    'public_id' => $this->message->sender?->public_id,
                    'username' => $this->message->sender?->username,
                    'role' => $this->message->sender?->role,
                ],
            ],
            'owner_public_id' => $this->ownerPublicId,
        ];
    }
}
