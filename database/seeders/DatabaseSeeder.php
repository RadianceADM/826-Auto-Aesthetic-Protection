<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\VehicleSize;
use Illuminate\Database\Seeder;

/**
 * 826's starting content, only from what they publish themselves (their
 * Google Site and Facebook pages): the services and their descriptions,
 * the film brands, the main number and email, and the three branches
 * known so far, as drafts. Prices, durations, hours, bays and addresses
 * stay blank until the owner gives them. Only fills blanks, so re-seeding
 * never overwrites edits from the admin.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * @var array<string, array{description: string|null, services: list<array{0: string, 1: string|null}>}>
     */
    private const CATALOG = [
        'Detailing' => [
            'description' => null,
            'services' => [
                ['Exterior detailing', 'Deep cleaning, polishing and waxing to restore and protect the paint.'],
                ['Interior detailing', 'A full clean of the seats, carpets and hard surfaces, with vacuuming, shampooing and leather care.'],
            ],
        ],
        'Coating' => [
            'description' => null,
            'services' => [
                ['Graphene coating', 'A long-lasting, high-gloss finish that resists water spots, UV and chemical damage.'],
                ['Self-healing graphene coating', null],
                ['Borophene coating', 'An advanced coating for more gloss and chemical resistance.'],
                ['Interior leather coating', 'Protects leather from stains, cracking and fading while keeping its natural feel.'],
            ],
        ],
        'Paint Protection Film' => [
            'description' => 'Film brands: 826 Film, XPEL, LLumar and STEK.',
            'services' => [
                ['Full body PPF', 'Clear or colored film over the whole car, guarding the paint against scratches, chips and swirl marks.'],
                ['Per panel PPF', 'Film on the parts that take the most hits, like the hood, bumper and side mirrors.'],
                ['Windshield PPF', 'A near-invisible film that helps the windshield resist chips and cracks.'],
            ],
        ],
        'Window Tint' => [
            'description' => null,
            'services' => [
                ['Nano ceramic tint', 'Cuts heat, UV and glare while keeping the view clear, without blocking phone or GPS signal.'],
            ],
        ],
    ];

    /**
     * A starting list for the price list; rename, add or hide sizes in the admin.
     */
    private const SIZES = ['Motorcycle', 'Small car / Hatchback', 'Sedan', 'SUV / Crossover', 'Pickup / Van'];

    /**
     * The branches 826 lists on Facebook, by city only; the rest comes from the owner.
     *
     * @var list<array{name: string, slug: string, city: string, facebook: string}>
     */
    private const BRANCHES = [
        ['name' => 'Cainta – Ortigas Extension', 'slug' => 'cainta', 'city' => 'Cainta', 'facebook' => 'https://www.facebook.com/826AutoAesthetic/'],
        ['name' => 'San Mateo', 'slug' => 'san-mateo', 'city' => 'San Mateo', 'facebook' => 'https://www.facebook.com/826CoatingAndProtection/'],
        ['name' => 'Quezon City', 'slug' => 'quezon-city', 'city' => 'Quezon City', 'facebook' => 'https://www.facebook.com/826quezoncity/'],
    ];

    public function run(): void
    {
        if (app()->isLocal()) {
            User::firstOrCreate(
                ['email' => 'admin@826auto.test'],
                ['name' => '826 Admin', 'password' => 'password', 'is_owner' => true],
            );
        }

        $settings = SiteSetting::instance();
        $settings->update(array_filter([
            'tagline' => 'Paint protection film, graphene coating, detailing and ceramic tint',
            'intro' => '826 Auto Aesthetic & Protection is a Filipino-owned car care company: paint protection film, graphene coating, nano ceramic tint and detailing for every kind of vehicle, at our branches or at your home.',
            'phone' => '0948-804-2284',
            'contact_email' => '826autoaestheticandprotection@gmail.com',
            'facebook_url' => 'https://www.facebook.com/826AutoAesthetic',
            'instagram_url' => 'https://www.instagram.com/826autoaesthetic/',
        ], fn ($value, string $key) => blank($settings->{$key}), ARRAY_FILTER_USE_BOTH));

        if (VehicleSize::query()->doesntExist()) {
            foreach (self::SIZES as $order => $name) {
                VehicleSize::create(['name' => $name, 'sort_order' => $order]);
            }
        }

        $services = collect();

        foreach (array_keys(self::CATALOG) as $order => $name) {
            $category = ServiceCategory::firstOrCreate(
                ['slug' => str($name)->slug()->toString()],
                ['name' => $name, 'description' => self::CATALOG[$name]['description'], 'sort_order' => $order],
            );

            foreach (self::CATALOG[$name]['services'] as $index => [$service, $summary]) {
                $services->push(Service::firstOrCreate(
                    ['slug' => str($service)->slug()->toString()],
                    [
                        'service_category_id' => $category->id,
                        'name' => $service,
                        'summary' => $summary,
                        'sort_order' => $order * 10 + $index,
                    ],
                ));
            }
        }

        foreach (self::BRANCHES as $order => $known) {
            $branch = Branch::firstOrCreate(
                ['slug' => $known['slug']],
                ['name' => $known['name'], 'city' => $known['city'], 'facebook_url' => $known['facebook'], 'status' => 'draft', 'sort_order' => $order],
            );

            if ($branch->wasRecentlyCreated) {
                $branch->services()->sync($services->pluck('id'));
            }
        }
    }
}
