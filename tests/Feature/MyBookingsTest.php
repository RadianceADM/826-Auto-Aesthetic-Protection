<?php

namespace Tests\Feature;

use App\Mail\CustomerBookingUpdate;
use App\Mail\TeamBookingAlert;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Guest;
use App\Models\MailSetting;
use App\Models\PaymentSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsShop;
use Tests\TestCase;

/**
 * A customer's own bookings: paying the fee, cancelling and moving.
 */
class MyBookingsTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'secret']);

        // Monday, Nov 16, 2026
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->buildShop();
        $this->guest = Guest::factory()->create();

        PaymentSetting::instance()->update(['reservation_fee_enabled' => true, 'amount_value' => 500, 'hold_minutes' => 30, 'gcash_enabled' => true, 'gcash_number' => '0917 000 0000']);
    }

    private function hold(): Booking
    {
        $this->actingAs($this->guest, 'guest')->postJson('/api/bookings', $this->reservation())->assertCreated();

        return Booking::sole();
    }

    private function pay(Booking $booking, array $as = [])
    {
        return $this->postJson("/api/my-bookings/{$booking->id}/payment", [
            'method' => 'gcash',
            'proof' => UploadedFile::fake()->image('gcash.png', 600, 1200),
            ...$as,
        ]);
    }

    public function test_a_screenshot_before_the_deadline_goes_to_the_branch_to_check(): void
    {
        $booking = $this->hold();

        $this->travel(20)->minutes();
        $this->pay($booking)->assertOk()->assertJsonPath('booking.stage', 'payment_review');

        $booking->refresh();
        $this->assertSame('payment_review', $booking->status);
        $this->assertTrue(Storage::disk('local')->exists($booking->payment_proof));
        Mail::assertSent(TeamBookingAlert::class, fn (TeamBookingAlert $mail) => $mail->kind === 'payment');

        // Still holding the bay after the deadline, while the branch checks
        $this->travel(30)->minutes();
        $this->assertTrue($booking->refresh()->holdsABay());
    }

    public function test_a_screenshot_after_the_deadline_is_refused(): void
    {
        $booking = $this->hold();

        $this->travel(31)->minutes();
        $this->pay($booking)->assertStatus(409)->assertJson(['message' => 'Your hold ran out, so the slot opened up. Book another time.']);

        $this->assertSame('awaiting_payment', $booking->refresh()->status);
        $this->assertSame('expired', $booking->stage());
    }

    public function test_the_expire_command_lapses_old_holds_once_and_tells_the_customer(): void
    {
        $booking = $this->hold();
        $paid = Booking::factory()->hold(10)->create(['branch_id' => $this->branch->id, 'status' => 'payment_review']);

        $this->travel(31)->minutes();
        $this->artisan('bookings:expire-holds')->expectsOutput('1 hold expired.')->assertSuccessful();
        $this->artisan('bookings:expire-holds')->expectsOutput('0 holds expired.')->assertSuccessful();

        $this->assertSame('expired', $booking->refresh()->status);
        $this->assertSame('payment_review', $paid->refresh()->status);
        Mail::assertSent(CustomerBookingUpdate::class, fn (CustomerBookingUpdate $mail) => $mail->kind === 'expired');
    }

    public function test_a_branch_with_its_own_gcash_is_paid_there(): void
    {
        $this->branch->update(['gcash_enabled' => true, 'gcash_number' => '0999 111 2222', 'gcash_name' => 'Cainta branch']);

        $booking = $this->hold();

        $this->getJson("/api/my-bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('booking.methods.0.accountNumber', '0999 111 2222')
            ->assertJsonCount(1, 'booking.methods');
    }

    public function test_only_the_customer_or_the_private_link_opens_a_booking(): void
    {
        $booking = $this->hold();
        $stranger = Guest::factory()->create();

        $this->actingAs($stranger, 'guest')->getJson("/api/my-bookings/{$booking->id}")->assertNotFound();
        $this->actingAs($stranger, 'guest')->getJson("/api/my-bookings/{$booking->id}?key={$booking->access_key}")->assertOk();
        $this->actingAs($stranger, 'guest')->getJson('/api/my-bookings')->assertOk()->assertJsonCount(0, 'bookings');
        $this->actingAs($this->guest, 'guest')->getJson('/api/my-bookings')->assertOk()->assertJsonCount(1, 'bookings');
    }

    public function test_cancelling_before_the_cutoff_frees_the_bay(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => false]);
        BookingSetting::instance()->update(['cancel_cutoff_hours' => 24]);
        $booking = $this->hold();

        $this->postJson("/api/my-bookings/{$booking->id}/cancel")->assertOk()->assertJsonPath('booking.stage', 'cancelled');

        $this->assertSame(['cancelled', 'guest'], [$booking->refresh()->status, $booking->cancelled_by]);
        $this->assertFalse($booking->holdsABay());
    }

    public function test_cancelling_after_the_cutoff_is_refused(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => false]);
        BookingSetting::instance()->update(['cancel_cutoff_hours' => 24]);
        $booking = $this->hold();

        // Tuesday 8 AM, an hour before drop-off
        $this->travelTo(CarbonImmutable::parse('2026-11-17 08:00', 'Asia/Manila'));
        $this->postJson("/api/my-bookings/{$booking->id}/cancel")->assertStatus(409);

        $this->assertSame('confirmed', $booking->refresh()->status);
    }

    public function test_moving_to_a_free_time_keeps_the_booking_and_counts(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => false]);
        BookingSetting::instance()->update(['max_reschedules' => 1, 'reschedule_cutoff_hours' => 12]);
        $booking = $this->hold();

        $this->postJson("/api/my-bookings/{$booking->id}/reschedule", ['date' => '2026-11-18', 'time' => '13:00'])
            ->assertOk()
            ->assertJsonPath('booking.startsAt', '2026-11-18T13:00:00+08:00');

        $booking->refresh();
        $this->assertSame(['2026-11-18 13:00', '2026-11-18 16:00', 1], [$booking->starts_at->format('Y-m-d H:i'), $booking->ends_at->format('Y-m-d H:i'), $booking->reschedule_count]);
        Mail::assertSent(TeamBookingAlert::class, fn (TeamBookingAlert $mail) => $mail->kind === 'rescheduled');

        // Only once
        $this->postJson("/api/my-bookings/{$booking->id}/reschedule", ['date' => '2026-11-19', 'time' => '13:00'])->assertUnprocessable();
    }

    public function test_moving_into_a_full_time_is_refused(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => false]);
        $booking = $this->hold();
        Booking::factory()->count(2)->at('2026-11-18 12:00', '2026-11-18 15:00')->create(['branch_id' => $this->branch->id]);

        $this->postJson("/api/my-bookings/{$booking->id}/reschedule", ['date' => '2026-11-18', 'time' => '13:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time');

        $this->assertSame('2026-11-17 09:00', $booking->refresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_an_unpaid_hold_cant_be_moved(): void
    {
        $booking = $this->hold();

        $this->postJson("/api/my-bookings/{$booking->id}/reschedule", ['date' => '2026-11-18', 'time' => '13:00'])->assertUnprocessable();
    }

    public function test_the_day_times_for_a_move_leave_out_only_others(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => false]);
        $this->branch->update(['bays' => 1]);
        $booking = $this->hold();

        $query = http_build_query(['booking' => $booking->id, 'date' => '2026-11-17']);

        // Its own time is still offered
        $this->getJson("/api/availability/day?{$query}")->assertOk()->assertJsonPath('slots.1.time', '09:00');
    }
}
