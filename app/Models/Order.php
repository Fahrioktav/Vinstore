<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price',
        'status',
        'store_id',
    ];

    // Relasi ke produk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relasi ke user (customer)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke store 
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
