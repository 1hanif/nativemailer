<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * Read a setting, falling back to $default. Never throws — the
     * SMTP catcher must boot even if the table doesn't exist yet.
     */
    public static function get(string $key, $default = null)
    {
        try {
            return static::query()->find($key)?->value ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public static function set(string $key, $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
