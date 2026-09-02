<?php

namespace App\Filament\Resources\WeeklyChallenges;

use App\Filament\Resources\WeeklyChallenges\Pages\CreateWeeklyChallenge;
use App\Filament\Resources\WeeklyChallenges\Pages\EditWeeklyChallenge;
use App\Filament\Resources\WeeklyChallenges\Pages\ListWeeklyChallenges;
use App\Filament\Resources\WeeklyChallenges\Schemas\WeeklyChallengeForm;
use App\Filament\Resources\WeeklyChallenges\Tables\WeeklyChallengesTable;
use App\Models\WeeklyChallenge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WeeklyChallengeResource extends Resource
{
    protected static ?string $model = WeeklyChallenge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return WeeklyChallengeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WeeklyChallengesTable::configure($table);
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
            'index' => ListWeeklyChallenges::route('/'),
            'create' => CreateWeeklyChallenge::route('/create'),
            'edit' => EditWeeklyChallenge::route('/{record}/edit'),
        ];
    }
}
