<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backtest_data', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');              // e.g. BTCUSDT
            $table->string('timeframe');           // e.g. 1h, 5m
            $table->date('from_date');             // range start
            $table->date('to_date');               // range end
            $table->string('file_path');           // storage path
            $table->string('file_name');           // e.g. binance_BTCUSDT_1h.csv
            $table->bigInteger('file_size')->default(0); // in bytes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_data');
    }
};
