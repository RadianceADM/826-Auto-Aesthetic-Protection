<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchBlock;
use App\Support\Schedule\Length;
use App\Support\Schedule\OpenCalendar;
use App\Support\Schedule\Slot;
use Carbon\CarbonImmutable;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Open hours, ready-by times and drop-off times, before bookings come in.
 * The branch is open 8 AM – 6 PM Monday to Saturday, closed on Sunday.
 */
class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday, Nov 16, 2026
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));

        $this->branch = Branch::factory()->create();
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment);
    }

    private function calendar(): OpenCalendar
    {
        return OpenCalendar::for($this->branch->refresh(), $this->at('2026-11-16'), $this->at('2026-12-31'));
    }

    /**
     * @param  list<Slot>  $slots
     * @return list<string>
     */
    private function times(array $slots): array
    {
        return array_map(fn (Slot $slot) => $slot->start->format('H:i'), $slots);
    }

    public function test_an_hours_job_is_ready_the_same_day(): void
    {
        $ready = $this->calendar()->finish($this->at('2026-11-17 09:00'), new Length(minutes: 180));

        $this->assertSame('2026-11-17 12:00', $ready->format('Y-m-d H:i'));
    }

    public function test_an_hours_job_must_finish_by_closing(): void
    {
        $slots = $this->calendar()->candidates($this->at('2026-11-17'), new Length(minutes: 180), $this->at('2026-11-16 07:00'));

        // 8 AM to 3 PM; 4 PM would run past 6 PM
        $this->assertSame(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'], $this->times($slots));
        $this->assertSame('2026-11-17 18:00', end($slots)->readyBy->format('Y-m-d H:i'));
    }

    public function test_an_hours_job_longer_than_the_day_carries_into_the_next_open_day(): void
    {
        // 12 hours from Saturday 2 PM: 4 hours Saturday, Sunday closed, 8 hours Monday
        $ready = $this->calendar()->finish($this->at('2026-11-21 14:00'), new Length(minutes: 720));

        $this->assertSame('2026-11-23 16:00', $ready->format('Y-m-d H:i'));

        $slots = $this->calendar()->candidates($this->at('2026-11-17'), new Length(minutes: 720), $this->at('2026-11-16 07:00'));
        $this->assertSame('08:00', $slots[0]->start->format('H:i'));
        $this->assertSame('17:00', end($slots)->start->format('H:i'));
    }

    public function test_a_days_job_counts_the_drop_off_day_and_is_ready_at_closing(): void
    {
        $ready = $this->calendar()->finish($this->at('2026-11-17 10:00'), new Length(days: 3));

        $this->assertSame('2026-11-19 18:00', $ready->format('Y-m-d H:i'));
    }

    public function test_a_days_job_skips_the_closed_weekday(): void
    {
        // Friday, Saturday, (Sunday closed), Monday
        $ready = $this->calendar()->finish($this->at('2026-11-20 09:00'), new Length(days: 3));

        $this->assertSame('2026-11-23 18:00', $ready->format('Y-m-d H:i'));
    }

    public function test_a_holiday_in_the_middle_of_a_job_moves_ready_by_later(): void
    {
        BranchBlock::factory()->between('2026-11-30 00:00', '2026-12-01 00:00')->create(['title' => 'Bonifacio Day']);

        // Saturday Nov 28, (Sunday), (Monday holiday), Tuesday Dec 1
        $ready = $this->calendar()->finish($this->at('2026-11-28 09:00'), new Length(days: 2));

        $this->assertSame('2026-12-01 18:00', $ready->format('Y-m-d H:i'));
    }

    public function test_a_closure_for_every_branch_counts_too(): void
    {
        BranchBlock::factory()->between('2026-11-17 00:00', '2026-11-18 00:00')->create(['branch_id' => null]);

        $this->assertSame([], $this->calendar()->candidates($this->at('2026-11-17'), new Length(minutes: 60), $this->at('2026-11-16 07:00')));
    }

    public function test_days_then_hours_carry_into_the_next_open_day(): void
    {
        // 2 days (ready Wed 6 PM) plus 4 hours: Thursday noon
        $ready = $this->calendar()->finish($this->at('2026-11-17 09:00'), new Length(days: 2, minutes: 240));

        $this->assertSame('2026-11-19 12:00', $ready->format('Y-m-d H:i'));
    }

    public function test_drop_off_times_step_from_opening_until_the_last_drop_off(): void
    {
        $this->branch->update([
            'weekly_hours' => BranchFactory::hours('08:30', '17:30', '15:00'),
            'step_minutes' => 30,
        ]);

        $slots = $this->calendar()->candidates($this->at('2026-11-17'), new Length(minutes: 60), $this->at('2026-11-16 07:00'));

        $this->assertSame('08:30', $slots[0]->start->format('H:i'));
        $this->assertSame('09:00', $slots[1]->start->format('H:i'));
        $this->assertSame('15:00', end($slots)->start->format('H:i'));
    }

    public function test_multi_day_jobs_stop_at_the_multi_day_drop_off_time(): void
    {
        $this->branch->update(['multiday_dropoff_by' => '12:00']);

        $days = $this->calendar()->candidates($this->at('2026-11-17'), new Length(days: 2), $this->at('2026-11-16 07:00'));
        $hours = $this->calendar()->candidates($this->at('2026-11-17'), new Length(minutes: 60), $this->at('2026-11-16 07:00'));

        $this->assertSame('12:00', end($days)->start->format('H:i'));
        $this->assertSame('17:00', end($hours)->start->format('H:i'));
    }

    public function test_times_before_the_earliest_drop_off_are_left_out(): void
    {
        $slots = $this->calendar()->candidates($this->at('2026-11-16'), new Length(minutes: 60), $this->at('2026-11-16 10:30'));

        $this->assertSame('11:00', $slots[0]->start->format('H:i'));
    }

    public function test_a_partial_closure_removes_the_times_inside_it_and_pauses_work(): void
    {
        BranchBlock::factory()->between('2026-11-17 12:00', '2026-11-17 14:00')->create(['branch_id' => $this->branch->id, 'title' => 'Brownout']);

        $calendar = $this->calendar();
        $slots = $calendar->candidates($this->at('2026-11-17'), new Length(minutes: 60), $this->at('2026-11-16 07:00'));

        $this->assertNotContains('12:00', $this->times($slots));
        $this->assertNotContains('13:00', $this->times($slots));
        $this->assertContains('14:00', $this->times($slots));

        // 3 hours from 11 AM: 11–12, then 2–4 PM
        $this->assertSame('2026-11-17 16:00', $calendar->finish($this->at('2026-11-17 11:00'), new Length(minutes: 180))->format('Y-m-d H:i'));
    }

    public function test_a_closed_day_has_no_drop_off_times(): void
    {
        $this->assertSame([], $this->calendar()->candidates($this->at('2026-11-22'), new Length(minutes: 60), $this->at('2026-11-16 07:00')));
    }

    public function test_bad_or_inverted_hours_count_as_closed(): void
    {
        $this->branch->update(['weekly_hours' => ['1' => ['open' => '18:00', 'close' => '08:00'], '2' => ['open' => 'noon', 'close' => '18:00'], '3' => ['open' => '8:00', 'close' => '17:00']]]);

        $hours = $this->branch->refresh()->weeklyHours();

        $this->assertNull($hours[1]);
        $this->assertNull($hours[2]);
        $this->assertSame(['open' => '08:00', 'close' => '17:00', 'last_dropoff' => '17:00'], $hours[3]);
        $this->assertNull($hours[7]);
    }

    public function test_length_labels(): void
    {
        $this->assertSame('3 days', (new Length(days: 3))->label());
        $this->assertSame('1 hour', (new Length(minutes: 60))->label());
        $this->assertSame('1 hr 30 min', (new Length(minutes: 90))->label());
        $this->assertSame('45 min', (new Length(minutes: 45))->label());
        $this->assertSame('1 day + 4 hours', (new Length(days: 1, minutes: 240))->label());
    }
}
