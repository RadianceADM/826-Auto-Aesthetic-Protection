<?php

namespace App\Support\Schedule;

use Carbon\CarbonImmutable;

/**
 * A drop-off time that has room, and when the car will be ready.
 */
final readonly class Slot
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $readyBy,
    ) {}

    /**
     * "Ready Thu, Nov 19, 6:00 PM", or "Ready 1:00 PM" the same day.
     */
    public function readyLabel(): string
    {
        return 'Ready '.($this->readyBy->isSameDay($this->start)
            ? $this->readyBy->format('g:i A')
            : $this->readyBy->format('D, M j, g:i A'));
    }

    /**
     * @return array{time: string, label: string, startsAt: string, readyBy: string, readyLabel: string}
     */
    public function toArray(): array
    {
        return [
            'time' => $this->start->format('H:i'),
            'label' => $this->start->format('g:i A'),
            'startsAt' => $this->start->toIso8601String(),
            'readyBy' => $this->readyBy->toIso8601String(),
            'readyLabel' => $this->readyLabel(),
        ];
    }
}
