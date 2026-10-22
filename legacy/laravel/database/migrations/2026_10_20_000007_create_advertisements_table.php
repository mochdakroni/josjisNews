<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('sponsor_name', 120)->nullable();
            $table->string('position', 50)->index(); // leaderboard_top, infeed_home, sidebar_home, sidebar_article
            $table->string('ad_type', 20)->default('image'); // image, html
            $table->string('image_url', 2048)->nullable();
            $table->string('target_url', 2048)->nullable();
            $table->text('html_code')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
