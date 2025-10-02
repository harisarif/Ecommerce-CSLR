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
        Schema::create('shop_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id');
            $table->foreignId('user_id');
            $table->timestamps();

            $table->unique(['shop_id', 'user_id']); // prevent duplicate follows
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_followers');
    }
};
