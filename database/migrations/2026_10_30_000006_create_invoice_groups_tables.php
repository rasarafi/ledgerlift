<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_invoice_groups', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('invoice_group_id', true);
            $table->text('invoice_group_name')->nullable();
            $table->string('invoice_group_identifier_format', 255);
            $table->integer('invoice_group_next_id');
            $table->integer('invoice_group_left_pad')->default(0);
            $table->index(['invoice_group_next_id'], 'invoice_group_next_id');
            $table->index(['invoice_group_left_pad'], 'invoice_group_left_pad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_invoice_groups');
    }
};
