<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu baris buku besar pendapatan marketplace ("dompet admin").
 *
 * Saldo dompet admin = SUM(amount) atas seluruh baris. Pembalikan saat refund
 * dicatat sebagai baris bernilai negatif, bukan dengan menghapus baris
 * pendapatannya, agar riwayatnya tetap bisa diaudit.
 */
class PlatformRevenue extends Model
{
    use HasFactory;

    /**
     * Biaya layanan yang dipungut dari pesanan yang sudah lunas.
     */
    public const SOURCE_SERVICE_FEE = 'service_fee';

    /**
     * Pembalikan biaya layanan ketika refund pesanan disetujui admin.
     */
    public const SOURCE_SERVICE_FEE_REVERSAL = 'service_fee_reversal';

    /**
     * Deposit lelang yang hangus karena pemenangnya tidak pernah membayar.
     */
    public const SOURCE_AUCTION_DEPOSIT_FORFEIT = 'auction_deposit_forfeit';

    protected $fillable = [
        'public_id',
        'order_id',
        'source',
        'amount',
        'description',
    ];

    protected $hidden = [
        'id',
        'order_id',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PlatformRevenue $revenue) {
            if (empty($revenue->public_id)) {
                $revenue->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'REV'.random_int(10000000, 99999999);
        } while (DB::table('platform_revenues')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Saldo dompet admin saat ini.
     */
    public static function balance(): int
    {
        return (int) self::sum('amount');
    }

    /**
     * Catat biaya layanan sebuah pesanan yang baru lunas.
     *
     * Dipanggil dari webhook Midtrans dan dari sinkronisasi status pembayaran,
     * yang keduanya bisa berjalan berkali-kali untuk pesanan yang sama.
     * Idempotensinya dijamin kunci unik (order_id, source) di level database —
     * pengecekan di PHP saja tidak cukup karena dua notifikasi bisa datang
     * bersamaan.
     */
    public static function recordServiceFee(Order $order): ?self
    {
        $amount = (int) $order->service_fee;

        if ($amount <= 0 || $order->getKey() === null) {
            return null;
        }

        try {
            return self::create([
                'order_id' => $order->getKey(),
                'source' => self::SOURCE_SERVICE_FEE,
                'amount' => $amount,
                'description' => 'Biaya layanan pesanan '.$order->public_id,
            ]);
        } catch (QueryException $e) {
            // Sudah pernah dicatat (pelanggaran kunci unik) — bukan kesalahan.
            return null;
        }
    }

    /**
     * Catat deposit lelang yang hangus sebagai pendapatan marketplace.
     *
     * Idempotensinya bersandar pada kunci unik (order_id, source) yang sama
     * dengan biaya layanan: satu pesanan lelang hanya punya satu pemenang, jadi
     * hanya ada satu deposit yang bisa hangus atasnya.
     */
    public static function recordAuctionDepositForfeit(Order $order, AuctionDeposit $deposit): ?self
    {
        $amount = (int) $deposit->amount;

        if ($amount <= 0 || $order->getKey() === null) {
            return null;
        }

        try {
            return self::create([
                'order_id' => $order->getKey(),
                'source' => self::SOURCE_AUCTION_DEPOSIT_FORFEIT,
                'amount' => $amount,
                'description' => 'Deposit lelang hangus '.$deposit->public_id.' atas pesanan '.$order->public_id,
            ]);
        } catch (QueryException $e) {
            // Sudah pernah dicatat (pelanggaran kunci unik) — bukan kesalahan.
            return null;
        }
    }

    /**
     * Balikkan biaya layanan sebuah pesanan yang refund-nya disetujui.
     *
     * Nominalnya diambil dari baris pendapatan yang benar-benar tercatat, bukan
     * dari kolom service_fee pesanan: bila biaya layanannya tidak pernah masuk
     * (pesanan batal sebelum lunas), tidak ada yang perlu dibalikkan.
     */
    public static function reverseServiceFee(Order $order, ?string $reason = null): ?self
    {
        $recorded = self::where('order_id', $order->getKey())
            ->where('source', self::SOURCE_SERVICE_FEE)
            ->value('amount');

        if (! $recorded) {
            return null;
        }

        try {
            return self::create([
                'order_id' => $order->getKey(),
                'source' => self::SOURCE_SERVICE_FEE_REVERSAL,
                'amount' => -abs((int) $recorded),
                'description' => $reason ?? 'Pembalikan biaya layanan atas refund pesanan '.$order->public_id,
            ]);
        } catch (QueryException $e) {
            return null;
        }
    }
}
