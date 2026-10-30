<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_projects', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('project_id', true);
            $table->integer('client_id');
            $table->text('project_name')->nullable();
        });

        Schema::create('ip_tasks', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('task_id', true);
            $table->integer('project_id');
            $table->text('task_name')->nullable();
            $table->longText('task_description');
            $table->decimal('task_price', 20, 2)->nullable();
            $table->date('task_finish_date');
            $table->boolean('task_status');
            $table->integer('tax_rate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_tasks');
        Schema::dropIfExists('ip_projects');
    }
};
