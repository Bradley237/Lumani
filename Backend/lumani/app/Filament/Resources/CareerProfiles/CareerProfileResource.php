<?php

namespace App\Filament\Resources\CareerProfiles;

use App\Filament\Resources\CareerProfiles\Pages\CreateCareerProfile;
use App\Filament\Resources\CareerProfiles\Pages\EditCareerProfile;
use App\Filament\Resources\CareerProfiles\Pages\ListCareerProfiles;
use App\Filament\Resources\CareerProfiles\Schemas\CareerProfileForm;
use App\Filament\Resources\CareerProfiles\Tables\CareerProfilesTable;
use App\Models\CareerProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CareerProfileResource extends Resource
{
    protected static ?string $model = CareerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Academic Management';

    public static function form(Schema $schema): Schema
    {
        return CareerProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CareerProfilesTable::configure($table);
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
            'index' => ListCareerProfiles::route('/'),
            'create' => CreateCareerProfile::route('/create'),
            'edit' => EditCareerProfile::route('/{record}/edit'),
        ];
    }
}
