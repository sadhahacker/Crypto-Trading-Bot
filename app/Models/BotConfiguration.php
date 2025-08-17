<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotConfiguration extends Model
{
    protected $table = 'bot_configurations';

    // Mass assignable fields
    protected $fillable = [
        'config_key',
        'config_value',
        'description',
    ];

    // Optional: Disable timestamps if you don't want them
    // public $timestamps = false;

    /**
     * Get a config value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $config = self::where('config_key', $key)->first();
        return $config ? $config->config_value : $default;
    }

    /**
     * Set a config value by key
     */
    public static function setValue(string $key, $value)
    {
        return self::updateOrCreate(
            ['config_key' => $key],
            ['config_value' => $value]
        );
    }
}
