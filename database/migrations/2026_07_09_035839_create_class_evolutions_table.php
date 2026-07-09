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
        Schema::create('class_evolutions', function (Blueprint $table) {
            $table->id();
            $table->string('from_class_id');
            $table->string('to_class_id');
            $table->unsignedTinyInteger('required_level');
            $table->unsignedTinyInteger('choice_order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_evolutions');
    }
};
