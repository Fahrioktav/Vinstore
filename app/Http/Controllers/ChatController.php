<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Percakapan langsung antara pembeli dan penjual.
 *
 * Dibedakan dari SupportController, yang melayani chat bantuan ke admin. Yang
 * ini menghubungkan dua pihak yang bertransaksi, dan admin TIDAK ikut membaca.
 *
 * Satu utas per pasangan (toko, pembeli), sesuai unique(store_id, buyer_id) di
 * tabel `conversations`. Barang atau pesanan yang sedang dibicarakan menempel
 * pada pesannya, bukan pada utasnya — lihat App\Models\Message.
 *
 * Kedua sisi memakai satu berkas halaman yang sama (`chat/index`) dengan `mode`
 * yang berbeda. Yang membedakan hanyalah siapa lawan bicaranya dan bagaimana
 * daftar utasnya diberi judul; gelembung pesan, pengiriman, dan penerimaan
 * real-time-nya identik, jadi menduplikasinya hanya melahirkan dua tampilan
 * yang lambat laun berbeda.
 */
class ChatController extends Controller
{
    // ================= SISI PEMBELI =================

    public function index()
    {
        return $this->renderInbox('buyer', null);
    }

    /**
     * Buka percakapan dengan penjual sebuah produk.
     *
     * Utasnya dibuat bila belum ada, tetapi TIDAK ada pesan yang dikirim di
     * sini — pembeli belum menulis apa pun. Yang dibawa hanyalah konteksnya,
     * yang akan menempel pada pesan pertama yang benar-benar ia kirim.
     */
    public function startFromProduct(Product $product)
    {
        $buyer = Auth::user();
        $product->loadMissing('store');

        if (! $product->store) {
            return back()->with('error', 'Produk ini tidak memiliki toko yang bisa dihubungi.');
        }

        if ($blocker = $this->storeContactBlockReason($product->store, $buyer)) {
            return back()->with('error', $blocker);
        }

        // Produk yang belum disetujui tidak tampil di mana pun, jadi tidak ada
        // alasan sah untuk menanyakannya — kecuali oleh sellernya sendiri, yang
        // sudah tertolak di atas.
        if ($product->approval_status !== Product::STATUS_APPROVED) {
            return back()->with('error', 'Produk ini belum tersedia untuk ditanyakan.');
        }

        $conversation = Conversation::between($product->store, $buyer);

        return redirect()->route('chat.show', [
            'conversation' => $conversation->public_id,
            'produk' => $product->public_id,
        ]);
    }

    /**
     * Buka percakapan dengan penjual sebuah pesanan.
     */
    public function startFromOrder(string $order)
    {
        $buyer = Auth::user();

        // Dicari sendiri lewat public_id, bukan lewat route-model binding.
        // Order tidak menyetel getRouteKeyName(), jadi binding otomatis akan
        // mencocokkannya dengan `id` numerik — persis yang tidak boleh muncul
        // di URL menurut konvensi proyek ini.
        $order = Order::where('public_id', $order)
            ->where('user_id', $buyer->id)
            ->firstOrFail();

        $store = $order->store_id ? Store::find($order->store_id) : null;

        if (! $store) {
            return back()->with('error', 'Toko penjual pesanan ini sudah tidak tersedia.');
        }

        if ($blocker = $this->storeContactBlockReason($store, $buyer)) {
            return back()->with('error', $blocker);
        }

        $conversation = Conversation::between($store, $buyer);

        return redirect()->route('chat.show', [
            'conversation' => $conversation->public_id,
            'pesanan' => $order->public_id,
        ]);
    }

    // ================= SISI PENJUAL =================

    public function sellerIndex()
    {
        return $this->renderInbox('seller', null);
    }

    // ================= DIPAKAI KEDUA SISI =================

