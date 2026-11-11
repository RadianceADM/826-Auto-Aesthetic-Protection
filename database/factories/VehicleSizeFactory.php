<?php

namespace Database\Factories;

use App\Models\VehicleSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleSize>
 */
class VehicleSizeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Sedan', 'SUV', 'Hatchback', 'Pickup']).' '.fake()->unique()->numberBetween(1, 9999),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
