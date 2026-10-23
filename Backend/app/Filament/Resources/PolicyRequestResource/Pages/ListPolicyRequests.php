<?php

namespace App\Filament\Resources\PolicyRequestResource\Pages;

use App\Filament\Resources\PolicyRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolicyRequests extends ListRecords
{
    protected static string $resource = PolicyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
