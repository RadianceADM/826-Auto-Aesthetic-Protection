<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Photo;
use App\Models\ServiceRate;
use App\Models\SiteSetting;
use App\Models\VehicleSize;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsShop;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
    }

    /**
     * @return array<string, mixed>
     */
    private function app(string $url = '/'): array
    {
        return $this->get($url)->viewData('app');
    }

    public function test_every_page_renders_the_react_shell(): void
    {
        $this->buildShop();

        foreach (['/', '/services', "/services/{$this->detail->slug}", '/branches', '/branches/cainta', '/gallery', '/book', '/home-service', '/bookings', '/bookings/5'] as $page) {
            $this->get($page)->assertOk()->assertSee('<div id="app"></div>', escape: false)->assertSee('window.APP826', escape: false);
        }
    }

    public function test_unknown_pages_drafts_and_hidden_services_are_404_with_noindex(): void
    {
        $this->buildShop();
        $this->branch->update(['status' => 'draft']);
        $this->detail->update(['is_active' => false]);

        foreach (['/nope', '/branches/cainta', "/services/{$this->detail->slug}"] as $page) {
            $this->get($page)->assertNotFound()->assertSee('<meta name="robots" content="noindex" />', escape: false);
        }
    }

    public function test_old_style_links_go_to_the_right_page(): void
    {
        $this->get('/reserve')->assertRedirect('/book');
        $this->get('/prices')->assertRedirect('/services');
        $this->get('/locations')->assertRedirect('/branches');
    }

    public function test_the_site_knows_the_folder_it_is_served_from(): void
    {
        $this->assertSame('', $this->app()['base']);

        config(['app.url' => 'https://dran.dev/demo/826']);
        $this->app['url']->forceRootUrl('https://dran.dev/demo/826');

        $this->assertSame('/demo/826', $this->app()['base']);
    }

    public function test_the_catalog_lists_only_bookable_rates_for_shown_sizes(): void
    {
        $this->buildShop();
        $hidden = VehicleSize::factory()->create(['name' => 'Bus', 'is_active' => false]);
        ServiceRate::factory()->hours(5)->create(['service_id' => $this->detail->id, 'vehicle_size_id' => $hidden->id]);
        $suv = VehicleSize::factory()->create(['name' => 'SUV']);
        ServiceRate::factory()->create(['service_id' => $this->detail->id, 'vehicle_size_id' => $suv->id, 'price' => null]);

        $catalog = $this->app()['catalog'];

        $this->assertSame(['Sedan', 'SUV'], array_column($catalog['sizes'], 'name'));
        $this->assertSame(['Detailing', 'Coating'], array_column($catalog['categories'], 'name'));

        $detail = $catalog['categories'][0]['services'][0];
        $this->assertSame('Exterior detailing', $detail['name']);
        $this->assertSame([['sizeId' => $this->sedan->id, 'price' => 2500, 'days' => 0, 'minutes' => 180, 'duration' => '3 hours']], $detail['rates']);
        $this->assertTrue($detail['homeServiceOk']);
    }

    public function test_branches_show_what_customers_need_and_drafts_stay_hidden(): void
    {
        $this->buildShop();
        $this->branch->update(['phone' => '0948-804-2284', 'map_lat' => 14.58, 'map_lng' => 121.12, 'offers_home_service' => true, 'home_service_areas' => ['Cainta', ' ', 'Antipolo']]);
        Branch::factory()->draft()->create(['name' => 'Secret branch']);
        Branch::factory()->create(['name' => 'Taytay', 'status' => 'coming_soon']);

        $branches = collect($this->app()['branches'])->keyBy('name');

        $this->assertSame(['Cainta', 'Taytay'], $branches->keys()->sort()->values()->all());
        $this->assertTrue($branches['Cainta']['bookable']);
        $this->assertFalse($branches['Taytay']['bookable']);
        $this->assertSame('+639488042284', $branches['Cainta']['phoneHref']);
        $this->assertStringContainsString('ll=14.58%2C121.12', $branches['Cainta']['map']['waze']);
        $this->assertSame(['Cainta', 'Antipolo'], $branches['Cainta']['homeService']['areas']);
        $this->assertSame(['day' => 7, 'label' => 'Sunday', 'open' => null, 'close' => null, 'lastDropoff' => null], $branches['Cainta']['hours'][6]);
        $this->assertSame([$this->detail->id, $this->coating->id], $branches['Cainta']['serviceIds']);
    }

    public function test_blank_settings_are_left_off_the_site(): void
    {
        $settings = $this->app()['settings'];

        $this->assertNull($settings['phone']);
        $this->assertNull($settings['phoneHref']);
        $this->assertNull($settings['hero']);
        $this->assertFalse($this->app()['booking']['fee']['enabled']);
    }

    public function test_photos_become_webp_renditions_and_are_deleted_with_the_photo(): void
    {
        Storage::disk('public')->put('photos/ppf.png', UploadedFile::fake()->image('ppf.png', 1600, 1000)->getContent());

        $photo = Photo::factory()->create(['image' => 'photos/ppf.png']);

        $this->assertSame('photos/ppf.webp', $photo->image);
        $this->assertSame([1600, 1000], [$photo->width, $photo->height]);
        Storage::disk('public')->assertExists(['photos/ppf.webp', 'photos/ppf-lg.webp', 'photos/ppf-og.jpg', 'photos/ppf-thumb.webp']);

        $photo->delete();

        Storage::disk('public')->assertMissing(['photos/ppf.webp', 'photos/ppf-thumb.webp']);
    }

    public function test_photos_are_imported_from_a_folder_and_the_hero_is_set(): void
    {
        $folder = storage_path('framework/testing/import');
        @mkdir($folder, 0777, true);
        file_put_contents("{$folder}/a.jpg", UploadedFile::fake()->image('a.jpg', 1200, 800)->getContent());
        file_put_contents("{$folder}/b.png", UploadedFile::fake()->image('b.png', 800, 1200)->getContent());
        file_put_contents("{$folder}/notes.txt", 'not a photo');

        $this->artisan('826:import-photos', ['paths' => [$folder], '--category' => 'ppf', '--featured' => true])
            ->expectsOutputToContain('Imported 2 of 2 photos.')
            ->assertSuccessful();

        $this->assertSame(['ppf', 'ppf'], Photo::pluck('category')->all());

        $this->artisan('826:import-photos', ['paths' => ["{$folder}/a.jpg"], '--hero' => true])->assertSuccessful();

        $this->assertStringEndsWith('.webp', SiteSetting::current()->hero_image);
        $this->assertNotNull($this->app()['settings']['hero']);

        $this->artisan('826:import-photos', ['paths' => [$folder], '--category' => 'nope'])->assertFailed();

        array_map('unlink', glob("{$folder}/*"));
        rmdir($folder);
    }
}
