<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TradeInRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    // Kedua seller sedang saling mengirimkan barangnya.
    public const STATUS_SHIPPING = 'shipping';

    // Kedua belah pihak sudah menerima barang; kepemilikan berpindah penuh.
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    // Payment status constants
    public const PAYMENT_NOT_REQUIRED = 'not_required';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_EXPIRED = 'expired';

    // Selisih uang dikembalikan ke pembayarnya karena tukar tambahnya gagal.
    public const PAYMENT_REFUNDED = 'refunded';

    // Peran yang wajib membayar selisih harga. Pihak yang produknya lebih
    // murahlah yang membayar — bisa pengaju, bisa juga penerima.
    public const PAYER_REQUESTER = 'requester';

    public const PAYER_RESPONDER = 'responder';

    protected $fillable = [
        'public_id',
        'requester_store_id',
        'responder_store_id',
        'offered_product_id',
        'requested_product_id',
        // Snapshot identitas: riwayat tukar tambah harus tetap terbaca meski produk
        // atau tokonya dihapus di kemudian hari (temuan V3-01).
        'offered_product_name',
        'requested_product_name',
        'requester_store_name',
        'responder_store_name',
        'additional_cash',
        'payer_role',
        'note',
        'status',
        'responded_at',
        'payment_status',
        'shipping_started_at',
        'payment_reference',
        'snap_token',
        'midtrans_transaction_id',
        'paid_at',
        'requester_tracking_number',
        'requester_shipped_at',
        'requester_received_at',
        'responder_tracking_number',
        'responder_shipped_at',
        'responder_received_at',
        'completed_at',
        'payout_released_at',
    ];

    protected $hidden = [
        'id',
        'requester_store_id',
        'responder_store_id',
        'offered_product_id',
        'requested_product_id',
    ];

    protected $casts = [
        'additional_cash' => 'decimal:2',
        'responded_at' => 'datetime',
        'shipping_started_at' => 'datetime',
        'paid_at' => 'datetime',
        'requester_shipped_at' => 'datetime',
        'requester_received_at' => 'datetime',
        'responder_shipped_at' => 'datetime',
        'responder_received_at' => 'datetime',
        'completed_at' => 'datetime',
        'payout_released_at' => 'datetime',
    ];

    protected $appends = [
        'display_offered_product_name',
        'display_requested_product_name',
        'display_requester_store_name',
        'display_responder_store_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (TradeInRequest $tradeIn) {
            if (empty($tradeIn->public_id)) {
                $tradeIn->public_id = self::generatePublicId();
            }

            $tradeIn->fillMissingSnapshots();
        });
    }

    /**
     * Salin nama produk dan toko ke baris tukar tambah ini.
     *
     * Dilakukan otomatis saat pembuatan agar tidak ada satu pun jalur pembuatan
     * tukar tambah yang bisa lupa mengisinya.
     */
    public function fillMissingSnapshots(): void
    {
        $this->offered_product_name ??= Product::whereKey($this->offered_product_id)->value('name');
        $this->requested_product_name ??= Product::whereKey($this->requested_product_id)->value('name');
        $this->requester_store_name ??= Store::whereKey($this->requester_store_id)->value('store_name');
        $this->responder_store_name ??= Store::whereKey($this->responder_store_id)->value('store_name');
    }

    /**
     * Nama-nama untuk ditampilkan.
     *
     * Snapshot didahulukan karena itulah identitas PADA SAAT tukar tambah disepakati;
     * relasi hidup hanya dipakai bila snapshot-nya belum ada (data lama).
     */
    public function getDisplayOfferedProductNameAttribute(): string
    {
        return $this->offered_product_name
            ?? $this->offeredProduct?->name
            ?? 'Produk tidak tersedia';
    }

    public function getDisplayRequestedProductNameAttribute(): string
    {
        return $this->requested_product_name
            ?? $this->requestedProduct?->name
            ?? 'Produk tidak tersedia';
    }

    public function getDisplayRequesterStoreNameAttribute(): string
    {
        return $this->requester_store_name
            ?? $this->requesterStore?->store_name
            ?? 'Toko tidak tersedia';
    }

    public function getDisplayResponderStoreNameAttribute(): string
    {
        return $this->responder_store_name
            ?? $this->responderStore?->store_name
            ?? 'Toko tidak tersedia';
    }

    /**
     * Prefixnya sengaja tetap `BRT` meski fiturnya sudah berganti nama menjadi
     * tukar tambah. Nilai ini sudah tersimpan di baris yang ada dan ditampilkan
     * ke pengguna; menggantinya hanya membuat data lama dan data baru berbeda
     * pola, tanpa manfaat apa pun karena idnya memang tidak bermakna.
     */
    public static function generatePublicId(): string
    {
        do {
            $publicId = 'BRT'.random_int(10000000, 99999999);
        } while (DB::table('trade_in_requests')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function requesterStore()
    {
        return $this->belongsTo(Store::class, 'requester_store_id');
    }

    public function responderStore()
    {
        return $this->belongsTo(Store::class, 'responder_store_id');
    }

    public function offeredProduct()
    {
        return $this->belongsTo(Product::class, 'offered_product_id');
    }

    public function requestedProduct()
    {
        return $this->belongsTo(Product::class, 'requested_product_id');
    }

    /**
     * Cek apakah tukar tambah ini memerlukan pembayaran (ada additional_cash)
     */
    public function requiresPayment(): bool
    {
        return $this->additional_cash > 0;
    }

    /* ============ Siapa yang membayar selisih, siapa yang menerima ============ */

    /**
     * Peran yang wajib membayar selisih harga, atau null bila tidak ada selisih.
     *
     * Data lama tidak punya kolom ini; sebelum perubahan selisih dua arah,
     * satu-satunya pembayar yang mungkin adalah pengaju.
     */
    public function payerRole(): ?string
    {
        if (! $this->requiresPayment()) {
            return null;
        }

        return $this->payer_role ?? self::PAYER_REQUESTER;
    }

    /**
     * Peran yang berhak menerima selisih — kebalikan dari pembayarnya.
     */
    public function payeeRole(): ?string
    {
        return match ($this->payerRole()) {
            self::PAYER_REQUESTER => self::PAYER_RESPONDER,
            self::PAYER_RESPONDER => self::PAYER_REQUESTER,
            default => null,
        };
    }

    public function payerStoreId(): ?int
    {
        return match ($this->payerRole()) {
            self::PAYER_REQUESTER => $this->requester_store_id,
            self::PAYER_RESPONDER => $this->responder_store_id,
            default => null,
        };
    }

    /**
     * Apakah toko ini yang wajib membayar selisihnya.
     */
    public function isPayer(?Store $store): bool
    {
        $payerStoreId = $this->payerStoreId();

        return $store !== null
            && $payerStoreId !== null
            && $payerStoreId === $store->id;
    }

    /**
     * Bolehkah toko ini membayar selisih sekarang.
     */
    public function canPayBy(?Store $store): bool
    {
        return $this->isPayer($store)
            && $this->status === self::STATUS_ACCEPTED
            && $this->payment_status === self::PAYMENT_PENDING;
    }

    /**
     * Nama toko yang membayar selisih. Dipakai panel admin saat memutuskan
     * pencairan — sejak selisih dua arah, pembayarnya tidak selalu pengaju.
     */
    public function getPayerStoreNameAttribute(): ?string
    {
        return match ($this->payerRole()) {
            self::PAYER_REQUESTER => $this->display_requester_store_name,
            self::PAYER_RESPONDER => $this->display_responder_store_name,
            default => null,
        };
    }

    /**
     * Label pihak pembayar untuk pesan yang dibaca kedua seller.
     */
    public function payerLabel(): string
    {
        return $this->payerRole() === self::PAYER_RESPONDER
            ? 'penerima tukar tambah'
            : 'pengaju tukar tambah';
    }

    /**
     * Cek apakah pembayaran sudah selesai (atau tidak diperlukan)
     */
    public function isPaymentCompleted(): bool
    {
        return $this->payment_status === self::PAYMENT_NOT_REQUIRED
            || $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Cek apakah tukar tambah sudah siap untuk ditukar kepemilikan produknya
     * (sudah accepted dan payment sudah selesai jika diperlukan)
     */
    public function isReadyToExchange(): bool
    {
        return $this->status === self::STATUS_ACCEPTED
            && $this->isPaymentCompleted();
    }

    /* ===================== Pengiriman dua arah ===================== */

    /**
     * Tukar tambah yang barangnya sedang dalam perjalanan antar kedua seller.
     */
    public function isShipping(): bool
    {
        return $this->status === self::STATUS_SHIPPING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Peran sebuah toko dalam tukar tambah ini: 'requester', 'responder', atau null.
     */
    public function roleOfStore(?Store $store): ?string
    {
        if (! $store) {
            return null;
        }

        return match ($store->id) {
            $this->requester_store_id => 'requester',
            $this->responder_store_id => 'responder',
            default => null,
        };
    }

    /**
     * Apakah kedua belah pihak sudah mengonfirmasi penerimaan barang.
     */
    public function bothPartiesReceived(): bool
    {
        return $this->requester_received_at !== null
            && $this->responder_received_at !== null;
    }

    /**
     * Produk yang harus DIKIRIM oleh peran tertentu.
     * Requester mengirim produk yang ia tawarkan; responder mengirim produk
     * miliknya yang diminta.
     */
    public function productShippedBy(string $role): ?Product
    {
        return $role === 'requester'
            ? $this->offeredProduct
            : $this->requestedProduct;
    }

    /* ==================== Tenggat pengiriman ==================== */

    /**
     * Berapa hari kedua seller punya waktu mengisi nomor resi sejak tukar tambah
     * masuk tahap saling kirim.
     *
     * Lewat dari ini, pihak yang sudah memenuhi kewajibannya boleh melaporkan
     * pihak lawan ke admin. Tanpa tenggat, satu seller yang menghilang membuat
     * tukar tambah menggantung selamanya dan kedua produk terkunci dari penjualan.
     */
    public const SHIPPING_DEADLINE_DAYS = 3;

    public function shippingDeadlineAt(): ?\Illuminate\Support\Carbon
    {
        return $this->shipping_started_at?->copy()->addDays(self::SHIPPING_DEADLINE_DAYS);
    }

    public function isShippingOverdue(): bool
    {
        $deadline = $this->shippingDeadlineAt();

        return $this->isShipping() && $deadline !== null && now()->greaterThan($deadline);
    }

    /**
     * Peran yang belum mengisi resi, atau null bila keduanya sudah.
     */
    public function partyMissingShipment(): ?string
    {
        if ($this->requester_shipped_at === null) {
            return 'requester';
        }

        if ($this->responder_shipped_at === null) {
            return 'responder';
        }

        return null;
    }

    /**
     * Bolehkah toko ini melaporkan bahwa pihak lawan tidak mengirim?
     *
     * Syaratnya: pelapor sudah mengirim barangnya sendiri, pihak lawan belum,
     * dan tenggat sudah lewat. Yang lalai tidak boleh melaporkan yang taat.
     */
    public function canReportStalledBy(?Store $store): bool
    {
        $role = $this->roleOfStore($store);

        if ($role === null || ! $this->isShippingOverdue()) {
            return false;
        }

        // Pelapor harus sudah mengirim barangnya sendiri. Memeriksa
        // partyMissingShipment() saja tidak cukup: fungsi itu mengembalikan
        // 'requester' lebih dulu, sehingga saat KEDUA pihak belum mengirim,
        // responder yang sama-sama lalai ikut lolos.
        if ($this->{$role.'_shipped_at'} === null) {
            return false;
        }

        $counterpart = $role === 'requester' ? 'responder' : 'requester';

        return $this->{$counterpart.'_shipped_at'} === null
            && ! $this->hasPendingRefund()
            && ! $this->hasApprovedRefund();
    }

    public function reportBlockReasonFor(?Store $store): ?string
    {
        $role = $this->roleOfStore($store);

        if ($role === null) {
            return null;
        }

        if (! $this->isShipping()) {
            return null;
        }

        if ($this->hasPendingRefund()) {
            return 'Laporan untuk tukar tambah ini sedang ditinjau admin.';
        }

        $counterpart = $role === 'requester' ? 'responder' : 'requester';
        $iShipped = $this->{$role.'_shipped_at'} !== null;
        $theyShipped = $this->{$counterpart.'_shipped_at'} !== null;

        if ($iShipped && $theyShipped) {
            return null;
        }

        $deadline = $this->shippingDeadlineAt()?->translatedFormat('d M Y H:i');

        // Yang belum mengirim perlu tahu tenggatnya — dialah yang harus
        // bertindak. Sebelumnya pesan tenggat justru hanya sampai ke pihak
        // yang menunggu.
        if (! $iShipped) {
            if ($this->isShippingOverdue()) {
                return 'Tenggat pengiriman sudah lewat. Segera isi nomor resi sebelum pihak lawan melaporkan tukar tambah ini.';
            }

            return 'Anda belum mengisi nomor resi. Batas waktunya '.($deadline ?? '-').'.';
        }

        if (! $this->isShippingOverdue()) {
            return 'Pihak lawan belum mengisi resi. Anda dapat melapor ke admin setelah '
                .($deadline ?? 'tenggat terlampaui').'.';
        }

        return null;
    }

    public function getCanReportStalledAttribute(): bool
    {
        return $this->canReportStalledBy(Auth::user()?->store);
    }

    public function getReportBlockReasonAttribute(): ?string
    {
        return $this->reportBlockReasonFor(Auth::user()?->store);
    }

    public function getShippingDeadlineAtAttribute(): ?string
    {
        return $this->shippingDeadlineAt()?->toIso8601String();
    }

    /* ============ Pencairan selisih uang & sanggahan tukar tambah ============ */

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function activePayoutRequest(): ?PayoutRequest
    {
        if ($this->relationLoaded('payoutRequests')) {
            return $this->payoutRequests
                ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
                ->sortByDesc('id')
                ->first();
        }

        return PayoutRequest::where('trade_in_request_id', $this->id)
            ->blocking()
            ->latest('id')
            ->first();
    }

    public function hasPendingRefund(): bool
    {
        if ($this->relationLoaded('refundRequests')) {
            return $this->refundRequests->contains(fn ($refund) => $refund->status === 'pending');
        }

        return RefundRequest::where('trade_in_request_id', $this->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function hasApprovedRefund(): bool
    {
        if ($this->relationLoaded('refundRequests')) {
            return $this->refundRequests->contains(fn ($refund) => $refund->status === 'approved');
        }

        return RefundRequest::where('trade_in_request_id', $this->id)
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * Selisih uang tukar tambah adalah hak pihak yang menyerahkan produk LEBIH MAHAL —
     * yaitu kebalikan dari pembayarnya. Bisa responder (pengaju yang menambah
     * uang) maupun requester (penerima yang menambah uang).
     */
    public function payoutRecipientStoreId(): ?int
    {
        return match ($this->payeeRole()) {
            self::PAYER_REQUESTER => $this->requester_store_id,
            self::PAYER_RESPONDER => $this->responder_store_id,
            default => null,
        };
    }

    /**
     * Bolehkah penerima selisih mengajukan pencairan uang tukar tambah ini?
     *
     * Syaratnya mengikuti pola escrow pesanan: barang harus benar-benar sudah
     * sampai di kedua belah pihak. Status 'completed' hanya tercapai setelah
     * requester DAN responder sama-sama mengonfirmasi penerimaan.
     */
    public function canRequestPayout(): bool
    {
        return $this->requiresPayment()
            && $this->payment_status === self::PAYMENT_PAID
            && $this->status === self::STATUS_COMPLETED
            && $this->payout_released_at === null
            && $this->payoutRecipientStoreId() !== null
            && ! $this->hasPendingRefund()
            && ! $this->hasApprovedRefund()
            && $this->activePayoutRequest() === null;
    }

    /**
     * Versi ber-viewer: hanya penerima selisih yang boleh mencairkan.
     *
     * Sejak selisih bisa mengalir dua arah, peran penerima tidak lagi bisa
     * disimpulkan dari daftar mana kartu itu ditampilkan.
     */
    public function canRequestPayoutBy(?Store $store): bool
    {
        $recipientStoreId = $this->payoutRecipientStoreId();

        return $store !== null
            && $recipientStoreId !== null
            && $recipientStoreId === $store->id
            && $this->canRequestPayout();
    }

    /**
     * Versi ber-viewer: hanya pembayar yang boleh meminta uangnya kembali.
     */
    public function canRequestRefundBy(?Store $store): bool
    {
        return $this->isPayer($store) && $this->canRequestRefund();
    }

    public function payoutBlockReason(): ?string
    {
        if (! $this->requiresPayment()) {
            return null;
        }

        if ($this->payout_released_at !== null) {
            return 'Selisih uang tukar tambah ini sudah dicairkan.';
        }

        if ($this->payment_status !== self::PAYMENT_PAID) {
            return 'Selisih uang tukar tambah ini belum dibayar '.$this->payerLabel().'.';
        }

        if ($this->status !== self::STATUS_COMPLETED) {
            return 'Pencairan baru bisa diajukan setelah kedua pihak mengonfirmasi barang diterima.';
        }

        if ($this->hasApprovedRefund()) {
            return 'Selisih uang tukar tambah ini sudah dikembalikan ke '.$this->payerLabel().'.';
        }

        if ($this->hasPendingRefund()) {
            return 'Ada pengajuan pengembalian dana yang belum diputus admin.';
        }

        if ($this->activePayoutRequest() !== null) {
            return 'Pengajuan pencairan untuk tukar tambah ini sudah ada.';
        }

        return null;
    }

    /**
     * Bolehkah pembayar selisih meminta uangnya kembali?
     *
     * Berlaku ketika selisih sudah dibayar tapi tukar tambahnya tidak pernah tuntas —
     * misalnya pihak lawan tidak pernah mengirimkan barangnya. Keputusan akhir
     * tetap di admin, yang bisa melihat status pengiriman kedua pihak.
     */
    public function canRequestRefund(): bool
    {
        return $this->requiresPayment()
            && $this->payment_status === self::PAYMENT_PAID
            && $this->status !== self::STATUS_COMPLETED
            && $this->payout_released_at === null
            && ! $this->hasPendingRefund()
            && ! $this->hasApprovedRefund();
    }

    public function markPayoutReleased(): void
    {
        $this->forceFill(['payout_released_at' => now()])->save();
    }

    public function getCanRequestPayoutAttribute(): bool
    {
        return $this->canRequestPayoutBy(Auth::user()?->store);
    }

    public function getPayoutBlockReasonAttribute(): ?string
    {
        // Alasan pemblokiran hanya relevan bagi pihak yang berhak menerima
        // selisihnya; pembayar tidak perlu melihat kenapa lawannya belum cair.
        $recipientStoreId = $this->payoutRecipientStoreId();
        $store = Auth::user()?->store;

        if ($store === null || $recipientStoreId === null || $recipientStoreId !== $store->id) {
            return null;
        }

        return $this->payoutBlockReason();
    }

    public function getCanRequestRefundAttribute(): bool
    {
        return $this->canRequestRefundBy(Auth::user()?->store);
    }

    public function getIsPayerAttribute(): bool
    {
        return $this->isPayer(Auth::user()?->store);
    }

    public function getCanPayAttribute(): bool
    {
        return $this->canPayBy(Auth::user()?->store);
    }

    public function getPayerLabelAttribute(): ?string
    {
        return $this->requiresPayment() ? $this->payerLabel() : null;
    }

    public function getLatestPayoutAttribute(): ?array
    {
        $payout = $this->relationLoaded('payoutRequests')
            ? $this->payoutRequests->sortByDesc('id')->first()
            : PayoutRequest::where('trade_in_request_id', $this->id)->latest('id')->first();

        if (! $payout) {
            return null;
        }

        return [
            'public_id' => $payout->public_id,
            'status' => $payout->status,
            'amount' => $payout->amount,
            'admin_note' => $payout->admin_note,
        ];
    }

    public function getLatestRefundAttribute(): ?array
    {
        $refund = $this->relationLoaded('refundRequests')
            ? $this->refundRequests->sortByDesc('id')->first()
            : RefundRequest::where('trade_in_request_id', $this->id)->latest('id')->first();

        if (! $refund) {
            return null;
        }

        return [
            'public_id' => $refund->public_id,
            'status' => $refund->status,
            'admin_note' => $refund->admin_note,
        ];
    }
}
