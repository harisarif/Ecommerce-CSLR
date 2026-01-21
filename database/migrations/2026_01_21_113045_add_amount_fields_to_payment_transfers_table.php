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
        Schema::table('payment_transfers', function (Blueprint $table) {
            // Checkout (display only)
            $table->integer('checkout_amount_cents'); // AED
            $table->string('checkout_currency', 3);   // AED

            // Stripe balance (REAL money)
            $table->integer('gross_amount_cents');    // USD
            $table->integer('stripe_fee_cents');      // USD
            $table->integer('net_amount_cents');      // USD
            $table->string('settlement_currency', 3); // USD

            $table->decimal('exchange_rate', 10, 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transfers', function (Blueprint $table) {
            //
        });
    }
};
