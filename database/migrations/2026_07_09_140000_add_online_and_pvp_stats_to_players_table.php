<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('pvp_wins')->default(0)->after('int_stat');
            $table->unsignedInteger('pvp_losses')->default(0)->after('pvp_wins');
            $table->integer('pvp_rating')->default(1000)->after('pvp_losses');
            $table->timestamp('last_seen_at')->nullable()->after('pvp_rating');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['pvp_wins', 'pvp_losses', 'pvp_rating', 'last_seen_at']);
        });
    }
};
