<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->json('settings')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();

            $table->unique('user_id'); // 1 shop per user (vendor)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
