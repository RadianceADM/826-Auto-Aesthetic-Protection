<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Models\Guest;
use App\Models\ServiceRate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sample hours, prices and bookings: only for local testing and the demo.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->seed();
    }

    public function test_it_refuses_outside_local_and_demo(): void
    {
        $this->artisan('826:demo-data')->assertFailed();

        $this->assertSame(0, ServiceRate::count());
        $this->assertSame(0, Booking::count());
    }

    public function test_it_fills_blanks_adds_marked_bookings_and_clears_only_those(): void
    {
        config(['app.demo' => true]);
        $real = Booking::factory()->create(['branch_id' => Branch::first()->id]);
        Branch::where('slug', 'san-mateo')->update(['bays' => 5]);

        $this->artisan('826:demo-data')->assertSuccessful();

        $this->assertGreaterThan(0, ServiceRate::count());
        $this->assertSame(5, Branch::where('slug', 'san-mateo')->value('bays'));
        $this->assertTrue(Branch::where('slug', 'cainta')->sole()->isOnsiteBookable());
        $this->assertSame(6, Booking::where('source', 'demo')->count());

        $this->artisan('826:demo-data', ['--clear' => true])->assertSuccessful();

        $this->assertSame([$real->id], Booking::pluck('id')->all());
        $this->assertSame(0, Guest::where('google_id', 'like', 'demo-%')->count());
        $this->assertSame(0, BranchBlock::count());
    }
}
