<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cart extends Model
{
    protected $fillable = ['public_id', 'user_id', 'product_id', 'quantity'];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Cart $cart) {
            if (empty($cart->public_id)) {
                $cart->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'CRT' . random_int(10000000, 99999999);
        } while (DB::table('carts')->where('public_id', $publicId)->exists());

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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
