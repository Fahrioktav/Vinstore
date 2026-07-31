<?php

namespace App\Events;

use App\Models\AuctionBid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionBidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bid;
    public $auctionData;

    /**
     * Create a new event instance.
     */
    public function __construct(AuctionBid $bid, array $auctionData)
    {
        $this->bid = $bid;
        $this->auctionData = $auctionData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('auction.' . $this->bid->auction_id);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'AuctionBidPlaced';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'bid' => [
                'id' => $this->bid->id,
                'amount' => $this->bid->amount,
                'user' => [
                    'public_id' => $this->bid->user->public_id,
                    'name' => $this->bid->user->name,
                ],
                'created_at' => $this->bid->created_at->toISOString(),
            ],
            'auction' => $this->auctionData,
        ];
    }
}
