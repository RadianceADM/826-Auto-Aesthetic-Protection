<?php

namespace Tests\Feature;

use App\Mail\CustomerBookingUpdate;
use App\Mail\TeamBookingAlert;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Guest;
use App\Models\MailSetting;
use App\Models\PaymentSetting;
use App\Models\ServiceRate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsShop;
use Tests\TestCase;

/**
 * Customers reserving a drop-off time on the site.
 */
class OnsiteBookingTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday, Nov 16, 2026
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->buildShop();
        $this->guest = Guest::factory()->create(['email' => 'maria@example.com']);
    }

    private function reserve(array $overrides = [])
    {
        return $this->actingAs($this->guest, 'guest')->postJson('/api/bookings', $this->reservation($overrides));
    }

    public function test_with_the_fee_off_a_reservation_is_confirmed_at_once(): void
    {
        Mail::fake();
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'secret']);

        $this->reserve()
            ->assertCreated()
            ->assertJsonPath('booking.stage', 'confirmed')
            ->assertJsonPath('booking.readyBy', '2026-11-17T12:00:00+08:00')
            ->assertJsonPath('booking.items.0.name', 'Exterior detailing');

        $booking = Booking::sole();
        $this->assertSame(['onsite', 'confirmed', 'site'], [$booking->kind, $booking->status, $booking->source]);
        $this->assertSame('2026-11-17 09:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-17 12:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertSame([2500, 0, 180], [$booking->total_price, $booking->total_days, $booking->total_minutes]);
        $this->assertSame('ABC1234', $booking->plate_number);
        $this->assertMatchesRegularExpression('/^826-[2-9A-HJ-NP-Z]{5}$/', $booking->reference);
        $this->assertSame($this->guest->id, $booking->guest_id);
        $this->assertNull($booking->amount_due);
        $this->assertNull($booking->hold_expires_at);

        Mail::assertSent(TeamBookingAlert::class, fn (TeamBookingAlert $mail) => $mail->kind === 'reserved');
        Mail::assertSent(CustomerBookingUpdate::class, fn (CustomerBookingUpdate $mail) => $mail->kind === 'reserved' && $mail->hasTo('maria@example.com'));
    }

    public function test_with_the_fee_on_the_bay_is_held_for_the_customer_to_pay(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => true, 'amount_rule' => 'fixed', 'amount_value' => 500, 'hold_minutes' => 45, 'gcash_enabled' => true, 'gcash_number' => '0917 000 0000']);

        $this->reserve()
            ->assertCreated()
            ->assertJsonPath('booking.stage', 'awaiting_payment')
            ->assertJsonPath('booking.amountDue', 500)
            ->assertJsonPath('booking.holdExpiresAt', '2026-11-16T07:45:00+08:00')
            ->assertJsonPath('booking.methods.0.key', 'gcash');

        $booking = Booking::sole();
        $this->assertSame(['awaiting_payment', 500, 'fee'], [$booking->status, $booking->amount_due, $booking->amount_kind]);
    }

    public function test_a_percent_fee_follows_the_estimate(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => true, 'amount_rule' => 'percent', 'amount_value' => 20, 'maya_enabled' => true, 'maya_number' => '0918 000 0000']);

        $this->reserve(['services' => [$this->detail->id, $this->coating->id]])->assertCreated()->assertJsonPath('booking.amountDue', 4100);
    }

    public function test_several_services_add_up_and_days_come_before_hours(): void
    {
        $this->reserve(['services' => [$this->coating->id, $this->detail->id]])
            ->assertCreated()
            ->assertJsonPath('booking.totalPrice', 20500)
            ->assertJsonPath('booking.length.label', '3 days + 3 hours');

        // Tue, Wed, Thu (ready 6 PM), then 3 hours Friday
        $this->assertSame('2026-11-20 11:00', Booking::sole()->ends_at->format('Y-m-d H:i'));
    }

    public function test_a_service_without_a_rate_or_not_offered_at_the_branch_is_refused(): void
    {
        ServiceRate::query()->where('service_id', $this->coating->id)->update(['price' => null]);
        $this->reserve(['services' => [$this->coating->id]])->assertUnprocessable()->assertJsonValidationErrors('services');

        ServiceRate::query()->where('service_id', $this->coating->id)->update(['price' => 18000]);
        $this->branch->services()->detach($this->detail->id);
        $this->reserve()->assertUnprocessable()->assertJsonValidationErrors('services');

        $this->assertSame(0, Booking::count());
    }

    public function test_times_off_the_grid_after_closing_or_too_soon_are_refused(): void
    {
        $this->reserve(['time' => '09:30'])->assertUnprocessable()->assertJsonValidationErrors('time');
        // 3 hours from 4 PM runs past 6 PM
        $this->reserve(['time' => '16:00'])->assertUnprocessable()->assertJsonValidationErrors('time');
        // 2 hours' notice from 7 AM
        $this->reserve(['date' => '2026-11-16', 'time' => '08:00'])->assertUnprocessable()->assertJsonValidationErrors('time');
        // Sunday
        $this->reserve(['date' => '2026-11-22'])->assertUnprocessable()->assertJsonValidationErrors('time');

        $this->assertSame(0, Booking::count());
    }

    public function test_a_full_time_is_refused_on_the_final_check(): void
    {
        Booking::factory()->count(2)->at('2026-11-17 08:00', '2026-11-17 11:00')->create(['branch_id' => $this->branch->id]);

        $this->reserve()->assertUnprocessable()->assertJsonValidationErrors(['time' => 'Someone just took that time']);
        $this->reserve(['time' => '11:00'])->assertCreated();
    }

    public function test_a_busy_branch_lock_asks_to_try_again(): void
    {
        $lock = Cache::lock("booking:branch:{$this->branch->id}", 30);
        $lock->get();

        // Waiting for a lock needs the clock to run
        $this->travelBack();

        try {
            $this->reserve()->assertStatus(409);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, Booking::count());
    }

    public function test_one_unpaid_hold_and_a_few_upcoming_bookings_per_customer(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => true, 'amount_value' => 500, 'gcash_enabled' => true, 'gcash_number' => '0917 000 0000']);

        $this->reserve()->assertCreated();
        $this->reserve(['time' => '12:00'])->assertUnprocessable()->assertJsonValidationErrors(['time' => 'waiting for its reservation fee']);

        // Once the hold lapses, it no longer counts
        $this->travel(31)->minutes();
        PaymentSetting::instance()->update(['reservation_fee_enabled' => false]);
        BookingSetting::instance()->update(['max_upcoming_per_guest' => 2]);
        $this->reserve(['time' => '14:00'])->assertCreated();

        $this->reserve(['time' => '12:00'])->assertCreated();
        $this->reserve(['time' => '13:00'])->assertUnprocessable()->assertJsonValidationErrors(['time' => 'upcoming bookings']);
    }

    public function test_a_draft_branch_or_one_with_something_missing_takes_no_bookings(): void
    {
        $this->branch->update(['bays' => 0]);
        $this->reserve()->assertUnprocessable()->assertJsonValidationErrors('branch');

        $this->branch->update(['bays' => 2, 'status' => 'draft']);
        $this->reserve()->assertUnprocessable()->assertJsonValidationErrors('branch');

        $this->branch->update(['status' => 'open']);
        PaymentSetting::instance()->update(['reservation_fee_enabled' => true]);
        $this->reserve()->assertUnprocessable()->assertJsonValidationErrors('branch');
    }

    public function test_the_details_are_checked(): void
    {
        $this->reserve(['phone' => '123', 'plate_number' => '', 'vehicle_model' => '', 'consent' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'plate_number', 'vehicle_model', 'consent']);
    }

    public function test_the_honeypot_is_quietly_accepted(): void
    {
        $this->reserve(['fax_number' => 'spam'])->assertCreated();

        $this->assertSame(0, Booking::count());
    }

    public function test_a_month_and_a_day_of_open_times(): void
    {
        $this->branch->update(['bays' => 1]);
        Booking::factory()->at('2026-11-17 08:00', '2026-11-17 18:00')->create(['branch_id' => $this->branch->id, 'name' => 'Secret Person']);

        $query = http_build_query(['branch' => $this->branch->id, 'size' => $this->sedan->id, 'services' => [$this->detail->id], 'month' => '2026-11']);

        $this->getJson("/api/availability?{$query}")
            ->assertOk()
            ->assertJsonPath('days.2026-11-16', 'open')
            ->assertJsonPath('days.2026-11-17', 'full')
            ->assertJsonPath('days.2026-11-22', 'closed')
            ->assertJsonPath('length.label', '3 hours')
            ->assertJsonPath('total', 2500)
            ->assertDontSee('Secret Person');

        $day = http_build_query(['branch' => $this->branch->id, 'size' => $this->sedan->id, 'services' => [$this->detail->id], 'date' => '2026-11-18']);

        $this->getJson("/api/availability/day?{$day}")
            ->assertOk()
            ->assertJsonPath('slots.0.time', '08:00')
            ->assertJsonPath('slots.0.readyLabel', 'Ready 11:00 AM')
            ->assertJsonCount(8, 'slots');
    }

    public function test_a_demo_checks_everything_and_saves_nothing(): void
    {
        config(['app.demo' => true]);

        $this->postJson('/api/bookings', $this->reservation())->assertCreated()->assertJson(['demo' => true]);
        $this->postJson('/api/bookings', $this->reservation(['time' => '09:30']))->assertUnprocessable();

        $this->assertSame(0, Booking::count());
    }

    public function test_later_price_changes_leave_booked_items_alone(): void
    {
        $this->reserve()->assertCreated();

        ServiceRate::query()->where('service_id', $this->detail->id)->update(['price' => 9999, 'duration_minutes' => 600]);
        $this->detail->update(['name' => 'Renamed']);

        $booking = Booking::sole();
        $this->assertSame(['Exterior detailing', 2500, 180], [$booking->items[0]->service_name, $booking->items[0]->price, $booking->items[0]->duration_minutes]);
        $this->assertSame('2026-11-17 12:00', $booking->ends_at->format('Y-m-d H:i'));
    }
}
