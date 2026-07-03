<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Channel privat thread bantuan (support) antara user/seller dan admin.
 * Diidentifikasi oleh public_id pemilik thread. Yang boleh bergabung:
 *  - admin (dapat melayani semua thread), atau
 *  - user/seller pemilik thread itu sendiri.
 */
Broadcast::channel('support.{publicId}', function ($user, string $publicId) {
    return $user->role === 'admin'
        || (string) $user->public_id === (string) $publicId;
});
