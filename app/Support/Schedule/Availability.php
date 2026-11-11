<?php

namespace App\Support\Schedule;

use App\Models\Branch;
use Carbon\CarbonImmutable;

/**
 * Which drop-off times a branch has room for, for a job of a given length:
 * a month at a glance, one day's times, and the final check made (under the
 * branch's lock) before a reservation is saved or moved.
 */
final class Availability
{
    /**
     * The earliest drop-off: now plus the branch's notice.
     */
    public static function earliestStart(Branch $branch): CarbonImmutable
    {
        return now()->toImmutable()->addHours($branch->leadHours())->startOfMinute();
    }

    /**
     * The last day that can be booked: the end of the month so many months out.
     */
    public static function lastDay(Branch $branch): CarbonImmutable
    {
        return today()->toImmutable()->addMonthsNoOverflow($branch->monthsAhead())->endOfMonth()->startOfDay();
    }

    /**
     * Each day of a month: "open" (at least one time has room), "full"
     * (times exist but every one is taken) or "closed" (no times: closed,
     * past, too soon, or past the booking window).
     *
     * @return array{month: string, first: string, last: string, days: array<string, string>}
     */
    public static function month(Branch $branch, Length $length, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        $earliest = self::earliestStart($branch);
        $last = self::lastDay($branch);

        $calendar = OpenCalendar::for($branch, $start, $end);
        $candidates = [];
        $latestReady = null;

        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $slots = $date > $last || $date < $earliest->startOfDay() ? [] : $calendar->candidates($date, $length, $earliest);
            $candidates[$date->toDateString()] = $slots;

            foreach ($slots as $slot) {
                $latestReady = $latestReady === null ? $slot->readyBy : $latestReady->max($slot->readyBy);
            }
        }

        $occupancy = $latestReady ? Occupancy::load($branch, $start, $latestReady) : null;
        $days = [];

        foreach ($candidates as $date => $slots) {
            $days[$date] = match (true) {
                $slots === [] => 'closed',
                self::anyFits($occupancy, $slots) => 'open',
                default => 'full',
            };
        }

        return [
            'month' => $start->format('Y-m'),
            'first' => $earliest->toDateString(),
            'last' => $last->toDateString(),
            'days' => $days,
        ];
    }

    /**
     * The drop-off times on one day that have room.
     *
     * @return list<Slot>
     */
    public static function day(Branch $branch, Length $length, CarbonImmutable $date, ?int $exceptBookingId = null): array
    {
        $date = $date->startOfDay();

        if ($date > self::lastDay($branch)) {
            return [];
        }

        $slots = OpenCalendar::for($branch, $date, $date)->candidates($date, $length, self::earliestStart($branch));

        if ($slots === []) {
            return [];
        }

        $occupancy = Occupancy::load($branch, $slots[0]->start, max(array_map(fn (Slot $slot) => $slot->readyBy, $slots)), $exceptBookingId);

        return array_values(array_filter($slots, fn (Slot $slot) => $occupancy->fits($slot->start, $slot->readyBy)));
    }

    /**
     * The final word on one drop-off time: the slot when it's free, else
     * why not ("closed", "not_a_start" or "full").
     */
    public static function check(Branch $branch, Length $length, CarbonImmutable $start, ?int $exceptBookingId = null): Slot|string
    {
        if ($length->isEmpty() || (int) $branch->bays <= 0) {
            return 'closed';
        }

        $date = $start->startOfDay();

        if ($date > self::lastDay($branch)) {
            return 'not_a_start';
        }

        $slot = collect(OpenCalendar::for($branch, $date, $date)->candidates($date, $length, self::earliestStart($branch)))
            ->first(fn (Slot $candidate) => $candidate->start->equalTo($start));

        if (! $slot instanceof Slot) {
            return 'not_a_start';
        }

        return Occupancy::load($branch, $slot->start, $slot->readyBy, $exceptBookingId)->fits($slot->start, $slot->readyBy)
            ? $slot
            : 'full';
    }

    /**
     * The team's check for a walk-in, a call or a move: any time the shop
     * is open (no step, notice or booking window), as long as a bay is free
     * until the job is ready.
     */
    public static function checkForStaff(Branch $branch, Length $length, CarbonImmutable $start, ?int $exceptBookingId = null): Slot|string
    {
        if ($length->isEmpty()) {
            return 'closed';
        }

        $calendar = OpenCalendar::for($branch, $start, $start);

        if (! $calendar->isOpenAt($start)) {
            return 'not_a_start';
        }

        $ready = $calendar->finish($start, $length);

        if ($ready === null) {
            return 'closed';
        }

        $slot = new Slot($start, $ready);

        return (int) $branch->bays > 0 && Occupancy::load($branch, $start, $ready, $exceptBookingId)->fits($start, $ready) ? $slot : 'full';
    }

    /**
     * @param  list<Slot>  $slots
     */
    private static function anyFits(?Occupancy $occupancy, array $slots): bool
    {
        foreach ($slots as $slot) {
            if ($occupancy === null || $occupancy->fits($slot->start, $slot->readyBy)) {
                return true;
            }
        }

        return false;
    }
}
