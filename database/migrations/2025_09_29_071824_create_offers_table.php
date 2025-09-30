<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            // link to product - assumes products table exists
            $table->foreignId('product_id');

            // buyer (user who sent the offer)
            $table->foreignId('buyer_id');

            // seller user id (shop owner)
            $table->foreignId('seller_id');

            $table->decimal('price', 12, 2);
            $table->text('message')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('responded_at')->nullable();

            $table->boolean('buyer_read')->default(false);
            $table->boolean('seller_read')->default(false);

            $table->timestamps();

            // helpful index
            $table->index(['buyer_id','seller_id','product_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
