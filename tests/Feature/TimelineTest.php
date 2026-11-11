<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Models\User;
use App\Support\Schedule\Lanes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use RefreshDatabase;

    private function item(int $id, string $start, string $end, bool $block = false): array
    {
        return ['id' => $id, 'start' => CarbonImmutable::parse($start), 'end' => CarbonImmutable::parse($end), 'block' => $block];
    }

    public function test_lanes_never_exceed_the_busiest_moment(): void
    {
        $lanes = Lanes::assign([
            $this->item(1, '2026-11-17 09:00', '2026-11-17 12:00'),
            $this->item(2, '2026-11-17 10:00', '2026-11-17 13:00'),
            $this->item(3, '2026-11-17 12:00', '2026-11-17 15:00'),
            $this->item(4, '2026-11-17 11:00', '2026-11-17 12:00'),
        ]);

        $this->assertCount(3, $lanes);
        $this->assertSame([1, 3], array_column($lanes[0], 'id'));
        $this->assertSame([2], array_column($lanes[1], 'id'));
        $this->assertSame([4], array_column($lanes[2], 'id'));
    }

    public function test_blocks_take_a_lane_first_at_the_same_moment(): void
    {
        $lanes = Lanes::assign([
            $this->item(1, '2026-11-17 09:00', '2026-11-17 12:00'),
            $this->item(2, '2026-11-17 09:00', '2026-11-17 18:00', block: true),
        ]);

        $this->assertSame([2], array_column($lanes[0], 'id'));
        $this->assertSame([1], array_column($lanes[1], 'id'));
    }

    public function test_the_week_shows_bays_multi_day_bars_and_over_capacity(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $branch = Branch::factory()->bays(1)->create();
        Booking::factory()->at('2026-11-17 09:00', '2026-11-19 18:00')->create(['branch_id' => $branch->id, 'name' => 'Coating Customer']);
        Booking::factory()->at('2026-11-18 09:00', '2026-11-18 12:00')->create(['branch_id' => $branch->id, 'name' => 'Squeezed In']);
        Booking::factory()->hold()->at('2026-11-20 09:00', '2026-11-20 12:00')->create(['branch_id' => $branch->id, 'name' => 'Unpaid Hold']);
        Booking::factory()->status('cancelled')->at('2026-11-20 09:00', '2026-11-20 12:00')->create(['branch_id' => $branch->id, 'name' => 'Gone Away']);
        BranchBlock::factory()->between('2026-11-21 00:00', '2026-11-22 00:00')->create(['branch_id' => $branch->id, 'title' => 'Team outing']);

        $this->actingAs(User::factory()->owner()->create())
            ->get("/admin/timeline?branch={$branch->id}&date=2026-11-16")
            ->assertOk()
            ->assertSee('Bay 1')
            ->assertSee('Over capacity')
            ->assertSee('More cars than bays')
            ->assertSee('Coating Customer')
            ->assertSee('Squeezed In')
            ->assertSee('Unpaid hold until')
            ->assertSee('Team outing')
            ->assertDontSee('Gone Away');
    }
}
