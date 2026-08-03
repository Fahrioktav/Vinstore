<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PriceGuess extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'product_id',
        'user_id',
        'amount',
    ];

    protected $hidden = [
        'id',
        'product_id',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PriceGuess $guess) {
            if (empty($guess->public_id)) {
                $guess->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'PGS'.random_int(10000000, 99999999);
        } while (DB::table('price_guesses')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
