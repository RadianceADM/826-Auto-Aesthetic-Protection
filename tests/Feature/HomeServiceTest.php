<?php

namespace Tests\Feature;

use App\Mail\TeamBookingAlert;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\MailSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsShop;
use Tests\TestCase;

/**
 * Home service: a request the branch schedules by hand, never a bay.
 */
class HomeServiceTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->buildShop();
        $this->branch->update(['offers_home_service' => true, 'home_service_areas' => ['Cainta', 'Antipolo']]);
        $this->guest = Guest::factory()->create();
    }

    private function request(array $overrides = [])
    {
        return $this->actingAs($this->guest, 'guest')->postJson('/api/home-requests', [
            ...$this->reservation(),
            'preferred_date' => '2026-11-18',
            'preferred_window' => 'morning',
            'address' => '12 Sampaguita St.',
            'area' => 'Cainta',
            'has_water' => true,
            'has_power' => false,
            ...$overrides,
        ]);
    }

    public function test_a_request_is_saved_for_the_branch_to_schedule(): void
    {
        Mail::fake();
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'secret']);

        $this->request()->assertCreated()->assertJsonPath('booking.stage', 'requested');

        $booking = Booking::sole();
        $this->assertSame(['home', 'requested', 'Cainta', true, false, null], [$booking->kind, $booking->status, $booking->area, $booking->has_water, $booking->has_power, $booking->has_shade]);
        $this->assertNull($booking->starts_at);
        $this->assertSame('2026-11-18', $booking->preferred_date->toDateString());
        $this->assertFalse($booking->holdsABay());
        Mail::assertSent(TeamBookingAlert::class, fn (TeamBookingAlert $mail) => $mail->kind === 'home_request');
    }

    public function test_only_services_that_can_be_done_at_home(): void
    {
        $this->request(['services' => [$this->coating->id]])->assertUnprocessable()->assertJsonValidationErrors(['services' => 'needs the shop']);
    }

    public function test_the_area_must_be_listed_or_other(): void
    {
        $this->request(['area' => 'Makati'])->assertUnprocessable()->assertJsonValidationErrors('area');
        $this->request(['area' => 'Other'])->assertUnprocessable()->assertJsonValidationErrors('area_other');
        $this->request(['area' => 'Other', 'area_other' => 'Taytay'])->assertCreated();

        $this->assertSame('Other: Taytay', Booking::sole()->area);
    }

    public function test_a_branch_without_home_service_refuses(): void
    {
        $this->branch->update(['offers_home_service' => false]);

        $this->request()->assertUnprocessable()->assertJsonValidationErrors('branch');
    }

    public function test_the_day_must_be_from_tomorrow(): void
    {
        $this->request(['preferred_date' => '2026-11-16'])->assertUnprocessable()->assertJsonValidationErrors('preferred_date');
    }
}
