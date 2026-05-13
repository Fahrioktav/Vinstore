<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Auction extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'store_id',
        'winner_id',
        'name',
        'description',
        'image',
        'starting_price',
        'min_increment',
        'current_price',
        'bids_count',
        'approval_status',
        'status',
        'starts_at',
        'ends_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'ended_at',
    ];

    protected $hidden = [
        'id',
        'store_id',
        'winner_id',
        'approved_by',
    ];

    protected $casts = [
        'starting_price' => 'decimal:2',
        'min_increment' => 'decimal:2',
        'current_price' => 'decimal:2',
        'bids_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approved_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Auction $auction) {
            if (empty($auction->public_id)) {
                $auction->public_id = self::generatePublicId();
            }

            if ($auction->current_price === null) {
                $auction->current_price = $auction->starting_price;
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'AUC' . random_int(10000000, 99999999);
        } while (DB::table('auctions')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeVisible($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function bids()
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function highestBid()
    {
        return $this->hasOne(AuctionBid::class)->latestOfMany('amount');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function isActive(): bool
    {
        return $this->approval_status === 'approved'
            && $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }
}
