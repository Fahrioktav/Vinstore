<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Kolom yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'public_id',
        'username',
        'first_name',
        'last_name',
        'email',
        'google_id',
        'phone',
        'address',
        'photo',
        'password',
        'role',
        // Akun tidak pernah dihapus, hanya dinonaktifkan — lihat temuan V4-12
        // dan migrasi 2026_08_09_000008_add_deactivation_to_users_table.
        'deactivated_at',
        'deactivation_reason',
    ];

    /**
     * Kolom yang disembunyikan saat serialisasi.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->public_id)) {
                $user->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) random_int(1000000000, 9999999999);
        } while (DB::table('users')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    /**
     * Tipe data untuk kolom tertentu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Relasi: User memiliki satu Store
     */
    public function store()
    {
        return $this->hasOne(Store::class);
    }

    /* ===================== Penonaktifan akun ===================== */

    /**
     * Akun nonaktif: tidak bisa masuk, sesinya diputus, dan bila ia seorang
     * seller produknya berhenti tampil di etalase.
     *
     * Riwayatnya tetap utuh — itulah sebabnya penonaktifan dipakai alih-alih
     * penghapusan (temuan V4-12).
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isDeactivated();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deactivated_at');
    }

    public function scopeDeactivated($query)
    {
        return $query->whereNotNull('deactivated_at');
    }

    public function deactivate(?string $reason = null): void
    {
        if ($this->isDeactivated()) {
            return;
        }

        $this->forceFill([
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ])->save();
    }

    public function reactivate(): void
    {
        if ($this->isActive()) {
            return;
        }

        $this->forceFill([
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ])->save();
    }

    /**
     * Admin terakhir tidak boleh dinonaktifkan — tidak akan ada lagi yang bisa
     * mengaktifkannya kembali, dan seluruh panel admin jadi tak terjangkau.
     */
    public function canBeDeactivated(): bool
    {
        if ($this->isDeactivated()) {
            return false;
        }

        if ($this->role !== 'admin') {
            return true;
        }

        return self::where('role', 'admin')->active()->count() > 1;
    }

    /**
     * Helper pengecekan role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isValidator(): bool
    {
        return $this->role === 'validator';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Relasi opsional jika user bisa melakukan order (sebagai customer)
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    public function auctionBids()
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function wonAuctions()
    {
        return $this->hasMany(Auction::class, 'winner_id');
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function supportMessages()
    {
        return $this->hasMany(SupportMessage::class, 'user_id');
    }
}
