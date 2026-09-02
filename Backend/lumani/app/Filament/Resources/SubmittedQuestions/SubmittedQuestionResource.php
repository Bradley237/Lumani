<?php

namespace App\Filament\Resources\SubmittedQuestions;

use App\Filament\Resources\SubmittedQuestions\Pages\CreateSubmittedQuestion;
use App\Filament\Resources\SubmittedQuestions\Pages\EditSubmittedQuestion;
use App\Filament\Resources\SubmittedQuestions\Pages\ListSubmittedQuestions;
use App\Filament\Resources\SubmittedQuestions\Schemas\SubmittedQuestionForm;
use App\Filament\Resources\SubmittedQuestions\Tables\SubmittedQuestionsTable;
use App\Models\SubmittedQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SubmittedQuestionResource extends Resource
{
    protected static ?string $model = SubmittedQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Submitted Questions';

    public static function form(Schema $schema): Schema
    {
        return SubmittedQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubmittedQuestionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubmittedQuestions::route('/'),
            'create' => CreateSubmittedQuestion::route('/create'),
            'edit' => EditSubmittedQuestion::route('/{record}/edit'),
        ];
    }
}
