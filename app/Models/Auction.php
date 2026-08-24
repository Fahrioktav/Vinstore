<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Auction extends Model
{
    use HasFactory;

    // Approval Status Constants
    // DRAFT = pengajuan ditarik kembali oleh seller untuk diperbaiki.
    const STATUS_DRAFT = 'draft';

    const STATUS_PENDING_VALIDATOR = 'pending_validator';

    const STATUS_PENDING_ADMIN = 'pending_admin';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    /**
     * Status pengajuan yang masih boleh diubah/ditarik oleh seller,
     * selama lelangnya belum menerima penawaran.
     */
    const EDITABLE_APPROVAL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_VALIDATOR,
        self::STATUS_PENDING_ADMIN,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'public_id',
        'store_id',
        'winner_id',
        'name',
        'description',
        'image',
        // Bukti keaslian, sama seperti pada produk. Pada lelang justru paling
        // menentukan: penawar mengangkat harga tanpa pernah memegang barangnya.
        'certificate',
        // Dipakai menghitung ongkir pesanan pemenang, sama seperti pada produk.
        'weight',
        'length',
        'width',
        'height',
        'starting_price',
        'min_increment',
        'current_price',
        'bids_count',
        'approval_status',
        'status',
        'starts_at',
        'ends_at',
        'approved_at',
        'approved_by',
        'validated_at',
        'validated_by',
        'rejection_reason',
        'ended_at',
    ];

    protected $hidden = [
        'id',
        'store_id',
        'winner_id',
        'approved_by',
    ];

    protected $casts = [
        'starting_price' => 'decimal:2',
        'min_increment' => 'decimal:2',
        'current_price' => 'decimal:2',
        'bids_count' => 'integer',
        'weight' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approved_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Besaran jaminan selalu ikut saat lelang diserialisasi ke Inertia, supaya
     * halaman lelang tidak perlu menghitung ulang aturannya sendiri.
     */
    protected $appends = [
        'deposit_amount',
        'requires_deposit',
    ];

    protected static function booted(): void
    {
        static::creating(function (Auction $auction) {
            if (empty($auction->public_id)) {
                $auction->public_id = self::generatePublicId();
            }

            if ($auction->current_price === null) {
                $auction->current_price = $auction->starting_price;
            }
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = 'AUC'.random_int(10000000, 99999999);
        } while (DB::table('auctions')->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeVisible($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Lelang yang penawarannya sedang dibuka.
     *
     * Jendela waktunya ikut diperiksa, tidak hanya kolom `status`. Kolom itu
     * baru berpindah ke `ended` ketika penjadwal menjalankan `auctions:finish`,
     * dan penjadwal adalah proses terpisah yang bisa saja sedang tidak berjalan.
     * Tanpa pemeriksaan waktu, lelang yang sudah lewat tenggat masih akan
     * tersaring sebagai "Berlangsung" — sama persis dengan aturan isActive().
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    /**
     * Lelang yang masa penawarannya sudah habis.
     *
     * Termasuk yang tenggatnya sudah lewat tetapi kolom `status`-nya belum
     * sempat dipindahkan penjadwal — alasannya sama dengan scopeOngoing().
     * `cancelled` sengaja tidak ikut: lelang yang dibatalkan tidak pernah
     * benar-benar selesai, dan tidak punya pemenang untuk ditampilkan.
     */
    public function scopeFinished($query)
    {
        return $query->where(function ($query) {
            $query->where('status', 'ended')
                ->orWhere(function ($query) {
                    $query->whereIn('status', ['scheduled', 'active'])
                        ->where('ends_at', '<=', now());
                });
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function bids()
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function highestBid()
    {
        return $this->hasOne(AuctionBid::class)->latestOfMany('amount');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function isActive(): bool
    {
        return $this->approval_status === 'approved'
            && $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }

    public function deposits()
    {
        return $this->hasMany(AuctionDeposit::class);
    }

    /**
     * Tutup lelang ini: tetapkan pemenangnya dan buatkan pesanannya.
     *
     * Logika ini sebelumnya ditulis dua kali — di AuctionController dan di
     * perintah `auctions:finish` — sehingga setiap perubahan aturan harus
     * diterapkan di dua tempat sekaligus. Sekarang keduanya memanggil ini.
     *
     * Idempoten: lelang yang sudah `ended` dilewati, dan pesanan hanya dibuat
     * bila belum ada.
     *
     * Pesanan sengaja dibuat TANPA komponen biaya pengiriman. Pemenang belum
     * pernah memilih titik antar — lelang tidak melewati checkout — jadi ongkir,
     * biaya berat, dan biaya layanannya baru dihitung saat ia membuka halaman
     * pembayaran lelang. Lihat AuctionController::checkout().
     */
    public function finishNow(): void
    {
        // Jaminan yang uangnya sudah diterima Midtrans tetapi kabarnya belum
        // sampai harus diselaraskan lebih dulu. Bila tidak, jaminan pemenang
        // ikut ditandai `expired` beberapa baris di bawah: uang mukanya hilang
        // dari tagihan dan pemiliknya kehilangan jalur pengembalian.
        //
        // Sengaja DI LUAR transaksi — panggilan HTTP tidak boleh dilakukan
        // sambil memegang kunci baris.
        AuctionDeposit::syncFromMidtrans(
            $this->deposits()->where('status', AuctionDeposit::STATUS_PENDING)->get()
        );

        DB::transaction(function () {
            $auction = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $auction || $auction->status === 'ended') {
                return;
            }

            // Diurutkan menurut `id` saat nominalnya seri, bukan `created_at`
            // yang presisinya hanya sampai detik — persoalan yang sama dengan
            // temuan V6-02.
            $highestBid = AuctionBid::where('auction_id', $auction->id)
                ->orderByDesc('amount')
                ->orderBy('id')
                ->first();

            $updates = [
                'status' => 'ended',
                'ended_at' => now(),
            ];

            if ($highestBid) {
                $updates['winner_id'] = $highestBid->user_id;
            }

            $auction->update($updates);

            // Jaminan yang tidak pernah dibayar sampai lelangnya tutup tidak
            // perlu lagi menunggu apa pun.
            AuctionDeposit::where('auction_id', $auction->id)
                ->where('status', AuctionDeposit::STATUS_PENDING)
                ->update(['status' => AuctionDeposit::STATUS_EXPIRED]);

            if (! $highestBid || Order::where('auction_id', $auction->id)->exists()) {
                return;
            }

            // Jaminan pemenang berubah menjadi uang muka pesanannya. Yang kalah
            // tetap berstatus `paid` dan boleh mengajukan pengembalian.
            $deposit = AuctionDeposit::where('auction_id', $auction->id)
                ->where('user_id', $highestBid->user_id)
                ->where('status', AuctionDeposit::STATUS_PAID)
                ->lockForUpdate()
                ->first();

            $depositCredit = 0;

            if ($deposit) {
                $deposit->forceFill([
                    'status' => AuctionDeposit::STATUS_APPLIED,
                    'applied_at' => now(),
                ])->save();

                $depositCredit = (int) $deposit->amount;
            }

            $order = Order::create([
                'user_id' => $highestBid->user_id,
                'product_id' => null,
                // Snapshot agar riwayat pesanan lelang tetap terbaca meski
                // lelang atau tokonya dihapus.
                'product_name' => $auction->name,
                'product_price' => $highestBid->amount,
                'auction_id' => $auction->id,
                'store_id' => $auction->store_id,
                'store_name' => $auction->store?->store_name,
                'quantity' => 1,
                'price' => $highestBid->amount,
                'deposit_credit' => $depositCredit,
                'status' => 'Waiting',
                // Alamat awal diambil dari profil pemenang; ia masih bisa
                // menggantinya di halaman pembayaran lelang.
                'shipping_address' => $highestBid->user?->address,
                'payment_status' => 'pending',
                'payment_method' => 'midtrans',
            ]);

            $order->update(['payment_reference' => $order->public_id]);
        });

        $this->refresh();
    }

    /**
     * Nominal jaminan yang harus dibayar peserta lelang ini, atau 0 bila lelang
     * ini tidak memungut jaminan.
     *
     * Dihitung dari HARGA AWAL, bukan harga berjalan. Nominal yang ikut naik
     * setiap ada penawaran akan menagih peserta lama untuk kekurangan yang tidak
     * pernah ia setujui, dan membuat besaran jaminan tidak bisa disebutkan di
     * muka.
     */
    public function depositAmount(): int
    {
        $config = config('marketplace.auction_deposit');
        $startingPrice = (int) round((float) $this->starting_price);

        if ($startingPrice < (int) $config['threshold']) {
            return 0;
        }

        return (int) round($startingPrice * (float) $config['percent'] / 100);
    }

    public function requiresDeposit(): bool
    {
        return $this->depositAmount() > 0;
    }

    public function getDepositAmountAttribute(): int
    {
        return $this->depositAmount();
    }

    public function getRequiresDepositAttribute(): bool
    {
        return $this->requiresDeposit();
    }

    /**
     * Jaminan milik seorang pengguna atas lelang ini, apa pun statusnya.
     */
    public function depositOf(?User $user): ?AuctionDeposit
    {
        if (! $user) {
            return null;
        }

        if ($this->relationLoaded('deposits')) {
            return $this->deposits->firstWhere('user_id', $user->id);
        }

        return $this->deposits()->where('user_id', $user->id)->first();
    }

    /**
     * Bolehkah pengguna ini menawar dari sisi jaminan?
     *
     * Lelang yang tidak memungut jaminan selalu mengembalikan true.
     */
    public function depositSatisfiedBy(?User $user): bool
    {
        if (! $this->requiresDeposit()) {
            return true;
        }

        return (bool) $this->depositOf($user)?->isActive();
    }
}
