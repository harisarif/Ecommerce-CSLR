<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id');
            $table->foreignId('sender_id');
            $table->foreignId('recipient_id');
            $table->decimal('price', 10, 2);
            $table->enum('type', ['offer', 'counter_offer', 'response']); // identify message type
            $table->string('message')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_counters');
    }
};
