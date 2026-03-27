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
            $table->string('trustap_transaction_id')->nullable()->after('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transfers', function (Blueprint $table) {
         $table->dropColumn('trustap_transaction_id');
        });
    }
};
