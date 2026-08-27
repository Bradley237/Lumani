<?php

namespace App\Filament\Resources\WeeklyChallenges\Pages;

use App\Filament\Resources\WeeklyChallenges\WeeklyChallengeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWeeklyChallenge extends EditRecord
{
    protected static string $resource = WeeklyChallengeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
