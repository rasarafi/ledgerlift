<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_invoices_recurring', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('invoice_recurring_id', true);
            $table->integer('invoice_id');
            $table->date('recur_start_date');
            $table->date('recur_end_date')->nullable();
            // Upgrade 027_1.5.4.sql changed the column with an explicit CHARACTER SET utf8, which SHOW CREATE TABLE keeps.
            $table->string('recur_frequency', 255)->charset('utf8mb3')->collation('utf8mb3_general_ci');
            $table->date('recur_next_date')->nullable();
            $table->index(['invoice_id'], 'invoice_id');
        });

        Schema::create('ip_invoice_sumex', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('sumex_id', true);
            $table->integer('sumex_invoice');
            $table->integer('sumex_reason');
            $table->string('sumex_diagnosis', 500);
            $table->string('sumex_observations', 500);
            $table->date('sumex_treatmentstart');
            $table->date('sumex_treatmentend');
            $table->date('sumex_casedate');
            $table->string('sumex_casenumber', 35)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_invoice_sumex');
        Schema::dropIfExists('ip_invoices_recurring');
    }
};
