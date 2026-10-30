<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_clients', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('client_id', true);
            $table->dateTime('client_date_created');
            $table->dateTime('client_date_modified');
            $table->text('client_name')->nullable();
            $table->string('client_company', 150)->nullable();
            $table->text('client_address_1')->nullable();
            $table->text('client_address_2')->nullable();
            $table->text('client_city')->nullable();
            $table->text('client_state')->nullable();
            $table->text('client_zip')->nullable();
            $table->text('client_country')->nullable();
            $table->text('client_phone')->nullable();
            $table->text('client_fax')->nullable();
            $table->text('client_mobile')->nullable();
            $table->text('client_email')->nullable();
            $table->text('client_web')->nullable();
            $table->text('client_vat_id')->nullable();
            $table->text('client_tax_code')->nullable();
            $table->string('client_language', 255)->nullable()->default('system');
            $table->integer('client_active')->default(1);
            $table->string('client_surname', 255)->nullable();
            $table->string('client_invoicing_contact', 50)->nullable();
            $table->string('client_title', 50)->nullable();
            $table->boolean('client_einvoicing_active')->default(0);
            $table->string('client_einvoicing_version', 25)->nullable();
            $table->string('client_avs', 16)->nullable();
            $table->string('client_insurednumber', 30)->nullable();
            $table->string('client_veka', 30)->nullable();
            $table->date('client_birthdate')->nullable();
            $table->integer('client_gender')->nullable()->default(0);
            $table->index(['client_active'], 'client_active');
        });

        Schema::create('ip_client_notes', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('client_note_id', true);
            $table->integer('client_id');
            $table->date('client_note_date');
            $table->longText('client_note');
            $table->index(['client_id', 'client_note_date'], 'client_id');
        });

        Schema::create('ip_client_custom', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->integer('client_custom_id', true);
            $table->integer('client_id');
            $table->integer('client_custom_fieldid');
            $table->text('client_custom_fieldvalue')->nullable();
            $table->unique(['client_id', 'client_custom_fieldid'], 'client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_client_custom');
        Schema::dropIfExists('ip_client_notes');
        Schema::dropIfExists('ip_clients');
    }
};
