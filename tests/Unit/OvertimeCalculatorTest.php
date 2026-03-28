<?php

namespace Tests\Unit;

use App\Services\OvertimeCalculator;
use Tests\TestCase;

class OvertimeCalculatorTest extends TestCase
{
    public function test_no_overtime_under_weekly_threshold(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: null,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 35.0,
            dailyHours: 8.0
        );

        $this->assertEquals(0.0, $result['overtime_hours']);
        $this->assertEquals(35.0, $result['regular_hours']);
    }

    public function test_weekly_overtime_over_40_hours(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: null,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 45.0,
            dailyHours: 9.0
        );

        $this->assertEquals(5.0, $result['overtime_hours']);
        $this->assertEquals(40.0, $result['regular_hours']);
        $this->assertEquals(1.5, $result['multiplier']);
    }

    public function test_daily_overtime_california_rules(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: 8,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 36.0,
            dailyHours: 10.0
        );

        // Daily overtime: 10 - 8 = 2 hours
        $this->assertEquals(2.0, $result['overtime_hours']);
        $this->assertEquals(8.0, $result['regular_hours']);
    }

    public function test_both_daily_and_weekly_takes_higher(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: 8,
            multiplier: 1.5
        );

        $result = $calculator->calculate(
            weeklyHours: 48.0,
            dailyHours: 12.0
        );

        // Weekly OT: 48 - 40 = 8
        // Daily OT: 12 - 8 = 4
        // Take the higher: 8
        $this->assertEquals(8.0, $result['overtime_hours']);
    }

    public function test_custom_multiplier(): void
    {
        $calculator = new OvertimeCalculator(
            weeklyThreshold: 40,
            dailyThreshold: null,
            multiplier: 2.0
        );

        $result = $calculator->calculate(
            weeklyHours: 50.0,
            dailyHours: 10.0
        );

        $this->assertEquals(2.0, $result['multiplier']);
        $this->assertEquals(10.0, $result['overtime_hours']);
    }
}
