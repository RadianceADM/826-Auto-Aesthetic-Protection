<?php

namespace App\Support\Schedule;

use Carbon\CarbonImmutable;

/**
 * Puts bookings and bays-out blocks into lanes for the timeline: each item
 * goes in the lowest lane that's free when it starts (blocks first at the
 * same moment), so lanes never exceed the busiest moment. Lanes past the
 * branch's bays are over capacity.
 */
final class Lanes
{
    /**
     * @template T of array{start: CarbonImmutable, end: CarbonImmutable, block?: bool, id?: int|string}
     *
     * @param  list<T>  $items
     * @return list<list<T>>
     */
    public static function assign(array $items): array
    {
        usort($items, fn (array $a, array $b) => [$a['start']->getTimestamp(), empty($a['block']) ? 1 : 0, $a['id'] ?? 0]
            <=> [$b['start']->getTimestamp(), empty($b['block']) ? 1 : 0, $b['id'] ?? 0]);

        $lanes = [];
        $ends = [];

        foreach ($items as $item) {
            $lane = null;

            foreach ($ends as $index => $end) {
                if ($end <= $item['start']->getTimestamp()) {
                    $lane = $index;

                    break;
                }
            }

            if ($lane === null) {
                $lane = count($lanes);
                $lanes[$lane] = [];
            }

            $lanes[$lane][] = $item;
            $ends[$lane] = $item['end']->getTimestamp();
        }

        return array_values($lanes);
    }
}
