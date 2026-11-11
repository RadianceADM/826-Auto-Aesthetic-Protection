<?php

namespace App\Support\Schedule;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchBlock;
use Carbon\CarbonImmutable;

/**
 * When a branch's bays are all taken, between two moments: the bookings
 * holding a bay plus bays out of service, swept into "full" stretches. A
 * new job fits when none of its time falls in one.
 */
final class Occupancy
{
    /**
     * Full stretches as [start, end) timestamps, sorted and merged.
     *
     * @param  list<array{0: int, 1: int}>  $full
     */
    private function __construct(private readonly array $full) {}

    /**
     * Two indexed queries: bookings and bay blocks touching the window.
     */
    public static function load(Branch $branch, CarbonImmutable $from, CarbonImmutable $to, ?int $exceptBookingId = null): self
    {
        $bays = (int) $branch->bays;

        if ($bays <= 0) {
            return new self([[PHP_INT_MIN, PHP_INT_MAX]]);
        }

        $events = [];

        Booking::query()
            ->where('branch_id', $branch->getKey())
            ->holdingABay()
            ->touching($from, $to)
            ->when($exceptBookingId, fn ($query) => $query->whereKeyNot($exceptBookingId))
            ->get(['id', 'starts_at', 'ends_at'])
            ->each(function (Booking $booking) use (&$events) {
                $events[] = [$booking->starts_at->getTimestamp(), 1];
                $events[] = [$booking->ends_at->getTimestamp(), -1];
            });

        BranchBlock::query()
            ->bayBlocks()
            ->forBranch($branch)
            ->overlapping($from, $to)
            ->get(['id', 'bays', 'starts_at', 'ends_at'])
            ->each(function (BranchBlock $block) use (&$events, $bays) {
                $count = $block->bays === null ? $bays : min($bays, max(1, $block->bays));
                $events[] = [$block->starts_at->getTimestamp(), $count];
                $events[] = [$block->ends_at->getTimestamp(), -$count];
            });

        // Ends before starts at the same moment: back-to-back jobs share a bay
        usort($events, fn (array $a, array $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $full = [];
        $used = 0;
        $since = null;

        foreach ($events as [$moment, $change]) {
            $used += $change;

            if ($used >= $bays && $since === null) {
                $since = $moment;
            } elseif ($used < $bays && $since !== null) {
                if ($moment > $since) {
                    self::append($full, $since, $moment);
                }

                $since = null;
            }
        }

        if ($since !== null) {
            self::append($full, $since, PHP_INT_MAX);
        }

        return new self($full);
    }

    /**
     * Whether a job from $start until $end finds a free bay the whole time.
     */
    public function fits(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $from = $start->getTimestamp();
        $to = $end->getTimestamp();

        // The first full stretch that ends after the job starts
        $low = 0;
        $high = count($this->full);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->full[$middle][1] <= $from) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low === count($this->full) || $this->full[$low][0] >= $to;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $full
     */
    private static function append(array &$full, int $start, int $end): void
    {
        $last = array_key_last($full);

        if ($last !== null && $full[$last][1] >= $start) {
            $full[$last][1] = max($full[$last][1], $end);

            return;
        }

        $full[] = [$start, $end];
    }
}
