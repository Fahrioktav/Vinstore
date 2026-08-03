<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan pencairan dana. Sumbernya dua macam, dan tepat satu yang terisi:
 *
 * - order_id: hasil penjualan satu pesanan (jual beli / lelang)
 * - barter_request_id: selisih uang (additional_cash) pada satu barter
 *
 * Dana tidak pernah cair sendiri. Seller mengajukan, lalu admin menyetujui
 * sambil mengunggah bukti transfer ke rekening yang diajukan.
 */
class PayoutRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'public_id',
        'order_id',
        'barter_request_id',
        'store_id',
        'amount',
        'bank_name',
        'account_number',
        'account_holder',
        'status',
        'admin_note',
        'transfer_proof',
        'transferred_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = [
        'id',
        'order_id',
        'barter_request_id',
        'store_id',
        'reviewed_by',
    ];

    protected $appends = [
        'source_type',
    ];

    /**
     * 'order' atau 'barter' — dipakai UI admin untuk memberi label sumber dana
     * tanpa perlu menebak dari relasi mana yang terisi.
     */
    public function getSourceTypeAttribute(): string
    {
        return $this->barter_request_id !== null ? 'barter' : 'order';
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'transferred_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PayoutRequest $payout) {
            if (empty($payout->public_id)) {
                $payout->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'PYT'.random_int(10000000, 99999999);
        } while (DB::table('payout_requests')->where('public_id', $publicId)->exists());

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

    public function barterRequest()
    {
        return $this->belongsTo(BarterRequest::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Pengajuan yang masih "menahan" pesanan: menunggu keputusan admin, atau
     * sudah disetujui. Pesanan dengan salah satu dari ini tidak boleh diajukan
     * ulang.
     */
    public function scopeBlocking($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }
}
