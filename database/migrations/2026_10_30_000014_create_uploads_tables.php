<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_uploads', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('upload_id', true);
            $table->integer('client_id');
            $table->char('url_key', 32);
            $table->longText('file_name_original');
            $table->longText('file_name_new');
            $table->date('uploaded_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_uploads');
    }
};
