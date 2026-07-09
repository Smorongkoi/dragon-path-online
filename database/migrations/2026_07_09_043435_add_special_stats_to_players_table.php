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
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedSmallInteger('atk_stat')->default(0)->after('def');
            $table->unsignedSmallInteger('agi')->default(0)->after('atk_stat');
            $table->unsignedSmallInteger('vit')->default(0)->after('agi');
            $table->unsignedSmallInteger('luk')->default(0)->after('vit');
            $table->unsignedSmallInteger('int_stat')->default(0)->after('luk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['atk_stat', 'agi', 'vit', 'luk', 'int_stat']);
        });
    }
};
