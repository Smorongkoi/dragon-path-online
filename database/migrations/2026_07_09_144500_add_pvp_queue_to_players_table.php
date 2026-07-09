<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->timestamp('pvp_queue_at')->nullable()->after('last_seen_at');
            $table->json('pvp_match')->nullable()->after('pvp_queue_at');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['pvp_queue_at', 'pvp_match']);
        });
    }
};
