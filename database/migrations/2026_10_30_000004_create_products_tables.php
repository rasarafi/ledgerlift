<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_products', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('product_id', true);
            $table->integer('family_id')->nullable();
            $table->text('product_sku')->nullable();
            $table->text('product_name')->nullable();
            $table->longText('product_description');
            $table->decimal('product_price', 20, 2)->nullable();
            $table->decimal('purchase_price', 20, 2)->nullable();
            $table->text('provider_name')->nullable();
            $table->integer('tax_rate_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->integer('product_tariff')->nullable();
        });

        Schema::create('ip_families', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('family_id', true);
            $table->text('family_name')->nullable();
        });

        Schema::create('ip_units', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('unit_id', true);
            $table->string('unit_name', 50)->nullable();
            $table->string('unit_name_plrl', 50)->nullable();
        });

        Schema::create('ip_item_lookups', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('item_lookup_id', true);
            $table->string('item_name', 100)->default('');
            $table->longText('item_description');
            $table->decimal('item_price', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_item_lookups');
        Schema::dropIfExists('ip_units');
        Schema::dropIfExists('ip_families');
        Schema::dropIfExists('ip_products');
    }
};
