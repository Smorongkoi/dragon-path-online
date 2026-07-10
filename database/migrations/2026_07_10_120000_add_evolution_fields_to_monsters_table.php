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
        Schema::table('monsters', function (Blueprint $table) {
            $table->string('family_key')->nullable()->after('id')->index();
            $table->unsignedTinyInteger('evolution_stage')->default(0)->after('family_key')->index();
            $table->foreignId('evolves_from_id')->nullable()->after('evolution_stage')->constrained('monsters')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evolves_from_id');
            $table->dropIndex(['family_key']);
            $table->dropIndex(['evolution_stage']);
            $table->dropColumn(['family_key', 'evolution_stage']);
        });
    }
};
