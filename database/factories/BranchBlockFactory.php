<?php

namespace Database\Factories;

use App\Models\BranchBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchBlock>
 */
class BranchBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = CarbonImmutable::today()->addDays(10);

        return [
            'kind' => 'closed',
            'starts_at' => $day,
            'ends_at' => $day->addDay(),
            'title' => 'Holiday',
        ];
    }

    public function between(string $from, string $to): static
    {
        return $this->state(fn () => ['starts_at' => CarbonImmutable::parse($from), 'ends_at' => CarbonImmutable::parse($to)]);
    }

    public function bays(?int $bays = null): static
    {
        return $this->state(fn () => ['kind' => 'bays', 'bays' => $bays]);
    }
}
