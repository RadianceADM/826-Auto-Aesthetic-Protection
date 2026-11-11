<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceRate;
use App\Models\SiteSetting;
use App\Models\VehicleSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The starting content holds only what 826 publishes, and nothing invented.
 */
class SeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seed_has_826_s_services_and_branches_but_no_prices_or_hours(): void
    {
        $this->seed();

        $this->assertSame(10, Service::count());
        $this->assertSame(0, ServiceRate::count());
        $this->assertSame(5, VehicleSize::count());

        $branches = Branch::ordered()->get();
        $this->assertSame(['Cainta – Ortigas Extension', 'San Mateo', 'Quezon City'], $branches->pluck('name')->all());
        $this->assertSame(['draft'], $branches->pluck('status')->unique()->values()->all());
        $this->assertFalse($branches->contains(fn (Branch $branch) => $branch->hasHours() || $branch->bays > 0 || filled($branch->address) || filled($branch->phone)));
        $this->assertSame(10, $branches->first()->services()->count());

        $this->assertSame('0948-804-2284', SiteSetting::current()->phone);
    }

    public function test_seeding_again_keeps_admin_edits(): void
    {
        $this->seed();
        SiteSetting::instance()->update(['phone' => '0917 000 0000']);
        Branch::where('slug', 'cainta')->update(['name' => 'Cainta Main']);

        $this->seed();

        $this->assertSame('0917 000 0000', SiteSetting::current()->phone);
        $this->assertSame('Cainta Main', Branch::where('slug', 'cainta')->value('name'));
        $this->assertSame(3, Branch::count());
    }
}
