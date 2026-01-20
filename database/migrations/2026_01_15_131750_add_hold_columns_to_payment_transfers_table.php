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

            // Stripe objects
            $table->string('payment_intent_id')->nullable()->after('order_id');
            $table->string('charge_id')->nullable()->after('payment_intent_id');

            // Hold & release
            $table->timestamp('release_at')->nullable()->after('currency');

            // Status upgrade
            $table->enum('status', [
                'on_hold',
                'released',
                'refunded',
                'blocked',
                'failed'
            ])->default('on_hold')->change();
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
