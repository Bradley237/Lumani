<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tier')
                    ->options(collect(SubscriptionTier::cases())->mapWithKeys(
                        fn (SubscriptionTier $tier) => [$tier->value => $tier->label()]
                    )->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if ($state === SubscriptionTier::Tier2000->value) {
                            $set('coin_allotment', 500);
                            $set('amount_fcfa', 2000);
                        } elseif ($state === SubscriptionTier::Tier5000->value) {
                            $set('coin_allotment', 1500);
                            $set('amount_fcfa', 5000);
                        }
                    }),
                TextInput::make('coin_allotment')
                    ->required()
                    ->numeric()
                    ->default(500)
                    ->helperText('500 for tier_2000, 1500 for tier_5000'),
                TextInput::make('amount_fcfa')
                    ->required()
                    ->numeric()
                    ->default(2000)
                    ->helperText('Subscription price in FCFA'),
                DateTimePicker::make('start_date')
                    ->required()
                    ->default(now()),
                DateTimePicker::make('end_date')
                    ->required()
                    ->default(now()->addMonth()),
                Select::make('status')
                    ->options(collect(SubscriptionStatus::cases())->mapWithKeys(
                        fn (SubscriptionStatus $status) => [$status->value => $status->label()]
                    )->all())
                    ->default(SubscriptionStatus::Active->value)
                    ->required(),
            ]);
    }
}
