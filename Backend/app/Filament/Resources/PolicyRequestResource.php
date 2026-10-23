<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PolicyRequestResource\Pages;
use App\Filament\Resources\PolicyRequestResource\RelationManagers;
use App\Models\PolicyRequest;
use App\Services\MockInsurerService;
use App\Services\ReconciliationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PolicyRequestResource extends Resource
{
    protected static ?string $model = PolicyRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Policy Requests';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->description('Fill in the policy request details below or use the quick fill button.')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('fill_sample_data')
                            ->label('⚡ Fill Sample Data')
                            ->color('success')
                            ->icon('heroicon-m-sparkles')
                            ->action(function (Forms\Set $set) {
                                $today = now();
                                $names = ['Aarav Sharma', 'Priya Patel', 'Rohan Verma', 'Ananya Iyer', 'Vikram Malhotra', 'Sneha Kulkarni'];
                                $randomName = $names[array_rand($names)];
                                $start = (clone $today)->addDays(rand(3, 10));
                                $end = (clone $start)->addDays(rand(4, 12));
                                $plans = ['BASIC', 'PLUS', 'PREMIUM'];
                                $destinations = ['DOMESTIC', 'INTERNATIONAL'];
                                $sums = [250000, 500000, 1000000, 2000000];

                                $set('client_request_id', 'AGT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
                                $set('agent_id', 'AGT-' . rand(101, 999));
                                $set('customer_name', $randomName);
                                $set('date_of_birth', (clone $today)->subYears(rand(24, 52))->format('Y-m-d'));
                                $set('destination', $destinations[array_rand($destinations)]);
                                $set('trip_start', $start->format('Y-m-d'));
                                $set('trip_end', $end->format('Y-m-d'));
                                $set('plan_code', $plans[array_rand($plans)]);
                                $set('sum_insured', $sums[array_rand($sums)]);
                            }),
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('client_request_id')
                            ->label('Client Request ID')
                            ->placeholder('e.g. AGT-2026-0001')
                            ->default(fn () => 'AGT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)))
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('agent_id')
                            ->label('Agent ID')
                            ->placeholder('e.g. AGT-101')
                            ->default('AGT-101')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->placeholder('Full name of traveller')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->required()
                            ->maxDate(now()->subDay())
                            ->live(),
                        Forms\Components\Select::make('destination')
                            ->label('Destination')
                            ->options([
                                'DOMESTIC' => '🇮🇳 Domestic (India)',
                                'INTERNATIONAL' => '🌏 International (1.80×)',
                            ])
                            ->default('DOMESTIC')
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('trip_start')
                            ->label('Trip Start Date')
                            ->required()
                            ->minDate(now()->addDay())
                            ->live(),
                        Forms\Components\DatePicker::make('trip_end')
                            ->label('Trip End Date')
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('plan_code')
                            ->label('Plan Code')
                            ->options([
                                'BASIC' => 'BASIC — ₹15/day (1.00×)',
                                'PLUS' => 'PLUS — ₹20/day (1.25×)',
                                'PREMIUM' => 'PREMIUM — ₹30/day (1.50×)',
                            ])
                            ->default('PLUS')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('sum_insured')
                            ->label('Sum Insured')
                            ->numeric()
                            ->prefix('₹')
                            ->default(500000)
                            ->required()
                            ->minValue(1000)
                            ->maxValue(10000000)
                            ->placeholder('e.g. 500000'),
                    ])->columns(2),

                Forms\Components\Section::make('Live Premium Estimate')
                    ->visible(fn ($record) => $record === null)
                    ->schema([
                        Forms\Components\Placeholder::make('premium_breakdown')
                            ->label('Estimated Premium')
                            ->content(function (Forms\Get $get) {
                                $dob = $get('date_of_birth');
                                $start = $get('trip_start');
                                $end = $get('trip_end');
                                $dest = $get('destination');
                                $plan = $get('plan_code');

                                if (!$dob || !$start || !$end || !$dest || !$plan) {
                                    return '✨ Fill in Dates, Plan, and Destination (or click "⚡ Fill Sample Data" above) to see real-time calculation.';
                                }

                                try {
                                    $calculator = app(\App\Services\PremiumCalculator::class);
                                    $calc = $calculator->calculate([
                                        'date_of_birth' => $dob,
                                        'trip_start' => $start,
                                        'trip_end' => $end,
                                        'destination' => $dest,
                                        'plan_code' => $plan,
                                    ]);

                                    return "₹" . number_format($calc['calculated_premium'], 2) . " [{$calc['travel_days']} days @ ₹{$calc['base_daily_rate']}/day × {$calc['age_multiplier']} age factor (age {$calc['age_at_travel']}) × {$calc['destination_multiplier']} dest factor × {$calc['coverage_multiplier']} plan factor]";
                                } catch (\Throwable $e) {
                                    return '⚠️ ' . $e->getMessage();
                                }
                            }),
                    ]),

                Forms\Components\Section::make('Premium Calculation')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\TextInput::make('travel_days')
                            ->label('Travel Days')
                            ->disabled()
                            ->numeric(),
                        Forms\Components\TextInput::make('age_at_travel')
                            ->label('Age at Travel')
                            ->disabled()
                            ->numeric(),
                        Forms\Components\TextInput::make('base_daily_rate')
                            ->label('Base Daily Rate')
                            ->disabled()
                            ->numeric()
                            ->prefix('₹'),
                        Forms\Components\TextInput::make('age_multiplier')
                            ->label('Age Multiplier')
                            ->disabled()
                            ->numeric()
                            ->step(0.01),
                        Forms\Components\TextInput::make('destination_multiplier')
                            ->label('Destination Multiplier')
                            ->disabled()
                            ->numeric()
                            ->step(0.01),
                        Forms\Components\TextInput::make('coverage_multiplier')
                            ->label('Coverage Multiplier')
                            ->disabled()
                            ->numeric()
                            ->step(0.01),
                        Forms\Components\TextInput::make('calculated_premium')
                            ->label('Calculated Premium')
                            ->disabled()
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01),
                    ])->columns(3),

                Forms\Components\Section::make('Issuance Status')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'RECEIVED' => 'Received',
                                'VALIDATED' => 'Validated',
                                'SUBMITTED' => 'Submitted',
                                'PENDING_CONFIRMATION' => 'Pending Confirmation',
                                'ISSUED' => 'Issued',
                                'FAILED' => 'Failed',
                                'NEEDS_RECONCILIATION' => 'Needs Reconciliation',
                                default => $state,
                            }),
                        Forms\Components\TextInput::make('provider_reference')
                            ->label('Provider Reference')
                            ->disabled(),
                        Forms\Components\TextInput::make('policy_number')
                            ->label('Policy Number')
                            ->disabled(),
                        Forms\Components\TextInput::make('attempt_count')
                            ->label('Attempt Count')
                            ->disabled()
                            ->numeric(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_request_id')
                    ->label('Client Request ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('agent_id')
                    ->label('Agent ID')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'RECEIVED' => 'gray',
                        'VALIDATED' => 'info',
                        'SUBMITTED' => 'warning',
                        'PENDING_CONFIRMATION' => 'warning',
                        'ISSUED' => 'success',
                        'FAILED' => 'danger',
                        'NEEDS_RECONCILIATION' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('destination')
                    ->label('Destination')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'DOMESTIC' => 'success',
                        'INTERNATIONAL' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('plan_code')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'BASIC' => 'gray',
                        'PLUS' => 'info',
                        'PREMIUM' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('calculated_premium')
                    ->label('Premium')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('policy_number')
                    ->label('Policy Number')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),
                Tables\Columns\TextColumn::make('provider_reference')
                    ->label('Provider Ref')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'RECEIVED' => 'Received',
                        'VALIDATED' => 'Validated',
                        'SUBMITTED' => 'Submitted',
                        'PENDING_CONFIRMATION' => 'Pending Confirmation',
                        'ISSUED' => 'Issued',
                        'FAILED' => 'Failed',
                        'NEEDS_RECONCILIATION' => 'Needs Reconciliation',
                    ]),
                Tables\Filters\SelectFilter::make('destination')
                    ->label('Destination')
                    ->options([
                        'DOMESTIC' => 'Domestic',
                        'INTERNATIONAL' => 'International',
                    ]),
                Tables\Filters\SelectFilter::make('plan_code')
                    ->label('Plan')
                    ->options([
                        'BASIC' => 'Basic',
                        'PLUS' => 'Plus',
                        'PREMIUM' => 'Premium',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('Created Date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details')
                    ->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('retry_submission')
                    ->label('Retry Submission')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (PolicyRequest $record) => in_array($record->status, ['FAILED', 'NEEDS_RECONCILIATION']))
                    ->requiresConfirmation()
                    ->modalHeading('Retry Submission')
                    ->modalDescription('This will resubmit the policy request to the mock insurer. Are you sure?')
                    ->action(function (PolicyRequest $record) {
                        $mockInsurer = app(MockInsurerService::class);

                        // Reset to SUBMITTED state
                        $record->transitionTo('SUBMITTED', [
                            'reason' => 'Manual retry from admin panel',
                        ]);

                        // Submit to mock insurer
                        $result = $mockInsurer->submit($record, 'success');

                        // Record attempt
                        $attemptNumber = $record->issuanceAttempts()->count() + 1;
                        $record->issuanceAttempts()->create([
                            'attempt_number' => $attemptNumber,
                            'request_payload' => $record->toArray(),
                            'response_payload' => $result,
                            'response_status' => $result['status'],
                            'started_at' => now(),
                            'completed_at' => now(),
                        ]);

                        $record->increment('attempt_count');
                        $record->transitionTo('PENDING_CONFIRMATION', [
                            'provider_reference' => $result['provider_reference'],
                            'scenario' => 'success',
                        ]);

                        Notification::make()
                            ->title('Submission retried successfully')
                            ->body('Provider reference: ' . $result['provider_reference'])
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('trigger_reconciliation')
                    ->label('Reconcile')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (PolicyRequest $record) => $record->status === 'NEEDS_RECONCILIATION' || $record->status === 'PENDING_CONFIRMATION')
                    ->requiresConfirmation()
                    ->action(function (PolicyRequest $record) {
                        $reconciliationService = app(ReconciliationService::class);
                        $result = $reconciliationService->forceReconcile($record->provider_reference);

                        Notification::make()
                            ->title($result['success'] ? 'Reconciliation complete' : 'Reconciliation failed')
                            ->body($result['message'])
                            ->{$result['success'] ? 'success' : 'danger'}()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\Action::make('bulk_reconcile')
                        ->label('Trigger Reconciliation')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records) {
                            $reconciliationService = app(ReconciliationService::class);
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->provider_reference) {
                                    $reconciliationService->forceReconcile($record->provider_reference);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk reconciliation complete')
                                ->body("Reconciled {$count} requests")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StateHistoryRelationManager::class,
            RelationManagers\IssuanceAttemptsRelationManager::class,
            RelationManagers\ProviderEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPolicyRequests::route('/'),
            'create' => Pages\CreatePolicyRequest::route('/create'),
            'view' => Pages\ViewPolicyRequest::route('/{record}'),
        ];
    }
}