    /**
     * Buka satu percakapan.
     *
     * Rute yang sama melayani pembeli dan penjual: yang menentukan bukan peran
     * pengguna melainkan kedudukannya di dalam utas itu. Seller yang membeli
     * dari toko lain adalah pembeli di utas tersebut, dan halamannya harus
     * memperlakukannya begitu.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $user = Auth::user();
        $conversation->loadMissing('store');

        if (! $conversation->isParticipant($user)) {
            abort(403, 'Anda bukan bagian dari percakapan ini.');
        }

        $mode = $conversation->buyer_id === $user->id ? 'buyer' : 'seller';

        return $this->renderInbox($mode, $conversation, [
            'product' => $this->resolvePendingProduct($request->query('produk'), $conversation),
            'order' => $this->resolvePendingOrder($request->query('pesanan'), $conversation, $user),
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'produk' => 'nullable|string',
            'pesanan' => 'nullable|string',
        ], [
            'body.required' => 'Pesan tidak boleh kosong.',
            'body.max' => 'Pesan maksimal 2000 karakter.',
        ]);

        $user = Auth::user();
        $conversation->loadMissing('store');

        if (! $conversation->isParticipant($user)) {
            abort(403, 'Anda bukan bagian dari percakapan ini.');
        }

        // Toko yang penjualnya dinonaktifkan tidak bisa lagi menerima pesan
        // baru. Riwayatnya tetap terbaca — yang ditutup hanya kotak kirimnya.
        if ($conversation->buyer_id === $user->id
            && $conversation->store?->user?->isDeactivated()) {
            return $this->respondWithError(
                $request,
                'Penjual ini sedang dinonaktifkan sehingga tidak dapat menerima pesan.'
            );
        }

        // Konteks hanya diterima dari pembeli, dan hanya yang benar-benar
        // miliknya. Tanpa pemeriksaan ini siapa pun yang tahu sebuah public_id
        // bisa menempelkan pesanan orang lain ke percakapannya sendiri, dan
        // nama barang pada pesanan itu ikut terbaca lawan bicaranya.
        $product = $conversation->buyer_id === $user->id
            ? $this->resolvePendingProduct($validated['produk'] ?? null, $conversation)
            : null;

        $order = $conversation->buyer_id === $user->id
            ? $this->resolvePendingOrder($validated['pesanan'] ?? null, $conversation, $user)
            : null;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'product_id' => $product?->id,
            'order_id' => $order?->id,
            'body' => $validated['body'],
        ]);

        $conversation->touchLastMessage($message);

        $message->load(['sender', 'product', 'order']);

        try {
            broadcast(new ChatMessageSent($message, $conversation->public_id))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        $payload = ['message' => $this->serializeMessage($message)];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return back();
    }

    // ================= HELPERS =================

    /**
     * Alasan sebuah toko tidak boleh dihubungi pembeli ini, atau null bila boleh.
     */
    private function storeContactBlockReason(Store $store, User $buyer): ?string
    {
        if ($store->user_id === $buyer->id) {
            return 'Anda tidak dapat mengirim pesan ke toko Anda sendiri.';
        }

        $store->loadMissing('user');

        if ($store->user?->isDeactivated()) {
            return 'Penjual ini sedang dinonaktifkan sehingga tidak dapat menerima pesan.';
        }

        return null;
    }

    /**
     * Produk yang dirujuk, hanya bila ia benar-benar milik toko pada percakapan
     * ini. Produk toko lain tidak ada urusannya dengan utas ini.
     */
    private function resolvePendingProduct(?string $publicId, Conversation $conversation): ?Product
    {
        if (blank($publicId)) {
            return null;
        }

        return Product::where('public_id', $publicId)
            ->where('store_id', $conversation->store_id)
            ->first();
    }

    /**
     * Pesanan yang dirujuk, hanya bila ia milik pembeli pada percakapan ini DAN
     * berasal dari toko yang sedang diajak bicara.
     */
    private function resolvePendingOrder(?string $publicId, Conversation $conversation, User $user): ?Order
    {
        if (blank($publicId)) {
            return null;
        }

        return Order::where('public_id', $publicId)
            ->where('user_id', $conversation->buyer_id)
            ->where('store_id', $conversation->store_id)
            ->when($conversation->buyer_id !== $user->id, fn ($query) => $query->whereRaw('1 = 0'))
            ->first();
    }

