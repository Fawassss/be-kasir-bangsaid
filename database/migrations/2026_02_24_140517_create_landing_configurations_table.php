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
        Schema::create('landing_configurations', function (Blueprint $table) {
            $table->id();
            // Hero Section
            $table->string('hero_image')->nullable();
            $table->text('hero_description')->nullable();
            
            // About Us Section
            $table->string('about_image_1')->nullable();
            $table->string('about_image_2')->nullable();
            $table->string('about_image_3')->nullable();
            $table->string('about_image_4')->nullable();
            $table->text('about_description')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_configurations');
    }
};
