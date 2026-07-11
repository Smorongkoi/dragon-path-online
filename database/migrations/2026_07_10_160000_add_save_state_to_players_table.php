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
            $table->json('current_encounter')->nullable()->after('pvp_match');
            $table->decimal('world_x', 5, 4)->default(0.3800)->after('current_encounter');
            $table->decimal('world_y', 5, 4)->default(0.5200)->after('world_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['current_encounter', 'world_x', 'world_y']);
        });
    }
};
