<?php

namespace App\Filament\Resources\PolicyRequestResource\Pages;

use App\Filament\Resources\PolicyRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolicyRequest extends EditRecord
{
    protected static string $resource = PolicyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
