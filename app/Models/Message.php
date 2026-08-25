<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pesan di dalam percakapan pembeli-penjual.
 *
 * Boleh membawa konteks: barang yang ditanyakan, atau pesanan yang
 * dipermasalahkan. Keduanya opsional dan hanya diisi pada pesan pertama yang
 * dikirim dari halaman terkait — pesan susulan di utas yang sama tidak perlu
 * mengulanginya.
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'product_id',
        'order_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'conversation_id',
        'sender_id',
        'product_id',
        'order_id',
    ];

    /**
     * Kartu konteks selalu ikut saat pesan diserialisasi, supaya halaman chat
     * tidak perlu memuat produk/pesanannya sendiri.
     */
    protected $appends = [
        'context',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Keterangan singkat barang/pesanan yang dirujuk pesan ini.
     *
     * Sengaja hanya nama, gambar, dan tautannya — bukan seluruh model. Produk
     * tebak harga yang harganya masih disembunyikan tidak boleh bocor lewat
     * kartu chat, dan cara paling aman adalah tidak pernah mengirim harganya
     * sama sekali dari sini.
     *
     * Mengembalikan null bila produk/pesanannya sudah terhapus — kolomnya
     * `nullOnDelete`, jadi pesannya sendiri tetap terbaca.
     */
    public function getContextAttribute(): ?array
    {
        if ($this->product_id !== null && $this->relationLoaded('product') && $this->product) {
            return [
                'type' => 'product',
                'label' => 'Menanyakan produk',
                'name' => $this->product->name,
                'image' => $this->product->image,
                'url' => '/products/'.$this->product->public_id,
            ];
        }

        if ($this->order_id !== null && $this->relationLoaded('order') && $this->order) {
            // Tanpa tautan dengan sengaja. Halaman invoice hanya boleh dibuka
            // pembelinya, jadi tautan yang sama akan mati di sisi seller —
            // dan nomor pesanannya sudah cukup untuk dicari di dashboard.
            return [
                'type' => 'order',
                'label' => 'Terkait pesanan',
                'name' => $this->order->display_item_name,
                'image' => null,
                'url' => null,
                'reference' => $this->order->public_id,
            ];
        }

        return null;
    }
}
