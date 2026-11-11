<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Models\Guest;
use App\Models\Service;
use App\Models\ServiceRate;
use App\Models\VehicleSize;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * SAMPLE data for local testing and the portfolio demo only, never for
 * 826's real site: made-up hours, bays and prices where those are blank,
 * and one booking in every stage. The real numbers come from the owner.
 * Bookings, customers and blocks it makes are marked, and --clear removes
 * only those (sample hours and prices stay until edited in the admin).
 */
#[Signature('826:demo-data {--clear : Remove the sample bookings, customers and blocks} {--force : Run outside local and demo mode}')]
#[Description('Add (or remove) SAMPLE hours, prices and bookings for testing or a demo')]
class DemoData extends Command
{
    public const SOURCE = 'demo';

    public const GUEST_PREFIX = 'demo-';

    public const BLOCK_PREFIX = 'Sample:';

    /**
     * Made-up starting prices by category, for the smallest car; bigger
     * sizes add 15% each, motorcycles take 40%.
     *
     * @var array<string, array{0: int, 1: string, 2: float}>
     */
    private const SAMPLE = [
        'exterior-detailing' => [2500, 'hours', 4],
        'interior-detailing' => [3000, 'hours', 5],
        'graphene-coating' => [15000, 'days', 2],
        'self-healing-graphene-coating' => [22000, 'days', 3],
        'borophene-coating' => [25000, 'days', 3],
        'interior-leather-coating' => [5000, 'hours', 3],
        'full-body-ppf' => [120000, 'days', 5],
        'per-panel-ppf' => [8000, 'hours', 4],
        'windshield-ppf' => [9000, 'hours', 2],
        'nano-ceramic-tint' => [9000, 'hours', 3],
    ];

    public function handle(): int
    {
        if (! app()->isLocal() && ! config('app.demo') && ! $this->option('force')) {
            $this->error('Sample data is for local testing and the demo only. Use --force if you really mean it.');

            return self::FAILURE;
        }

        $this->clear();

        if ($this->option('clear')) {
            $this->info('Sample bookings, customers and blocks removed.');

            return self::SUCCESS;
        }

        $branches = $this->openBranches();
        $this->fillRates();
        $this->bookings($branches[0]);

        $this->info('Sample data added: hours and bays on '.collect($branches)->pluck('name')->implode(', ').', prices where blank, and bookings at '.$branches[0]->name.'.');
        $this->line('Remove the bookings with: php artisan 826:demo-data --clear');

        return self::SUCCESS;
    }

    private function clear(): void
    {
        Booking::query()->where('source', self::SOURCE)->get()->each->delete();
        Guest::query()->where('google_id', 'like', self::GUEST_PREFIX.'%')->delete();
        BranchBlock::query()->where('title', 'like', self::BLOCK_PREFIX.'%')->delete();
    }

    /**
     * @return list<Branch>
     */
    private function openBranches(): array
    {
        $branches = Branch::query()->ordered()->get();

        if ($branches->isEmpty()) {
            $branches = collect([Branch::create(['name' => 'Sample branch', 'slug' => 'sample-branch', 'city' => 'Cainta'])]);
        }

        $hours = [];

        foreach (range(1, 7) as $day) {
            $hours[(string) $day] = $day === 7 ? null : ['open' => '08:00', 'close' => '18:00', 'last_dropoff' => '16:00'];
        }

        $open = [];

        foreach ($branches as $index => $branch) {
            $branch->update(array_filter([
                'weekly_hours' => $branch->hasHours() ? null : $hours,
                'bays' => $branch->bays > 0 ? null : 3,
                'multiday_dropoff_by' => $branch->multiday_dropoff_by ? null : '12:00',
                'status' => $branch->status === 'draft' ? ($index < 2 ? 'open' : 'coming_soon') : null,
                'offers_home_service' => $index === 0 ? true : null,
                'home_service_areas' => $index === 0 && blank($branch->home_service_areas) ? ['Cainta', 'Antipolo', 'Taytay', 'Pasig'] : null,
            ], fn ($value) => $value !== null));

            if ($branch->services()->doesntExist()) {
                $branch->services()->sync(Service::query()->pluck('id'));
            }

            if ($branch->status === 'open') {
                $open[] = $branch;
            }
        }

        Service::query()->whereIn('slug', ['exterior-detailing', 'interior-detailing'])->update(['home_service_ok' => true]);

        return $open ?: [$branches->first()];
    }

    private function fillRates(): void
    {
        $sizes = VehicleSize::query()->ordered()->get();

        foreach (Service::query()->get() as $service) {
            [$price, $unit, $amount] = self::SAMPLE[$service->slug] ?? [5000, 'hours', 3];

            foreach ($sizes as $step => $size) {
                if (ServiceRate::query()->where('service_id', $service->id)->where('vehicle_size_id', $size->id)->exists()) {
                    continue;
                }

                $isMotorcycle = str_contains(strtolower($size->name), 'motor');

                if ($isMotorcycle && in_array($service->slug, ['nano-ceramic-tint', 'windshield-ppf', 'interior-detailing', 'interior-leather-coating'], true)) {
                    continue;
                }

                $factor = $isMotorcycle ? 0.4 : 1 + 0.15 * max(0, $step - 1);

                ServiceRate::create([
                    'service_id' => $service->id,
                    'vehicle_size_id' => $size->id,
                    'price' => (int) (round($price * $factor / 100) * 100),
                    'duration_days' => $unit === 'days' ? (int) $amount : null,
                    'duration_minutes' => $unit === 'hours' ? (int) round(($isMotorcycle ? max(1, $amount - 1) : $amount) * 60) : null,
                ]);
            }
        }
    }

