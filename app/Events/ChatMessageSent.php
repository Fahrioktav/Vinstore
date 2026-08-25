<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pesan baru pada percakapan pembeli-penjual.
 *
 * Disiarkan per percakapan, bukan per pengguna: hanya ada dua pihak di dalamnya
 * dan keduanya mendengarkan kanal yang sama.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $conversationPublicId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.'.$this->conversationPublicId);
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing(['sender', 'product', 'order']);

        return [
            'message' => [
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toISOString(),
                'context' => $this->message->context,
                'sender' => [
                    'public_id' => $this->message->sender?->public_id,
                    'username' => $this->message->sender?->username,
                ],
            ],
            'conversation_public_id' => $this->conversationPublicId,
        ];
    }
}
