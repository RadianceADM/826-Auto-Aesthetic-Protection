<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * An open branch with 2 bays, open 8 AM – 6 PM Monday to Saturday.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => 'open',
            'city' => $name,
            'address' => fake()->streetAddress(),
            'phone' => '0917'.fake()->numerify('#######'),
            'bays' => 2,
            'weekly_hours' => self::hours(),
            'step_minutes' => 60,
            'lead_hours' => 2,
            'months_ahead' => 3,
        ];
    }

    /**
     * The same hours Monday to Saturday, closed on Sunday.
     *
     * @return array<string, array{open: string, close: string, last_dropoff: string|null}|null>
     */
    public static function hours(string $open = '08:00', string $close = '18:00', ?string $lastDropoff = null): array
    {
        $hours = [];

        foreach (range(1, 7) as $day) {
            $hours[(string) $day] = $day === 7 ? null : ['open' => $open, 'close' => $close, 'last_dropoff' => $lastDropoff];
        }

        return $hours;
    }

    public function bays(int $bays): static
    {
        return $this->state(fn () => ['bays' => $bays]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function homeService(array $areas = ['Cainta', 'Antipolo']): static
    {
        return $this->state(fn () => ['offers_home_service' => true, 'home_service_areas' => $areas]);
    }
}
