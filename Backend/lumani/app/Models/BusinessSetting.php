<?php

namespace App\Models;

use App\Enums\BusinessSettingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property BusinessSettingType $type
 * @property string $group
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BusinessSetting extends Model
{
    use HasFactory;

    /**
     * Request-level in-memory cache to prevent redundant database lookups.
     *
     * @var array<string, mixed>
     */
    protected static array $runtimeCache = [];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BusinessSettingType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (BusinessSetting $setting) {
            static::$runtimeCache[$setting->key] = $setting->castValue($setting->value);
        });

        static::deleted(function (BusinessSetting $setting) {
            unset(static::$runtimeCache[$setting->key]);
        });
    }

    /**
     * Retrieve and type-cast a setting value, with in-memory request-level caching.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$runtimeCache)) {
            return static::$runtimeCache[$key];
        }

        /** @var BusinessSetting|null $setting */
        $setting = static::where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        $value = $setting->castValue($setting->value);
        static::$runtimeCache[$key] = $value;

        return $value;
    }

    /**
     * Helper to set and persist a setting value, updating the in-memory cache.
     */
    public static function set(string $key, mixed $value): static
    {
        /** @var BusinessSetting $setting */
        $setting = static::where('key', $key)->firstOrFail();

        $stringValue = match (true) {
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };

        $setting->value = $stringValue;
        $setting->save();

        static::$runtimeCache[$key] = $setting->castValue($setting->value);

        return $setting;
    }

    /**
     * Flush all cached settings from the request-level runtime cache.
     */
    public static function flushRuntimeCache(): void
    {
        static::$runtimeCache = [];
    }

    /**
     * Type-cast a string value according to this setting's defined type.
     */
    public function castValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            BusinessSettingType::Integer => (int) $value,
            BusinessSettingType::Decimal => (float) $value,
            BusinessSettingType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        };
    }
}
