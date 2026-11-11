<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceRate;
use App\Models\VehicleSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRate>
 */
class ServiceRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'vehicle_size_id' => VehicleSize::factory(),
            'price' => 5000,
            'duration_minutes' => 180,
            'duration_days' => null,
        ];
    }

    public function hours(float $hours): static
    {
        return $this->state(fn () => ['duration_minutes' => (int) round($hours * 60), 'duration_days' => null]);
    }

    public function days(int $days): static
    {
        return $this->state(fn () => ['duration_minutes' => null, 'duration_days' => $days]);
    }
}
