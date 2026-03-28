<?php

namespace App\Services;

use Carbon\Carbon;

class TimeRounder
{
    private int $intervalMinutes;

    public function __construct(
        private readonly string $rule = 'EXACT',
    ) {
        $this->intervalMinutes = match ($this->rule) {
            'NEAREST_5' => 5,
            'NEAREST_6' => 6,
            'NEAREST_15' => 15,
            default => 0,
        };
    }

    /**
     * Round a timestamp to the nearest interval.
     * Raw timestamp is always preserved; this is for display/payroll.
     */
    public function round(Carbon $time): Carbon
    {
        if ($this->intervalMinutes === 0) {
            return $time->copy();
        }

        $minutes = $time->minute;
        $remainder = $minutes % $this->intervalMinutes;

        if ($remainder === 0) {
            return $time->copy()->second(0);
        }

        $halfInterval = $this->intervalMinutes / 2.0;

        if ($remainder < $halfInterval) {
            // Round down
            return $time->copy()->minute($minutes - $remainder)->second(0);
        }

        // Round up
        return $time->copy()->minute($minutes + ($this->intervalMinutes - $remainder))->second(0);
    }

    /**
     * Round a decimal hours value to the nearest interval fraction.
     */
    public function roundHours(float $hours): float
    {
        if ($this->intervalMinutes === 0) {
            return round($hours, 2);
        }

        $fraction = $this->intervalMinutes / 60.0;

        return round(round($hours / $fraction) * $fraction, 2);
    }
}
