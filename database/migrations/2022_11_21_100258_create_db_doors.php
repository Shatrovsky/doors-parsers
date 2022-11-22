<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDbDoors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->integer('parent_id')->nullable();
            $table->integer('lft')->nullable();
            $table->integer('rgt')->nullable();
            $table->timestamps();
        });

        Schema::create('glasses', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->timestamps();
        });

        Schema::create('trademarks', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->text('url')->nullable();
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->integer('attribute_id');
            $table->text('title')->nullable();
            $table->integer('is_generation_hidden')->nullable();
            $table->text('generation_title')->nullable();
            $table->text('description')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
        });

        Schema::create('accessory_groups', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->timestamps();
        });

        Schema::create('accessories', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->integer('accessory_group_id')->nullable();
            $table->decimal('quantity')->nullable();
            $table->integer('price')->nullable();
            $table->integer('price_dealer')->nullable();
            $table->integer('discount')->nullable();
            $table->integer('discount_dealer')->nullable();
            $table->text('label')->nullable();
            $table->text('vendor_code')->nullable();
            $table->jsonb('pictures')->nullable();
            $table->timestamps();
        });

        Schema::create('color_groups', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->integer('id')->unique()->nullable();
            $table->integer('color_group_id')->nullable();
            $table->text('title')->nullable();
            $table->text('picture')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->text('url')->nullable();
            $table->integer('category_id')->nullable();
            $table->integer('color_id')->nullable();
            $table->integer('glass_id')->nullable();
            $table->integer('accessory_group_id')->nullable();
            $table->integer('trademark_id')->nullable();
            $table->integer('price')->nullable();
            $table->integer('price_dealer')->nullable();
            $table->integer('discount')->nullable();
            $table->integer('discount_dealer')->nullable();
            $table->text('label')->nullable();
            $table->text('vendor_code')->nullable();
            $table->integer('position')->nullable();
            $table->jsonb('pictures')->nullable();
            $table->jsonb('options')->nullable();
            $table->jsonb('properties')->nullable();
            $table->jsonb('accessory_properties')->nullable();
            $table->jsonb('analogs')->nullable();
            $table->jsonb('related_products')->nullable();
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->text('title')->nullable();
            $table->integer('position')->nullable();
            $table->boolean('is_accessory')->default(false);
            $table->timestamps();
        });

        Schema::create('property_values', function (Blueprint $table) {
            $table->integer('id')->unique();
            $table->integer('property_id')->nullable();
            $table->integer('product_id')->nullable();
            $table->text('title')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('property_values');
        Schema::drop('properties');
        Schema::drop('products');
        Schema::drop('colors');
        Schema::drop('color_groups');
        Schema::drop('accessories');
        Schema::drop('accessory_groups');
        Schema::drop('attribute_values');
        Schema::drop('attributes');
        Schema::drop('trademarks');
        Schema::drop('glasses');
        Schema::drop('categories');
    }
}
