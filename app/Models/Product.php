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

    /**
     * Seberapa meleset sebuah tebakan masih dianggap benar, dalam persen dari
     * harga diskon.
     *
     * Menuntut tebakan persis sampai rupiah membuat fitur ini praktis tidak
     * pernah punya pemenang. Sebaliknya, tanpa ambang sama sekali, tebakan
     * terdekat selalu menang berapa pun melesetnya — itulah perilaku lama yang
     * membuat tebakan Rp 750.000 memenangkan produk seharga Rp 800.000.
     *
     * Naikkan angka ini bila ingin pemenang lebih sering muncul.
     */
    public const GUESS_TOLERANCE_PERCENT = 5;

    protected $fillable = [
        'public_id',
        'store_id',
        'name',
        'stock',
        // Jumlah yang sedang ditahan pesanan yang belum lunas. Lihat
        // migrasi 2026_08_09_000007_add_stock_reservation.
        'reserved_stock',
        'price',
        // Berat satuan dalam gram; dipakai menghitung biaya berat di checkout.
        'weight',
        // Dimensi paket dalam cm, untuk menghitung berat volumetrik.
        'length',
        'width',
        'height',
        'category',
        'description',
        'image',
        'images',
        'video',
        'certificate',
        'is_trade_in_enabled',
        'locked_for_trade_in_id',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'validated_at',
        'validated_by',
        'sale_type',
        'guess_discount_price',
        'guess_starts_at',
        'guess_ends_at',
        'guess_status',
        'guess_winner_id',
        'guess_winning_amount',
        'guess_finished_at',
        'guess_finished_reason',
        'winner_priority_until',
    ];

    /**
     * Alasan sesi tebak harga berakhir.
     */
    public const FINISH_EXACT_GUESS = 'exact_guess';

    public const FINISH_PERIOD_ENDED = 'period_ended';

    public const FINISH_NO_WINNER = 'no_winner';

    protected $hidden = [
        'id',
    ];

    /**
     * Sisa yang benar-benar bisa dibeli selalu ikut diserialisasi: frontend
     * tidak boleh menghitungnya sendiri dari stock - reserved_stock, karena
     * aturannya bisa berubah.
     */
    protected $appends = [
        'available_stock',
        'is_fully_reserved',
    ];

    protected $casts = [
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'price' => 'decimal:2',
        'weight' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'images' => 'array',
        'is_trade_in_enabled' => 'boolean',
        'approved_at' => 'datetime',
        'validated_at' => 'datetime',
        'guess_starts_at' => 'datetime',
        'guess_ends_at' => 'datetime',
        'guess_finished_at' => 'datetime',
        'winner_priority_until' => 'datetime',
        'guess_winning_amount' => 'decimal:2',
        'guess_discount_price' => 'decimal:2',
    ];

    /**
     * Produk yang dapat ditawarkan/diajukan untuk tukar tambah:
     * sudah disetujui, masih punya stok, ditandai bisa ditukar tambah oleh seller,
     * dan tidak sedang terikat tukar tambah lain yang belum tuntas.
     * (Produk yang sudah terjual/stok habis otomatis tidak masuk.)
     */
    public function scopeTradeInEnabled($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED)
            ->where('is_trade_in_enabled', true)
            ->where('stock', '>', 0)
            ->whereNull('locked_for_trade_in_id');
    }

    /**
     * Produk yang sedang dikunci karena terikat tukar tambah yang sudah disetujui
     * tetapi belum tuntas (menunggu pembayaran selisih atau menunggu kedua
     * belah pihak saling menerima barang).
     *
     * Selama terkunci, produk tidak boleh dibeli pembeli biasa maupun
     * ditawarkan pada tukar tambah lain — lihat temuan T-08.
     */
    public function isLockedForTradeIn(): bool
    {
        return $this->locked_for_trade_in_id !== null;
    }

    public function lockedForTradeIn()
    {
        return $this->belongsTo(TradeInRequest::class, 'locked_for_trade_in_id');
    }

    /**
     * Produk yang sudah lolos kedua tahap dan tampil ke buyer.
     *
     * Produk milik seller yang akunnya dinonaktifkan ikut disaring keluar.
     * Tanpa ini, penonaktifan tidak berarti apa-apa bagi pembeli: barang seller
     * yang diblokir tetap terpajang dan tetap bisa dibeli (temuan V4-12).
     *
     * Produknya tidak dihapus — pesanan lama masih merujuk padanya, dan seller
     * yang diaktifkan kembali langsung mendapatkan etalasenya utuh.
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED)
            ->whereHas('store.user', fn ($q) => $q->whereNull('deactivated_at'));
    }

    /**
     * Apakah produk ini sedang tidak dapat dibeli karena akun sellernya
     * dinonaktifkan. Dipakai controller untuk menolak pembelian dengan alasan
     * yang benar, bukan sekadar "produk tidak ditemukan".
     */
    public function isSellerDeactivated(): bool
    {
        return $this->store?->user?->isDeactivated() ?? false;
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
            $publicId = 'PRD'.random_int(10000000, 99999999);
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
        $this->attributes['stock'] = max(0, (int) $value);
    }

    public function setReservedStockAttribute($value)
    {
        $this->attributes['reserved_stock'] = max(0, (int) $value);
    }

    /**
     * Jumlah yang benar-benar masih bisa dibeli sekarang.
     *
     * `stock` adalah barang yang dimiliki seller; sebagiannya bisa sedang
     * ditahan pesanan orang lain yang belum lunas. Yang boleh dijual adalah
     * selisihnya.
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, (int) $this->stock - (int) $this->reserved_stock);
    }

    /**
     * Apakah seluruh sisa stok sedang ditahan pesanan yang belum dibayar.
     *
     * Dipakai halaman produk untuk menjelaskan kenapa barangnya terlihat tapi
     * tidak bisa dibeli — tanpa keterangan ini pembeli hanya melihat tombol
     * yang mati tanpa alasan.
     */
    public function getIsFullyReservedAttribute(): bool
    {
        return $this->stock > 0 && $this->available_stock <= 0;
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

    public function tradeInRequestsOffered()
    {
        return $this->hasMany(TradeInRequest::class, 'offered_product_id');
    }

    public function tradeInRequestsRequested()
    {
        return $this->hasMany(TradeInRequest::class, 'requested_product_id');
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
     * Berapa rupiah sebuah tebakan masih boleh meleset dan tetap dianggap benar.
     */
    public function guessToleranceAmount(): ?float
    {
        if ($this->guess_discount_price === null) {
            return null;
        }

        return (float) $this->guess_discount_price * (self::GUESS_TOLERANCE_PERCENT / 100);
    }

    /**
     * Apakah sebuah tebakan cukup dekat dengan harga diskon untuk dianggap benar.
     *
     * Produk tanpa harga diskon (belum dikonfigurasi) tidak mungkin ditebak
     * dengan benar — lebih baik tidak ada pemenang daripada memenangkan
     * seseorang atas angka yang tidak pernah ditetapkan.
     */
    public function isGuessWithinTolerance(float $amount): bool
    {
        $tolerance = $this->guessToleranceAmount();

        if ($tolerance === null) {
            return false;
        }

        return abs($amount - (float) $this->guess_discount_price) <= $tolerance;
    }

    /**
     * Seberapa jauh sebuah tebakan meleset dari harga diskon, dalam rupiah.
     * null bila harga diskonnya belum ditetapkan.
     */
    public function guessDistance(float $amount): ?float
    {
        if ($this->guess_discount_price === null) {
            return null;
        }

        return abs($amount - (float) $this->guess_discount_price);
    }

    /**
     * Tebakan PERSIS pada harga diskonnya.
     *
     * Tebakan seperti ini tidak mungkin dikalahkan siapa pun, jadi menunggu
     * periode habis tidak ada gunanya — sesinya ditutup seketika dan
     * pemenangnya diumumkan. Lihat PriceGuessController::store().
     *
     * Perbandingannya memakai ambang satu rupiah, bukan `==`: kedua nilainya
     * berasal dari kolom decimal dan dibandingkan sebagai float.
     */
    public function isExactGuess(float $amount): bool
    {
        $distance = $this->guessDistance($amount);

        return $distance !== null && $distance < 1.0;
    }

    /**
     * Harga yang benar-benar ditagihkan kepada pembeli ini.
     *
     * Pemenang tebak harga yang masih dalam masa prioritas membayar harga
     * DISKON; semua orang lain — termasuk pemenang yang prioritasnya sudah
     * lewat — membayar harga normal.
     */
    public function effectivePriceFor(?User $user): float
    {
        if ($this->isTebakHarga()
            && $this->guess_discount_price !== null
            && $user
            && $this->guess_winner_id === $user->id
            && $this->isWinnerPriorityActive()
        ) {
            return (float) $this->guess_discount_price;
        }

        return (float) $this->price;
    }

    /**
     * Harga DISKON wajib disembunyikan dari pembeli selama periode tebak harga
     * dan masa hak prioritas — itulah angka yang sedang ditebak. Harga normal
     * (`price`) justru tetap terlihat, karena menjadi patokan pembeli menebak
     * berapa diskonnya.
     *
     * Pemenang yang sedang memegang prioritas berhak melihatnya.
     */
    public function shouldHidePriceFor(?User $user): bool
    {
        if (! $this->isTebakHarga()) {
            return false;
        }

        return match ($this->guess_status) {
            self::GUESS_SCHEDULED, self::GUESS_ACTIVE => true,
            self::GUESS_ENDED => ! ($user && $this->guess_winner_id === $user->id),
            default => false, // public / null
        };
    }

    /**
     * Sembunyikan harga diskon dari serialisasi untuk viewer yang tidak berhak.
     * Dipakai pada endpoint publik (marketplace, detail produk) sebelum dikirim
     * ke Inertia.
     */
    public function maskRealPriceFor(?User $user): self
    {
        if ($this->shouldHidePriceFor($user)) {
            $this->makeHidden(['guess_discount_price']);
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
        if (! $this->isTebakHarga()) {
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
