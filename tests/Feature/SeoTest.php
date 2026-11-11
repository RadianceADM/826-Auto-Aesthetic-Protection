<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsShop;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        config(['app.url' => 'https://826auto.test']);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'Asia/Manila'));
        $this->buildShop();
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $url): array
    {
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $this->get($url)->getContent(), $match);

        return json_decode($match[1], true);
    }

    public function test_every_page_has_its_own_title_and_description(): void
    {
        $this->get('/book')
            ->assertSee('<title>Reserve a drop-off time | 826 Auto Aesthetic &amp; Protection</title>', escape: false)
            ->assertSee('<link rel="canonical" href="https://826auto.test/book" />', escape: false);

        $this->get('/branches/cainta')->assertSee('<title>826 Cainta | 826 Auto Aesthetic &amp; Protection</title>', escape: false);
        $this->get("/services/{$this->detail->slug}")->assertSee('<title>Exterior detailing | 826 Auto Aesthetic &amp; Protection</title>', escape: false);
    }

    public function test_a_branch_describes_itself_without_making_anything_up(): void
    {
        $this->branch->update(['phone' => '0948-804-2284', 'address' => 'Ortigas Avenue Extension', 'city' => 'Cainta', 'map_lat' => 14.58, 'map_lng' => 121.12]);

        $branch = $this->schema('/branches/cainta');

        $this->assertSame('AutomotiveBusiness', $branch['@type']);
        $this->assertSame('+639488042284', $branch['telephone']);
        $this->assertSame('Cainta', $branch['address']['addressLocality']);
        $this->assertSame(14.58, $branch['geo']['latitude']);
        $this->assertCount(6, $branch['openingHoursSpecification']);
        $this->assertArrayNotHasKey('aggregateRating', $branch);
        $this->assertArrayNotHasKey('priceRange', $branch);
    }

    public function test_the_home_page_names_the_brand(): void
    {
        SiteSetting::instance()->update(['facebook_url' => 'https://www.facebook.com/826AutoAesthetic']);

        $brand = $this->schema('/')['@graph'][1];

        $this->assertSame('Organization', $brand['@type']);
        $this->assertSame('826 Auto Aesthetic & Protection', $brand['name']);
        $this->assertSame(['https://www.facebook.com/826AutoAesthetic'], $brand['sameAs']);
    }

    public function test_the_sitemap_lists_the_pages_services_and_listed_branches(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://826auto.test/services</loc>', escape: false)
            ->assertSee('<loc>https://826auto.test/branches/cainta</loc>', escape: false)
            ->assertSee("<loc>https://826auto.test/services/{$this->detail->slug}</loc>", escape: false);

        $this->branch->update(['status' => 'draft']);
        $this->get('/sitemap.xml')->assertDontSee('branches/cainta');
    }

    public function test_robots_txt_keeps_crawlers_out_of_the_admin_and_points_at_the_sitemap(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'public/robots.txt would shadow the route on LiteSpeed');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: https://826auto.test/sitemap.xml');
    }

    public function test_a_demo_is_kept_out_of_search_engines(): void
    {
        config(['app.demo' => true]);

        $this->get('/services')
            ->assertSee('<meta name="robots" content="noindex" />', escape: false)
            ->assertDontSee('rel="canonical"', escape: false);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');

        $this->assertTrue($this->get('/')->viewData('app')['demo']);
    }

    public function test_the_page_has_crawlable_content_without_javascript(): void
    {
        SiteSetting::instance()->update(['phone' => '0948-804-2284']);

        $this->get('/')
            ->assertSee('<noscript>', escape: false)
            ->assertSee('Detailing: Exterior detailing')
            ->assertSee('<a href="tel:+639488042284">0948-804-2284</a>', escape: false);
    }
}
