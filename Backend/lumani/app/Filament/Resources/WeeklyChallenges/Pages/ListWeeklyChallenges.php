<?php

namespace App\Filament\Resources\WeeklyChallenges\Pages;

use App\Filament\Resources\WeeklyChallenges\WeeklyChallengeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyChallenges extends ListRecords
{
    protected static string $resource = WeeklyChallengeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
