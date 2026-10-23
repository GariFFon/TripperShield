<?php

namespace App\Filament\Resources\PolicyRequestResource\RelationManagers;

use App\Models\IssuanceAttempt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IssuanceAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'issuanceAttempts';

    protected static ?string $recordTitleAttribute = 'attempt_number';

    protected static ?string $title = 'Issuance Attempts';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('attempt_number')
                    ->label('Attempt #')
                    ->disabled()
                    ->numeric(),
                Forms\Components\Textarea::make('request_payload')
                    ->label('Request Payload')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('response_payload')
                    ->label('Response Payload')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('response_status')
                    ->label('Response Status')
                    ->disabled(),
                Forms\Components\Textarea::make('error_message')
                    ->label('Error Message')
                    ->disabled(),
                Forms\Components\TextInput::make('started_at')
                    ->label('Started At')
                    ->disabled(),
                Forms\Components\TextInput::make('completed_at')
                    ->label('Completed At')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('attempt_number')
            ->columns([
                Tables\Columns\TextColumn::make('attempt_number')
                    ->label('Attempt #')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('Response Status')
                    ->badge()
                    ->colors([
                        'success' => 'SUBMITTED',
                        'warning' => 'PENDING',
                        'danger' => 'FAILED',
                    ])
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('attempt_number', 'desc')
            ->headerActions([])
            ->actions([]);
    }
}