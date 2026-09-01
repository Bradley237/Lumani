<?php

namespace App\Filament\Pages;

use App\Models\BusinessSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageBusinessSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Business Settings';

    protected static ?string $slug = 'business-settings';

    protected string $view = 'filament.pages.manage-business-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = BusinessSetting::all()->keyBy('key');

        $formData = [];
        foreach ($settings as $key => $setting) {
            $formData[$key] = $setting->castValue($setting->value);
        }

        $this->getSchema('schema')?->fill($formData);
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quiz Settings')
                    ->description('Tune student experience point earnings from chapter quizzes.')
                    ->components([
                        TextInput::make('quiz_xp_per_correct_answer')
                            ->label('XP Per Correct Answer')
                            ->numeric()
                            ->required()
                            ->helperText('XP awarded per correct question in chapter quizzes.'),
                        TextInput::make('quiz_xp_completion_bonus')
                            ->label('Quiz Completion Bonus XP')
                            ->numeric()
                            ->required()
                            ->helperText('Bonus XP awarded for submitting a completed quiz.'),
                    ])->columns(2),

                Section::make('XP & Coins Economy')
                    ->description('Configure the exchange rate and threshold for converting student XP into coins.')
                    ->components([
                        TextInput::make('xp_to_coins_ratio_xp')
                            ->label('XP Chunk Threshold')
                            ->numeric()
                            ->required()
                            ->helperText('XP threshold chunk required to convert into coins.'),
                        TextInput::make('xp_to_coins_ratio_coins')
                            ->label('Coins Awarded Per Chunk')
                            ->numeric()
                            ->required()
                            ->helperText('Coins awarded per XP conversion threshold chunk.'),
                    ])->columns(2),

                Section::make('Missions & Cooldowns')
                    ->description('Set caps and time windows for rewarded ads, check-in streaks, and referral bonuses.')
                    ->components([
                        TextInput::make('watch_ad_daily_cap')
                            ->label('Watch Ad Daily Cap')
                            ->numeric()
                            ->required()
                            ->helperText('Maximum number of rewarded ads a student can watch per reset window.'),
                        TextInput::make('watch_ad_reset_hours')
                            ->label('Watch Ad Reset Window (Hours)')
                            ->numeric()
                            ->required()
                            ->helperText('Rolling window in hours before watched ad count resets.'),
                        TextInput::make('checkin_reset_hours')
                            ->label('Daily Check-in Cooldown (Hours)')
                            ->numeric()
                            ->required()
                            ->helperText('Cooldown window in hours before a student can claim the next daily check-in.'),
                        TextInput::make('referral_cap_hours')
                            ->label('Referral Reward Cooldown (Hours)')
                            ->numeric()
                            ->required()
                            ->helperText('Rolling window in hours between eligible referral rewards for a referrer.'),
                    ])->columns(2),

                Section::make('Exam Session Time Limits')
                    ->description('Maximum allowed time caps (in minutes) by past paper question composition.')
                    ->components([
                        TextInput::make('exam_time_cap_mcq_minutes')
                            ->label('MCQ Time Cap (Minutes)')
                            ->numeric()
                            ->required()
                            ->helperText('Maximum allowed time in minutes for MCQ-only past paper exam sessions.'),
                        TextInput::make('exam_time_cap_structural_minutes')
                            ->label('Structural Time Cap (Minutes)')
                            ->numeric()
                            ->required()
                            ->helperText('Maximum allowed time in minutes for structural/essay-only exam sessions.'),
                        TextInput::make('exam_time_cap_mixed_minutes')
                            ->label('Mixed Time Cap (Minutes)')
                            ->numeric()
                            ->required()
                            ->helperText('Maximum allowed time in minutes for mixed composition past paper exam sessions.'),
                    ])->columns(3),

                Section::make('Weekly Challenge Rewards')
                    ->description('Score percentage cutoffs and coin rewards for the weekly competitive challenges.')
                    ->components([
                        TextInput::make('challenge_reward_high_threshold_percent')
                            ->label('High Tier Score Threshold (%)')
                            ->numeric()
                            ->step('any')
                            ->required()
                            ->helperText('Score percentage threshold required to earn the highest tier challenge coin reward.'),
                        TextInput::make('challenge_reward_high_coins')
                            ->label('High Tier Coins Awarded')
                            ->numeric()
                            ->required()
                            ->helperText('Coins awarded for achieving the highest weekly challenge tier.'),
                        TextInput::make('challenge_reward_mid_threshold_percent')
                            ->label('Mid Tier Score Threshold (%)')
                            ->numeric()
                            ->step('any')
                            ->required()
                            ->helperText('Score percentage threshold required to earn the mid-tier challenge coin reward.'),
                        TextInput::make('challenge_reward_mid_coins')
                            ->label('Mid Tier Coins Awarded')
                            ->numeric()
                            ->required()
                            ->helperText('Coins awarded for achieving the mid-tier weekly challenge score.'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->getSchema('schema')?->getState() ?? [];

        foreach ($state as $key => $value) {
            $setting = BusinessSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                $setting->save();
            }
        }

        BusinessSetting::flushRuntimeCache();

        Notification::make()
            ->title('Business settings saved successfully.')
            ->success()
            ->send();
    }
}
