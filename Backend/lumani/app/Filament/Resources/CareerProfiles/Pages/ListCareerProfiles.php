<?php

namespace App\Filament\Resources\CareerProfiles\Pages;

use App\Filament\Resources\CareerProfiles\CareerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCareerProfiles extends ListRecords
{
    protected static string $resource = CareerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
