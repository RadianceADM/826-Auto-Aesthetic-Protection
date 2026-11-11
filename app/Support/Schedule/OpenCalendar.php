<?php

namespace App\Support\Schedule;

use App\Models\Branch;
use App\Models\BranchBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * When a branch is open: its weekly hours, minus closures (holidays, half
 * days). Works out when a job dropped off at a given time is ready, and the
 * drop-off times a day offers. Bookings and bays don't come into it; that's
 * Occupancy.
 */
final class OpenCalendar
{
    /**
     * How far a job's ready-by is looked for before giving up.
     */
    public const MAX_WALK_DAYS = 120;

    /**
     * Open intervals per date, worked out once.
     *
     * @var array<string, list<array{0: CarbonImmutable, 1: CarbonImmutable}>>
     */
    private array $intervals = [];

    /**
     * @param  Collection<int, BranchBlock>  $closures
     */
    public function __construct(
        private readonly Branch $branch,
        private readonly Collection $closures,
    ) {}

    /**
     * The branch's calendar from a date, with every closure that a job
     * starting by $to could run into (one query).
     */
    public static function for(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): self
    {
        $closures = BranchBlock::query()
            ->closures()
            ->forBranch($branch)
            ->overlapping($from->startOfDay(), $to->addDays(self::MAX_WALK_DAYS + 1))
            ->orderBy('starts_at')
            ->get(['id', 'branch_id', 'kind', 'starts_at', 'ends_at']);

        return new self($branch, $closures);
    }

    /**
     * The open stretches of a date: its hours, with closures cut out
     * (usually one stretch, none when closed).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function intervals(CarbonImmutable $date): array
    {
        $key = $date->toDateString();

        if (array_key_exists($key, $this->intervals)) {
            return $this->intervals[$key];
        }

        $hours = $this->branch->hoursOn($date);

        if ($hours === null) {
            return $this->intervals[$key] = [];
        }

        $intervals = [[$hours['open'], $hours['close']]];

        foreach ($this->closures as $closure) {
            if ($closure->starts_at >= $hours['close'] || $closure->ends_at <= $hours['open']) {
                continue;
            }

            $cut = [];

            foreach ($intervals as [$open, $close]) {
                if ($closure->ends_at <= $open || $closure->starts_at >= $close) {
                    $cut[] = [$open, $close];

                    continue;
                }

                if ($closure->starts_at > $open) {
                    $cut[] = [$open, $closure->starts_at];
                }

                if ($closure->ends_at < $close) {
                    $cut[] = [$closure->ends_at, $close];
                }
            }

            $intervals = $cut;
        }

        return $this->intervals[$key] = $intervals;
    }

    public function isOpenAt(CarbonImmutable $moment): bool
    {
        foreach ($this->intervals($moment) as [$open, $close]) {
            if ($moment >= $open && $moment < $close) {
                return true;
            }
        }

        return false;
    }

    public function openMinutes(CarbonImmutable $date): int
    {
        return array_sum(array_map(fn (array $interval) => self::minutesBetween($interval[0], $interval[1]), $this->intervals($date)));
    }

    /**
     * When a job dropped off at $start is ready. Day jobs count the
     * drop-off day as day 1 and are ready at closing on their last open day;
     * hour jobs then run through open hours, carrying into the next open day
     * if they don't fit. Null when it can't finish within MAX_WALK_DAYS.
     */
    public function finish(CarbonImmutable $start, Length $length): ?CarbonImmutable
    {
        $cursor = $start;

        if ($length->days > 0) {
            $date = $start->startOfDay();
            $counted = 0;

            for ($i = 0; $i <= self::MAX_WALK_DAYS && $counted < $length->days; $i++, $date = $date->addDay()) {
                $intervals = $this->intervals($date);

                if ($intervals === []) {
                    continue;
                }

                if (++$counted === $length->days) {
                    $cursor = $intervals[array_key_last($intervals)][1]->max($start);
                }
            }

            if ($counted < $length->days) {
                return null;
            }
        }

        $remaining = $length->minutes;

        if ($remaining <= 0) {
            return $cursor;
        }

        $date = $cursor->startOfDay();

        for ($i = 0; $i <= self::MAX_WALK_DAYS; $i++, $date = $date->addDay()) {
            foreach ($this->intervals($date) as [$open, $close]) {
                if ($close <= $cursor) {
                    continue;
                }

                $from = $open->max($cursor);
                $available = self::minutesBetween($from, $close);

                if ($remaining <= $available) {
                    return $from->addMinutes($remaining);
                }

                $remaining -= $available;
                $cursor = $close;
            }
        }

        return null;
    }

    /**
     * The drop-off times on a date for a job of this length, and when
     * each is ready, ignoring other bookings. Times step from opening until
     * the last drop-off (the multi-day cutoff for day jobs), skip closures
     * and anything before $earliest. A job that fits in a day must be
     * ready by closing; a longer one carries over.
     *
     * @return list<Slot>
     */
    public function candidates(CarbonImmutable $date, Length $length, CarbonImmutable $earliest): array
    {
        $hours = $this->branch->hoursOn($date);
        $intervals = $this->intervals($date);

        if ($hours === null || $intervals === [] || $length->isEmpty()) {
            return [];
        }

        $last = $hours['lastDropoff'];
        $cutoff = $this->branch->multidayDropoffBy();

        if ($length->isMultiDay() && $cutoff !== null) {
            $last = $last->min($date->startOfDay()->setTimeFromTimeString($cutoff));
        }

        $dayEnd = $intervals[array_key_last($intervals)][1];
        $sameDay = ! $length->isMultiDay() && $length->minutes <= $this->openMinutes($date);
        $step = $this->branch->stepMinutes();
        $slots = [];

        for ($start = $hours['open']; $start <= $last; $start = $start->addMinutes($step)) {
            if ($start < $earliest || ! $this->isOpenAt($start)) {
                continue;
            }

            $ready = $this->finish($start, $length);

            if ($ready === null || ($sameDay && $ready > $dayEnd)) {
                continue;
            }

            $slots[] = new Slot($start, $ready);
        }

        return $slots;
    }

    private static function minutesBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return max(0, intdiv($to->getTimestamp() - $from->getTimestamp(), 60));
    }
}
