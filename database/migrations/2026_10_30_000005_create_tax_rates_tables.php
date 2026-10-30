<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_tax_rates', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('tax_rate_id', true);
            $table->text('tax_rate_name')->nullable();
            $table->decimal('tax_rate_percent', 5, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_tax_rates');
    }
};
