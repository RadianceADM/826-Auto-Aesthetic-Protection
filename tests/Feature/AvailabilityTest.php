<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Support\Schedule\Availability;
use App\Support\Schedule\Length;
use App\Support\Schedule\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bays: how many cars fit at once, and what keeps a bay.
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday, Nov 16, 2026
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));

        $this->branch = Branch::factory()->bays(2)->create();
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment);
    }

    private function threeHours(): Length
    {
        return new Length(minutes: 180);
    }

    private function book(string $start, string $end, array $state = []): Booking
    {
        return Booking::factory()->at($start, $end)->create(['branch_id' => $this->branch->id, ...$state]);
    }

    public function test_two_bays_take_two_overlapping_jobs_but_not_three(): void
    {
        $this->book('2026-11-17 09:00', '2026-11-17 12:00');

        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));

        $this->book('2026-11-17 10:00', '2026-11-17 13:00');

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 11:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 12:00')));
    }

    public function test_back_to_back_jobs_share_a_bay(): void
    {
        $this->branch->update(['bays' => 1]);
        $this->book('2026-11-17 09:00', '2026-11-17 12:00');

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 10:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 12:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, new Length(minutes: 60), $this->at('2026-11-17 08:00')));
    }

    public function test_a_multi_day_job_keeps_its_bay_overnight(): void
    {
        $this->branch->update(['bays' => 1]);
        $this->book('2026-11-17 09:00', '2026-11-19 18:00', ['total_days' => 3, 'total_minutes' => 0]);

        $this->assertSame('full', Availability::check($this->branch, new Length(minutes: 60), $this->at('2026-11-18 08:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, new Length(minutes: 60), $this->at('2026-11-20 08:00')));
    }

    public function test_bays_out_of_service_lower_capacity_and_all_bays_fill_it(): void
    {
        BranchBlock::factory()->bays(1)->between('2026-11-17 00:00', '2026-11-18 00:00')->create(['branch_id' => $this->branch->id]);
        $this->book('2026-11-17 09:00', '2026-11-17 12:00');

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 12:00')));

        BranchBlock::factory()->bays()->between('2026-11-18 00:00', '2026-11-19 00:00')->create(['branch_id' => $this->branch->id]);

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-18 12:00')));
    }

    public function test_an_unpaid_hold_keeps_the_bay_until_it_lapses_without_cron(): void
    {
        $this->branch->update(['bays' => 1]);
        $this->book('2026-11-17 09:00', '2026-11-17 12:00', ['status' => 'awaiting_payment', 'hold_expires_at' => now()->addMinutes(30)]);

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));

        $this->travel(31)->minutes();

        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
        $this->assertSame('expired', Booking::sole()->stage());
    }

    public function test_a_screenshot_to_check_keeps_the_bay_past_the_deadline(): void
    {
        $this->branch->update(['bays' => 1]);
        $this->book('2026-11-17 09:00', '2026-11-17 12:00', ['status' => 'payment_review', 'hold_expires_at' => now()->subMinutes(5)]);

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
    }

    public function test_done_cancelled_expired_and_no_show_free_the_bay(): void
    {
        $this->branch->update(['bays' => 1]);

        foreach (['completed', 'cancelled', 'expired', 'no_show', 'declined'] as $status) {
            $this->book('2026-11-17 09:00', '2026-11-17 12:00', ['status' => $status]);
        }

        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
    }

    public function test_home_service_never_takes_a_bay(): void
    {
        $this->branch->update(['bays' => 1]);
        Booking::factory()->home()->create([
            'branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'starts_at' => $this->at('2026-11-17 09:00'),
            'ends_at' => $this->at('2026-11-17 12:00'),
        ]);

        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
    }

    public function test_another_branch_s_bookings_dont_count(): void
    {
        $this->branch->update(['bays' => 1]);
        Booking::factory()->at('2026-11-17 09:00', '2026-11-17 12:00')->create();

        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
    }

    public function test_zero_bays_takes_nothing(): void
    {
        $this->branch->update(['bays' => 0]);

        $this->assertSame('closed', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:00')));
    }

    public function test_times_off_the_grid_too_soon_or_too_far_ahead_are_refused(): void
    {
        $this->assertSame('not_a_start', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 09:30')));
        // 2 hours' notice from 7 AM: 8 AM is too soon
        $this->assertSame('not_a_start', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-16 08:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-16 09:00')));
        // 3 months ahead: up to the end of February
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2027-02-26 09:00')));
        $this->assertSame('not_a_start', Availability::check($this->branch, $this->threeHours(), $this->at('2027-03-01 09:00')));
    }

    public function test_moving_a_booking_doesnt_count_the_booking_itself(): void
    {
        $this->branch->update(['bays' => 1]);
        $booking = $this->book('2026-11-17 09:00', '2026-11-17 12:00');

        $this->assertSame('full', Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 10:00')));
        $this->assertInstanceOf(Slot::class, Availability::check($this->branch, $this->threeHours(), $this->at('2026-11-17 10:00'), $booking->id));
    }

    public function test_a_month_marks_days_open_full_or_closed(): void
    {
        $this->branch->update(['bays' => 1]);
        // Tuesday is taken from opening to closing
        $this->book('2026-11-17 08:00', '2026-11-17 18:00');

        $month = Availability::month($this->branch, $this->threeHours(), $this->at('2026-11-01'));

        $this->assertSame('2026-11', $month['month']);
        $this->assertSame('2026-11-16', $month['first']);
        $this->assertSame('closed', $month['days']['2026-11-15']);
        $this->assertSame('open', $month['days']['2026-11-16']);
        $this->assertSame('full', $month['days']['2026-11-17']);
        $this->assertSame('open', $month['days']['2026-11-18']);
        $this->assertSame('closed', $month['days']['2026-11-22']);
        $this->assertCount(30, $month['days']);
    }

    public function test_a_day_lists_only_times_with_room(): void
    {
        $this->branch->update(['bays' => 1]);
        $this->book('2026-11-17 09:00', '2026-11-17 12:00');

        $times = array_map(fn (Slot $slot) => $slot->start->format('H:i'), Availability::day($this->branch, $this->threeHours(), $this->at('2026-11-17')));

        $this->assertSame(['12:00', '13:00', '14:00', '15:00'], $times);
    }
}
