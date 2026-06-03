<?php

namespace Flowtriq\Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class FlowtriqSetting extends Model
{
    protected $table = 'flowtriq_settings';
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, with fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $row = static::find($key);

            return $row ? $row->value : $default;
        } catch (\Exception) {
            return $default;
        }
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get all settings as a key-value array.
     */
    public static function allSettings(): array
    {
        try {
            return static::pluck('value', 'key')->toArray();
        } catch (\Exception) {
            return [];
        }
    }
}
