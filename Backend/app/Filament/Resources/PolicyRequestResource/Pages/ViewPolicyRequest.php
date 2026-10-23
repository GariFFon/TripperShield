<?php

namespace App\Filament\Resources\PolicyRequestResource\Pages;

use App\Filament\Resources\PolicyRequestResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewPolicyRequest extends ViewRecord
{
    protected static string $resource = PolicyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit action - read only
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('client_request_id')
                            ->label('Client Request ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('agent_id')
                            ->label('Agent ID')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('customer_name')
                            ->label('Customer Name'),
                        Infolists\Components\TextEntry::make('date_of_birth')
                            ->label('Date of Birth')
                            ->date(),
                        Infolists\Components\TextEntry::make('destination')
                            ->label('Destination')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'DOMESTIC' => 'success',
                                'INTERNATIONAL' => 'info',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('trip_start')
                            ->label('Trip Start')
                            ->date(),
                        Infolists\Components\TextEntry::make('trip_end')
                            ->label('Trip End')
                            ->date(),
                        Infolists\Components\TextEntry::make('plan_code')
                            ->label('Plan')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'BASIC' => 'gray',
                                'PLUS' => 'info',
                                'PREMIUM' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('sum_insured')
                            ->label('Sum Insured')
                            ->money('INR'),
                    ])->columns(2),

                Infolists\Components\Section::make('Premium Calculation')
                    ->schema([
                        Infolists\Components\TextEntry::make('travel_days')
                            ->label('Travel Days')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('age_at_travel')
                            ->label('Age at Travel')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('base_daily_rate')
                            ->label('Base Daily Rate')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('age_multiplier')
                            ->label('Age Multiplier')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                        Infolists\Components\TextEntry::make('destination_multiplier')
                            ->label('Destination Multiplier')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                        Infolists\Components\TextEntry::make('coverage_multiplier')
                            ->label('Coverage Multiplier')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                        Infolists\Components\TextEntry::make('calculated_premium')
                            ->label('Calculated Premium')
                            ->money('INR')
                            ->weight('bold'),
                    ])->columns(3),

                Infolists\Components\Section::make('Issuance Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Current Status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'RECEIVED' => 'gray',
                                'VALIDATED' => 'info',
                                'SUBMITTED' => 'warning',
                                'PENDING_CONFIRMATION' => 'warning',
                                'ISSUED' => 'success',
                                'FAILED' => 'danger',
                                'NEEDS_RECONCILIATION' => 'purple',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('provider_reference')
                            ->label('Provider Reference')
                            ->placeholder('—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('policy_number')
                            ->label('Policy Number')
                            ->placeholder('—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('attempt_count')
                            ->label('Attempt Count')
                            ->numeric(),
                    ])->columns(2),
            ]);
    }
}