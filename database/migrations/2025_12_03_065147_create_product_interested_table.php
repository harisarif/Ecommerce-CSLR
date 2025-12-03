<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('product_interested', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_owner_id'); // owner of product
            $table->unsignedBigInteger('viewer_id'); // the user visiting
            $table->unsignedBigInteger('viewer_shop_id'); // the viewer's shop

            $table->timestamps();

            $table->unique(['product_id', 'viewer_id']); // avoid duplicates
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_interested');
    }
};
