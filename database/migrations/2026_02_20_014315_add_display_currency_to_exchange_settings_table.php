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
        if (! Schema::hasColumn('exchange_settings', 'display_currency')) {
            Schema::table('exchange_settings', function (Blueprint $table) {
                $table->string('display_currency', 3)->default('USD')->after('default_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('exchange_settings', 'display_currency')) {
            Schema::table('exchange_settings', function (Blueprint $table) {
                $table->dropColumn('display_currency');
            });
        }
    }
};
