<?php

namespace Tests\Unit;

use App\Services\TimeRounder;
use Carbon\Carbon;
use Tests\TestCase;

class TimeRounderTest extends TestCase
{
    public function test_exact_returns_unchanged(): void
    {
        $rounder = new TimeRounder('EXACT');
        $time = Carbon::parse('2026-03-28 08:07:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:07:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_5_rounds_down(): void
    {
        $rounder = new TimeRounder('NEAREST_5');
        $time = Carbon::parse('2026-03-28 08:07:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:05:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_5_rounds_up(): void
    {
        $rounder = new TimeRounder('NEAREST_5');
        $time = Carbon::parse('2026-03-28 08:08:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:10:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_6_rounds(): void
    {
        $rounder = new TimeRounder('NEAREST_6');
        $time = Carbon::parse('2026-03-28 08:04:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:06:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_15_rounds_down(): void
    {
        $rounder = new TimeRounder('NEAREST_15');
        $time = Carbon::parse('2026-03-28 08:07:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_nearest_15_rounds_up(): void
    {
        $rounder = new TimeRounder('NEAREST_15');
        $time = Carbon::parse('2026-03-28 08:08:00');

        $result = $rounder->round($time);

        $this->assertEquals('2026-03-28 08:15:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_round_hours_decimal(): void
    {
        $rounder = new TimeRounder('NEAREST_15');

        // 8 hours 7 minutes = 8.1167 -> round to nearest 0.25 = 8.00
        $this->assertEquals(8.0, $rounder->roundHours(8.1167));

        // 8 hours 20 minutes = 8.3333 -> round to nearest 0.25 = 8.25
        $this->assertEquals(8.25, $rounder->roundHours(8.3333));

        // 8 hours 30 minutes = 8.5 -> round to nearest 0.25 = 8.5
        $this->assertEquals(8.5, $rounder->roundHours(8.5));
    }
}
