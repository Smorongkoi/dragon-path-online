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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('browser_token')->unique();
            $table->string('name')->default('นักผจญภัย');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('class_id')->default('normal');
            $table->unsignedInteger('exp')->default(0);
            $table->unsignedInteger('hp')->default(100);
            $table->unsignedInteger('max_hp')->default(100);
            $table->unsignedInteger('mp')->default(30);
            $table->unsignedInteger('max_mp')->default(30);
            $table->unsignedInteger('atk')->default(10);
            $table->unsignedInteger('def')->default(5);
            $table->json('inventory')->nullable();
            $table->json('class_history')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
