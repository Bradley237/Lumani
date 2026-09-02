<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageAppSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'App Settings';

    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.manage-app-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $setting = AppSetting::current();
        $this->getSchema('schema')?->fill([
            'free_mode_enabled' => (bool) $setting->free_mode_enabled,
        ]);
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Global Overrides')
                    ->description('Configure platform-wide testing and override modes.')
                    ->components([
                        Toggle::make('free_mode_enabled')
                            ->label('Enable Free Mode (Global Override)')
                            ->helperText('When enabled, all chapters and past papers become accessible to every user with zero coin charges, and all users act as having an active subscription.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->getSchema('schema')?->getState() ?? [];
        $setting = AppSetting::current();
        $setting->free_mode_enabled = (bool) ($state['free_mode_enabled'] ?? false);
        $setting->save();

        Notification::make()
            ->title('Settings saved successfully.')
            ->success()
            ->send();
    }
}
