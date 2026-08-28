<?php

namespace App\Filament\Resources\SubmittedQuestions\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Resources\SubmittedQuestions\SubmittedQuestionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSubmittedQuestion extends CreateRecord
{
    protected static string $resource = SubmittedQuestionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_by'] = $data['submitted_by'] ?? Auth::id();
        $data['review_status'] = ReviewStatus::Pending->value;

        return $data;
    }
}
