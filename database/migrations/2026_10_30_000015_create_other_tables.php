<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_imports', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('import_id', true);
            $table->dateTime('import_date');
        });

        Schema::create('ip_import_details', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('import_detail_id', true);
            $table->integer('import_id');
            $table->string('import_lang_key', 35);
            $table->string('import_table_name', 35);
            $table->integer('import_record_id');
            $table->index(['import_id', 'import_record_id'], 'import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_import_details');
        Schema::dropIfExists('ip_imports');
    }
};
