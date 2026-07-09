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
        Schema::create('skills', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('class_id');
            $table->string('name');
            $table->unsignedInteger('damage')->default(0);
            $table->unsignedInteger('mana_cost')->default(0);
            $table->unsignedTinyInteger('cooldown')->default(0);
            $table->text('description')->nullable();
            $table->string('animation_key')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
