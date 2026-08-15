<?php

namespace App\Http\Controllers;

use App\Events\AuctionBidPlaced;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionDeposit;
use App\Models\Order;
use App\Services\GeocodingService;
use App\Services\MidtransService;
use App\Services\ShippingCostService;
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

        if ($auction->approval_status !== 'approved' && ! $canPreview) {
            abort(404);
        }

        $auction->load([
            'store.user',
            'winner',
            'order',
            'bids' => fn ($query) => $query->with('user')->latest('amount')->latest('id')->take(20),
        ]);

        return Inertia::render('auctions/show', [
            'auction' => $auction,
            // Jaminan milik pembuka halaman, supaya halaman tahu harus
            // menampilkan tombol bayar deposit, form penawaran, atau pengajuan
            // pengembalian.
            'myDeposit' => $auction->depositOf($user),
        ]);
    }

    public function create()
    {
        return Inertia::render('seller/auctions/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->auctionRules(true),
            $this->auctionMessages()
        );

        $store = Auth::user()->store;

        if (! $store) {
            return redirect()->route('store.register')->with('error', 'Anda harus memiliki toko terlebih dahulu.');
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time().'_auction_'.$image->getClientOriginalName();
            $imagePath = $image->storeAs('auctions', $imageName, 'public');
        }

        Auction::create(array_merge($this->auctionAttributes($validated), [
            'store_id' => $store->id,
            'image' => $imagePath,
            'current_price' => $validated['starting_price'],
            'approval_status' => 'pending_validator',
            'status' => 'pending',
        ]));

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil diajukan dan menunggu persetujuan admin.');
    }

    /**
     * Aturan validasi pengajuan lelang.
     *
     * Catatan tentang `starts_at`: pembandingnya adalah AWAL MENIT BERJALAN,
     * bukan `now`. Input `<input type="datetime-local">` tidak mengirim detik,
     * jadi seller yang memilih pukul 22.53 saat jam menunjukkan 22.53.20
     * sebenarnya mengirim 22.53.00 — dua puluh detik di masa lalu — dan
     * `after_or_equal:now` menolaknya tanpa alasan yang masuk akal dari sudut
     * pandang seller (temuan V6-08).
     *
     * @param  bool  $imageRequired  Foto wajib saat pengajuan baru, opsional
     *                               saat mengedit atau mengajukan ulang karena
     *                               foto lamanya dipertahankan.
     */
    private function auctionRules(bool $imageRequired): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => ($imageRequired ? 'required' : 'nullable').'|image|mimes:jpg,jpeg,png|max:2048',
            // Berat dan dimensi dipakai menghitung ongkir pesanan pemenang,
            // persis seperti pada produk biasa.
            'weight' => 'required|integer|min:1|max:500000',
            'length' => 'nullable|integer|min:1|max:500',
            'width' => 'nullable|integer|min:1|max:500',
            'height' => 'nullable|integer|min:1|max:500',
            'starting_price' => 'required|numeric|min:1000',
            'min_increment' => 'required|numeric|min:1000',
            'starts_at' => ['required', 'date', 'after_or_equal:'.now()->startOfMinute()->format('Y-m-d H:i:s')],
            'ends_at' => 'required|date|after:starts_at',
        ];
    }

    private function auctionMessages(): array
    {
        return [
            'starts_at.after_or_equal' => 'Waktu mulai tidak boleh di masa lalu. Pilih menit ini atau sesudahnya.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'weight.required' => 'Berat barang wajib diisi agar ongkir pemenang bisa dihitung.',
        ];
    }

    /**
     * Atribut lelang yang berasal langsung dari form, sudah dinormalkan.
     */
    private function auctionAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'weight' => (int) $validated['weight'],
            // Dimensi boleh kosong: berat volumetrik hanya dihitung bila
            // ketiganya terisi.
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'starting_price' => $validated['starting_price'],
            'min_increment' => $validated['min_increment'],
            'starts_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], config('app.timezone')),
            'ends_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], config('app.timezone')),
        ];
    }

    public function edit(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (! $this->canEditAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Lelang hanya bisa diedit sebelum berjalan dan belum memiliki bid.');
        }

        return Inertia::render('seller/auctions/edit', compact('auction'));
    }

    public function update(Request $request, Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (! $this->canEditAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Lelang hanya bisa diedit sebelum berjalan dan belum memiliki bid.');
        }

        $validated = $request->validate(
            $this->auctionRules(false),
            $this->auctionMessages()
        );

        if ($request->hasFile('image')) {
            if ($auction->image && Storage::disk('public')->exists($auction->image)) {
                Storage::disk('public')->delete($auction->image);
            }

            $image = $request->file('image');
            $imageName = time().'_auction_'.$image->getClientOriginalName();
            $auction->image = $image->storeAs('auctions', $imageName, 'public');
        }

        $auction->fill(array_merge($this->auctionAttributes($validated), [
            'current_price' => $validated['starting_price'],
            'approval_status' => 'pending_validator',
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ]))->save();

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil diperbarui dan menunggu persetujuan admin.');
    }

    public function destroy(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (! $this->canEditAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Lelang hanya bisa dihapus sebelum berjalan dan belum memiliki bid.');
        }

        if ($auction->image && Storage::disk('public')->exists($auction->image)) {
            Storage::disk('public')->delete($auction->image);
        }

        $auction->delete();

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil dihapus.');
    }

    /**
     * Seller menarik kembali pengajuan lelang dari antrean validator/admin
     * supaya bisa memperbaiki datanya lebih dulu. Setelah ini lelang berstatus
     * draft: tidak tampil ke pembeli, tidak muncul di antrean validator, dan
     * dapat diedit lalu diajukan ulang lewat form edit.
     */
    public function withdrawSubmission(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (! $this->canWithdrawAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Pengajuan ini sudah tidak dapat ditarik kembali.');
        }

        $auction->update([
            'approval_status' => Auction::STATUS_DRAFT,
            'status' => 'pending',
            'validated_at' => null,
            'validated_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ]);

        return redirect()->route('seller.auctions.edit', $auction->public_id)
            ->with('success', 'Pengajuan ditarik kembali. Silakan perbaiki datanya lalu ajukan ulang.');
    }

    public function relistForm(Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (! $this->canRelistAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Hanya lelang selesai tanpa bid yang bisa diajukan ulang.');
        }

        return Inertia::render('seller/auctions/relist', compact('auction'));
    }

    public function relist(Request $request, Auction $auction)
    {
        $this->authorizeSellerAuction($auction);

        if (! $this->canRelistAuction($auction)) {
            return redirect()->route('seller.dashboard')->with('error', 'Hanya lelang selesai tanpa bid yang bisa diajukan ulang.');
        }

        $validated = $request->validate(
            $this->auctionRules(false),
            $this->auctionMessages()
        );

        $imagePath = $auction->image;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time().'_auction_'.$image->getClientOriginalName();
            $imagePath = $image->storeAs('auctions', $imageName, 'public');
        }

        Auction::create(array_merge($this->auctionAttributes($validated), [
            'store_id' => Auth::user()->store->id,
            'image' => $imagePath,
            'current_price' => $validated['starting_price'],
            'approval_status' => 'pending_validator',
            'status' => 'pending',
        ]));

        return redirect()->route('seller.dashboard')->with('success', 'Barang lelang berhasil diajukan ulang dan menunggu persetujuan admin.');
    }

    public function bid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
        ]);

        $user = Auth::user();
        $newBid = null;

        // Penyegaran status dikerjakan DI LUAR transaksi penawaran. Sebelumnya
        // keduanya berada dalam satu transaksi, sehingga penutupan lelang yang
        // dilakukan di sini ikut dibatalkan begitu penawarannya ditolak — dan
        // memang selalu ditolak, karena lelangnya baru saja ditutup (V6-06).
        $this->refreshAuctionStatus($auction);
        $auction->refresh();

        try {
            DB::transaction(function () use ($auction, $validated, $user, &$newBid) {
                $auction = Auction::whereKey($auction->getKey())->lockForUpdate()->firstOrFail();

                if (! $auction->isActive()) {
                    throw new \RuntimeException('Lelang tidak sedang aktif.');
                }

                if ($user->role === 'seller' && $user->store && $auction->store_id === $user->store->id) {
                    throw new \RuntimeException('Anda tidak boleh menawar barang lelang milik toko sendiri.');
                }

                // Lelang bernilai tinggi hanya boleh ditawar oleh yang sudah
                // menaruh jaminan. Diperiksa di dalam kunci supaya jaminan yang
                // baru saja hangus tidak sempat dipakai menawar.
                if (! $auction->depositSatisfiedBy($user)) {
                    throw new \RuntimeException(
                        'Anda harus membayar deposit sebesar Rp '
                        .number_format($auction->depositAmount(), 0, ',', '.')
                        .' sebelum dapat menawar pada lelang ini.'
                    );
                }

                $minimumBid = (float) $auction->current_price + (float) $auction->min_increment;
                $amount = (float) $validated['amount'];

                if ($amount < $minimumBid) {
                    throw new \RuntimeException('Nominal bid minimal '.number_format($minimumBid, 0, ',', '.').'.');
                }

                // Cek apakah user ini adalah yang terakhir melakukan bid.
                //
                // Diurutkan menurut `id`, bukan `created_at`. Kolom waktu itu
                // presisinya hanya sampai detik, dan menjelang penutupan lelang
                // beberapa penawaran dalam satu detik adalah hal yang lumrah —
                // saat seri, baris mana yang terambil tidak dijamin basis data.
                // Akibatnya penawar yang sah bisa ditolak, atau justru lolos
                // menawar dua kali beruntun (temuan V6-02).
                $lastBid = AuctionBid::where('auction_id', $auction->id)
                    ->latest('id')
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

    /**
     * Halaman pembayaran pemenang lelang.
     *
     * Lelang tidak melewati checkout, jadi di sinilah pemenang memilih titik
     * antar, metode pengiriman, dan jenis pengemasannya — barulah ongkir, biaya
     * berat, dan biaya layanan bisa dihitung. Sampai halaman ini dikirimkan,
     * pesanannya masih berisi harga menang saja.
     */
    public function checkout(Auction $auction)
    {
        $order = $this->winnerOrderOrFail($auction);

        if (! $order instanceof Order) {
            return $order;
        }

        $auction->load('store');

        return Inertia::render('auctions/checkout', [
            'auction' => $auction,
            'order' => $order,
            'feeRates' => app(ShippingCostService::class)->publicRates(),
        ]);
    }

    public function pay(Request $request, Auction $auction)
    {
        $order = $this->winnerOrderOrFail($auction);

        if (! $order instanceof Order) {
            return $order;
        }

        $validated = $request->validate([
            'shipping_address' => 'required|string|max:1000',
            'shipping_method' => 'required|in:standard,express',
            'packaging_type' => 'nullable|string',
            'shipping_latitude' => 'required|numeric|between:-90,90',
            'shipping_longitude' => 'required|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ], [
            'shipping_latitude.required' => 'Pilih titik pengantaran di peta terlebih dahulu.',
            'shipping_longitude.required' => 'Pilih titik pengantaran di peta terlebih dahulu.',
        ]);

        $auction->load('store');

        $destLat = (float) $validated['shipping_latitude'];
        $destLng = (float) $validated['shipping_longitude'];

        // Seluruh komponen biaya dihitung ulang di server; angka dari frontend
        // hanya pratinjau. Berat dan dimensinya milik lelang itu sendiri.
        $quote = app(ShippingCostService::class)->quoteLine(
            $auction,
            1,
            (int) round((float) $order->product_price),
            $validated['shipping_method'],
            $destLat,
            $destLng,
            true,
            $validated['packaging_type'] ?? null,
        );

        $order->update([
            'price' => $quote['total'],
            'shipping_address' => $validated['shipping_address'],
            'shipping_area' => app(GeocodingService::class)->areaName($destLat, $destLng),
            'shipping_method' => $validated['shipping_method'],
            'shipping_cost' => $quote['shipping_cost'],
            'packaging_fee' => $quote['packaging_fee'],
            'packaging_type' => $quote['packaging_type'],
            'weight_fee' => $quote['weight_fee'],
            'service_fee' => $quote['service_fee'],
            'weight_gram' => $quote['weight_gram'],
            'volumetric_weight_gram' => $quote['volumetric_weight_gram'],
            'shipping_distance_km' => $quote['distance_km'],
            'shipping_latitude' => $destLat,
            'shipping_longitude' => $destLng,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Midtrans menolak order_id yang sudah pernah dipakai. Pemenang yang
        // kembali ke halaman ini untuk mengganti alamat atau jenis pengemasannya
        // karena itu perlu referensi baru — nominalnya pun sudah berbeda.
        // Referensi lama sengaja ditinggalkan tanpa pasangan: transaksi Snap
        // yang menagih angka lama memang tidak boleh lagi diselesaikan.
        if ($order->snap_token) {
            $order->update([
                'payment_reference' => $this->nextPaymentReference($order),
                'snap_token' => null,
                'snap_redirect_url' => null,
            ]);
        }

        // Yang ditagihkan adalah tagihan penuh dikurangi deposit yang sudah
        // masuk lebih dulu. `price` sendiri tetap tagihan penuh.
        $order->refresh();
        $amountDue = $order->amountDue();

        try {
            $transaction = app(MidtransService::class)->createSnapTransaction(
                $order->payment_reference,
                $amountDue,
                Auth::user(),
                $this->auctionItemDetails($auction, $order, $quote, $amountDue)
            );

            $order->update([
                'snap_token' => $transaction['token'] ?? null,
                'snap_redirect_url' => $transaction['redirect_url'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal membuat pembayaran Midtrans: '.$e->getMessage());
        }

        return redirect()->route('auctions.show', $auction->public_id)->with([
            'success' => 'Rincian pembayaran lelang siap. Silakan selesaikan pembayaran.',
            'snap_token' => $transaction['token'],
            'snap_context' => 'order',
        ]);
    }

    /**
     * Referensi pembayaran berikutnya untuk sebuah pesanan: public_id-nya
     * dengan akhiran urutan percobaan (ORD12345678-2, -3, dan seterusnya).
     *
     * Tetap memuat public_id-nya secara utuh supaya masih terbaca manusia saat
     * dicocokkan dengan dashboard Midtrans.
     */
    private function nextPaymentReference(Order $order): string
    {
        $attempt = 2;

        if (preg_match('/-(\d+)$/', (string) $order->payment_reference, $matches)) {
            $attempt = (int) $matches[1] + 1;
        }

        return $order->public_id.'-'.$attempt;
    }

    /**
     * Pesanan lelang milik pemenang yang sedang login, atau redirect bila
     * pesanannya tidak boleh lagi dibayar.
     *
     * @return Order|\Illuminate\Http\RedirectResponse
     */
    private function winnerOrderOrFail(Auction $auction)
    {
        $this->finalizeExpiredAuctions();

        $auction->refresh()->load('order');
        $order = $auction->order;

        if (! $order || $auction->winner_id !== Auth::id()) {
            abort(403, 'Anda bukan pemenang lelang ini.');
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('order')->with('success', 'Pembayaran lelang ini sudah lunas.');
        }

        // Pesanan yang sudah gugur tidak boleh dibuatkan transaksi Snap baru.
        // Tanpa penjaga ini pemenang yang membuka halamannya setelah lewat
        // tenggat tetap dilayani, membayar, lalu uangnya masuk ke pesanan yang
        // berstatus Cancelled (temuan V6-03).
        if ($order->isPaymentDead() || $order->status === 'Cancelled') {
            return redirect()->route('order')->with(
                'error',
                'Batas waktu pembayaran lelang ini sudah lewat, sehingga pesanannya dibatalkan.'
            );
        }

        return $order;
    }

    /**
     * Baris item_details Midtrans untuk pesanan lelang.
     *
     * Jumlah seluruh baris wajib sama persis dengan gross_amount, jadi deposit
     * yang sudah dibayar masuk sebagai baris bernilai NEGATIF — bukan dengan
     * mengecilkan harga barangnya, supaya pemenang melihat potongannya.
     */
    private function auctionItemDetails(Auction $auction, Order $order, array $quote, int $amountDue): array
    {
        $costs = app(ShippingCostService::class);
        $methodLabel = $order->shipping_method === 'express' ? 'Express' : 'Standard';

        $rows = [
            [$auction->public_id, (int) round((float) $order->product_price), 'Lelang - '.$auction->name],
            ['SHIP-'.$order->public_id, $quote['shipping_cost'], 'Ongkir '.$methodLabel.' '.$costs->regionLabel($quote['region'])],
            ['WEIGHT-'.$order->public_id, $quote['weight_fee'], 'Biaya Berat '.number_format($quote['billable_weight_gram'] / 1000, 2, ',', '.').' kg'],
            ['PACK-'.$order->public_id, $quote['packaging_fee'], 'Pengemasan '.$costs->packagingLabel($quote['packaging_type'])],
            ['SVC-'.$order->public_id, $quote['service_fee'], 'Biaya Layanan'],
        ];

        $items = [];

        foreach ($rows as [$id, $amount, $name]) {
            if ((int) $amount <= 0) {
                continue;
            }

            $items[] = [
                'id' => MidtransService::truncate($id, 50),
                'price' => (int) $amount,
                'quantity' => 1,
                // mb_substr lewat MidtransService::truncate(): substr() memotong
                // per byte dan bisa membelah karakter multibyte, membuat payload
                // gagal di-encode ke JSON.
                'name' => MidtransService::truncate($name, 50),
            ];
        }

        if ((int) $order->deposit_credit > 0) {
            $items[] = [
                'id' => MidtransService::truncate('DEP-'.$order->public_id, 50),
                'price' => -((int) $order->deposit_credit),
                'quantity' => 1,
                'name' => MidtransService::truncate('Potongan deposit lelang', 50),
            ];
        }

        // Penjaga terakhir: bila karena satu dan lain hal jumlah baris tidak
        // sama dengan yang ditagihkan, Midtrans akan menolak transaksinya.
        // Lebih baik dikirim sebagai satu baris ringkas daripada gagal total.
        $sum = array_sum(array_column($items, 'price'));

        if ($sum !== $amountDue) {
            return [[
                'id' => MidtransService::truncate($order->public_id, 50),
                'price' => $amountDue,
                'quantity' => 1,
                'name' => MidtransService::truncate('Pembayaran lelang - '.$auction->name, 50),
            ]];
        }

        return $items;
    }

    /**
     * Pembeli membayar uang jaminan agar berhak menawar.
     *
     * Jaminannya dibuat sekali per pengguna per lelang; pemanggilan berikutnya
     * memakai transaksi Snap yang sama selama belum dibayar.
     */
    public function payDeposit(Auction $auction)
    {
        $this->activateApprovedAuctions();
        $auction->refresh();

        $user = Auth::user();

        if (! $auction->requiresDeposit()) {
            return back()->with('error', 'Lelang ini tidak memungut deposit.');
        }

        if (! $auction->isActive()) {
            return back()->with('error', 'Deposit hanya bisa dibayar selama lelang berlangsung.');
        }

        if ($user->role === 'seller' && $user->store && $auction->store_id === $user->store->id) {
            return back()->with('error', 'Anda tidak boleh menawar barang lelang milik toko sendiri.');
        }

        $existing = $auction->depositOf($user);

        if ($existing && $existing->isActive()) {
            return back()->with('success', 'Deposit Anda sudah aktif. Silakan langsung menawar.');
        }

        if ($existing && $existing->status !== AuctionDeposit::STATUS_PENDING) {
            return back()->with('error', 'Deposit Anda pada lelang ini sudah tidak dapat dipakai lagi.');
        }

        $deposit = $existing;

        if (! $deposit) {
            $deposit = AuctionDeposit::create([
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'amount' => $auction->depositAmount(),
                'status' => AuctionDeposit::STATUS_PENDING,
            ]);

            $deposit->update(['payment_reference' => $deposit->public_id]);
        }

        if ($deposit->snap_token) {
            return back()->with([
                'success' => 'Silakan selesaikan pembayaran deposit.',
                'snap_token' => $deposit->snap_token,
                'snap_context' => 'deposit',
            ]);
        }

        try {
            $transaction = app(MidtransService::class)->createSnapTransaction(
                $deposit->payment_reference,
                (int) $deposit->amount,
                $user,
                [[
                    'id' => $deposit->public_id,
                    'price' => (int) $deposit->amount,
                    'quantity' => 1,
                    'name' => MidtransService::truncate('Deposit lelang - '.$auction->name, 50),
                ]]
            );

            $deposit->update([
                'snap_token' => $transaction['token'] ?? null,
                'snap_redirect_url' => $transaction['redirect_url'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal membuat pembayaran deposit: '.$e->getMessage());
        }

        return back()->with([
            'success' => 'Deposit dibuat. Silakan selesaikan pembayaran agar bisa menawar.',
            'snap_token' => $transaction['token'],
            'snap_context' => 'deposit',
        ]);
    }

    /**
     * Daftar deposit milik pembeli, tempat ia mengajukan pengembalian.
     */
    public function myDeposits()
    {
        $deposits = AuctionDeposit::with(['auction.store', 'reviewer'])
            ->where('user_id', Auth::id())
            ->latest('id')
            ->get()
            ->each(function (AuctionDeposit $deposit) {
                // Dihitung di server agar halaman tidak perlu mengulang
                // aturannya sendiri dan berisiko berbeda.
                $deposit->setAttribute('can_request_refund', $deposit->canRequestRefund());
                $deposit->setAttribute('refund_block_reason', $deposit->refundBlockReason());
            });

        return Inertia::render('deposits/index', compact('deposits'));
    }

    /**
     * Peserta yang kalah meminta uang jaminannya kembali. Admin yang akan
     * mentransfernya ke rekening yang diisi di sini.
     */
    public function requestDepositRefund(Request $request, AuctionDeposit $deposit)
    {
        if ($deposit->user_id !== Auth::id()) {
            abort(403, 'Ini bukan deposit Anda.');
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'account_holder' => 'required|string|max:150',
        ]);

        $deposit->load('auction');

        if (! $deposit->canRequestRefund()) {
            return back()->with('error', $deposit->refundBlockReason() ?? 'Deposit ini belum dapat dimintakan kembali.');
        }

        try {
            DB::transaction(function () use ($deposit, $validated) {
                $locked = AuctionDeposit::whereKey($deposit->getKey())->lockForUpdate()->firstOrFail();

                // Diperiksa ulang di dalam kunci: penutupan lelang bisa saja
                // mengubah statusnya tepat setelah pengecekan di atas.
                if ($locked->status !== AuctionDeposit::STATUS_PAID) {
                    throw new \RuntimeException('Deposit ini sudah tidak dapat dimintakan kembali.');
                }

                $locked->forceFill([
                    'status' => AuctionDeposit::STATUS_REFUND_REQUESTED,
                    'bank_name' => $validated['bank_name'],
                    'account_number' => $validated['account_number'],
                    'account_holder' => $validated['account_holder'],
                    'refund_requested_at' => now(),
                ])->save();
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pengembalian deposit dikirim dan menunggu verifikasi admin.');
    }

    public function adminDepositIndex()
    {
        $deposits = AuctionDeposit::with(['auction.store', 'user', 'reviewer'])
            ->latest('id')
            ->get();

        return Inertia::render('admin/deposits/index', compact('deposits'));
    }

    /**
     * Admin menyetujui pengembalian setelah benar-benar mentransfer dananya.
     * Bukti transfer wajib, mengikuti pola pencairan saldo toko.
     */
    public function approveDepositRefund(Request $request, AuctionDeposit $deposit)
    {
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:1000',
            'transfer_proof' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'transfer_proof.required' => 'Bukti transfer wajib diunggah sebelum pengembalian disetujui.',
        ]);

        if ($deposit->status !== AuctionDeposit::STATUS_REFUND_REQUESTED) {
            return back()->with('error', 'Pengajuan pengembalian deposit ini sudah diproses.');
        }

        $proofPath = $request->file('transfer_proof')->store('deposits', 'public');

        try {
            DB::transaction(function () use ($deposit, $validated, $proofPath) {
                $locked = AuctionDeposit::whereKey($deposit->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->status !== AuctionDeposit::STATUS_REFUND_REQUESTED) {
                    throw new \RuntimeException('Pengajuan pengembalian deposit ini sudah diproses.');
                }

                $locked->forceFill([
                    'status' => AuctionDeposit::STATUS_REFUNDED,
                    'admin_note' => $validated['admin_note'] ?? null,
                    'transfer_proof' => $proofPath,
                    'refunded_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ])->save();
            });
        } catch (\RuntimeException $e) {
            // Batalkan unggahan bukti bila pengembaliannya gagal diproses.
            Storage::disk('public')->delete($proofPath);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengembalian deposit ditandai sudah ditransfer.');
    }

    /**
     * Pengajuan ditolak: depositnya kembali berstatus `paid` agar pembeli bisa
     * memperbaiki nomor rekeningnya lalu mengajukan ulang.
     */
    public function rejectDepositRefund(Request $request, AuctionDeposit $deposit)
    {
        $validated = $request->validate([
            'admin_note' => 'required|string|max:1000',
        ], [
            'admin_note.required' => 'Alasan penolakan wajib diisi agar pembeli tahu apa yang harus diperbaiki.',
        ]);

        try {
            DB::transaction(function () use ($deposit, $validated) {
                $locked = AuctionDeposit::whereKey($deposit->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->status !== AuctionDeposit::STATUS_REFUND_REQUESTED) {
                    throw new \RuntimeException('Pengajuan pengembalian deposit ini sudah diproses.');
                }

                $locked->forceFill([
                    'status' => AuctionDeposit::STATUS_PAID,
                    'admin_note' => $validated['admin_note'],
                    'refund_requested_at' => null,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ])->save();
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan pengembalian deposit ditolak. Pembeli dapat mengajukan ulang.');
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
            ->each(fn (Auction $auction) => $auction->finishNow());
    }

    private function refreshAuctionStatus(Auction $auction): void
    {
        if ($auction->approval_status !== 'approved') {
            return;
        }

        if ($auction->ends_at->lte(now())) {
            $auction->finishNow();

            return;
        }

        if ($auction->starts_at->lte(now()) && $auction->status === 'scheduled') {
            $auction->update(['status' => 'active']);
            $auction->refresh();
        }
    }

    private function authorizeSellerAuction(Auction $auction): void
    {
        $store = Auth::user()->store;

        if (! $store || $auction->store_id !== $store->id) {
            abort(403, 'Anda tidak memiliki akses ke lelang ini.');
        }
    }

    /**
     * Lelang boleh diubah/dihapus selama belum ada penawaran masuk dan belum
     * benar-benar berjalan.
     *
     * Catatan: sebelumnya daftar approval_status di sini berisi 'pending' —
     * nilai yang tidak pernah ada di kolom itu (nilai yang sah adalah
     * pending_validator/pending_admin/approved/rejected). Akibatnya lelang yang
     * baru diajukan SELALU gagal lolos pengecekan, sehingga seller tidak pernah
     * bisa memperbaiki typo maupun membatalkan pengajuannya (temuan T-07).
     */
    private function canEditAuction(Auction $auction): bool
    {
        return $auction->bids_count === 0
            && in_array($auction->status, ['pending', 'scheduled'], true)
            && in_array($auction->approval_status, Auction::EDITABLE_APPROVAL_STATUSES, true);
    }

    /**
     * Apakah pengajuan masih bisa ditarik kembali oleh seller.
     * Hanya yang masih mengantre di validator/admin dan belum ada penawaran.
     */
    private function canWithdrawAuction(Auction $auction): bool
    {
        return $auction->bids_count === 0
            && in_array($auction->approval_status, [
                Auction::STATUS_PENDING_VALIDATOR,
                Auction::STATUS_PENDING_ADMIN,
            ], true);
    }

    private function canRelistAuction(Auction $auction): bool
    {
        return $auction->status === 'ended' && $auction->bids_count === 0;
    }
}
