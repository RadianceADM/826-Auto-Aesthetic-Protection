<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Service;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, built from APP_URL so they name the real domain.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        // A demo copy is kept out of search engines entirely
        if (config('app.demo')) {
            return response("User-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: '.SiteController::pageUrl('sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $pages = collect(['', 'services', 'branches', 'gallery', 'book'])
            ->concat(Service::query()->active()->ordered()->pluck('slug')->map(fn (string $slug) => "services/{$slug}"))
            ->concat(Branch::query()->listed()->ordered()->pluck('slug')->map(fn (string $slug) => "branches/{$slug}"))
            ->map(fn (string $path) => ['url' => SiteController::pageUrl($path), 'updatedAt' => null]);

        return response()
            ->view('sitemap', ['pages' => $pages])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
