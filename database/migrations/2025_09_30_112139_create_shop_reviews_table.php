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
        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id');
            $table->foreignId('user_id');
            $table->tinyInteger('rating')->comment('1-5 stars');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'user_id']); // one review per user per shop
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_reviews');
    }
};
