<?php

namespace Tests\Unit;

use App\Services\PremiumCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PremiumCalculatorTest extends TestCase
{
    private PremiumCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PremiumCalculator();
    }

    public function test_valid_request_produces_expected_premium(): void
    {
        // Example from spec: 5 days, age 28 (1.00), INTERNATIONAL (1.80), PLUS (1.25) = 20 × 5 × 1.00 × 1.80 × 1.25 = ₹225.00
        // We need a DOB that gives age 28 on the trip_start date
        $tripStart = Carbon::now()->addDays(10)->startOfDay();
        $tripEnd = Carbon::now()->addDays(14)->startOfDay(); // 5 days inclusive
        $dob = $tripStart->copy()->subYears(28)->subMonths(7); // Born ~April if trip is November

        $data = [
            'plan_code' => 'PLUS',
            'destination' => 'INTERNATIONAL',
            'date_of_birth' => $dob->format('Y-m-d'),
            'trip_start' => $tripStart->format('Y-m-d'),
            'trip_end' => $tripEnd->format('Y-m-d'),
        ];

        $result = $this->calculator->calculate($data);

        $this->assertEquals(5, $result['travel_days']);
        $this->assertEquals(28, $result['age_at_travel']);
        $this->assertEquals(20.00, $result['base_daily_rate']);
        $this->assertEquals(1.00, $result['age_multiplier']);
        $this->assertEquals(1.80, $result['destination_multiplier']);
        $this->assertEquals(1.25, $result['coverage_multiplier']);
        $this->assertEquals(225.00, $result['calculated_premium']);
    }

    public function test_basic_plan_calculation(): void
    {
        $tripStart = Carbon::now()->addDays(30)->startOfDay();
        $tripEnd = Carbon::now()->addDays(34)->startOfDay(); // 5 days
        // Age 36 -> 36-50 range = 1.25 multiplier
        $dob = $tripStart->copy()->subYears(36);

        $data = [
            'plan_code' => 'BASIC',
            'destination' => 'DOMESTIC',
            'date_of_birth' => $dob->format('Y-m-d'),
            'trip_start' => $tripStart->format('Y-m-d'),
            'trip_end' => $tripEnd->format('Y-m-d'),
        ];

        $result = $this->calculator->calculate($data);

        // BASIC: 15 × 5 × 1.25 × 1.00 × 1.00 = 93.75
        $this->assertEquals(5, $result['travel_days']);
        $this->assertEquals(15.00, $result['base_daily_rate']);
        $this->assertEquals(1.25, $result['age_multiplier']);
        $this->assertEquals(1.00, $result['destination_multiplier']);
        $this->assertEquals(1.00, $result['coverage_multiplier']);
        $this->assertEquals(93.75, $result['calculated_premium']);
    }

    public function test_premium_plan_calculation(): void
    {
        $tripStart = Carbon::now()->addDays(60)->startOfDay();
        $tripEnd = Carbon::now()->addDays(69)->startOfDay(); // 10 days
        // Age 66 -> 66+ range = 2.00 multiplier
        $dob = $tripStart->copy()->subYears(66);

        $data = [
            'plan_code' => 'PREMIUM',
            'destination' => 'INTERNATIONAL',
            'date_of_birth' => $dob->format('Y-m-d'),
            'trip_start' => $tripStart->format('Y-m-d'),
            'trip_end' => $tripEnd->format('Y-m-d'),
        ];

        $result = $this->calculator->calculate($data);

        // PREMIUM: 30 × 10 × 2.00 × 1.80 × 1.50 = 1620.00
        $this->assertEquals(10, $result['travel_days']);
        $this->assertEquals(30.00, $result['base_daily_rate']);
        $this->assertEquals(2.00, $result['age_multiplier']);
        $this->assertEquals(1.80, $result['destination_multiplier']);
        $this->assertEquals(1.50, $result['coverage_multiplier']);
        $this->assertEquals(1620.00, $result['calculated_premium']);
    }

    public function test_invalid_travel_dates_rejected(): void
    {
        $tripStart = Carbon::now()->addDays(10)->format('Y-m-d');
        $tripEnd = Carbon::now()->addDays(5)->format('Y-m-d'); // End before start

        $data = [
            'plan_code' => 'PLUS',
            'destination' => 'INTERNATIONAL',
            'date_of_birth' => Carbon::now()->subYears(30)->format('Y-m-d'),
            'trip_start' => $tripStart,
            'trip_end' => $tripEnd,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trip end date must be on or after trip start date');

        $this->calculator->calculate($data);
    }

    public function test_unsupported_plan_rejected(): void
    {
        $data = [
            'plan_code' => 'INVALID_PLAN',
            'destination' => 'INTERNATIONAL',
            'date_of_birth' => Carbon::now()->subYears(30)->format('Y-m-d'),
            'trip_start' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'trip_end' => Carbon::now()->addDays(14)->format('Y-m-d'),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported plan code: INVALID_PLAN');

        $this->calculator->calculate($data);
    }

    public function test_unsupported_destination_rejected(): void
    {
        $data = [
            'plan_code' => 'PLUS',
            'destination' => 'MARS',
            'date_of_birth' => Carbon::now()->subYears(30)->format('Y-m-d'),
            'trip_start' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'trip_end' => Carbon::now()->addDays(14)->format('Y-m-d'),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported destination: MARS');

        $this->calculator->calculate($data);
    }

    public function test_age_multiplier_ranges(): void
    {
        // Test each age range
        $testCases = [
            ['age' => 10, 'expected' => 0.80],   // 0-17
            ['age' => 25, 'expected' => 1.00],   // 18-35
            ['age' => 40, 'expected' => 1.25],   // 36-50
            ['age' => 60, 'expected' => 1.50],   // 51-65
            ['age' => 70, 'expected' => 2.00],   // 66+
        ];

        foreach ($testCases as $case) {
            $tripStart = Carbon::now()->addDays(10);
            $dob = $tripStart->copy()->subYears($case['age']);

            $data = [
                'plan_code' => 'BASIC',
                'destination' => 'DOMESTIC',
                'date_of_birth' => $dob->format('Y-m-d'),
                'trip_start' => $tripStart->format('Y-m-d'),
                'trip_end' => $tripStart->copy()->addDays(5)->format('Y-m-d'),
            ];

            $result = $this->calculator->calculate($data);
            $this->assertEquals($case['expected'], $result['age_multiplier'], "Failed for age {$case['age']}");
        }
    }

    public function test_get_supported_plans(): void
    {
        $plans = $this->calculator->getSupportedPlans();
        $this->assertEquals(['BASIC', 'PLUS', 'PREMIUM'], $plans);
    }

    public function test_get_supported_destinations(): void
    {
        $destinations = $this->calculator->getSupportedDestinations();
        $this->assertEquals(['DOMESTIC', 'INTERNATIONAL'], $destinations);
    }

    public function test_is_valid_plan(): void
    {
        $this->assertTrue($this->calculator->isValidPlan('BASIC'));
        $this->assertTrue($this->calculator->isValidPlan('PLUS'));
        $this->assertTrue($this->calculator->isValidPlan('PREMIUM'));
        $this->assertFalse($this->calculator->isValidPlan('INVALID'));
    }

    public function test_is_valid_destination(): void
    {
        $this->assertTrue($this->calculator->isValidDestination('DOMESTIC'));
        $this->assertTrue($this->calculator->isValidDestination('INTERNATIONAL'));
        $this->assertFalse($this->calculator->isValidDestination('MARS'));
    }
}