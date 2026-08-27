<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $free_mode_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AppSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'free_mode_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'free_mode_enabled' => 'boolean',
        ];
    }

    /**
     * Get the singleton AppSetting record.
     */
    public static function current(): self
    {
        /** @var self $setting */
        $setting = static::firstOrCreate(
            ['id' => 1],
            ['free_mode_enabled' => false]
        );

        return $setting;
    }

    /**
     * Check if free mode is globally enabled.
     */
    public static function isFreeModeEnabled(): bool
    {
        return (bool) static::current()->free_mode_enabled;
    }
}
