<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefundRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'order_id',
        'user_id',
        'reason',
        'proof_image',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = [
        'id',
        'order_id',
        'user_id',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RefundRequest $refundRequest) {
            if (empty($refundRequest->public_id)) {
                $refundRequest->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'REF' . random_int(10000000, 99999999);
        } while (DB::table('refund_requests')->where('public_id', $publicId)->exists());

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
