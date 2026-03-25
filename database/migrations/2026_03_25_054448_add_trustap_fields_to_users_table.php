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
        Schema::table('users', function (Blueprint $table) {
            // OAuth (for sellers)
            $table->string('trustap_oauth_user_id')->nullable();
            $table->text('trustap_access_token')->nullable();
            $table->text('trustap_refresh_token')->nullable();
            $table->timestamp('trustap_token_expires_at')->nullable();

            // Guest (for buyers)
            $table->string('trustap_guest_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'trustap_oauth_user_id',
                'trustap_access_token',
                'trustap_refresh_token',
                'trustap_token_expires_at',
                'trustap_guest_user_id',
            ]);
        });
    }
};
