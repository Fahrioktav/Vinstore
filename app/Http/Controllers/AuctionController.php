<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Order;
use App\Services\MidtransService;
use App\Events\AuctionBidPlaced;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Throwable;

class AuctionController extends Controller
{
    public function index()
    {
        $this->finalizeExpiredAuctions();
        $this->activateApprovedAuctions();

        $auctions = Auction::visible()
            ->with(['store', 'winner'])
            ->latest()
            ->get();

        return Inertia::render('auctions/index', compact('auctions'));
    }

    public function show(Auction $auction)
    {
        $this->finalizeExpiredAuctions();
        $this->activateApprovedAuctions();

        $auction->refresh();

        $user = Auth::user();
        $canPreview = $user && (
            $user->role === 'admin'
            || ($user->role === 'seller' && $user->store && $auction->store_id === $user->store->id)
        );

        if ($auction->approval_status !== 'approved' && !$canPreview) {
            abort(404);
        }

        $auction->load([
            'store.user',
            'winner',
            'order',
            'bids' => fn ($query) => $query->with('user')->latest('amount')->latest()->take(20),
        ]);

        return Inertia::render('auctions/show', compact('auction'));
    }

    public function create()
    {
        return Inertia::render('seller/auctions/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'starting_price' => 'required|numeric|min:1000',
            'min_increment' => 'required|numeric|min:1000',
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
        ]);

        $store = Auth::user()->store;

        if (!$store) {
            return redirect()->route('store.register')->with('error', 'Anda harus memiliki toko terlebih dahulu.');
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_auction_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('auctions', $imageName, 'public');
        }

