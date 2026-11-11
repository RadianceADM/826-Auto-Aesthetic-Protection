<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRate;
use App\Models\SiteSetting;
use App\Models\VehicleSize;

/**
 * A branch ready for online bookings: open 8 AM – 6 PM Monday to
 * Saturday with 2 bays, a Sedan size, and two services with a price and
 * duration for it (a 3-hour detail and a 3-day coating), with Google
 * sign-in set up.
 */
trait BuildsShop
{
    protected Branch $branch;

    protected VehicleSize $sedan;

    protected Service $detail;

    protected Service $coating;

    protected function buildShop(int $bays = 2): void
    {
        $this->branch = Branch::factory()->bays($bays)->create(['name' => 'Cainta', 'slug' => 'cainta']);
        $this->sedan = VehicleSize::factory()->create(['name' => 'Sedan']);

        $detailing = ServiceCategory::factory()->create(['name' => 'Detailing', 'sort_order' => 1]);
        $coatings = ServiceCategory::factory()->create(['name' => 'Coating', 'sort_order' => 2]);

        $this->detail = Service::factory()->homeServiceOk()->create(['name' => 'Exterior detailing', 'service_category_id' => $detailing->id]);
        $this->coating = Service::factory()->create(['name' => 'Graphene coating', 'service_category_id' => $coatings->id]);

        ServiceRate::factory()->hours(3)->create(['service_id' => $this->detail->id, 'vehicle_size_id' => $this->sedan->id, 'price' => 2500]);
        ServiceRate::factory()->days(3)->create(['service_id' => $this->coating->id, 'vehicle_size_id' => $this->sedan->id, 'price' => 18000]);

        $this->branch->services()->sync([$this->detail->id, $this->coating->id]);

        // Customers sign in with Google to reserve
        SiteSetting::instance()->update(['google_client_id' => 'client.apps.googleusercontent.com', 'google_client_secret' => 'secret']);
    }

    /**
     * A reservation as the site sends it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function reservation(array $overrides = []): array
    {
        return [
            'branch' => $this->branch->id,
            'size' => $this->sedan->id,
            'services' => [$this->detail->id],
            'date' => '2026-11-17',
            'time' => '09:00',
            'name' => 'Maria Santos',
            'phone' => '0917 123 4567',
            'email' => 'maria@example.com',
            'vehicle_model' => 'Toyota Vios',
            'plate_number' => 'abc 1234',
            'consent' => true,
            ...$overrides,
        ];
    }
}
