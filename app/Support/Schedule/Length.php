<?php

namespace App\Support\Schedule;

/**
 * How long a job keeps a car in the shop: so many open days, then so many
 * minutes of open hours. A day job is ready at closing on its last day.
 */
final readonly class Length
{
    public function __construct(
        public int $days = 0,
        public int $minutes = 0,
    ) {}

    /**
     * @param  iterable<Length>  $lengths
     */
    public static function sum(iterable $lengths): self
    {
        $days = 0;
        $minutes = 0;

        foreach ($lengths as $length) {
            $days += $length->days;
            $minutes += $length->minutes;
        }

        return new self($days, $minutes);
    }

    public function isEmpty(): bool
    {
        return $this->days <= 0 && $this->minutes <= 0;
    }

    public function isMultiDay(): bool
    {
        return $this->days > 0;
    }

    /**
     * "3 days", "2 hours", "1 hr 30 min", "3 days + 4 hours".
     */
    public function label(): string
    {
        $parts = [];

        if ($this->days > 0) {
            $parts[] = $this->days === 1 ? '1 day' : "{$this->days} days";
        }

        if ($this->minutes > 0) {
            $hours = intdiv($this->minutes, 60);
            $rest = $this->minutes % 60;

            $parts[] = match (true) {
                $rest === 0 => $hours === 1 ? '1 hour' : "{$hours} hours",
                $hours === 0 => "{$rest} min",
                default => "{$hours} hr {$rest} min",
            };
        }

        return $parts === [] ? '' : implode(' + ', $parts);
    }

    /**
     * @return array{days: int, minutes: int, label: string}
     */
    public function toArray(): array
    {
        return ['days' => $this->days, 'minutes' => $this->minutes, 'label' => $this->label()];
    }
}
