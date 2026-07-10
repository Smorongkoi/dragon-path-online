<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('bot_wins')->default(0)->after('pvp_rating');
            $table->unsignedInteger('bot_losses')->default(0)->after('bot_wins');
            $table->integer('bot_rating')->default(1000)->after('bot_losses');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['bot_wins', 'bot_losses', 'bot_rating']);
        });
    }
};
