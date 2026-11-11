<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\BranchBlocks\BranchBlockResource;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use App\Support\Schedule\Lanes;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * A branch's bays over a day or a week: every car holding a bay from
 * drop-off to ready-by, holds waiting for their fee, bays out of service
 * and closures, plus scheduled home visits. More cars than bays at once
 * show in red overflow lanes.
 */
class Timeline extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Timeline';

    protected string $view = 'filament.pages.timeline';

    #[Url]
    public ?int $branch = null;

    #[Url]
    public string $date = '';

    #[Url]
    public string $range = 'week';

    public function mount(): void
    {
        $branches = $this->branchOptions();
        $this->branch ??= array_key_first($branches);

        abort_unless($this->branch === null || auth()->user()->canManageBranch($this->branch), 403);

        $this->date = $this->start()->toDateString();
        $this->range = in_array($this->range, ['day', 'week'], true) ? $this->range : 'week';
    }

    public function updatedBranch(): void
    {
        abort_unless($this->branch === null || auth()->user()->canManageBranch($this->branch), 403);
    }

    public function previous(): void
    {
        $this->date = $this->start()->sub($this->range === 'day' ? '1 day' : '1 week')->toDateString();
    }

    public function next(): void
    {
        $this->date = $this->start()->add($this->range === 'day' ? '1 day' : '1 week')->toDateString();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    public function showRange(string $range): void
    {
        $this->range = $range === 'day' ? 'day' : 'week';
    }

    /**
     * @return array<int, string>
     */
    public function branchOptions(): array
    {
        return Branch::query()->visibleTo(auth()->user())->ordered()->pluck('name', 'id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newBooking')
                ->label('Add a booking')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn () => BookingResource::getUrl('create')),
            Action::make('block')
                ->label('Close or block bays')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->url(fn () => BranchBlockResource::getUrl('index')),
        ];
    }

    private function start(): CarbonImmutable
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? CarbonImmutable::parse($this->date) : CarbonImmutable::today();

        return $date->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $branch = $this->branch ? Branch::query()->visibleTo(auth()->user())->find($this->branch) : null;

        if (! $branch) {
            return ['shop' => null, 'branches' => $this->branchOptions()];
        }

        $first = $this->range === 'day' ? $this->start() : $this->start()->startOfWeek();
        $dayCount = $this->range === 'day' ? 1 : 7;
        $last = $first->addDays($dayCount);

        $days = [];

        for ($i = 0; $i < $dayCount; $i++) {
            $date = $first->addDays($i);
            $hours = $branch->hoursOn($date);
            $days[] = [
                'date' => $date,
                'open' => $hours['open'] ?? null,
                'close' => $hours['close'] ?? null,
                'isToday' => $date->isToday(),
            ];
        }

        $bookings = Booking::query()
            ->where('branch_id', $branch->id)
            ->whereNotNull('starts_at')
            ->whereNotIn('status', ['cancelled', 'declined', 'expired', 'no_show'])
            ->where(fn ($open) => $open->where('status', '!=', 'awaiting_payment')->orWhereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->touching($first, $last)
            ->with('items')
            ->orderBy('starts_at')
            ->get();

        $blocks = BranchBlock::query()->forBranch($branch)->overlapping($first, $last)->orderBy('starts_at')->get();

        $items = [];

        foreach ($bookings->where('kind', 'onsite') as $booking) {
            $items[] = $this->bookingItem($booking);
        }

        foreach ($blocks->where('kind', 'bays') as $block) {
            $count = $block->bays === null ? max(1, $branch->bays) : max(1, $block->bays);

            for ($i = 0; $i < $count; $i++) {
                $items[] = [
                    'id' => "block-{$block->id}-{$i}",
                    'block' => true,
                    'start' => $block->starts_at,
                    'end' => $block->ends_at,
                    'label' => $block->title,
                    'sub' => 'Bay out of service',
                    'style' => 'block',
                    'url' => null,
                ];
            }
        }

        $lanes = Lanes::assign($items);
        $bays = max(0, (int) $branch->bays);

        $rows = [];

        for ($index = 0; $index < max($bays, count($lanes)); $index++) {
            $rows[] = [
                'label' => $index < $bays ? 'Bay '.($index + 1) : 'Over capacity',
                'overflow' => $index >= $bays,
                'segments' => $this->segments($lanes[$index] ?? [], $days),
            ];
        }

        $home = $bookings->where('kind', 'home')->map(fn (Booking $booking) => $this->bookingItem($booking))->values()->all();

        $closures = $blocks->where('kind', 'closed')->values();

        return [
            'shop' => $branch,
            'branches' => $this->branchOptions(),
            'days' => $days,
            'rows' => $rows,
            'homeSegments' => $this->segments($home, $days),
            'closureSegments' => $this->segments($closures->map(fn (BranchBlock $block) => [
                'id' => "closed-{$block->id}",
                'start' => $block->starts_at,
                'end' => $block->ends_at,
                'label' => $block->title,
                'sub' => 'Closed',
                'style' => 'closed',
                'url' => null,
            ])->all(), $days),
            'overCapacity' => count($lanes) > $bays,
            'unscheduledHome' => Booking::query()->where('branch_id', $branch->id)->where('kind', 'home')->where('status', 'requested')->count(),
            'agenda' => collect([...$items, ...$home])
                ->filter(fn (array $item) => empty($item['block']))
                ->sortBy(fn (array $item) => $item['start']->getTimestamp())
                ->values()
                ->all(),
            'heading' => $this->range === 'day'
                ? $first->format('l, M j, Y')
                : $first->format('M j').' – '.$last->subDay()->format($first->month === $last->subDay()->month ? 'j, Y' : 'M j, Y'),
            'range' => $this->range,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingItem(Booking $booking): array
    {
        $hold = $booking->stage() === 'awaiting_payment';
        $overdue = in_array($booking->status, ['checked_in', 'ready'], true) && $booking->ends_at?->isPast();

        return [
            'id' => $booking->id,
            'start' => $booking->starts_at,
            'end' => $booking->ends_at ?? $booking->starts_at->addHour(),
            'label' => $booking->name,
            'sub' => trim($booking->servicesLabel().($booking->vehicle_model ? " · {$booking->vehicle_model}" : '')),
            'style' => match (true) {
                $overdue => 'overdue',
                $hold => 'hold',
                $booking->status === 'payment_review' => 'review',
                $booking->status === 'checked_in' => 'in_shop',
                $booking->status === 'ready' => 'ready',
                $booking->status === 'completed' => 'done',
                default => 'confirmed',
            },
            'note' => match (true) {
                $hold => 'Unpaid hold until '.$booking->hold_expires_at?->format('g:i A'),
                $booking->status === 'payment_review' => 'Screenshot to check',
                $overdue => 'Past its ready-by',
                default => null,
            },
            'time' => $booking->starts_at->format('D g:i A').' → '.($booking->ends_at?->isSameDay($booking->starts_at) ? $booking->ends_at->format('g:i A') : $booking->ends_at?->format('D g:i A')),
            'url' => BookingResource::getUrl('view', ['record' => $booking]),
        ];
    }

    /**
     * Each item cut into one piece per day it covers, placed within that
     * day's open hours as percentages (closed nights fold away).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $days
     * @return array<int, list<array<string, mixed>>>
     */
    private function segments(array $items, array $days): array
    {
        $byDay = [];

        foreach ($days as $index => $day) {
            $byDay[$index] = [];

            if (! $day['open']) {
                continue;
            }

            $open = $day['open']->getTimestamp();
            $close = $day['close']->getTimestamp();
            $span = max(1, $close - $open);

            foreach ($items as $item) {
                $from = max($open, $item['start']->getTimestamp());
                $to = min($close, $item['end']->getTimestamp());

                if ($to <= $from) {
                    continue;
                }

                $byDay[$index][] = [
                    ...$item,
                    'left' => round(($from - $open) / $span * 100, 3),
                    'width' => max(1.5, round(($to - $from) / $span * 100, 3)),
                    'startsHere' => $item['start']->getTimestamp() >= $open,
                    'endsHere' => $item['end']->getTimestamp() <= $close,
                ];
            }
        }

        return $byDay;
    }
}
