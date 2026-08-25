<?php

namespace App\Models;

use App\Services\MidtransService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * Banyaknya jaminan yang boleh ditanyakan ke Midtrans dalam satu permintaan.
     * Lihat syncFromMidtrans().
     */
    const MAX_SYNC_PER_REQUEST = 3;

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
     * Terapkan kabar pembayaran Midtrans atas jaminan ini.
     *
     * Satu-satunya tempat status pembayaran jaminan berpindah, dipakai baik
     * oleh webhook maupun oleh penyelarasan langsung ke Midtrans, supaya
     * aturannya tidak pernah bercabang di antara keduanya.
     *
     * Yang boleh menerima kabar pelunasan bukan hanya jaminan `pending`, tetapi
     * juga yang sudah telanjur ditandai `expired`.
     *
     * Alasannya: `expired` dipasang oleh penutupan lelang atas jaminan yang saat
     * itu belum dibayar — dan "belum dibayar pada detik itu" tidak sama dengan
     * "tidak akan pernah dibayar". Pembeli yang memilih virtual account bisa saja
     * sudah menekan bayar semenit sebelum lelangnya tutup. Menolak uangnya
     * membuat jaminan bernilai `expired` padahal dananya benar-benar diterima,
     * dan pemiliknya kehilangan jalur pengembalian sama sekali (temuan V7-01).
     *
     * Pemilik jaminan semacam itu pasti kalah — untuk menawar ia harus punya
     * jaminan yang sudah aktif — jadi menaikkannya ke `paid` selalu berujung ke
     * jalur pengembalian, tidak pernah ke uang muka.
     *
     * Status selebihnya (`applied`, `refunded`, `forfeited`) sudah milik alur
     * lelang dan tidak boleh ditarik mundur oleh kabar yang terlambat.
     *
     * @param  array<string, mixed>  $payload  Badan notifikasi atau jawaban API status.
     * @return bool Benar bila statusnya berpindah karena panggilan ini.
     */
    public function applyPaymentStatus(?string $paymentStatus, array $payload = []): bool
    {
        $this->midtrans_transaction_id = $payload['transaction_id'] ?? $this->midtrans_transaction_id;

        $gagal = in_array($paymentStatus, ['cancelled', 'denied', 'expired'], true);

        $menungguPelunasan = in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_EXPIRED,
        ], true);

        $berpindah = false;

        if ($paymentStatus === 'paid' && $menungguPelunasan) {
            $terlambat = $this->status === self::STATUS_EXPIRED;

            $this->status = self::STATUS_PAID;
            $this->paid_at = now();
            $berpindah = true;

            if ($terlambat) {
                Log::info('Deposit lelang lunas setelah lelangnya tutup; masuk jalur pengembalian.', [
                    'deposit_public_id' => $this->public_id,
                    'gross_amount' => $payload['gross_amount'] ?? null,
                ]);
            }
        } elseif ($gagal && $this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_EXPIRED;
            $berpindah = true;
        } elseif ($paymentStatus === 'paid') {
            Log::warning('Pembayaran deposit lelang datang atas jaminan yang sudah tidak menunggu bayaran.', [
                'deposit_public_id' => $this->public_id,
                'status' => $this->status,
                'gross_amount' => $payload['gross_amount'] ?? null,
            ]);
        }

        $this->save();

        return $berpindah;
    }

    /**
     * Masih adakah gunanya menanyakan status jaminan ini ke Midtrans?
     */
    public function needsPaymentSync(): bool
    {
        // Tanpa transaksi Snap tidak ada apa pun yang bisa ditanyakan.
        if (! $this->payment_reference || ! $this->snap_token) {
            return false;
        }

        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_EXPIRED], true)) {
            return false;
        }

        // Transaksi Snap kedaluwarsa dalam hitungan jam — QRIS bahkan dalam
        // hitungan menit. Lewat dua hari tanpa perubahan apa pun tidak mungkin
        // lagi ada uang yang masuk, jadi berhenti bertanya; kalau tidak, jaminan
        // mati akan ditanyakan ulang setiap kali halaman dibuka.
        //
        // Dihitung dari perubahan terakhir, bukan dari saat dibuat: jaminan yang
        // dibuka kembali mendapat transaksi Snap yang baru dan berhak atas dua
        // hari yang baru pula.
        return $this->updated_at !== null && $this->updated_at->gt(now()->subDays(2));
    }

    /**
     * Tanyakan langsung ke Midtrans status jaminan yang belum lunas.
     *
     * Webhook tetap jalur utamanya, tetapi ia tidak selalu sampai: di komputer
     * pengembang alamat lokal memang tidak bisa dihubungi Midtrans sama sekali,
     * dan di produksi notifikasi masih bisa gagal terkirim. Tanpa penyelarasan
     * ini uang yang sudah benar-benar diterima tetap tercatat `pending`, dan
     * pembelinya disuruh membayar deposit lagi padahal sudah membayar.
     *
     * Pola yang sama sudah dipakai pesanan; lihat
     * OrderController::syncPendingMidtransPayments().
     *
     * Jumlah panggilannya DIBATASI. Panggilannya berurutan dan menahan
     * permintaan halaman, jadi pembeli dengan sepuluh jaminan menunggu tidak
     * boleh berarti sepuluh panggilan jaringan untuk satu kali muat. Sisanya
     * ikut tersegarkan pada pemuatan berikutnya, dan webhook Midtrans tetap
     * jalur utamanya (temuan V8-04).
     *
     * @param  iterable<int, self|null>  $deposits
     */
    public static function syncFromMidtrans(iterable $deposits, int $maksimal = self::MAX_SYNC_PER_REQUEST): void
    {
        $midtrans = app(MidtransService::class);
        $terpakai = 0;

        foreach ($deposits as $deposit) {
            if (! $deposit instanceof self || ! $deposit->needsPaymentSync()) {
                continue;
            }

            if ($terpakai >= $maksimal) {
                break;
            }

            $terpakai++;

            try {
                $status = $midtrans->getTransactionStatus($deposit->payment_reference);
            } catch (Throwable $e) {
                // Transaksi yang metode bayarnya belum pernah dipilih pembeli
                // belum ada di Midtrans dan dijawab 404. Belum ada yang bisa
                // diselaraskan; biarkan tetap menunggu.
                continue;
            }

            $deposit->applyPaymentStatus(
                $midtrans->mapPaymentStatus(
                    $status['transaction_status'] ?? null,
                    $status['fraud_status'] ?? null
                ),
                $status
            );
        }
    }

    /**
     * Hanguskan deposit pemenang yang pesanannya telanjur kedaluwarsa, lalu
     * catat uangnya sebagai pendapatan marketplace.
     *
     * Dipanggil dari `orders:release-abandoned`. Idempoten: pesanan yang sudah
     * pernah diproses tidak akan menghasilkan baris pendapatan kedua, dijamin
     * kunci unik (order_id, source) pada platform_revenues.
     */
    /**
     * Kembalikan jaminan yang sudah menjadi uang muka, karena pesanannya
     * dibatalkan lewat pengembalian dana.
     *
     * Tanpa ini jaminan itu tertinggal berstatus `applied` — "sudah dipakai
     * sebagai uang muka pesanan" — padahal pesanannya sendiri sudah tidak ada.
     * Pemiliknya tidak bisa memintanya kembali (jalur pengembalian jaminan
     * hanya menerima status `paid`), dan admin yang mentransfer balik harus
     * menjumlahkan sendiri berapa yang sebenarnya diterima marketplace: sebagian
     * lewat checkout, sebagian lewat jaminan (temuan V11-02).
     *
     * Nominalnya sudah tercakup dalam `orders.price`, jadi tidak ada uang
     * tambahan yang harus dihitung ulang — yang dibereskan di sini adalah
     * catatannya, supaya jaminan itu tidak menggantung tanpa pemilik.
     *
     * Idempoten: jaminan yang sudah `refunded` dilewati begitu saja.
     */
    public static function returnForRefundedOrder(Order $order, ?string $catatan = null): ?self
    {
        if ($order->auction_id === null) {
            return null;
        }

        $deposit = self::where('auction_id', $order->auction_id)
            ->where('user_id', $order->user_id)
            ->whereIn('status', [self::STATUS_APPLIED, self::STATUS_PAID])
            ->lockForUpdate()
            ->first();

        if (! $deposit) {
            return null;
        }

        $deposit->forceFill([
            'status' => self::STATUS_REFUNDED,
            'refunded_at' => now(),
            'admin_note' => $catatan ?? 'Dikembalikan bersama pengembalian dana pesanan '.$order->public_id.'.',
        ])->save();

        return $deposit;
    }

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