        Auction::create([
            'store_id' => $store->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'image' => $imagePath,
            'starting_price' => $validated['starting_price'],
            'min_increment' => $validated['min_increment'],
            'current_price' => $validated['starting_price'],
            'starts_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], config('app.timezone')),
            'ends_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], config('app.timezone')),
            'approval_status' => 'pending_validator',
            'status' => 'pending',
        ]);

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil diajukan dan menunggu persetujuan admin.');
    }

    public function edit(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (!$this->canEditAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Lelang hanya bisa diedit sebelum berjalan dan belum memiliki bid.');
        }

        return Inertia::render('seller/auctions/edit', compact('auction'));
    }

    public function update(Request $request, Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (!$this->canEditAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Lelang hanya bisa diedit sebelum berjalan dan belum memiliki bid.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'starting_price' => 'required|numeric|min:1000',
            'min_increment' => 'required|numeric|min:1000',
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
        ]);

        if ($request->hasFile('image')) {
            if ($auction->image && Storage::disk('public')->exists($auction->image)) {
                Storage::disk('public')->delete($auction->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_auction_' . $image->getClientOriginalName();
            $auction->image = $image->storeAs('auctions', $imageName, 'public');
        }

        $auction->fill([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'starting_price' => $validated['starting_price'],
            'min_increment' => $validated['min_increment'],
            'current_price' => $validated['starting_price'],
            'starts_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], config('app.timezone')),
            'ends_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], config('app.timezone')),
            'approval_status' => 'pending_validator',
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ])->save();

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil diperbarui dan menunggu persetujuan admin.');
    }

    public function destroy(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (!$this->canEditAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Lelang hanya bisa dihapus sebelum berjalan dan belum memiliki bid.');
        }

        if ($auction->image && Storage::disk('public')->exists($auction->image)) {
            Storage::disk('public')->delete($auction->image);
        }

        $auction->delete();

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil dihapus.');
    }

    public function relistForm(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (!$this->canRelistAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Hanya lelang selesai tanpa bid yang bisa diajukan ulang.');
        }

        return Inertia::render('seller/auctions/relist', compact('auction'));
    }

    public function relist(Request $request, Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (!$this->canRelistAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Hanya lelang selesai tanpa bid yang bisa diajukan ulang.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'starting_price' => 'required|numeric|min:1000',
            'min_increment' => 'required|numeric|min:1000',
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
        ]);

        $imagePath = $auction->image;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_auction_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('auctions', $imageName, 'public');
        }

        Auction::create([
            'store_id' => Auth::user()->store->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'image' => $imagePath,
            'starting_price' => $validated['starting_price'],
            'min_increment' => $validated['min_increment'],
            'current_price' => $validated['starting_price'],
            'starts_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], config('app.timezone')),
            'ends_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], config('app.timezone')),
            'approval_status' => 'pending_validator',
            'status' => 'pending',
        ]);

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil diajukan ulang dan menunggu persetujuan admin.');
    }

    public function bid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
        ]);

        $user = Auth::user();
        $newBid = null;

        try {
            DB::transaction(function () use ($auction, $validated, $user, &$newBid) {
                $auction = Auction::whereKey($auction->getKey())->lockForUpdate()->firstOrFail();
                $this->refreshAuctionStatus($auction);

                if (!$auction->isActive()) {
                    throw new \RuntimeException('Lelang tidak sedang aktif.');
                }

                if ($user->role === 'seller' && $user->store && $auction->store_id === $user->store->id) {
                    throw new \RuntimeException('Anda tidak boleh menawar barang lelang milik toko sendiri.');
                }

                $minimumBid = (float) $auction->current_price + (float) $auction->min_increment;
                $amount = (float) $validated['amount'];

                if ($amount < $minimumBid) {
                    throw new \RuntimeException('Nominal bid minimal ' . number_format($minimumBid, 0, ',', '.') . '.');
                }

                // Cek apakah user ini adalah yang terakhir melakukan bid
                $lastBid = AuctionBid::where('auction_id', $auction->id)
                    ->latest()
                    ->first();

                if ($lastBid && $lastBid->user_id === $user->id) {
                    throw new \RuntimeException('Anda sudah melakukan bid terakhir. Tunggu pembeli lain melakukan bid terlebih dahulu.');
                }

                $newBid = AuctionBid::create([
                    'auction_id' => $auction->id,
                    'user_id' => $user->id,
                    'amount' => $amount,
                ]);

                $auction->update([
                    'current_price' => $amount,
                    'bids_count' => $auction->bids_count + 1,
                ]);
            });

            // Broadcast event setelah transaction berhasil
            if ($newBid) {
                $newBid->load('user');
                try {
                    broadcast(new AuctionBidPlaced($newBid, [
                        'current_price' => $auction->fresh()->current_price,
                        'bids_count' => $auction->fresh()->bids_count,
                    ]))->toOthers();
                } catch (Throwable $broadcastException) {
                    report($broadcastException);

                    Log::warning('Gagal mengirim broadcast penawaran lelang.', [
                        'auction_id' => $auction->id,
                        'bid_id' => $newBid->id,
                        'message' => $broadcastException->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penawaran berhasil diajukan.');
    }

    public function pay(Auction $auction)
    {
        $this->finalizeExpiredAuctions();

        $auction->refresh()->load('order');
        $order = $auction->order;

        if (!$order || $auction->winner_id !== Auth::id()) {
            abort(403, 'Anda bukan pemenang lelang ini.');
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('order')->with('success', 'Pembayaran lelang ini sudah lunas.');
        }

        // Jika snap_token sudah ada, langsung redirect dengan snap_token
        if ($order->snap_token) {
            return redirect()->route('auction.show', $auction->public_id)->with([
                'success' => 'Silakan selesaikan pembayaran lelang.',
                'snap_token' => $order->snap_token
            ]);
        }

        try {
            $transaction = app(MidtransService::class)->createSnapTransaction(
                $order->payment_reference,
                (int) round((float) $order->price),
                Auth::user(),
                [[
                    'id' => $auction->public_id,
                    'price' => (int) round((float) $order->price),
                    'quantity' => 1,
                    'name' => substr('Lelang - ' . $auction->name, 0, 50),
                ]]
            );

            $order->update([
                'snap_token' => $transaction['token'] ?? null,
                'snap_redirect_url' => $transaction['redirect_url'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal membuat pembayaran Midtrans: ' . $e->getMessage());
        }

        // Return dengan snap_token untuk trigger Snap Popup
        return redirect()->route('auction.show', $auction->public_id)->with([
            'success' => 'Order lelang berhasil dibuat. Silakan selesaikan pembayaran.',
            'snap_token' => $transaction['token']
        ]);
    }

    public function adminIndex()
    {
        $this->finalizeExpiredAuctions();
        $this->activateApprovedAuctions();

        // Hanya tampilkan lelang yang sudah divalidasi validator (pending_admin) dan yang sudah diproses
        $auctions = Auction::with(['store', 'winner', 'highestBid.user', 'validator'])
            ->whereIn('approval_status', [Auction::STATUS_PENDING_ADMIN, Auction::STATUS_APPROVED, Auction::STATUS_REJECTED])
            ->latest()
            ->get();

        return Inertia::render('admin/auctions/index', compact('auctions'));
    }

    public function approve(Auction $auction)
    {
        if ($auction->approval_status !== Auction::STATUS_PENDING_ADMIN) {
            return back()->with('error', 'Lelang ini belum divalidasi oleh validator.');
        }

        $status = now()->lt($auction->starts_at) ? 'scheduled' : 'active';

        if (now()->gt($auction->ends_at)) {
            return back()->with('error', 'Tanggal selesai lelang sudah lewat.');
        }

        $auction->update([
            'approval_status' => Auction::STATUS_APPROVED,
            'status' => $status,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Lelang berhasil disetujui.');
    }

    public function reject(Request $request, Auction $auction)
    {
        if ($auction->approval_status !== Auction::STATUS_PENDING_ADMIN) {
            return back()->with('error', 'Lelang ini belum divalidasi oleh validator.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $auction->update([
            'approval_status' => Auction::STATUS_REJECTED,
            'status' => 'cancelled',
            'approved_at' => null,
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return back()->with('success', 'Lelang berhasil ditolak.');
    }

    private function activateApprovedAuctions(): void
    {
        Auction::where('approval_status', 'approved')
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->update(['status' => 'active']);
    }

    private function finalizeExpiredAuctions(): void
    {
        Auction::where('approval_status', 'approved')
            ->whereIn('status', ['scheduled', 'active'])
            ->where('ends_at', '<=', now())
            ->each(fn (Auction $auction) => $this->finishAuction($auction));
    }

    private function refreshAuctionStatus(Auction $auction): void
    {
        if ($auction->approval_status !== 'approved') {
            return;
        }

        if ($auction->ends_at->lte(now())) {
            $this->finishAuction($auction);
            $auction->refresh();
            return;
        }

        if ($auction->starts_at->lte(now()) && $auction->status === 'scheduled') {
            $auction->update(['status' => 'active']);
            $auction->refresh();
        }
    }

    private function finishAuction(Auction $auction): void
    {
        DB::transaction(function () use ($auction) {
            $auction = Auction::whereKey($auction->getKey())->lockForUpdate()->first();

            if (!$auction || $auction->status === 'ended') {
                return;
            }

            $highestBid = AuctionBid::where('auction_id', $auction->id)
                ->orderByDesc('amount')
                ->orderBy('created_at')
                ->first();

            $updates = [
                'status' => 'ended',
                'ended_at' => now(),
            ];

            if ($highestBid) {
                $updates['winner_id'] = $highestBid->user_id;
            }

            $auction->update($updates);

            if ($highestBid && !Order::where('auction_id', $auction->id)->exists()) {
                $order = Order::create([
                    'user_id' => $highestBid->user_id,
                    'product_id' => null,
                    'auction_id' => $auction->id,
                    'store_id' => $auction->store_id,
                    'quantity' => 1,
                    'price' => $highestBid->amount,
                    'status' => 'Waiting',
                    'payment_status' => 'pending',
                    'payment_method' => 'midtrans',
                ]);

                $order->update(['payment_reference' => $order->public_id]);
            }
        });
    }

    private function authorizeSellerAuction(Auction $auction): void
    {
        $store = Auth::user()->store;

        if (!$store || $auction->store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses ke lelang ini.');
        }
    }

    private function canEditAuction(Auction $auction): bool
    {
        return $auction->bids_count === 0
            && in_array($auction->status, ['pending', 'scheduled'], true)
            && in_array($auction->approval_status, ['pending', 'approved', 'rejected'], true);
    }

    private function canRelistAuction(Auction $auction): bool
    {
        return $auction->status === 'ended' && $auction->bids_count === 0;
    }
}
