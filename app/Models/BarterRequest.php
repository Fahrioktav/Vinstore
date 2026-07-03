<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BarterRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'public_id',
        'requester_store_id',
        'responder_store_id',
        'offered_product_id',
        'requested_product_id',
        'additional_cash',
        'note',
        'status',
        'responded_at',
    ];

    protected $hidden = [
        'id',
        'requester_store_id',
        'responder_store_id',
        'offered_product_id',
        'requested_product_id',
    ];

    protected $casts = [
        'additional_cash' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BarterRequest $barter) {
            if (empty($barter->public_id)) {
                $barter->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'BRT' . random_int(10000000, 99999999);
        } while (DB::table('barter_requests')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function requesterStore()
    {
        return $this->belongsTo(Store::class, 'requester_store_id');
    }

    public function responderStore()
    {
        return $this->belongsTo(Store::class, 'responder_store_id');
    }

    public function offeredProduct()
    {
        return $this->belongsTo(Product::class, 'offered_product_id');
    }

    public function requestedProduct()
    {
        return $this->belongsTo(Product::class, 'requested_product_id');
    }
}
