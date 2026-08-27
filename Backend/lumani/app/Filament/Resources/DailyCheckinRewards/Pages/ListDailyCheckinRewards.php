<?php

namespace App\Filament\Resources\DailyCheckinRewards\Pages;

use App\Filament\Resources\DailyCheckinRewards\DailyCheckinRewardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyCheckinRewards extends ListRecords
{
    protected static string $resource = DailyCheckinRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
