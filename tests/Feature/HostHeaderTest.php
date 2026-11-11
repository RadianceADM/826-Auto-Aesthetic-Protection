<?php

namespace Tests\Feature;

use App\Mail\TeamBookingAlert;
use App\Models\Booking;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Links in pages and emails (password resets, "Open in the admin") must
 * never follow a Host or X-Forwarded-Host sent by the client.
 */
class HostHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarded_host_headers_are_ignored(): void
    {
        $this->withoutVite();
        $headers = ['X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'];

        $this->get('/', $headers)->assertOk()->assertDontSee('evil.example');
        $this->get('/admin/login', $headers)->assertOk()->assertDontSee('evil.example');
    }

    public function test_production_always_builds_urls_from_app_url(): void
    {
        config(['app.url' => 'https://826auto.test']);
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        $this->get('/admin/login', ['Host' => 'evil.example'])->assertOk()->assertDontSee('evil.example');

        $this->assertSame('https://826auto.test/admin/password-reset/request', URL::to('/admin/password-reset/request'));

        $booking = Booking::factory()->create();
        $html = (new TeamBookingAlert($booking, 'reserved'))->render();
        $this->assertStringContainsString('https://826auto.test/admin/bookings/'.$booking->id, $html);
        $this->assertStringNotContainsString('evil.example', $html);
    }
}
