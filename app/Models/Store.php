<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'store_name',
        'category',
        'description',
        'location',
        'latitude',
        'longitude',
        'photo',
        'available_balance',
        'withdrawn_balance',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'withdrawn_balance' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->public_id)) {
                $store->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'STR' . random_int(10000000, 99999999);
        } while (DB::table('stores')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class);
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function barterRequestsSent()
    {
        return $this->hasMany(BarterRequest::class, 'requester_store_id');
    }

    public function barterRequestsReceived()
    {
        return $this->hasMany(BarterRequest::class, 'responder_store_id');
    }
}
