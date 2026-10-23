<?php

namespace App\Filament\Resources\PolicyRequestResource\Pages;

use App\Filament\Resources\PolicyRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePolicyRequest extends CreateRecord
{
    protected static string $resource = PolicyRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $calculator = app(\App\Services\PremiumCalculator::class);
        $calc = $calculator->calculate($data);

        return array_merge($data, [
            'travel_days' => $calc['travel_days'],
            'age_at_travel' => $calc['age_at_travel'],
            'base_daily_rate' => $calc['base_daily_rate'],
            'age_multiplier' => $calc['age_multiplier'],
            'destination_multiplier' => $calc['destination_multiplier'],
            'coverage_multiplier' => $calc['coverage_multiplier'],
            'calculated_premium' => $calc['calculated_premium'],
            'status' => 'RECEIVED',
            'attempt_count' => 0,
        ]);
    }

    protected function afterCreate(): void
    {
        $this->record->stateHistory()->create([
            'from_state' => null,
            'to_state' => 'RECEIVED',
            'metadata' => [
                'source' => 'filament_admin',
                'calculated_premium' => $this->record->calculated_premium,
            ],
        ]);
    }
}
