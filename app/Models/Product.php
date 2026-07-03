<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    /**
     * Status alur approval produk (dua tahap).
     * Alur: PENDING_VALIDATOR -> PENDING_ADMIN -> APPROVED
     *        (atau REJECTED pada salah satu tahap)
     */
    public const STATUS_PENDING_VALIDATOR = 'pending_validator';
    public const STATUS_PENDING_ADMIN = 'pending_admin';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Jenis penjualan produk.
     */
    public const SALE_TYPE_NORMAL = 'normal';
    public const SALE_TYPE_TEBAK_HARGA = 'tebak_harga';

    /**
     * Siklus hidup fitur tebak harga (setelah produk disetujui admin):
     *   scheduled -> active -> ended -> public
     */
    public const GUESS_SCHEDULED = 'scheduled';
    public const GUESS_ACTIVE = 'active';
    public const GUESS_ENDED = 'ended';
    public const GUESS_PUBLIC = 'public';

    /**
     * Lama hak prioritas pembelian bagi pemenang tebak harga (dalam jam).
     */
    public const WINNER_PRIORITY_HOURS = 24;

    protected $fillable = [
        'public_id',
        'store_id',
        'name',
        'stock',
        'price',
        'category',
        'description',
        'image',
        'images',
        'video',
        'certificate',
        'is_barterable',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'validated_at',
        'validated_by',
        'sale_type',
        'guess_starts_at',
        'guess_ends_at',
        'guess_status',
        'guess_winner_id',
        'guess_winning_amount',
        'guess_finished_at',
        'winner_priority_until',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'stock' => 'integer',
        'price' => 'decimal:2',
        'images' => 'array',
        'is_barterable' => 'boolean',
        'approved_at' => 'datetime',
        'validated_at' => 'datetime',
        'guess_starts_at' => 'datetime',
        'guess_ends_at' => 'datetime',
        'guess_finished_at' => 'datetime',
        'winner_priority_until' => 'datetime',
        'guess_winning_amount' => 'decimal:2',
    ];

    /**
     * Produk yang dapat ditawarkan/diajukan untuk barter:
     * sudah disetujui, masih punya stok, dan ditandai bisa dibarter oleh seller.
     * (Produk yang sudah terjual/stok habis otomatis tidak masuk.)
     */
    public function scopeBarterable($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED)
            ->where('is_barterable', true)
            ->where('stock', '>', 0);
    }

    /**
     * Produk yang sudah lolos kedua tahap dan tampil ke buyer.
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    /**
     * Produk yang menunggu validasi dari validator barang antik.
     */
    public function scopePendingValidator($query)
    {
        return $query->where('approval_status', self::STATUS_PENDING_VALIDATOR);
    }

    /**
     * Produk yang sudah divalidasi validator dan menunggu persetujuan admin.
     */
    public function scopePendingAdmin($query)
    {
        return $query->where('approval_status', self::STATUS_PENDING_ADMIN);
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

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function barterRequestsOffered()
    {
        return $this->hasMany(BarterRequest::class, 'offered_product_id');
    }

    public function barterRequestsRequested()
    {
        return $this->hasMany(BarterRequest::class, 'requested_product_id');
    }

    /* ===================== Tebak Harga ===================== */

    public function priceGuesses()
    {
        return $this->hasMany(PriceGuess::class);
    }

    public function guessWinner()
    {
        return $this->belongsTo(User::class, 'guess_winner_id');
    }

    /**
     * Apakah produk ini dijual dengan jenis penjualan Tebak Harga.
     */
    public function isTebakHarga(): bool
    {
        return $this->sale_type === self::SALE_TYPE_TEBAK_HARGA;
    }

    /**
     * Apakah periode tebak harga sedang berjalan (pembeli boleh menebak).
     */
    public function isGuessingOpen(): bool
    {
        return $this->isTebakHarga()
            && $this->approval_status === self::STATUS_APPROVED
            && $this->guess_status === self::GUESS_ACTIVE
            && $this->guess_starts_at
            && $this->guess_ends_at
            && now()->between($this->guess_starts_at, $this->guess_ends_at);
    }

    /**
     * Apakah hak prioritas pemenang masih berlaku.
     */
    public function isWinnerPriorityActive(): bool
    {
        return $this->guess_status === self::GUESS_ENDED
            && $this->winner_priority_until
            && now()->lt($this->winner_priority_until);
    }

    /**
     * Harga asli WAJIB disembunyikan dari pembeli selama periode tebak harga
     * dan masa hak prioritas (kecuali pemenang yang sedang punya prioritas).
     * Saat status sudah 'public' atau produk normal, harga ditampilkan.
     */
    public function shouldHidePriceFor(?User $user): bool
    {
        if (!$this->isTebakHarga()) {
            return false;
        }

        return match ($this->guess_status) {
            self::GUESS_SCHEDULED, self::GUESS_ACTIVE => true,
            self::GUESS_ENDED => !($user && $this->guess_winner_id === $user->id),
            default => false, // public / null
        };
    }

    /**
     * Sembunyikan kolom harga asli dari serialisasi untuk viewer yang tidak berhak.
     * Dipakai pada endpoint publik (marketplace, detail produk) sebelum dikirim ke Inertia.
     */
    public function maskRealPriceFor(?User $user): self
    {
        if ($this->shouldHidePriceFor($user)) {
            $this->makeHidden(['price']);
        }

        return $this;
    }

    /**
     * Apakah produk dapat dibeli oleh viewer saat ini.
     * - Produk normal: selalu boleh (mengikuti aturan stok di controller).
     * - Tebak harga: hanya pemenang selama masa prioritas (status ended),
     *   atau siapa saja saat status sudah public.
     */
    public function isPurchasableBy(?User $user): bool
    {
        if (!$this->isTebakHarga()) {
            return true;
        }

        return match ($this->guess_status) {
            self::GUESS_ENDED => $user
                && $this->guess_winner_id === $user->id
                && $this->isWinnerPriorityActive(),
            self::GUESS_PUBLIC => true,
            default => false,
        };
    }
}
