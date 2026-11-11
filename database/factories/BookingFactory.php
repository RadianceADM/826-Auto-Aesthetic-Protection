<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * A confirmed onsite reservation: 3 hours, a week from now at 9 AM.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::today()->addDays(7)->setTime(9, 0);

        return [
            'kind' => 'onsite',
            'source' => 'site',
            'status' => 'confirmed',
            'branch_id' => Branch::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addHours(3),
            'vehicle_size_name' => 'Sedan',
            'vehicle_model' => fake()->randomElement(['Toyota Vios', 'Honda City', 'Mitsubishi Mirage']),
            'plate_number' => strtoupper(fake()->bothify('???####')),
            'name' => fake()->name(),
            'phone' => '0917'.fake()->numerify('#######'),
            'email' => fake()->safeEmail(),
            'total_price' => 5000,
            'total_minutes' => 180,
        ];
    }

    public function at(string $start, string $end): static
    {
        return $this->state(fn () => ['starts_at' => CarbonImmutable::parse($start), 'ends_at' => CarbonImmutable::parse($end)]);
    }

    public function hold(int $minutesLeft = 30): static
    {
        return $this->state(fn () => ['status' => 'awaiting_payment', 'hold_expires_at' => now()->addMinutes($minutesLeft), 'amount_due' => 500]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function home(): static
    {
        return $this->state(fn () => [
            'kind' => 'home',
            'status' => 'requested',
            'starts_at' => null,
            'ends_at' => null,
            'preferred_date' => CarbonImmutable::today()->addDays(5),
            'preferred_window' => 'morning',
            'address' => fake()->streetAddress(),
            'area' => 'Cainta',
        ]);
    }
}
