<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_quotes', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('quote_id', true);
            $table->integer('invoice_id')->default(0);
            $table->integer('user_id');
            $table->integer('client_id');
            $table->integer('invoice_group_id');
            $table->tinyInteger('quote_status_id')->default(1);
            $table->date('quote_date_created');
            $table->dateTime('quote_date_modified');
            $table->date('quote_date_expires');
            $table->string('quote_number', 100)->nullable();
            $table->decimal('quote_discount_amount', 20, 2)->nullable();
            $table->decimal('quote_discount_percent', 20, 2)->nullable();
            $table->char('quote_url_key', 32);
            $table->string('quote_password', 90)->nullable();
            $table->longText('notes')->nullable();
            $table->index(['user_id', 'client_id', 'invoice_group_id', 'quote_date_created', 'quote_date_expires', 'quote_number'], 'user_id');
            $table->index(['invoice_id'], 'invoice_id');
            $table->index(['quote_status_id'], 'quote_status_id');
        });

        Schema::create('ip_quote_items', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('item_id', true);
            $table->integer('quote_id');
            $table->integer('item_tax_rate_id');
            $table->integer('item_product_id')->nullable();
            $table->date('item_date_added');
            $table->text('item_name')->nullable();
            $table->text('item_description')->nullable();
            $table->decimal('item_quantity', 20, 8)->nullable();
            $table->decimal('item_price', 20, 2)->nullable();
            $table->decimal('item_discount_amount', 20, 2)->nullable();
            $table->integer('item_order')->default(0);
            $table->string('item_product_unit', 50)->nullable();
            $table->integer('item_product_unit_id')->nullable();
            $table->index(['quote_id', 'item_date_added', 'item_order'], 'quote_id');
            $table->index(['item_tax_rate_id'], 'item_tax_rate_id');
        });

        Schema::create('ip_quote_amounts', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('quote_amount_id', true);
            $table->integer('quote_id');
            $table->decimal('quote_item_subtotal', 20, 2)->nullable();
            $table->decimal('quote_item_tax_total', 20, 2)->nullable();
            $table->decimal('quote_tax_total', 20, 2)->nullable();
            $table->decimal('quote_total', 20, 2)->nullable();
            $table->index(['quote_id'], 'quote_id');
        });

        Schema::create('ip_quote_item_amounts', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('item_amount_id', true);
            $table->integer('item_id');
            $table->decimal('item_subtotal', 20, 2)->nullable();
            $table->decimal('item_tax_total', 20, 2)->nullable();
            $table->decimal('item_discount', 20, 2)->nullable();
            $table->decimal('item_total', 20, 2)->nullable();
            $table->index(['item_id'], 'item_id');
        });

        Schema::create('ip_quote_tax_rates', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('quote_tax_rate_id', true);
            $table->integer('quote_id');
            $table->integer('tax_rate_id');
            $table->integer('include_item_tax')->default(0);
            $table->decimal('quote_tax_rate_amount', 20, 2)->nullable();
            $table->index(['quote_id'], 'quote_id');
            $table->index(['tax_rate_id'], 'tax_rate_id');
        });

        Schema::create('ip_quote_custom', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->integer('quote_custom_id', true);
            $table->integer('quote_id');
            $table->integer('quote_custom_fieldid');
            $table->text('quote_custom_fieldvalue')->nullable();
            $table->unique(['quote_id', 'quote_custom_fieldid'], 'quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_quote_custom');
        Schema::dropIfExists('ip_quote_tax_rates');
        Schema::dropIfExists('ip_quote_item_amounts');
        Schema::dropIfExists('ip_quote_amounts');
        Schema::dropIfExists('ip_quote_items');
        Schema::dropIfExists('ip_quotes');
    }
};
