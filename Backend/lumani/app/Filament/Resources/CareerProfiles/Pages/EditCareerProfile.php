<?php

namespace App\Filament\Resources\CareerProfiles\Pages;

use App\Filament\Resources\CareerProfiles\CareerProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCareerProfile extends EditRecord
{
    protected static string $resource = CareerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
