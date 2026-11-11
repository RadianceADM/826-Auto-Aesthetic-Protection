<?php

namespace App\Support;

use App\Http\Controllers\SiteController;
use App\Models\Branch;
use App\Models\SiteSetting;

/**
 * schema.org JSON-LD for search engines: the brand, and each branch as an
 * automotive business with its address, pin, phone and hours. Only what's
 * filled in the admin is said.
 */
class StructuredData
{
    private const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @return array<string, mixed>
     */
    public static function brand(SiteSetting $settings): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    'name' => SiteController::BRAND,
                    'url' => SiteController::pageUrl(''),
                ],
                array_filter([
                    '@type' => 'Organization',
                    '@id' => SiteController::pageUrl('').'#brand',
                    'name' => SiteController::BRAND,
                    'url' => SiteController::pageUrl(''),
                    'description' => $settings->intro,
                    'slogan' => $settings->tagline,
                    'telephone' => $settings->phoneHref(),
                    'email' => $settings->contact_email,
                    'sameAs' => array_values(array_filter([$settings->facebook_url, $settings->instagram_url])),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function branch(Branch $branch, SiteSetting $settings): array
    {
        $hours = collect($branch->weeklyHours())
            ->filter()
            ->map(fn (array $day, int $weekday) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/'.self::DAYS[$weekday],
                'opens' => $day['open'],
                'closes' => $day['close'],
            ])
            ->values()
            ->all();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'AutomotiveBusiness',
            '@id' => SiteController::pageUrl("branches/{$branch->slug}").'#branch',
            ...array_filter([
                'name' => "826 Auto Aesthetic & Protection – {$branch->name}",
                'url' => SiteController::pageUrl("branches/{$branch->slug}"),
                'parentOrganization' => ['@id' => SiteController::pageUrl('').'#brand'],
                'telephone' => $branch->phoneHref() ?? $settings->phoneHref(),
                'address' => $branch->address ? array_filter([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $branch->address,
                    'addressLocality' => $branch->city,
                    'addressCountry' => 'PH',
                ]) : null,
                'geo' => $branch->map_lat !== null && $branch->map_lng !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $branch->map_lat, 'longitude' => $branch->map_lng] : null,
                'hasMap' => $branch->map()['google'] ?? null,
                'openingHoursSpecification' => $hours ?: null,
                'currenciesAccepted' => 'PHP',
            ]),
        ];
    }
}
