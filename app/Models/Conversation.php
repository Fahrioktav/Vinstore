<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Percakapan antara seorang pembeli dan sebuah toko.
 *
 * Satu utas per pasangan (toko, pembeli) — bukan satu utas per barang. Itulah
 * yang sudah ditetapkan skema `conversations` lewat unique(store_id, buyer_id),
 * dan itu pula yang dikenal pengguna dari marketplace lain: membuka kembali
 * percakapan lama dengan penjual yang sama, bukan mencari-cari utas mana yang
 * dulu dipakai membahas guci tertentu.
 *
 * Barang atau pesanan yang sedang dibicarakan menempel pada masing-masing
 * PESAN, bukan pada percakapannya. Lihat Message::product/order.
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'store_id',
        'buyer_id',
        'last_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'store_id',
        'buyer_id',
        'last_message_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            if (empty($conversation->public_id)) {
                $conversation->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'CNV'.random_int(10000000, 99999999);
        } while (DB::table('conversations')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Ambil percakapan yang sudah ada, atau buat bila belum pernah ada.
     *
     * Dikunci dalam transaksi karena dua permintaan yang datang hampir
     * bersamaan — pembeli menekan "Chat Penjual" dua kali, atau membukanya dari
     * dua tab — sama-sama menemukan utasnya belum ada lalu sama-sama membuatnya.
     * Yang kedua akan menabrak unique(store_id, buyer_id) dan gagal dengan galat
     * basis data alih-alih membuka percakapan yang sudah ada.
     */
    public static function between(Store $store, User $buyer): self
    {
        return DB::transaction(function () use ($store, $buyer) {
            $conversation = self::where('store_id', $store->id)
                ->where('buyer_id', $buyer->id)
                ->lockForUpdate()
                ->first();

            return $conversation ?? self::create([
                'store_id' => $store->id,
                'buyer_id' => $buyer->id,
            ]);
        });
    }

    /**
     * Apakah pengguna ini boleh membuka percakapan tersebut?
     *
     * Dua pihak saja: pembelinya sendiri, dan pemilik toko yang diajak bicara.
     * Admin sengaja TIDAK termasuk — percakapan jual beli bukan tiket bantuan,
     * dan chat bantuan ke admin sudah punya jalurnya sendiri.
     */
    public function isParticipant(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->buyer_id === $user->id
            || $this->store?->user_id === $user->id;
    }

    /**
     * Catat pesan terakhir supaya daftar percakapan bisa diurutkan dan
     * menampilkan cuplikannya tanpa memuat seluruh pesan tiap utas.
     */
    public function touchLastMessage(Message $message): void
    {
        $this->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ])->save();
    }

    /**
     * Jumlah pesan yang belum dibaca oleh pengguna ini — yaitu pesan yang
     * ditulis pihak lawan dan belum pernah dibuka.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
