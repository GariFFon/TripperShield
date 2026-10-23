<?php

namespace App\Filament\Resources\PolicyRequestResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StateHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'stateHistory';

    protected static ?string $recordTitleAttribute = 'to_state';

    protected static ?string $title = 'State History (Audit Trail)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('from_state')
                    ->label('From State')
                    ->disabled()
                    ->placeholder('Initial'),
                Forms\Components\TextInput::make('to_state')
                    ->label('To State')
                    ->disabled(),
                Forms\Components\Textarea::make('metadata')
                    ->label('Metadata')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_state')
            ->columns([
                Tables\Columns\TextColumn::make('from_state')
                    ->label('From')
                    ->badge()
                    ->color(fn (?string $state): string => match($state) {
                        'RECEIVED' => 'gray',
                        'VALIDATED' => 'info',
                        'SUBMITTED' => 'warning',
                        'PENDING_CONFIRMATION' => 'warning',
                        'ISSUED' => 'success',
                        'FAILED' => 'danger',
                        'NEEDS_RECONCILIATION' => 'purple',
                        default => 'gray',
                    })
                    ->placeholder('Initial'),
                Tables\Columns\TextColumn::make('to_state')
                    ->label('To')
                    ->badge()
                    ->color(fn (?string $state): string => match($state) {
                        'RECEIVED' => 'gray',
                        'VALIDATED' => 'info',
                        'SUBMITTED' => 'warning',
                        'PENDING_CONFIRMATION' => 'warning',
                        'ISSUED' => 'success',
                        'FAILED' => 'danger',
                        'NEEDS_RECONCILIATION' => 'purple',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Metadata')
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([]);
    }
}