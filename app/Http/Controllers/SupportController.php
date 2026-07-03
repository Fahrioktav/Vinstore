<?php

namespace App\Http\Controllers;

use App\Events\SupportMessageSent;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupportController extends Controller
{
    // ================= SISI USER / SELLER =================

    public function userChat()
    {
        $user = Auth::user();

        $messages = SupportMessage::with('sender')
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        // Tandai pesan dari admin sebagai sudah dibaca.
        SupportMessage::where('user_id', $user->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return Inertia::render('support/index', [
            'messages' => $messages,
        ]);
    }

    public function userSend(Request $request)
    {
        $validated = $request->validate(['body' => 'required|string|max:2000']);
        $user = Auth::user();

        $message = SupportMessage::create([
            'user_id' => $user->id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return $this->dispatchAndRespond($request, $message, $user->public_id);
    }

    // ================= SISI ADMIN =================

    public function adminIndex()
    {
        return $this->renderAdmin(null);
    }

    public function adminShow(string $userPublicId)
    {
        return $this->renderAdmin($userPublicId);
    }

    public function adminSend(Request $request, string $userPublicId)
    {
        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $owner = User::where('public_id', $userPublicId)->firstOrFail();
        $admin = Auth::user();

        $message = SupportMessage::create([
            'user_id' => $owner->id,
            'sender_id' => $admin->id,
            'body' => $validated['body'],
        ]);

        return $this->dispatchAndRespond($request, $message, $owner->public_id);
    }

    // ================= HELPERS =================

    private function renderAdmin(?string $userPublicId)
    {
        $ownerIds = SupportMessage::select('user_id')->distinct()->pluck('user_id');
        $owners = User::whereIn('id', $ownerIds)->get();

        $threads = $owners->map(function (User $u) {
            $last = SupportMessage::where('user_id', $u->id)->latest('id')->first();

            // Pesan belum dibaca admin = pesan yang ditulis oleh pemilik thread (user/seller).
            $unread = SupportMessage::where('user_id', $u->id)
                ->whereColumn('sender_id', 'user_id')
                ->whereNull('read_at')
                ->count();

            return [
                'public_id' => $u->public_id,
                'username' => $u->username,
                'role' => $u->role,
                'last_message' => $last?->body,
                'last_message_at' => $last?->created_at?->toISOString(),
                'unread' => $unread,
            ];
        })->sortByDesc('last_message_at')->values();

        $selectedPublicId = $userPublicId ?: ($threads->first()['public_id'] ?? null);

        $selectedOwner = null;
        $messages = collect();

        if ($selectedPublicId) {
            $selectedOwner = User::where('public_id', $selectedPublicId)->first();

            if ($selectedOwner) {
                $messages = SupportMessage::with('sender')
                    ->where('user_id', $selectedOwner->id)
                    ->orderBy('created_at')
                    ->get();

                // Tandai pesan dari user sebagai sudah dibaca admin.
                SupportMessage::where('user_id', $selectedOwner->id)
                    ->whereColumn('sender_id', 'user_id')
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
        }

        return Inertia::render('admin/support/index', [
            'threads' => $threads,
            'selectedOwner' => $selectedOwner ? [
                'public_id' => $selectedOwner->public_id,
                'username' => $selectedOwner->username,
                'role' => $selectedOwner->role,
                'email' => $selectedOwner->email,
            ] : null,
            'messages' => $messages,
        ]);
    }

    private function dispatchAndRespond(Request $request, SupportMessage $message, string $ownerPublicId)
    {
        $message->load('sender');

        try {
            broadcast(new SupportMessageSent($message, $ownerPublicId))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        $payload = [
            'message' => [
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
                'sender' => [
                    'public_id' => $message->sender?->public_id,
                    'username' => $message->sender?->username,
                    'role' => $message->sender?->role,
                ],
            ],
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return back();
    }
}
