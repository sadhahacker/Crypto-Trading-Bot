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
        Schema::create('exchange_settings', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_name')->default('binance');
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->decimal('stoploss_from_account_balance', 5, 2)->default(0.23);
            $table->decimal('takeprofit_from_account_balance', 5, 2)->default(0.30);
            $table->decimal('stoploss_from_coin', 5, 3)->default(0.03);
            $table->decimal('takeprofit_from_coin', 5, 3)->default(0.023);
            $table->string('default_type')->default('future');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_settings');
    }
};
