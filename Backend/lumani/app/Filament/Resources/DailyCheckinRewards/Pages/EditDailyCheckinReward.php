<?php

namespace App\Filament\Resources\DailyCheckinRewards\Pages;

use App\Filament\Resources\DailyCheckinRewards\DailyCheckinRewardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyCheckinReward extends EditRecord
{
    protected static string $resource = DailyCheckinRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
