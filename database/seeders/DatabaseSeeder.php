<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \DB::table('bot_configurations')->insert([
            ['config_key' => 'DEFAULT_SYMBOL', 'config_value' => 'BTCUSDT', 'description' => 'Default trading pair'],
            ['config_key' => 'DEFAULT_INTERVAL', 'config_value' => '1m', 'description' => 'Default candle interval'],
            ['config_key' => 'DEFAULT_LIMIT', 'config_value' => '1000', 'description' => 'Maximum number of candles to fetch'],
            ['config_key' => 'PREDICTION_THRESHOLD', 'config_value' => '6', 'description' => 'Threshold for prediction logic'],
        ]);
    }
}
