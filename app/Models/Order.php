<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'product_id',
        'auction_id',
        'quantity',
        'price',
        'status',
        'payment_reference',
        'payment_status',
        'payment_method',
        'midtrans_transaction_id',
        'snap_token',
        'snap_redirect_url',
        'paid_at',
        'stock_restored_at',
        'seller_released_at',
        'store_id',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'seller_released_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->public_id)) {
                $order->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'ORD' . random_int(10000000, 99999999);
        } while (DB::table('orders')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    // Relasi ke produk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function refundRequest()
    {
        return $this->hasOne(RefundRequest::class);
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

    public function restoreReservedStock(): void
    {
        if ($this->stock_restored_at !== null) {
            return;
        }

        DB::transaction(function () {
            $order = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (!$order || $order->stock_restored_at !== null) {
                return;
            }

            if ($order->product_id !== null) {
                Product::whereKey($order->product_id)->increment('stock', $order->quantity);
            }

            $order->forceFill([
                'stock_restored_at' => now(),
            ])->save();
        });
    }

    public function releaseSellerFunds(): void
    {
        if ($this->seller_released_at !== null) {
            return;
        }

        DB::transaction(function () {
            $order = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (
                !$order
                || $order->seller_released_at !== null
                || $order->payment_status !== 'paid'
                || !in_array($order->status, ['Delivered', 'Completed'], true)
            ) {
                return;
            }

            Store::whereKey($order->store_id)->increment('available_balance', $order->price);

            $order->forceFill([
                'seller_released_at' => now(),
            ])->save();
        });
    }
}
