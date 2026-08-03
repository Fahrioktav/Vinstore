<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
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
        'store_id',
        'reviewed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'transferred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WithdrawalRequest $withdrawalRequest) {
            if (empty($withdrawalRequest->public_id)) {
                $withdrawalRequest->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'WDR'.random_int(10000000, 99999999);
        } while (DB::table('withdrawal_requests')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
