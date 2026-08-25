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

/**
 * Kanal privat percakapan pembeli-penjual, diidentifikasi oleh public_id
 * percakapannya. Hanya dua pihak yang boleh bergabung: pembelinya dan pemilik
 * toko yang diajak bicara.
 *
 * Admin sengaja tidak diberi akses seperti pada kanal `support.*`. Percakapan
 * jual beli bukan tiket bantuan; membiarkan admin menyimaknya diam-diam adalah
 * keputusan yang harus diambil sadar, bukan diwarisi dari kanal sebelah.
 */
Broadcast::channel('conversation.{publicId}', function ($user, string $publicId) {
    $conversation = \App\Models\Conversation::with('store')
        ->where('public_id', $publicId)
        ->first();

    return (bool) $conversation?->isParticipant($user);
});
