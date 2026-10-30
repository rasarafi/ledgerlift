<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_custom_fields', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('custom_field_id', true);
            $table->string('custom_field_table', 50)->nullable();
            $table->string('custom_field_label', 50)->nullable();
            $table->string('custom_field_type', 255)->default('TEXT');
            $table->integer('custom_field_location')->nullable()->default(0);
            $table->integer('custom_field_order')->nullable()->default(999);
            $table->unique(['custom_field_table', 'custom_field_label'], 'custom_field_table_2');
            $table->index(['custom_field_table'], 'custom_field_table');
        });

        Schema::create('ip_custom_values', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('custom_values_id', true);
            $table->integer('custom_values_field');
            $table->text('custom_values_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_custom_values');
        Schema::dropIfExists('ip_custom_fields');
    }
};
