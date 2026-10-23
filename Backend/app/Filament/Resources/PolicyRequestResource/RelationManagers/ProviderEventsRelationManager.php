<?php

namespace App\Filament\Resources\PolicyRequestResource\RelationManagers;

use App\Models\ProviderEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProviderEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'providerEvents';

    protected static ?string $recordTitleAttribute = 'provider_event_id';

    protected static ?string $title = 'Provider Events (Callbacks)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('provider_event_id')
                    ->label('Event ID')
                    ->disabled(),
                Forms\Components\Select::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'ISSUED' => 'Issued',
                        'REJECTED' => 'Rejected',
                        'TIMEOUT' => 'Timeout',
                    ])
                    ->disabled(),
                Forms\Components\Textarea::make('payload')
                    ->label('Payload')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('processed_at')
                    ->label('Processed At')
                    ->disabled()
                    ->placeholder('Not processed'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('provider_event_id')
            ->columns([
                Tables\Columns\TextColumn::make('provider_event_id')
                    ->label('Event ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match($state) {
                        'ISSUED' => 'success',
                        'REJECTED' => 'danger',
                        'TIMEOUT' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('processed_at')
                    ->label('Processed')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([]);
    }
}