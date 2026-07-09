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
        Schema::create('character_classes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->unsignedTinyInteger('milestone_level');
            $table->integer('hp_bonus')->default(0);
            $table->integer('mp_bonus')->default(0);
            $table->integer('atk_bonus')->default(0);
            $table->integer('def_bonus')->default(0);
            $table->string('ability_name')->nullable();
            $table->text('ability_description')->nullable();
            $table->string('sprite_key')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_classes');
    }
};
