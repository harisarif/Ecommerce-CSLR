<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentTransfers extends Migration
{
    public function up()
    {
        Schema::create('payment_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('stripe_transfer_id')->nullable();
            $table->integer('amount_cents')->default(0);
            $table->integer('platform_fee_cents')->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('status')->default('pending'); // pending, succeeded, failed
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_transfers');
    }
}
