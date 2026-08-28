<?php

namespace App\Filament\Resources\SubmittedQuestions\Pages;

use App\Filament\Resources\SubmittedQuestions\SubmittedQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubmittedQuestions extends ListRecords
{
    protected static string $resource = SubmittedQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
