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
        'product_name',
        'product_price',
        'auction_id',
        'quantity',
        'price',
        'status',
        'tracking_number',
        'shipping_address',
        // Nama wilayah hasil pembacaan koordinat. Inilah tujuan sebenarnya
        // yang dilihat seller — lihat temuan V4-01.
        'shipping_area',
        'shipping_method',
        'shipping_cost',
        'packaging_fee',
        'packaging_type',
        'weight_fee',
        'service_fee',
        'weight_gram',
        'volumetric_weight_gram',
        'shipping_distance_km',
        'shipping_latitude',
        'shipping_longitude',
        'notes',
        'payment_reference',
        'payment_status',
        'payment_method',
        'midtrans_transaction_id',
        'snap_token',
        'snap_redirect_url',
        'paid_at',
        'stock_restored_at',
        'stock_committed_at',
        'seller_released_at',
        'delivered_at',
        'completed_at',
        'store_id',
        'store_name',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'stock_committed_at' => 'datetime',
        'seller_released_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'product_price' => 'decimal:2',
        'shipping_cost' => 'integer',
        'packaging_fee' => 'integer',
        'weight_fee' => 'integer',
        'service_fee' => 'integer',
        'weight_gram' => 'integer',
        'volumetric_weight_gram' => 'integer',
        'shipping_distance_km' => 'float',
        'shipping_latitude' => 'float',
        'shipping_longitude' => 'float',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Kolom turunan yang selalu ikut saat pesanan diserialisasi ke Inertia,
     * supaya frontend tidak perlu tahu apakah produknya masih ada atau tidak.
     */
    protected $appends = [
        'display_item_name',
        'display_store_name',
        // Dashboard seller menampilkan total tagihan pembeli; tanpa angka ini
        // seller mengira seluruhnya menjadi haknya.
        'seller_payout_amount',
    ];

    /**
     * Nama barang yang dipesan.
     *
     * Snapshot didahulukan karena itulah nama barang PADA SAAT transaksi.
     * Kalau seller mengganti nama produknya kemudian, riwayat pesanan lama
     * tetap menampilkan nama yang dulu dibeli — dan tetap terbaca walau
     * produknya sudah dihapus.
     */
    public function getDisplayItemNameAttribute(): string
    {
        return $this->product_name
            ?? $this->product?->name
            ?? $this->auction?->name
            ?? 'Produk tidak tersedia';
    }

    public function getDisplayStoreNameAttribute(): string
    {
        return $this->store_name
            ?? $this->store?->store_name
            ?? 'Toko tidak tersedia';
    }

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
            $publicId = 'ORD'.random_int(10000000, 99999999);
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

    /**
     * Berapa lama pembeli punya waktu untuk mengajukan sanggahan/refund setelah
     * seller menandai pesanan sampai. Lewat dari ini pesanan diselesaikan
     * otomatis oleh command orders:auto-complete agar dana seller tidak
     * tertahan selamanya saat pembeli lupa mengonfirmasi.
     */
    public const BUYER_CONFIRMATION_WINDOW_DAYS = 3;

    /**
     * Berapa lama sebuah pesanan boleh menahan stok sambil menunggu dibayar.
     *
     * Disamakan dengan masa berlaku transaksi Midtrans. Lewat dari ini,
     * command orders:release-abandoned melepaskan reservasinya agar barangnya
     * bisa dibeli orang lain.
     */
    public const PAYMENT_WINDOW_HOURS = 24;

    /**
     * Pesanan yang masih berjalan atau pernah melibatkan uang.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', '!=', 'Cancelled')
                ->orWhereIn('payment_status', ['paid', 'refunded', 'challenge']);
        });
    }

    /**
     * Status pembayaran yang sudah final: sekali tercapai, notifikasi Midtrans
     * berikutnya tidak boleh menariknya mundur.
     */
    public const SETTLED_PAYMENT_STATUSES = ['paid', 'refunded'];

    /**
     * Bolehkah status pembayaran baru ini diterapkan?
     *
     * Midtrans tidak menjamin urutan pengiriman notifikasi, dan
     * MidtransService::mapPaymentStatus() memetakan transaction_status yang
     * tidak dikenal menjadi 'pending'. Tanpa penjaga ini, satu notifikasi
     * telat/tak dikenal bisa mengubah pesanan lunas kembali menjadi 'pending' —
     * yang mencabut akses invoice pembeli sekaligus membatalkan pencairan dana
     * seller atas pesanan yang uangnya sudah diterima.
     *
     * Transisi keluar dari 'paid' hanya sah menuju 'refunded'.
     */
    public function canApplyPaymentStatus(?string $newStatus): bool
    {
        if ($newStatus === null || $newStatus === $this->payment_status) {
            return false;
        }

        if (! in_array($this->payment_status, self::SETTLED_PAYMENT_STATUSES, true)) {
            return true;
        }

        return $this->payment_status === 'paid' && $newStatus === 'refunded';
    }

    /**
     * Apakah pembeli boleh menekan tombol "Barang Diterima" sekarang.
     */
    public function canBeConfirmedByBuyer(): bool
    {
        return $this->payment_status === 'paid'
            && in_array($this->status, ['On The Way', 'Delivered'], true);
    }

    /**
     * Tandai pesanan selesai atas konfirmasi pembeli.
     *
     * Konfirmasi ini TIDAK lagi mencairkan dana. Pencairan hanya terjadi lewat
     * pengajuan seller yang disetujui admin — lihat PayoutRequest.
     */
    public function completeByBuyer(): void
    {
        DB::transaction(function () {
            $order = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $order || ! $order->canBeConfirmedByBuyer()) {
                return;
            }

            $order->forceFill([
                'status' => 'Completed',
                'completed_at' => now(),
            ])->save();
        });

        $this->refresh();
    }

    /**
     * Lepaskan reservasi stok tanpa jadi dibeli — pesanan batal, kedaluwarsa,
     * atau ditolak.
     *
     * Sejak stok ditahan (reserved_stock) alih-alih langsung dipotong, yang
     * dikembalikan adalah reservasinya, bukan stoknya. Pesanan yang sudah
     * telanjur lunas dan stoknya dipotong tidak boleh lewat jalur ini.
     *
     * `stock_restored_at` menjaga agar pelepasan hanya terjadi sekali walau
     * webhook Midtrans datang berkali-kali.
     */
    public function restoreReservedStock(): void
    {
        if ($this->stock_restored_at !== null || $this->stock_committed_at !== null) {
            return;
        }

        DB::transaction(function () {
            $order = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $order || $order->stock_restored_at !== null || $order->stock_committed_at !== null) {
                return;
            }

            if ($order->product_id !== null) {
                Product::whereKey($order->product_id)->decrement('reserved_stock', $order->quantity);
            }

            $order->forceFill([
                'stock_restored_at' => now(),
            ])->save();
        });

        $this->refresh();
    }

    /**
     * Ubah reservasi menjadi pengurangan stok yang sesungguhnya.
     *
     * Dipanggil ketika pembayaran benar-benar lunas. Sampai titik ini barangnya
     * masih tercatat sebagai milik seller dan tetap tampil di etalase; barulah
     * di sini ia berpindah tangan.
     *
     * Idempoten lewat `stock_committed_at`: notifikasi Midtrans tidak menjamin
     * hanya datang sekali.
     */
    public function commitReservedStock(): void
    {
        if ($this->stock_committed_at !== null || $this->stock_restored_at !== null) {
            return;
        }

        DB::transaction(function () {
            $order = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $order || $order->stock_committed_at !== null || $order->stock_restored_at !== null) {
                return;
            }

            if ($order->product_id !== null) {
                $product = Product::whereKey($order->product_id)->lockForUpdate()->first();

                if ($product) {
                    // Keduanya turun bersamaan: barangnya keluar dari stok, dan
                    // penahanannya tidak lagi diperlukan. Memotong stok tanpa
                    // melepas reservasi akan menahan unit yang sudah terjual.
                    $product->forceFill([
                        'stock' => max(0, (int) $product->stock - (int) $order->quantity),
                        'reserved_stock' => max(0, (int) $product->reserved_stock - (int) $order->quantity),
                    ])->save();
                }
            }

            $order->forceFill([
                'stock_committed_at' => now(),
            ])->save();
        });

        $this->refresh();
    }

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function platformRevenues()
    {
        return $this->hasMany(PlatformRevenue::class);
    }

    /**
     * Nilai barang murni, tanpa ongkir dan biaya-biaya lain.
     *
     * `price` menyimpan total tagihan, jadi tanpa pengurangan ini seller
     * seolah-olah berhak atas ongkir dan biaya layanan juga.
     */
    public function getItemSubtotalAttribute(): int
    {
        return max(0, (int) round($this->price)
            - (int) $this->shipping_cost
            - (int) $this->packaging_fee
            - (int) $this->weight_fee
            - (int) $this->service_fee);
    }

    /**
     * Nominal yang menjadi hak seller atas pesanan ini.
     *
     * Pembagian tagihan:
     *  - nilai barang + biaya pengemasan -> seller (sellerlah yang mengemas)
     *  - ongkir + biaya berat            -> biaya pengiriman, bukan hak seller
     *  - biaya layanan                   -> pendapatan marketplace (dompet admin)
     *
     * Sebelumnya pencairan memakai `price` (total tagihan) apa adanya, sehingga
     * seller ikut menerima ongkir DAN biaya layanan — biaya layanan yang sama
     * yang dicatat sebagai pendapatan platform. Uang yang sama dibayarkan dua
     * kali.
     *
     * Pesanan lelang tidak punya komponen biaya (semuanya 0), jadi nilainya
     * tetap sama dengan harga menang.
     */
    public function sellerPayoutAmount(): int
    {
        return $this->item_subtotal + (int) $this->packaging_fee;
    }

    public function getSellerPayoutAmountAttribute(): int
    {
        return $this->sellerPayoutAmount();
    }

    /**
     * Pengajuan pencairan yang sedang menahan pesanan ini: masih menunggu
     * keputusan admin, atau sudah disetujui.
     */
    public function activePayoutRequest(): ?PayoutRequest
    {
        // Pakai relasi yang sudah dimuat bila ada, supaya daftar pesanan di
        // dashboard seller tidak memicu query per baris.
        if ($this->relationLoaded('payoutRequests')) {
            return $this->payoutRequests
                ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
                ->sortByDesc('id')
                ->first();
        }

        return PayoutRequest::where('order_id', $this->id)
            ->blocking()
            ->latest('id')
            ->first();
    }

    public function hasPendingRefund(): bool
    {
        if ($this->relationLoaded('refundRequest')) {
            return $this->refundRequest !== null && $this->refundRequest->status === 'pending';
        }

        return RefundRequest::where('order_id', $this->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function getCanRequestPayoutAttribute(): bool
    {
        return $this->canRequestPayout();
    }

    public function getPayoutBlockReasonAttribute(): ?string
    {
        return $this->payoutBlockReason();
    }

    /**
     * Pengajuan terakhir apa pun statusnya — dipakai untuk menampilkan badge
     * "menunggu persetujuan" / "ditolak" di baris pesanan.
     */
    public function getLatestPayoutAttribute(): ?array
    {
        $payout = $this->relationLoaded('payoutRequests')
            ? $this->payoutRequests->sortByDesc('id')->first()
            : PayoutRequest::where('order_id', $this->id)->latest('id')->first();

        if (! $payout) {
            return null;
        }

        return [
            'public_id' => $payout->public_id,
            'status' => $payout->status,
            'amount' => $payout->amount,
            'admin_note' => $payout->admin_note,
            'bank_name' => $payout->bank_name,
            'account_number' => $payout->account_number,
            'account_holder' => $payout->account_holder,
        ];
    }

    /**
     * Bolehkah seller mengajukan pencairan untuk pesanan ini?
     *
     * Dana pesanan tidak pernah cair sendiri — tidak saat pembeli konfirmasi,
     * tidak pula lewat penjadwal. Seller harus mengajukan dan admin harus
     * menyetujui sambil mengunggah bukti transfer.
     *
     * Status "Delivered" sudah cukup untuk mengajukan, karena keputusan akhir
     * tetap ada di tangan admin. Yang tidak boleh adalah pesanan yang belum
     * dibayar, yang dananya sudah pernah dicairkan, yang sedang disengketakan,
     * atau yang pengajuannya masih menggantung.
     */
    public function canRequestPayout(): bool
    {
        return $this->payment_status === 'paid'
            && in_array($this->status, ['Delivered', 'Completed'], true)
            && $this->seller_released_at === null
            && $this->store_id !== null
            && ! $this->hasPendingRefund()
            && $this->activePayoutRequest() === null;
    }

    /**
     * Alasan pesanan belum bisa diajukan, untuk ditampilkan ke seller.
     */
    public function payoutBlockReason(): ?string
    {
        if ($this->seller_released_at !== null) {
            return 'Dana pesanan ini sudah dicairkan.';
        }

        if ($this->payment_status !== 'paid') {
            return 'Pesanan ini belum dibayar.';
        }

        if (! in_array($this->status, ['Delivered', 'Completed'], true)) {
            return 'Pencairan baru bisa diajukan setelah pesanan berstatus Delivered atau Completed.';
        }

        if ($this->hasPendingRefund()) {
            return 'Ada pengajuan refund yang belum diputus admin.';
        }

        if ($this->activePayoutRequest() !== null) {
            return 'Pengajuan pencairan untuk pesanan ini sudah ada.';
        }

        return null;
    }

    /**
     * Tandai dana pesanan sudah ditransfer ke rekening seller. Hanya dipanggil
     * dari persetujuan admin atas PayoutRequest.
     */
    public function markSellerPaid(): void
    {
        $this->forceFill(['seller_released_at' => now()])->save();
    }
}
