<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_settings', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('setting_id', true);
            $table->string('setting_key', 50);
            $table->longText('setting_value');
            $table->index(['setting_key'], 'setting_key');
        });

        Schema::create('ip_versions', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('version_id', true);
            $table->string('version_date_applied', 14);
            $table->string('version_file', 45);
            $table->integer('version_sql_errors');
            $table->index(['version_date_applied'], 'version_date_applied');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_versions');
        Schema::dropIfExists('ip_settings');
    }
};
