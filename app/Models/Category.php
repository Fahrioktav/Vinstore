<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    // Relasi: satu kategori memiliki banyak produk
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
