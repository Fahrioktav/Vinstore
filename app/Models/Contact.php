<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Contact extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'admin_reply',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            if (empty($contact->public_id)) {
                $contact->public_id = self::generatePublicId();
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'MSG' . random_int(10000000, 99999999);
        } while (DB::table('contacts')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
