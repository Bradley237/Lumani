<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Full Name'),
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('phone_number')->label('Phone')->placeholder('—'),
                        TextEntry::make('role')
                            ->label('Role')
                            ->formatStateUsing(fn ($state) => $state?->label() ?? '—')
                            ->badge(),
                        TextEntry::make('preferred_language')->label('Language'),
                        TextEntry::make('referral_code')->label('Referral Code')->copyable(),
                        TextEntry::make('created_at')->label('Registered')->dateTime('d M Y, H:i'),
                        TextEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('Not verified'),
                    ]),

                Section::make('Progress & Economy')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('coin_balance')->label('Coin Balance')->numeric(),
                        TextEntry::make('experience_points')->label('Experience Points')->numeric(),
                        TextEntry::make('xp_converted_total')->label('XP Converted (Total)')->numeric(),
                        TextEntry::make('day_streak')->label('Day Streak')->numeric(),
                    ]),

                Section::make('Exam Profile')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('exam_system')
                            ->label('Exam System')
                            ->formatStateUsing(fn ($state) => $state?->name ?? '—')
                            ->badge(),
                        TextEntry::make('level')
                            ->label('Level')
                            ->formatStateUsing(fn ($state) => $state?->name ?? '—')
                            ->badge(),
                        TextEntry::make('exam_date')
                            ->label('Target Exam Date')
                            ->date('d M Y')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
