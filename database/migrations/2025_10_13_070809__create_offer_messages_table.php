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
          Schema::create('offer_messages', function (Blueprint $table) {
            $table->id();
            $table->BigInteger('offer_id')->nullable();
            $table->BigInteger('sender_id');     // user id
            $table->BigInteger('recipient_id');  // user id (other party)
            $table->text('body')->nullable();
            $table->json('meta')->nullable(); // store attachments, type (e.g., counter_offer)
            $table->boolean('is_read')->default(false);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
