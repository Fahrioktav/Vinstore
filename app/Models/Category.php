<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'name',
        'image',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->public_id)) {
                $category->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'CAT' . random_int(10000000, 99999999);
        } while (DB::table('categories')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    // Relasi: satu kategori memiliki banyak produk
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
