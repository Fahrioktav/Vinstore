<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'store_id',
        'name',
        'stock',
        'price',
        'category',
        'description',
        'image',
        'certificate',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'stock' => 'integer',
        'price' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->public_id)) {
                $product->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'PRD' . random_int(10000000, 99999999);
        } while (DB::table('products')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    // Mutator untuk memastikan stock tidak pernah negatif
    public function setStockAttribute($value)
    {
        $this->attributes['stock'] = max(0, (int)$value);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
