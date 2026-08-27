<?php

namespace App\Filament\Resources\DailyCheckinRewards;

use App\Filament\Resources\DailyCheckinRewards\Pages\CreateDailyCheckinReward;
use App\Filament\Resources\DailyCheckinRewards\Pages\EditDailyCheckinReward;
use App\Filament\Resources\DailyCheckinRewards\Pages\ListDailyCheckinRewards;
use App\Filament\Resources\DailyCheckinRewards\Schemas\DailyCheckinRewardForm;
use App\Filament\Resources\DailyCheckinRewards\Tables\DailyCheckinRewardsTable;
use App\Models\DailyCheckinReward;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DailyCheckinRewardResource extends Resource
{
    protected static ?string $model = DailyCheckinReward::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Gamification';

    public static function form(Schema $schema): Schema
    {
        return DailyCheckinRewardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyCheckinRewardsTable::configure($table);
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
            'index' => ListDailyCheckinRewards::route('/'),
            'create' => CreateDailyCheckinReward::route('/create'),
            'edit' => EditDailyCheckinReward::route('/{record}/edit'),
        ];
    }
}
