<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Branch;
use App\Models\Guest;
use App\Models\PaymentSetting;
use App\Models\Photo;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\VehicleSize;
use App\Support\GoogleSignIn;
use App\Support\StructuredData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Serves the React site shell for every page, with 826's catalog,
 * branches and settings embedded (window.APP826), and per-page meta tags
 * and JSON-LD for link previews and search engines.
 */
class SiteController extends Controller
{
    public const BRAND = '826 Auto Aesthetic & Protection';

    public const SHORT_NAME = '826';

    /**
     * The default link-preview card (1200×630), in public/, once designed.
     */
    public const SHARE_IMAGE = 'og-image.jpg';

    /**
     * The "826" mark alone (reads on dark and light), and the whole logo
     * with "AUTO AESTHETIC & PROTECTION" in black (light backgrounds only).
     * Cut from 826's Facebook profile picture until the logo file arrives.
     */
    public const LOGO = 'brand/826-mark.webp';

    public const LOGO_FULL = 'brand/826-logo.webp';

    public function home(Request $request): Response
    {
        return $this->render($request, [
            'title' => self::BRAND.' | PPF, Graphene Coating, Tint and Detailing',
            'description' => '826 Auto Aesthetic & Protection: paint protection film, graphene coating, nano ceramic tint and detailing. Pick a branch and reserve your drop-off time online.',
            'schema' => StructuredData::brand(SiteSetting::current()),
        ]);
    }

    public function services(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Services and prices | '.self::BRAND,
            'description' => 'Paint protection film, graphene coating, nano ceramic tint and detailing at 826, with prices and how long each job takes by vehicle size.',
        ]);
    }

    public function service(Request $request, string $slug): Response
    {
        $service = Service::query()->active()->where('slug', $slug)->first();

        if (! $service) {
            return $this->notFound($request);
        }

        return $this->render($request, [
            'title' => "{$service->name} | ".self::BRAND,
            'description' => Str::limit((string) ($service->summary ?: $service->description), 155) ?: "{$service->name} at 826 Auto Aesthetic & Protection.",
        ]);
    }

    public function branches(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Branches | '.self::BRAND,
            'description' => 'Find an 826 branch near you: address, hours, map and how to reach them.',
        ]);
    }

    public function branch(Request $request, string $slug): Response
    {
        $branch = Branch::query()->listed()->where('slug', $slug)->first();

        if (! $branch) {
            return $this->notFound($request);
        }

        return $this->render($request, [
            'title' => "826 {$branch->name} | ".self::BRAND,
            'description' => Str::limit(collect(["826 Auto Aesthetic & Protection {$branch->name}.", $branch->address, $branch->status === 'open' ? 'Reserve your drop-off time online.' : 'Opening soon.'])->filter()->implode(' '), 155),
            'schema' => StructuredData::branch($branch, SiteSetting::current()),
        ]);
    }

    public function gallery(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Our work | '.self::BRAND,
            'description' => 'Cars coated, wrapped, tinted and detailed at 826 Auto Aesthetic & Protection.',
        ]);
    }

    public function book(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Reserve a drop-off time | '.self::BRAND,
            'description' => 'Pick a branch, your vehicle and services, and reserve an open drop-off time at 826.',
        ]);
    }

    public function homeService(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Request home service | '.self::BRAND,
            'description' => 'Ask an 826 branch to come to you. They’ll call or text to agree on a time.',
        ]);
    }

    public function myBookings(Request $request): Response
    {
        return $this->render($request, ['title' => 'My bookings | '.self::BRAND, 'noindex' => true]);
    }

    public function myBooking(Request $request): Response
    {
        return $this->render($request, ['title' => 'Your booking | '.self::BRAND, 'noindex' => true]);
    }

    public function notFound(Request $request): Response
    {
        return $this->render($request, ['title' => 'Page not found | '.self::BRAND, 'noindex' => true], 404);
    }

    /**
     * An absolute URL on the site, built from APP_URL so previews, the
     * sitemap and JSON-LD point at the real domain.
     */
    public static function pageUrl(string $path): string
    {
        $path = trim($path, '/');

        return rtrim(config('app.url'), '/').($path === '' ? '/' : "/{$path}");
    }

    /**
     * The catalog the site shows and books from: active categories and
     * services, with only bookable rates for active sizes.
     *
     * @return array{sizes: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    public static function catalog(): array
    {
        $sizes = VehicleSize::query()->active()->ordered()->get();
        $sizeIds = $sizes->modelKeys();

        $categories = ServiceCategory::query()
            ->active()
            ->ordered()
            ->with(['services' => fn ($services) => $services->where('is_active', true)->ordered()->with('rates')])
            ->get();

        return [
            'sizes' => $sizes->map->toSite()->values()->all(),
            'categories' => $categories->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'services' => $category->services->map(fn (Service $service) => $service->toSite(array_map('intval', $sizeIds)))->values()->all(),
            ])->filter(fn (array $category) => $category['services'] !== [])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function render(Request $request, array $meta, int $status = 200): Response
    {
        $settings = SiteSetting::current();
        $payments = PaymentSetting::instance();

        $meta = array_merge([
            'description' => Str::limit((string) $settings->intro, 155) ?: self::BRAND.'.',
            'image' => null,
            'imageAlt' => null,
            'imageSize' => null,
            'noindex' => false,
            'schema' => null,
        ], $meta);

        // A demo copy stays out of search results, page by page
        $meta['noindex'] = $meta['noindex'] || config('app.demo');

        if (! $meta['image']) {
            $card = is_file(public_path(self::SHARE_IMAGE)) ? self::pageUrl(self::SHARE_IMAGE) : null;
            $meta['image'] = $settings->shareImageUrl() ?? $card;
            $meta['imageAlt'] = $meta['image'] ? self::BRAND : null;
            $meta['imageSize'] = ! $settings->shareImageUrl() && $card ? [1200, 630] : null;
        }

        $meta['url'] = self::pageUrl($request->path());
        $guest = Auth::guard('guest')->user();

        return response()->view('site', [
            'meta' => $meta,
            'app' => [
                'name' => self::BRAND,
                'shortName' => self::SHORT_NAME,
                'logo' => is_file(public_path(self::LOGO)) ? asset(self::LOGO) : null,
                'logoFull' => is_file(public_path(self::LOGO_FULL)) ? asset(self::LOGO_FULL) : null,
                // "" at a domain's root, "/demo/826" when served from a folder
                'base' => rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/'),
                'demo' => (bool) config('app.demo'),
                'settings' => $settings->toSite(),
                'auth' => [
                    'googleEnabled' => GoogleSignIn::enabled(),
                    'guest' => $guest instanceof Guest ? $guest->toSite() : null,
                    'signInUrl' => url('/auth/google'),
                    'signOutUrl' => url('/auth/logout'),
                    // Local development only: sign in as a test customer without Google
                    'localSignInUrl' => app()->isLocal() && ! GoogleSignIn::enabled() ? url('/auth/local') : null,
                ],
                'catalog' => self::catalog(),
                'branches' => Branch::query()->listed()->ordered()->get()->map->toSite()->values(),
                'booking' => [
                    ...BookingSetting::current()->toSite(),
                    'fee' => [
                        'enabled' => $payments->feeEnabled(),
                        'label' => $payments->feeLabel(),
                        'holdMinutes' => $payments->holdMinutes(),
                    ],
                    'windows' => Booking::WINDOWS,
                ],
                'photos' => Photo::ordered()->get()->map->toSite()->values(),
                'photoCategories' => Photo::CATEGORIES,
            ],
        ], $status);
    }
}
