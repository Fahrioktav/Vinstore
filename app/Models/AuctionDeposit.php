<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Uang jaminan seorang peserta atas sebuah lelang.
 *
 * Siklus hidupnya:
 *
 *   pending ──bayar──> paid ─┬─ menang & bayar ──> applied   (jadi uang muka)
 *                            ├─ kalah ──> refund_requested ──> refunded
 *                            └─ menang tapi tidak bayar ──> forfeited
 *
 * `pending` yang tidak pernah dibayar sampai lelangnya tutup menjadi `expired`.
 */
class AuctionDeposit extends Model
{
    use HasFactory;

    /** Snap sudah dibuat, uangnya belum masuk. Belum boleh menawar. */
    const STATUS_PENDING = 'pending';

    /** Sudah dibayar. Inilah satu-satunya status yang memberi hak menawar. */
    const STATUS_PAID = 'paid';

    /** Dipakai sebagai uang muka atas pesanan lelang yang dimenangkan. */
    const STATUS_APPLIED = 'applied';

    /** Peserta kalah dan meminta uangnya kembali; menunggu keputusan admin. */
    const STATUS_REFUND_REQUESTED = 'refund_requested';

    /** Admin sudah mentransfer balik dan mengunggah buktinya. */
    const STATUS_REFUNDED = 'refunded';

    /** Hangus karena pemenang tidak membayar sampai tenggat. */
    const STATUS_FORFEITED = 'forfeited';

    /** Tidak pernah dibayar sampai lelangnya tutup. */
    const STATUS_EXPIRED = 'expired';

    /**
     * Status yang membuat peserta berhak menawar.
     *
     * `applied` ikut masuk supaya pemenang yang depositnya sudah menjadi uang
     * muka tidak tiba-tiba kehilangan haknya bila lelangnya sempat dibuka lagi.
     */
    const ACTIVE_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_APPLIED,
    ];

    protected $fillable = [
        'public_id',
        'auction_id',
        'user_id',
        'amount',
        'status',
        'payment_reference',
        'midtrans_transaction_id',
        'snap_token',
        'snap_redirect_url',
        'paid_at',
        'bank_name',
        'account_number',
        'account_holder',
        'refund_requested_at',
        'transfer_proof',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'refunded_at',
        'applied_at',
        'forfeited_at',
    ];

    protected $hidden = [
        'id',
        'auction_id',
        'user_id',
        'reviewed_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'refund_requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'applied_at' => 'datetime',
        'forfeited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuctionDeposit $deposit) {
            if (empty($deposit->public_id)) {
                $deposit->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'DEP'.random_int(10000000, 99999999);
        } while (DB::table('auction_deposits')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Jaminan yang sedang aktif — sudah dibayar dan belum berpindah status.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Bolehkah pemiliknya meminta uangnya kembali?
     *
     * Hanya jaminan yang sudah dibayar, lelangnya sudah tutup, dan pemiliknya
     * BUKAN pemenang. Pemenang tidak lewat jalur ini: depositnya menjadi uang
     * muka pesanannya.
     */
    public function canRequestRefund(): bool
    {
        if ($this->status !== self::STATUS_PAID) {
            return false;
        }

        $auction = $this->relationLoaded('auction') ? $this->auction : $this->auction()->first();

        if (! $auction || $auction->status !== 'ended') {
            return false;
        }

        return $auction->winner_id !== $this->user_id;
    }

    /**
     * Alasan pengembalian belum bisa diajukan, untuk ditampilkan ke pembeli.
     */
    public function refundBlockReason(): ?string
    {
        if ($this->status === self::STATUS_PENDING) {
            return 'Deposit ini belum dibayar.';
        }

        if ($this->status === self::STATUS_APPLIED) {
            return 'Deposit ini sudah dipakai sebagai uang muka pesanan lelang Anda.';
        }

        if ($this->status === self::STATUS_FORFEITED) {
            return 'Deposit ini hangus karena pesanan lelang tidak dibayar sampai tenggat.';
        }

        if ($this->status === self::STATUS_REFUND_REQUESTED) {
            return 'Pengajuan pengembalian deposit ini sedang diproses admin.';
        }

        if ($this->status === self::STATUS_REFUNDED) {
            return 'Deposit ini sudah dikembalikan.';
        }

        if ($this->status === self::STATUS_EXPIRED) {
            return 'Deposit ini tidak pernah dibayar sampai lelangnya tutup.';
        }

        $auction = $this->relationLoaded('auction') ? $this->auction : $this->auction()->first();

        if ($auction && $auction->status !== 'ended') {
            return 'Pengembalian baru bisa diajukan setelah lelangnya selesai.';
        }

        if ($auction && $auction->winner_id === $this->user_id) {
            return 'Anda memenangkan lelang ini, jadi deposit dipakai sebagai uang muka pembayaran.';
        }

        return null;
    }

    /**
     * Hanguskan deposit pemenang yang pesanannya telanjur kedaluwarsa, lalu
     * catat uangnya sebagai pendapatan marketplace.
     *
     * Dipanggil dari `orders:release-abandoned`. Idempoten: pesanan yang sudah
     * pernah diproses tidak akan menghasilkan baris pendapatan kedua, dijamin
     * kunci unik (order_id, source) pada platform_revenues.
     */
    public static function forfeitForAbandonedOrder(Order $order): ?self
    {
        if ($order->auction_id === null) {
            return null;
        }

        $deposit = self::where('auction_id', $order->auction_id)
            ->where('user_id', $order->user_id)
            ->whereIn('status', [self::STATUS_PAID, self::STATUS_APPLIED])
            ->first();

        if (! $deposit) {
            return null;
        }

        $deposit->forceFill([
            'status' => self::STATUS_FORFEITED,
            'forfeited_at' => now(),
        ])->save();

        PlatformRevenue::recordAuctionDepositForfeit($order, $deposit);

        return $deposit;
    }
}
