<?php

namespace Tests\Feature;

use App\Http\Controllers\GuestAuthController;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\Support\BuildsShop;
use Tests\TestCase;

class GuestSignInTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Monday, Nov 16, 2026
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->buildShop();
        SiteSetting::instance()->update(['google_client_id' => null, 'google_client_secret' => null]);
    }

    private function turnOnGoogle(): void
    {
        SiteSetting::instance()->update(['google_client_id' => 'client.apps.googleusercontent.com', 'google_client_secret' => 'secret']);
    }

    private function fakeGoogle(string $id = '1234567890', string $email = 'maria@example.com'): void
    {
        $user = (new GoogleUser)->map(['id' => $id, 'name' => 'Maria Santos', 'email' => $email, 'avatar' => 'https://lh3.example/photo.jpg']);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_sign_in_is_off_until_the_owner_adds_a_client(): void
    {
        $this->get('/auth/google')->assertNotFound();
        $this->assertFalse($this->get('/')->viewData('app')['auth']['googleEnabled']);
        $this->get('/auth/google')->assertNotFound();

        // Requests still work without signing in
    }

    public function test_the_secret_is_stored_encrypted_and_never_sent_to_the_site(): void
    {
        $this->turnOnGoogle();

        $this->assertNotSame('secret', SiteSetting::query()->toBase()->value('google_client_secret'));
        $this->assertTrue($this->get('/')->viewData('app')['auth']['googleEnabled']);
        $this->get('/')->assertDontSee('secret"', escape: false);
    }

    public function test_reserving_needs_a_signed_in_customer(): void
    {
        $this->turnOnGoogle();

        $this->postJson('/api/bookings', $this->reservation())->assertUnauthorized()->assertJson(['signIn' => true]);
        $this->postJson('/api/home-requests', $this->reservation())->assertUnauthorized()->assertJson(['signIn' => true]);
        $this->assertSame(0, Booking::count());
    }

    public function test_signing_in_creates_the_guest_and_returns_to_the_page(): void
    {
        $this->turnOnGoogle();
        $this->fakeGoogle();

        $this->withSession(['guest.return' => '/book?branch=cainta'])
            ->get('/auth/google/callback')
            ->assertRedirect(url('/book?branch=cainta'));

        $guest = Guest::sole();
        $this->assertSame(['Maria Santos', 'maria@example.com'], [$guest->name, $guest->email]);
        $this->assertAuthenticatedAs($guest, 'guest');
        $this->assertGuest('web');
        $this->assertSame(['name' => 'Maria Santos', 'email' => 'maria@example.com', 'avatar' => 'https://lh3.example/photo.jpg'], $this->get('/')->viewData('app')['auth']['guest']);
    }

    public function test_a_signed_in_customers_booking_is_theirs(): void
    {
        $this->turnOnGoogle();
        $guest = Guest::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs($guest, 'guest')->postJson('/api/bookings', $this->reservation(['email' => null]))->assertCreated();

        $booking = Booking::sole();
        $this->assertSame($guest->id, $booking->guest_id);
        $this->assertSame('maria@example.com', $booking->email);
        $this->assertSame('0917 123 4567', $guest->fresh()->phone);
    }

    public function test_a_guest_account_cannot_open_the_admin(): void
    {
        $this->actingAs(Guest::factory()->create(), 'guest')->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_return_path_never_leaves_the_site(): void
    {
        $this->assertSame('/book?branch=cainta', GuestAuthController::safeReturn('/book?branch=cainta'));
        $this->assertSame('/', GuestAuthController::safeReturn('https://evil.example/'));
        $this->assertSame('/', GuestAuthController::safeReturn('//evil.example'));
        $this->assertSame('/', GuestAuthController::safeReturn('/\\evil.example'));
        $this->assertSame('/', GuestAuthController::safeReturn(null));
    }

    public function test_local_development_signs_in_a_test_customer_without_google(): void
    {
        $this->app['env'] = 'local';

        $this->get('/auth/local?return=/book')->assertRedirect(url('/book'));
        $this->assertAuthenticatedAs(Guest::sole(), 'guest');

        $this->turnOnGoogle();
        $this->get('/auth/local')->assertNotFound();
    }

    public function test_signing_out(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guest')->post('/auth/logout')->assertNoContent();
        $this->assertGuest('guest');
    }
}
