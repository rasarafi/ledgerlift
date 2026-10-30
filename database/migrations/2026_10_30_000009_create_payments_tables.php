<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_payments', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('payment_id', true);
            $table->integer('invoice_id');
            $table->integer('payment_method_id')->default(0);
            $table->date('payment_date');
            $table->decimal('payment_amount', 20, 2)->nullable();
            $table->longText('payment_note');
            $table->index(['invoice_id'], 'invoice_id');
            $table->index(['payment_method_id'], 'payment_method_id');
            $table->index(['payment_amount'], 'payment_amount');
        });

        Schema::create('ip_payment_methods', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('payment_method_id', true);
            $table->text('payment_method_name')->nullable();
        });

        Schema::create('ip_payment_custom', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->integer('payment_custom_id', true);
            $table->integer('payment_id');
            $table->integer('payment_custom_fieldid');
            $table->text('payment_custom_fieldvalue')->nullable();
            $table->unique(['payment_id', 'payment_custom_fieldid'], 'payment_id');
        });

        Schema::create('ip_merchant_responses', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('merchant_response_id', true);
            $table->integer('invoice_id');
            $table->boolean('merchant_response_successful')->nullable()->default(1);
            $table->date('merchant_response_date');
            $table->string('merchant_response_driver', 35);
            $table->string('merchant_response', 255);
            $table->string('merchant_response_reference', 255);
            $table->index(['merchant_response_date'], 'merchant_response_date');
            $table->index(['invoice_id'], 'invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_merchant_responses');
        Schema::dropIfExists('ip_payment_custom');
        Schema::dropIfExists('ip_payment_methods');
        Schema::dropIfExists('ip_payments');
    }
};