    /**
     * Daftar percakapan milik pengguna beserta isi utas yang sedang dibuka.
     *
     * @param  string  $mode  'buyer' bila pengguna adalah pembelinya, 'seller'
     *                        bila ia pemilik tokonya.
     */
    private function renderInbox(string $mode, ?Conversation $selected, array $pending = [])
    {
        $user = Auth::user();

        $conversations = $this->conversationsFor($mode, $user);

        // Utas yang baru dibuat lewat tombol "Chat Penjual" belum punya pesan
        // apa pun, sehingga sengaja tidak ikut di daftar sebelah kiri — daftar
        // berisi percakapan, bukan niat berkirim pesan. Tetapi ia tetap harus
        // BISA DIBUKA, jadi pemilihannya tidak boleh bergantung pada daftar itu.
        $selected ??= $conversations->first();

        $threads = $conversations->map(function (Conversation $conversation) use ($mode, $user) {
            $lawan = $mode === 'buyer'
                ? $conversation->store?->store_name
                : $conversation->buyer?->username;

            return [
                'public_id' => $conversation->public_id,
                'title' => $lawan ?? 'Tidak diketahui',
                'photo' => $mode === 'buyer'
                    ? $conversation->store?->photo
                    : $conversation->buyer?->photo,
                'last_message' => $conversation->messages->sortBy('id')->last()?->body,
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'unread' => $conversation->messages
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ];
        })->values();

        $messages = collect();

        if ($selected) {
            $messages = $selected->messages()
                ->with(['sender', 'product', 'order'])
                ->orderBy('id')
                ->get();

            // Tandai pesan lawan bicara sebagai sudah dibaca.
            $selected->messages()
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            // Utas yang baru saja dibuka tidak boleh tetap tampil berlencana
            // di daftar sebelah kiri pada halaman yang sama.
            $threads = $threads->map(function (array $thread) use ($selected) {
                if ($thread['public_id'] === $selected->public_id) {
                    $thread['unread'] = 0;
                }

                return $thread;
            });
        }

        return Inertia::render('chat/index', [
            'mode' => $mode,
            'threads' => $threads,
            'selected' => $selected ? [
                'public_id' => $selected->public_id,
                'title' => $mode === 'buyer'
                    ? ($selected->store?->store_name ?? 'Toko')
                    : ($selected->buyer?->username ?? 'Pembeli'),
                'subtitle' => $mode === 'buyer'
                    ? ($selected->store?->location ?? null)
                    : ($selected->buyer?->email ?? null),
                'store_url' => $mode === 'buyer' && $selected->store
                    ? '/toko/'.$selected->store->public_id
                    : null,
            ] : null,
            'messages' => $messages->map(fn (Message $message) => $this->serializeMessage($message))->all(),
            // Konteks yang dibawa dari halaman produk/pesanan dan belum menempel
            // pada pesan mana pun — ditampilkan sebagai kartu di atas kotak
            // ketik sampai pembeli benar-benar mengirim pesannya.
            'pendingContext' => $this->serializePendingContext($pending),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Conversation>
     */
    private function conversationsFor(string $mode, User $user)
    {
        $query = Conversation::with(['store.user', 'buyer', 'messages'])
            ->whereNotNull('last_message_at');

        if ($mode === 'buyer') {
            $query->where('buyer_id', $user->id);
        } else {
            $storeId = $user->store?->id;

            // Seller tanpa toko tidak mungkin punya utas sebagai penjual.
            $query->where('store_id', $storeId ?? 0);
        }

        return $query->orderByDesc('last_message_at')->get();
    }

    private function serializeMessage(Message $message): array
    {
        return [
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'context' => $message->context,
            'sender' => [
                'public_id' => $message->sender?->public_id,
                'username' => $message->sender?->username,
            ],
        ];
    }

    /**
     * @param  array{product?: ?Product, order?: ?Order}  $pending
     */
    private function serializePendingContext(array $pending): ?array
    {
        if ($product = $pending['product'] ?? null) {
            return [
                'type' => 'product',
                'label' => 'Menanyakan produk',
                'name' => $product->name,
                'image' => $product->image,
                'param' => ['produk' => $product->public_id],
            ];
        }

        if ($order = $pending['order'] ?? null) {
            return [
                'type' => 'order',
                'label' => 'Terkait pesanan',
                'name' => $order->display_item_name,
                'image' => null,
                'reference' => $order->public_id,
                'param' => ['pesanan' => $order->public_id],
            ];
        }

        return null;
    }

    private function respondWithError(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }
}
