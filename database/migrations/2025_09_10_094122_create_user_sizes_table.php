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
        Schema::create('user_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('category'); // e.g. tops, pants, shoes
            $table->string('size');     // e.g. M, L, XL, 42, 44
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sizes');
    }
};
