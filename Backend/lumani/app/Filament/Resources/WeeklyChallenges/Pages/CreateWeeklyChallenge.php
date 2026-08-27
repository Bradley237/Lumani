<?php

namespace App\Filament\Resources\WeeklyChallenges\Pages;

use App\Filament\Resources\WeeklyChallenges\WeeklyChallengeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWeeklyChallenge extends CreateRecord
{
    protected static string $resource = WeeklyChallengeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
