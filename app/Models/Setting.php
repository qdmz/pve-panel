<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string $group
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    public static function getValue(string $key, $default = null)
    {
        $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        return $setting->castValue();
    }

    public static function setValue(string $key, mixed $value, ?string $type = null): void
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            static::create([
                'key' => $key,
                'value' => (string) $value,
                'type' => $type ?? 'string',
            ]);
        } else {
            $setting->update([
                'value' => (string) $value,
                'type' => $type ?? $setting->type,
            ]);
        }

        Cache::forget("setting.{$key}");
    }

    public function castValue()
    {
        switch ($this->type) {
            case 'integer':
                return (int) $this->value;
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($this->value, true);
            default:
                return $this->value;
        }
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Get all settings for a group as key=>value pairs.
     */
    public static function getByGroup(string $group): array
    {
        return Cache::remember("settings.group.{$group}", 3600, function () use ($group) {
            return static::where('group', $group)
                ->get()
                ->mapWithKeys(fn ($s) => [$s->key => $s->castValue()])
                ->toArray();
        });
    }

    /**
     * Clear cache after settings update.
     */
    public static function booted(): void
    {
        static::saved(fn ($s) => Cache::forget("setting.{$s->key}"));
        static::saved(fn ($s) => Cache::forget("settings.group.{$s->group}"));
        static::deleted(fn ($s) => Cache::forget("setting.{$s->key}"));
        static::deleted(fn ($s) => Cache::forget("settings.group.{$s->group}"));
    }
}
