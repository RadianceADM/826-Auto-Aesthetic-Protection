<?php

namespace Tests\Feature;

use App\Filament\Pages\BookingSettings;
use App\Filament\Pages\PaymentSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\BranchBlocks\Pages\ManageBranchBlocks;
use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Mail\CustomerBookingUpdate;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Models\MailSetting;
use App\Models\PaymentSetting;
use App\Models\Photo;
use App\Models\Service;
use App\Models\ServiceRate;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\VehicleSize;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\BuildsShop;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // Monday, Nov 16, 2026
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->buildShop();
        $this->owner = User::factory()->owner()->create();
    }

    public function test_visitors_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $booking = Booking::factory()->create(['branch_id' => $this->branch->id]);
        $home = Booking::factory()->home()->create(['branch_id' => $this->branch->id]);
        $photo = Photo::factory()->create();
        BranchBlock::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->owner);

        foreach ([
            '/admin',
            '/admin/timeline',
            '/admin/timeline?range=day',
            '/admin/bookings',
            '/admin/bookings?activeTab=action',
            '/admin/bookings/create',
            "/admin/bookings/{$booking->id}",
            "/admin/bookings/{$booking->id}/edit",
            "/admin/bookings/{$home->id}",
            '/admin/branch-blocks',
            '/admin/branches',
            '/admin/branches/create',
            "/admin/branches/{$this->branch->id}/edit",
            '/admin/services',
            '/admin/services/create',
            "/admin/services/{$this->detail->id}/edit",
            '/admin/service-categories',
            '/admin/vehicle-sizes',
            '/admin/photos',
            "/admin/photos/{$photo->id}/edit",
            '/admin/site-settings',
            '/admin/booking-rules',
            '/admin/payment-settings',
            '/admin/email-settings',
            '/admin/google-sign-in',
            '/admin/users',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_opening_a_booking_marks_it_read(): void
    {
        $booking = Booking::factory()->create(['branch_id' => $this->branch->id]);
        $this->assertNull($booking->read_at);

        $this->actingAs($this->owner)->get("/admin/bookings/{$booking->id}")->assertOk();

        $this->assertNotNull($booking->refresh()->read_at);
    }

    public function test_a_screenshot_is_checked_and_the_booking_confirmed(): void
    {
        Mail::fake();
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'secret']);
        $booking = Booking::factory()->create(['branch_id' => $this->branch->id, 'status' => 'payment_review', 'amount_due' => 500, 'hold_expires_at' => now()->addMinutes(5)]);

        $this->actingAs($this->owner);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('markPaid', data: ['payment_note' => 'GCash 1234'])
            ->assertHasNoActionErrors();

        $booking->refresh();
        $this->assertSame(['confirmed', 'GCash 1234'], [$booking->status, $booking->payment_note]);
        $this->assertNotNull($booking->paid_at);
        $this->assertNull($booking->hold_expires_at);
        Mail::assertSent(CustomerBookingUpdate::class, fn (CustomerBookingUpdate $mail) => $mail->kind === 'paid');
    }

    public function test_a_rejected_screenshot_gives_a_fresh_hold(): void
    {
        PaymentSetting::instance()->update(['hold_minutes' => 40]);
        $booking = Booking::factory()->create(['branch_id' => $this->branch->id, 'status' => 'payment_review', 'amount_due' => 500, 'payment_proof' => 'payment-proofs/1/x.webp']);

        $this->actingAs($this->owner);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('rejectProof', data: ['reason' => 'The amount doesn’t match'])
            ->assertHasNoActionErrors();

        $booking->refresh();
        $this->assertSame(['awaiting_payment', 'The amount doesn’t match', null], [$booking->status, $booking->payment_rejected_reason, $booking->payment_proof]);
        $this->assertSame('07:40', $booking->hold_expires_at->format('H:i'));
    }

    public function test_a_car_is_checked_in_marked_ready_and_picked_up(): void
    {
        Mail::fake();
        MailSetting::instance()->update(['address' => 'hello@826auto.test', 'password' => 'secret']);
        $booking = Booking::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->owner);

        $page = Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()]);
        $page->callAction('checkIn')->assertHasNoActionErrors();
        $this->assertSame('checked_in', $booking->refresh()->status);

        $page->callAction('markReady')->assertHasNoActionErrors();
        $this->assertSame('ready', $booking->refresh()->status);
        Mail::assertSent(CustomerBookingUpdate::class, fn (CustomerBookingUpdate $mail) => $mail->kind === 'ready');

        $page->callAction('complete')->assertHasNoActionErrors();
        $this->assertSame('completed', $booking->refresh()->status);
        $this->assertFalse($booking->holdsABay());
    }

    public function test_the_team_books_a_walk_in_any_time_the_branch_is_open(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'kind' => 'onsite',
                'source' => 'walk_in',
                'branch_id' => $this->branch->id,
                'vehicle_size_id' => $this->sedan->id,
                'services' => [$this->detail->id],
                // Off the hourly grid and inside the notice the site asks for
                'starts_at' => '2026-11-16 08:20:00',
                'name' => 'Walk In',
                'phone' => '0917 222 3333',
                'vehicle_model' => 'Honda City',
                'plate_number' => 'XYZ 987',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::sole();
        $this->assertSame(['confirmed', 'walk_in', '2026-11-16 08:20', '2026-11-16 11:20'], [$booking->status, $booking->source, $booking->starts_at->format('Y-m-d H:i'), $booking->ends_at->format('Y-m-d H:i')]);
        $this->assertNotNull($booking->read_at);
    }

    public function test_a_full_time_needs_book_anyway(): void
    {
        Booking::factory()->count(2)->at('2026-11-17 09:00', '2026-11-17 12:00')->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->owner);

        $form = [
            'kind' => 'onsite',
            'source' => 'phone',
            'branch_id' => $this->branch->id,
            'vehicle_size_id' => $this->sedan->id,
            'services' => [$this->detail->id],
            'starts_at' => '2026-11-17 10:00:00',
            'name' => 'Caller',
            'phone' => '0917 222 3333',
        ];

        Livewire::test(CreateBooking::class)->fillForm($form)->call('create')->assertHasFormErrors(['starts_at']);
        $this->assertSame(2, Booking::count());

        Livewire::test(CreateBooking::class)->fillForm([...$form, 'force' => true])->call('create')->assertHasNoFormErrors();
        $this->assertSame(3, Booking::count());
    }

    public function test_moving_a_booking_from_the_admin_rechecks_the_bays(): void
    {
        $this->branch->update(['bays' => 1]);
        $booking = Booking::factory()->at('2026-11-17 09:00', '2026-11-17 12:00')->create(['branch_id' => $this->branch->id]);
        Booking::factory()->at('2026-11-18 09:00', '2026-11-18 12:00')->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->owner);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('reschedule', data: ['starts_at' => '2026-11-18 10:00:00'])
            ->assertNotified('Not moved');
        $this->assertSame('2026-11-17 09:00', $booking->refresh()->starts_at->format('Y-m-d H:i'));

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('reschedule', data: ['starts_at' => '2026-11-18 12:00:00'])
            ->assertHasNoActionErrors();
        $this->assertSame(['2026-11-18 12:00', '2026-11-18 15:00'], [$booking->refresh()->starts_at->format('Y-m-d H:i'), $booking->ends_at->format('Y-m-d H:i')]);
    }

    public function test_a_home_request_is_scheduled_with_the_fee_when_on(): void
    {
        PaymentSetting::instance()->update(['reservation_fee_enabled' => true, 'amount_value' => 300]);
        $booking = Booking::factory()->home()->create(['branch_id' => $this->branch->id, 'total_price' => 2500]);

        $this->actingAs($this->owner);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('scheduleHome', data: ['starts_at' => '2026-11-20 09:00:00', 'ends_at' => '2026-11-20 12:00:00', 'ask_fee' => true])
            ->assertHasNoActionErrors();

        $booking->refresh();
        $this->assertSame(['awaiting_payment', 300, null], [$booking->status, $booking->amount_due, $booking->hold_expires_at]);
        $this->assertSame('awaiting_payment', $booking->stage());
    }

    public function test_closures_cover_whole_days(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(ManageBranchBlocks::class)
            ->callAction('create', data: ['branch_id' => null, 'kind' => 'closed', 'title' => 'Bonifacio Day', 'whole_days' => true, 'first_day' => '2026-11-30', 'last_day' => '2026-11-30'])
            ->assertHasNoActionErrors();

        $block = BranchBlock::sole();
        $this->assertNull($block->branch_id);
        $this->assertSame(['2026-11-30 00:00', '2026-12-01 00:00'], [$block->starts_at->format('Y-m-d H:i'), $block->ends_at->format('Y-m-d H:i')]);
        $this->assertSame($this->owner->id, $block->created_by);
    }

    public function test_a_branch_is_added_as_a_draft_and_its_hours_saved(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(CreateBranch::class)
            ->fillForm(['name' => 'Antipolo', 'slug' => 'antipolo', 'status' => 'draft', 'bays' => 0, 'step_minutes' => 60, 'lead_hours' => 2, 'months_ahead' => 3])
            ->call('create')
            ->assertHasNoFormErrors();

        $branch = Branch::where('slug', 'antipolo')->sole();
        $this->assertSame('draft', $branch->status);
        $this->assertFalse($branch->hasHours());

        Livewire::test(EditBranch::class, ['record' => $branch->getRouteKey()])
            ->fillForm([
                'weekly_hours' => [
                    1 => ['open_today' => true, 'open' => '09:00', 'close' => '17:00', 'last_dropoff' => '15:00'],
                    7 => ['open_today' => false, 'open' => '09:00', 'close' => '17:00'],
                ],
                'multiday_dropoff_by' => '12:00',
                'bays' => 3,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $branch->refresh();
        $this->assertSame(['open' => '09:00', 'close' => '17:00', 'last_dropoff' => '15:00'], $branch->weeklyHours()[1]);
        $this->assertNull($branch->weeklyHours()[7]);
        $this->assertSame(['12:00', 3], [$branch->multidayDropoffBy(), $branch->bays]);
    }

    public function test_service_prices_and_durations_are_set_per_size(): void
    {
        $suv = VehicleSize::factory()->create(['name' => 'SUV']);
        $this->actingAs($this->owner);

        Livewire::test(EditService::class, ['record' => $this->coating->getRouteKey()])
            ->assertFormSet(["rates.{$this->sedan->id}.price" => 18000, "rates.{$this->sedan->id}.unit" => 'days', "rates.{$this->sedan->id}.amount" => 3])
            ->fillForm([
                "rates.{$this->sedan->id}" => ['price' => 19000, 'unit' => 'days', 'amount' => 4],
                "rates.{$suv->id}" => ['price' => 2500, 'unit' => 'hours', 'amount' => 2.5],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $sedan = ServiceRate::where('service_id', $this->coating->id)->where('vehicle_size_id', $this->sedan->id)->sole();
        $this->assertSame([19000, 4, null], [$sedan->price, $sedan->duration_days, $sedan->duration_minutes]);
        $big = ServiceRate::where('service_id', $this->coating->id)->where('vehicle_size_id', $suv->id)->sole();
        $this->assertSame([2500, null, 150], [$big->price, $big->duration_days, $big->duration_minutes]);

        // A blank price removes the size
        Livewire::test(EditService::class, ['record' => $this->coating->getRouteKey()])
            ->fillForm(["rates.{$suv->id}" => ['price' => null, 'unit' => 'hours', 'amount' => null]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse(ServiceRate::where('service_id', $this->coating->id)->where('vehicle_size_id', $suv->id)->exists());
    }

    public function test_a_new_service_starts_with_its_rates(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Headlight restoration',
                'slug' => 'headlight-restoration',
                'service_category_id' => $this->detail->service_category_id,
                "rates.{$this->sedan->id}" => ['price' => 1500, 'unit' => 'hours', 'amount' => 1],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::where('slug', 'headlight-restoration')->sole();
        $this->assertSame(60, $service->rates()->sole()->duration_minutes);
    }

    public function test_settings_pages_save(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(SiteSettings::class)
            ->fillForm(['tagline' => 'Protect what you drive', 'phone' => '0948-804-2284', 'facebook_url' => 'https://www.facebook.com/826AutoAesthetic'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('0948-804-2284', SiteSetting::current()->phone);

        Livewire::test(SiteSettings::class)->fillForm(['instagram_url' => 'http://insecure.example'])->call('save')->assertHasFormErrors(['instagram_url']);

        Livewire::test(BookingSettings::class)
            ->fillForm(['cancel_cutoff_hours' => 12, 'reschedule_enabled' => true, 'reschedule_cutoff_hours' => 6, 'max_reschedules' => 2, 'max_unpaid_holds_per_guest' => 1, 'max_upcoming_per_guest' => 5])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame([12, 5], [BookingSetting::current()->cancel_cutoff_hours, BookingSetting::current()->max_upcoming_per_guest]);

        Livewire::test(PaymentSettings::class)
            ->fillForm(['reservation_fee_enabled' => true, 'amount_rule' => 'fixed', 'amount_value' => 500, 'hold_minutes' => 60])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame([true, 500, 60], [PaymentSetting::instance()->feeEnabled(), PaymentSetting::instance()->amount_value, PaymentSetting::instance()->holdMinutes()]);
    }

    public function test_the_branch_lists_what_stops_online_booking(): void
    {
        $branch = Branch::factory()->bays(0)->create(['weekly_hours' => null]);
        PaymentSetting::instance()->update(['reservation_fee_enabled' => true]);

        $missing = $branch->missing();

        $this->assertCount(4, $missing);
        $this->assertStringContainsString('bays', $missing[0]);
        $this->assertFalse($branch->isOnsiteBookable());

        $this->assertSame(['The reservation fee is on: add a GCash or Maya account here or in Payments.'], $this->branch->missing());

        PaymentSetting::instance()->update(['gcash_enabled' => true, 'gcash_number' => '0917 000 0000']);
        $this->assertTrue($this->branch->refresh()->isOnsiteBookable());
    }
}
