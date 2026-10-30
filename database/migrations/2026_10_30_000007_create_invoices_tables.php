<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_invoices', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('invoice_id', true);
            $table->integer('user_id');
            $table->integer('client_id');
            $table->integer('invoice_group_id');
            $table->tinyInteger('invoice_status_id')->default(1);
            $table->boolean('is_read_only')->nullable();
            $table->string('invoice_password', 90)->nullable();
            $table->date('invoice_date_created');
            $table->time('invoice_time_created')->default('00:00:00');
            $table->dateTime('invoice_date_modified');
            $table->date('invoice_date_due');
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('invoice_discount_amount', 20, 2)->nullable();
            $table->decimal('invoice_discount_percent', 20, 2)->nullable();
            $table->longText('invoice_terms');
            $table->char('invoice_url_key', 32);
            $table->integer('payment_method')->default(0);
            $table->integer('creditinvoice_parent_id')->nullable();
            $table->unique(['invoice_url_key'], 'invoice_url_key');
            $table->index(['user_id', 'client_id', 'invoice_group_id', 'invoice_date_created', 'invoice_date_due', 'invoice_number'], 'user_id');
            $table->index(['invoice_status_id'], 'invoice_status_id');
        });

        Schema::create('ip_invoice_items', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('item_id', true);
            $table->integer('invoice_id');
            $table->integer('item_tax_rate_id')->default(0);
            $table->integer('item_product_id')->nullable();
            $table->date('item_date_added');
            $table->integer('item_task_id')->nullable();
            $table->text('item_name')->nullable();
            $table->longText('item_description')->nullable();
            $table->decimal('item_quantity', 20, 8)->nullable();
            $table->decimal('item_price', 20, 2)->nullable();
            $table->decimal('item_discount_amount', 20, 2)->nullable();
            $table->integer('item_order')->default(0);
            $table->boolean('item_is_recurring')->nullable();
            $table->string('item_product_unit', 50)->nullable();
            $table->integer('item_product_unit_id')->nullable();
            $table->date('item_date')->nullable();
            $table->index(['invoice_id', 'item_tax_rate_id', 'item_date_added', 'item_order'], 'invoice_id');
        });

        Schema::create('ip_invoice_amounts', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('invoice_amount_id', true);
            $table->integer('invoice_id');
            $table->enum('invoice_sign', ['1', '-1'])->default('1');
            $table->decimal('invoice_item_subtotal', 20, 2)->nullable();
            $table->decimal('invoice_item_tax_total', 20, 2)->nullable();
            $table->decimal('invoice_tax_total', 20, 2)->nullable();
            $table->decimal('invoice_total', 20, 2)->nullable();
            $table->decimal('invoice_paid', 20, 2)->nullable();
            $table->decimal('invoice_balance', 20, 2)->nullable();
            $table->index(['invoice_id'], 'invoice_id');
            $table->index(['invoice_paid', 'invoice_balance'], 'invoice_paid');
        });

        Schema::create('ip_invoice_item_amounts', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('item_amount_id', true);
            $table->integer('item_id');
            $table->decimal('item_subtotal', 20, 2)->nullable();
            $table->decimal('item_tax_total', 20, 2)->nullable();
            $table->decimal('item_discount', 20, 2)->nullable();
            $table->decimal('item_total', 20, 2)->nullable();
            $table->index(['item_id'], 'item_id');
        });

        Schema::create('ip_invoice_tax_rates', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('invoice_tax_rate_id', true);
            $table->integer('invoice_id');
            $table->integer('tax_rate_id');
            $table->integer('include_item_tax')->default(0);
            $table->decimal('invoice_tax_rate_amount', 10, 2)->default('0.00');
            $table->index(['invoice_id', 'tax_rate_id'], 'invoice_id');
        });

        Schema::create('ip_invoice_custom', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->integer('invoice_custom_id', true);
            $table->integer('invoice_id');
            $table->integer('invoice_custom_fieldid');
            $table->text('invoice_custom_fieldvalue')->nullable();
            $table->unique(['invoice_id', 'invoice_custom_fieldid'], 'invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_invoice_custom');
        Schema::dropIfExists('ip_invoice_tax_rates');
        Schema::dropIfExists('ip_invoice_item_amounts');
        Schema::dropIfExists('ip_invoice_amounts');
        Schema::dropIfExists('ip_invoice_items');
        Schema::dropIfExists('ip_invoices');
    }
};
