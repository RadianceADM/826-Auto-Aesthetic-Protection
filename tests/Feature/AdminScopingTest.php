<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\BranchBlocks\Pages\ManageBranchBlocks;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Branch staff see and change only their own branches; owners see all.
 */
class AdminScopingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $cainta;

    private Branch $sanMateo;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->cainta = Branch::factory()->create(['name' => 'Cainta']);
        $this->sanMateo = Branch::factory()->create(['name' => 'San Mateo']);
        $this->staff = User::factory()->create();
        $this->staff->branches()->attach($this->cainta);
    }

    public function test_staff_list_only_their_branch_s_bookings(): void
    {
        $mine = Booking::factory()->create(['branch_id' => $this->cainta->id]);
        $theirs = Booking::factory()->create(['branch_id' => $this->sanMateo->id]);

        $this->actingAs($this->staff);

        Livewire::test(ListBookings::class, ['activeTab' => 'all'])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get("/admin/bookings/{$mine->id}")->assertOk();
        $this->get("/admin/bookings/{$theirs->id}")->assertNotFound();
        $this->get("/admin/bookings/{$theirs->id}/edit")->assertNotFound();
    }

    public function test_the_badge_counts_only_their_branch(): void
    {
        Booking::factory()->status('payment_review')->create(['branch_id' => $this->cainta->id]);
        Booking::factory()->status('payment_review')->count(2)->create(['branch_id' => $this->sanMateo->id]);

        $this->actingAs($this->staff);
        $this->assertSame('1', BookingResource::getNavigationBadge());

        $this->actingAs(User::factory()->owner()->create());
        $this->assertSame('3', BookingResource::getNavigationBadge());
    }

    public function test_staff_cant_open_the_shop_settings_or_users(): void
    {
        $this->actingAs($this->staff);

        foreach (['/admin/branches', '/admin/services', '/admin/service-categories', '/admin/vehicle-sizes', '/admin/photos', '/admin/site-settings', '/admin/booking-rules', '/admin/payment-settings', '/admin/email-settings', '/admin/google-sign-in', '/admin/users'] as $url) {
            $this->get($url)->assertForbidden();
        }

        foreach (['/admin', '/admin/timeline', '/admin/bookings', '/admin/bookings/create', '/admin/branch-blocks'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_staff_see_but_cant_change_closures_for_every_branch(): void
    {
        $everywhere = BranchBlock::factory()->create(['branch_id' => null, 'title' => 'Christmas']);
        $theirs = BranchBlock::factory()->create(['branch_id' => $this->sanMateo->id, 'title' => 'Their holiday']);
        $mine = BranchBlock::factory()->create(['branch_id' => $this->cainta->id, 'title' => 'Our holiday']);

        $this->actingAs($this->staff);

        Livewire::test(ManageBranchBlocks::class)
            ->assertCanSeeTableRecords([$everywhere, $mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableActionHidden('edit', $everywhere)
            ->assertTableActionVisible('edit', $mine);
    }

    public function test_the_payment_screenshot_is_only_for_that_branch_s_team(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/1/x.webp', 'image');
        $theirs = Booking::factory()->create(['branch_id' => $this->sanMateo->id, 'payment_proof' => 'payment-proofs/1/x.webp']);

        $this->actingAs($this->staff)->get("/admin-files/bookings/{$theirs->id}/proof")->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get("/admin-files/bookings/{$theirs->id}/proof")->assertOk();
        $this->actingAs($this->staff)->get("/admin-files/bookings/{$theirs->id}/proof")->assertForbidden();
    }

    public function test_the_timeline_refuses_another_branch(): void
    {
        $this->actingAs($this->staff);

        $this->get('/admin/timeline?branch='.$this->sanMateo->id)->assertForbidden();
        $this->get('/admin/timeline')->assertOk()->assertSee('Cainta');
    }

    public function test_staff_without_a_branch_see_nothing(): void
    {
        Booking::factory()->create(['branch_id' => $this->cainta->id]);
        $nobody = User::factory()->create();

        $this->actingAs($nobody);

        Livewire::test(ListBookings::class, ['activeTab' => 'all'])->assertCountTableRecords(0);
        $this->get('/admin/timeline')->assertOk()->assertSee('not assigned to a branch');
    }

    public function test_owners_see_every_branch(): void
    {
        $bookings = Booking::factory()->count(2)->sequence(['branch_id' => $this->cainta->id], ['branch_id' => $this->sanMateo->id])->create();

        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(ListBookings::class, ['activeTab' => 'all'])->assertCanSeeTableRecords($bookings);
    }
}
