<?php

namespace App\Services;

class OvertimeCalculator
{
    public function __construct(
        private readonly float $weeklyThreshold = 40.0,
        private readonly ?float $dailyThreshold = null,
        private readonly float $multiplier = 1.5,
    ) {}

    /**
     * Create from tenant overtime_rule config.
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            weeklyThreshold: $config['weekly_threshold'] ?? 40.0,
            dailyThreshold: $config['daily_threshold'] ?? null,
            multiplier: $config['multiplier'] ?? 1.5,
        );
    }

    /**
     * Calculate overtime hours.
     *
     * @return array{regular_hours: float, overtime_hours: float, multiplier: float}
     */
    public function calculate(float $weeklyHours, float $dailyHours): array
    {
        $weeklyOt = max(0, $weeklyHours - $this->weeklyThreshold);

        $dailyOt = 0.0;
        if ($this->dailyThreshold !== null) {
            $dailyOt = max(0, $dailyHours - $this->dailyThreshold);
        }

        // Take the higher overtime calculation
        $overtimeHours = max($weeklyOt, $dailyOt);

        // Regular hours = total minus overtime (use weekly for weekly OT, daily for daily OT)
        if ($weeklyOt >= $dailyOt) {
            $regularHours = $weeklyHours - $overtimeHours;
        } else {
            $regularHours = $dailyHours - $overtimeHours;
        }

        return [
            'regular_hours' => round($regularHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'multiplier' => $this->multiplier,
        ];
    }
}
