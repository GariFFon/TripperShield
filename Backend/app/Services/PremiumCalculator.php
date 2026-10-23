<?php

namespace App\Services;

use Carbon\Carbon;

class PremiumCalculator
{
    // Plan configurations
    private const PLAN_CONFIG = [
        'BASIC' => [
            'base_daily_rate' => 15.00,
            'coverage_multiplier' => 1.00,
        ],
        'PLUS' => [
            'base_daily_rate' => 20.00,
            'coverage_multiplier' => 1.25,
        ],
        'PREMIUM' => [
            'base_daily_rate' => 30.00,
            'coverage_multiplier' => 1.50,
        ],
    ];

    // Destination multipliers
    private const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => 1.00,
        'INTERNATIONAL' => 1.80,
    ];

    // Age multipliers (age at travel start)
    private const AGE_MULTIPLIERS = [
        ['min' => 0, 'max' => 17, 'multiplier' => 0.80],
        ['min' => 18, 'max' => 35, 'multiplier' => 1.00],
        ['min' => 36, 'max' => 50, 'multiplier' => 1.25],
        ['min' => 51, 'max' => 65, 'multiplier' => 1.50],
        ['min' => 66, 'max' => 999, 'multiplier' => 2.00],
    ];

    /**
     * Calculate premium for a policy request
     *
     * @param array $data Request data
     * @return array Calculated premium breakdown
     * @throws \InvalidArgumentException
     */
    public function calculate(array $data): array
    {
        $planCode = $data['plan_code'];
        $destination = $data['destination'];
        $dateOfBirth = $data['date_of_birth'];
        $tripStart = $data['trip_start'];
        $tripEnd = $data['trip_end'];

        // Validate plan
        if (!isset(self::PLAN_CONFIG[$planCode])) {
            throw new \InvalidArgumentException("Unsupported plan code: {$planCode}");
        }

        // Validate destination
        if (!isset(self::DESTINATION_MULTIPLIERS[$destination])) {
            throw new \InvalidArgumentException("Unsupported destination: {$destination}");
        }

        // Calculate travel days
        $start = Carbon::parse($tripStart);
        $end = Carbon::parse($tripEnd);

        if ($end->lt($start)) {
            throw new \InvalidArgumentException('Trip end date must be on or after trip start date');
        }

        $travelDays = $start->diffInDays($end) + 1; // Inclusive of both start and end

        // Calculate age at travel
        $dob = Carbon::parse($dateOfBirth);
        $ageAtTravel = (int) $dob->diffInYears($start);

        // Get multipliers
        $planConfig = self::PLAN_CONFIG[$planCode];
        $baseDailyRate = $planConfig['base_daily_rate'];
        $coverageMultiplier = $planConfig['coverage_multiplier'];
        $destinationMultiplier = self::DESTINATION_MULTIPLIERS[$destination];
        $ageMultiplier = $this->getAgeMultiplier($ageAtTravel);

        // Calculate premium: base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier
        $premium = $baseDailyRate
            * $travelDays
            * $ageMultiplier
            * $destinationMultiplier
            * $coverageMultiplier;

        return [
            'travel_days' => $travelDays,
            'age_at_travel' => $ageAtTravel,
            'base_daily_rate' => $baseDailyRate,
            'age_multiplier' => $ageMultiplier,
            'destination_multiplier' => $destinationMultiplier,
            'coverage_multiplier' => $coverageMultiplier,
            'calculated_premium' => round($premium, 2),
        ];
    }

    /**
     * Get age multiplier based on age at travel
     */
    private function getAgeMultiplier(int $age): float
    {
        foreach (self::AGE_MULTIPLIERS as $range) {
            if ($age >= $range['min'] && $age <= $range['max']) {
                return $range['multiplier'];
            }
        }

        // Default to highest multiplier for ages beyond defined ranges
        return 2.00;
    }

    /**
     * Get supported plan codes
     */
    public function getSupportedPlans(): array
    {
        return array_keys(self::PLAN_CONFIG);
    }

    /**
     * Get supported destinations
     */
    public function getSupportedDestinations(): array
    {
        return array_keys(self::DESTINATION_MULTIPLIERS);
    }

    /**
     * Validate plan code
     */
    public function isValidPlan(string $planCode): bool
    {
        return isset(self::PLAN_CONFIG[$planCode]);
    }

    /**
     * Validate destination
     */
    public function isValidDestination(string $destination): bool
    {
        return isset(self::DESTINATION_MULTIPLIERS[$destination]);
    }
}