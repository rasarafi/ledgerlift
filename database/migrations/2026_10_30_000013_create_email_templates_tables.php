<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_email_templates', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('email_template_id', true);
            $table->text('email_template_title')->nullable();
            $table->string('email_template_type', 255)->nullable();
            $table->longText('email_template_body');
            $table->text('email_template_subject')->nullable();
            $table->text('email_template_from_name')->nullable();
            $table->text('email_template_from_email')->nullable();
            $table->text('email_template_cc')->nullable();
            $table->text('email_template_bcc')->nullable();
            $table->string('email_template_pdf_template', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_email_templates');
    }
};