    private function bookings(Branch $branch): void
    {
        $guest = fn (string $name) => Guest::create([
            'google_id' => self::GUEST_PREFIX.str($name)->slug(),
            'name' => $name,
            'email' => str($name)->slug().'@example.com',
            'last_signed_in_at' => now(),
        ]);

        $size = VehicleSize::query()->ordered()->skip(2)->first() ?? VehicleSize::query()->first();
        $service = fn (string $slug) => Service::query()->where('slug', $slug)->with(['rates', 'category'])->first();
        $nextOpen = function (int $daysAhead) use ($branch): CarbonImmutable {
            $date = today()->toImmutable()->addDays($daysAhead);

            while ($branch->hoursOn($date) === null) {
                $date = $date->addDay();
            }

            return $date;
        };

        $samples = [
            ['Sample Customer A', 'confirmed', ['exterior-detailing'], $nextOpen(1)->setTime(9, 0), 'Toyota Vios'],
            ['Sample Customer B', 'checked_in', ['graphene-coating'], $nextOpen(0)->setTime(8, 0), 'Honda Civic'],
            ['Sample Customer C', 'payment_review', ['nano-ceramic-tint'], $nextOpen(2)->setTime(10, 0), 'Mitsubishi Montero'],
            ['Sample Customer D', 'awaiting_payment', ['per-panel-ppf'], $nextOpen(3)->setTime(13, 0), 'Ford Ranger'],
            ['Sample Customer E', 'ready', ['interior-detailing'], $nextOpen(0)->setTime(9, 0), 'Toyota Fortuner'],
        ];

        foreach ($samples as [$name, $status, $slugs, $start, $model]) {
            $rates = collect($slugs)->map(fn (string $slug) => $service($slug)?->rateFor($size->id)?->setRelation('service', $service($slug)))->filter();

            if ($rates->isEmpty()) {
                continue;
            }

            $days = (int) $rates->sum('duration_days');
            $minutes = (int) $rates->sum('duration_minutes');
            $end = $days > 0 ? $start->addDays($days - 1)->setTime(18, 0) : $start->addMinutes($minutes);
            $customer = $guest($name);

            $booking = Booking::create([
                'kind' => 'onsite',
                'source' => self::SOURCE,
                'status' => $status,
                'branch_id' => $branch->id,
                'guest_id' => $customer->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'vehicle_size_id' => $size->id,
                'vehicle_size_name' => $size->name,
                'vehicle_model' => $model,
                'plate_number' => 'SMP '.random_int(1000, 9999),
                'name' => $name,
                'phone' => '0900 000 '.random_int(1000, 9999),
                'email' => $customer->email,
                'total_price' => (int) $rates->sum('price'),
                'total_days' => $days,
                'total_minutes' => $minutes,
                'amount_due' => in_array($status, ['payment_review', 'awaiting_payment'], true) ? 500 : null,
                'hold_expires_at' => $status === 'awaiting_payment' ? now()->addHours(6) : null,
                'payment_method' => $status === 'payment_review' ? 'gcash' : null,
                'payment_submitted_at' => $status === 'payment_review' ? now()->subMinutes(10) : null,
            ]);

            foreach ($rates->values() as $index => $rate) {
                $booking->items()->create([
                    'service_id' => $rate->service_id,
                    'service_name' => $rate->service->name,
                    'category_name' => $rate->service->category?->name,
                    'price' => $rate->price,
                    'duration_minutes' => (int) $rate->duration_minutes,
                    'duration_days' => (int) $rate->duration_days,
                    'sort_order' => $index,
                ]);
            }
        }

        $home = $service('exterior-detailing')?->rateFor($size->id);

        if ($home) {
            $customer = $guest('Sample Customer F');
            $booking = Booking::create([
                'kind' => 'home',
                'source' => self::SOURCE,
                'status' => 'requested',
                'branch_id' => $branch->id,
                'guest_id' => $customer->id,
                'preferred_date' => $nextOpen(4),
                'preferred_window' => 'morning',
                'address' => 'Sample address',
                'area' => 'Antipolo',
                'has_water' => true,
                'has_power' => true,
                'has_shade' => false,
                'vehicle_size_id' => $size->id,
                'vehicle_size_name' => $size->name,
                'vehicle_model' => 'Nissan Navara',
                'name' => 'Sample Customer F',
                'phone' => '0900 000 1234',
                'email' => $customer->email,
                'total_price' => $home->price,
                'total_minutes' => (int) $home->duration_minutes,
            ]);
            $booking->items()->create(['service_id' => $home->service_id, 'service_name' => 'Exterior detailing', 'category_name' => 'Detailing', 'price' => $home->price, 'duration_minutes' => (int) $home->duration_minutes]);
        }

        $day = $nextOpen(6);
        BranchBlock::create([
            'branch_id' => $branch->id,
            'kind' => 'bays',
            'bays' => 1,
            'starts_at' => $day->startOfDay(),
            'ends_at' => $day->addDay()->startOfDay(),
            'title' => self::BLOCK_PREFIX.' bay 2 under repair',
        ]);
    }
}
