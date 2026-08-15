<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test untuk temuan T-07.
 *
 * canEditAuction() membandingkan approval_status dengan nilai 'pending' yang
 * tidak pernah ada di kolom itu, sehingga lelang yang baru diajukan selalu
 * gagal lolos pengecekan: seller tidak pernah bisa memperbaiki typo maupun
 * membatalkan pengajuannya.
 */
class AuctionSubmissionEditTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::create([
            'username' => 'seller1',
            'first_name' => 'Seller',
            'last_name' => 'Satu',
            'email' => 'seller1@vinstore.test',
            'phone' => '08110001',
            'address' => 'Jl. Antik No. 1',
            'password' => 'password',
            'role' => 'seller',
        ]);

        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Toko Antik',
            'category' => 'Keramik',
            'description' => 'Toko barang antik',
            'location' => 'Yogyakarta',
        ]);
    }

    private function makeAuction(string $approvalStatus = Auction::STATUS_PENDING_VALIDATOR): Auction
    {
        return Auction::create([
            'store_id' => $this->store->id,
            'name' => 'Guci Ming',
            'description' => 'Guci antik',
            'starting_price' => 1000000,
            'min_increment' => 50000,
            'current_price' => 1000000,
            'approval_status' => $approvalStatus,
            'status' => 'pending',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
        ]);
    }

    public function test_seller_bisa_membuka_form_edit_lelang_yang_baru_diajukan(): void
    {
        $auction = $this->makeAuction();

        $this->actingAs($this->seller)
            ->get('/seller/auctions/'.$auction->public_id.'/edit')
            ->assertOk();
    }

    public function test_seller_bisa_menarik_pengajuan_lalu_diarahkan_ke_form_edit(): void
    {
        $auction = $this->makeAuction();

        $this->actingAs($this->seller)
            ->post('/seller/auctions/'.$auction->public_id.'/withdraw')
            ->assertRedirect('/seller/auctions/'.$auction->public_id.'/edit');

        $auction->refresh();

        $this->assertSame(Auction::STATUS_DRAFT, $auction->approval_status);
        $this->assertSame('pending', $auction->status);
    }

    public function test_lelang_draft_tidak_muncul_di_antrean_validator(): void
    {
        $auction = $this->makeAuction();

        $this->actingAs($this->seller)->post('/seller/auctions/'.$auction->public_id.'/withdraw');

        $this->assertSame(
            0,
            Auction::where('approval_status', Auction::STATUS_PENDING_VALIDATOR)->count()
        );
    }

    public function test_menyimpan_form_edit_mengajukan_ulang_ke_validator(): void
    {
        $auction = $this->makeAuction(Auction::STATUS_DRAFT);

        $this->actingAs($this->seller)
            ->put('/seller/auctions/'.$auction->public_id, [
                'name' => 'Guci Ming (revisi)',
                'description' => 'Deskripsi sudah diperbaiki',
                // Berat wajib sejak pesanan lelang ikut menagih ongkir.
                'weight' => 2500,
                'starting_price' => 1200000,
                'min_increment' => 50000,
                'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(4)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('seller.dashboard'));

        $auction->refresh();

        $this->assertSame('Guci Ming (revisi)', $auction->name);
        $this->assertSame(Auction::STATUS_PENDING_VALIDATOR, $auction->approval_status);
    }

    public function test_lelang_yang_sudah_ada_bid_tidak_bisa_ditarik_atau_diedit(): void
    {
        $auction = $this->makeAuction();

        $bidder = User::create([
            'username' => 'penawar',
            'first_name' => 'Pen',
            'last_name' => 'Awar',
            'email' => 'penawar@vinstore.test',
            'phone' => '08910001',
            'address' => 'Jl. Penawar',
            'password' => 'password',
            'role' => 'user',
        ]);

        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => 1100000,
        ]);

        $auction->update(['bids_count' => 1]);

        $this->actingAs($this->seller)
            ->post('/seller/auctions/'.$auction->public_id.'/withdraw')
            ->assertSessionHas('error');

        $this->assertSame(
            Auction::STATUS_PENDING_VALIDATOR,
            $auction->fresh()->approval_status
        );
    }

    public function test_seller_lain_tidak_bisa_menarik_pengajuan_bukan_miliknya(): void
    {
        $auction = $this->makeAuction();

        $penyusup = User::create([
            'username' => 'seller2',
            'first_name' => 'Seller',
            'last_name' => 'Dua',
            'email' => 'seller2@vinstore.test',
            'phone' => '08110002',
            'address' => 'Jl. Lain',
            'password' => 'password',
            'role' => 'seller',
        ]);

        Store::create([
            'user_id' => $penyusup->id,
            'store_name' => 'Toko Lain',
            'category' => 'Logam',
            'description' => 'Toko lain',
            'location' => 'Solo',
        ]);

        $this->actingAs($penyusup)
            ->post('/seller/auctions/'.$auction->public_id.'/withdraw')
            ->assertForbidden();
    }
}
