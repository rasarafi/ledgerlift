<?php

use App\Database\LegacyTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_users', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('user_id', true);
            $table->integer('user_type')->default(0);
            $table->boolean('user_active')->nullable()->default(1);
            $table->dateTime('user_date_created');
            $table->dateTime('user_date_modified');
            $table->string('user_language', 255)->nullable()->default('system');
            $table->text('user_name')->nullable();
            $table->text('user_company')->nullable();
            $table->text('user_address_1')->nullable();
            $table->text('user_address_2')->nullable();
            $table->text('user_city')->nullable();
            $table->text('user_state')->nullable();
            $table->text('user_zip')->nullable();
            $table->text('user_country')->nullable();
            $table->string('user_invoicing_contact', 50)->nullable();
            $table->text('user_phone')->nullable();
            $table->text('user_fax')->nullable();
            $table->text('user_mobile')->nullable();
            $table->text('user_email')->nullable();
            $table->string('user_password', 60);
            $table->text('user_web')->nullable();
            $table->text('user_vat_id')->nullable();
            $table->text('user_tax_code')->nullable();
            $table->text('user_psalt')->nullable();
            $table->integer('user_all_clients')->default(0);
            $table->string('user_passwordreset_token', 100)->nullable()->default('');
            $table->dateTime('user_passwordreset_token_expiry')->nullable();
            $table->string('user_subscribernumber', 40)->nullable();
            $table->string('user_bank', 100)->nullable();
            $table->string('user_iban', 34)->nullable();
            $table->string('user_bic', 11)->nullable();
            $table->string('user_remittance_text', 105)->nullable();
            $table->bigInteger('user_gln')->nullable();
            $table->string('user_rcc', 7)->nullable();
        });

        Schema::create('ip_user_clients', function (Blueprint $table) {
            LegacyTable::myIsam($table);
            $table->integer('user_client_id', true);
            $table->integer('user_id');
            $table->integer('client_id');
            $table->index(['user_id', 'client_id'], 'user_id');
        });

        Schema::create('ip_user_custom', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->integer('user_custom_id', true);
            $table->integer('user_id');
            $table->integer('user_custom_fieldid');
            $table->text('user_custom_fieldvalue')->nullable();
            $table->unique(['user_id', 'user_custom_fieldid'], 'user_id');
        });

        Schema::create('ip_sessions', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->string('id', 128);
            $table->string('ip_address', 45);
            $table->unsignedInteger('timestamp')->default(0);
            $table->binary('data');
            $table->index(['timestamp'], 'ip_sessions_timestamp');
        });

        Schema::create('ip_login_log', function (Blueprint $table) {
            LegacyTable::innoDb($table);
            $table->string('login_name', 100);
            $table->integer('log_count')->nullable()->default(0);
            $table->dateTime('log_create_timestamp')->nullable();
            $table->primary('login_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_login_log');
        Schema::dropIfExists('ip_sessions');
        Schema::dropIfExists('ip_user_custom');
        Schema::dropIfExists('ip_user_clients');
        Schema::dropIfExists('ip_users');
    }
};
