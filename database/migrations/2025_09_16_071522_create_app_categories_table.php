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
        Schema::create('app_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->unsignedBigInteger('tree_id')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->string('parent_tree')->nullable();
            $table->string('title_meta_tag')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('keywords', 500)->nullable();
            $table->mediumInteger('category_order')->default(0);
            $table->mediumInteger('featured_order')->default(1);
            $table->mediumInteger('homepage_order')->default(5);
            $table->boolean('visibility')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_on_main_menu')->default(true);
            $table->boolean('show_image_on_main_menu')->default(false);
            $table->boolean('show_products_on_index')->default(false);
            $table->boolean('show_subcategory_products')->default(false);
            $table->string('storage', 20)->default('local');
            $table->string('image')->nullable();
            $table->boolean('show_description')->default(false);
            $table->timestamps();

            $table->index('parent_id', 'idx_parent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_categories');
    }
};
