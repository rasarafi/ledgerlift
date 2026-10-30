<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The rows a fresh InvoicePlane v1.7.2 install leaves behind (Mdl_setup::install_default_data,
 * install_default_settings and save_version, as they stand after the upgrade chain).
 * Ids are the legacy ones: the settings start at 19 because upgrades deleted and re-created
 * rows, and the invoice groups start at 3 for the same reason. Runs after every table migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ip_invoice_groups')->insert([
            ['invoice_group_id' => 3, 'invoice_group_name' => 'Invoice Default', 'invoice_group_identifier_format' => '{{{id}}}', 'invoice_group_next_id' => 1, 'invoice_group_left_pad' => 0],
            ['invoice_group_id' => 4, 'invoice_group_name' => 'Quote Default', 'invoice_group_identifier_format' => 'QUO{{{id}}}', 'invoice_group_next_id' => 1, 'invoice_group_left_pad' => 0],
        ]);

        DB::table('ip_payment_methods')->insert([
            ['payment_method_id' => 1, 'payment_method_name' => 'Cash'],
            ['payment_method_id' => 2, 'payment_method_name' => 'Credit Card'],
        ]);

        $settings = [
            [19, 'default_language', 'english'],
            [20, 'date_format', 'm/d/Y'],
            [21, 'currency_symbol', '$'],
            [22, 'currency_symbol_placement', 'before'],
            [23, 'currency_code', 'USD'],
            [24, 'invoices_due_after', '30'],
            [25, 'quotes_expire_after', '15'],
            [26, 'default_invoice_group', '3'],
            [27, 'default_quote_group', '4'],
            [28, 'thousands_separator', ','],
            [29, 'decimal_point', '.'],
            [30, 'cron_key', bin2hex(random_bytes(8))],
            [31, 'tax_rate_decimal_places', '2'],
            [32, 'pdf_invoice_template', 'InvoicePlane'],
            [33, 'pdf_invoice_template_paid', 'InvoicePlane - paid'],
            [34, 'pdf_invoice_template_overdue', 'InvoicePlane - overdue'],
            [35, 'pdf_quote_template', 'InvoicePlane'],
            [36, 'public_invoice_template', 'InvoicePlane_Web'],
            [37, 'public_quote_template', 'InvoicePlane_Web'],
            [38, 'disable_sidebar', '1'],
            [39, 'read_only_toggle', '4'],
            [40, 'invoice_pre_password', ''],
            [41, 'quote_pre_password', ''],
            [42, 'email_pdf_attachment', '1'],
            [43, 'generate_invoice_number_for_draft', '1'],
            [44, 'generate_quote_number_for_draft', '1'],
            [45, 'sumex', '0'],
            [46, 'sumex_sliptype', '1'],
            [47, 'sumex_canton', '0'],
            [48, 'system_theme', 'invoiceplane'],
            [49, 'default_hourly_rate', '0.00'],
            [50, 'projects_enabled', '1'],
            [51, 'pdf_quote_footer', ''],
            [52, 'default_item_decimals', '2'],
        ];

        DB::table('ip_settings')->insert(array_map(
            fn (array $row) => ['setting_id' => $row[0], 'setting_key' => $row[1], 'setting_value' => $row[2]],
            $settings,
        ));

        $versionFiles = [
            '000_1.0.0.sql',
            '001_1.0.1.sql',
            '002_1.0.2.sql',
            '003_1.1.0.sql',
            '004_1.1.1.sql',
            '005_1.1.2.sql',
            '006_1.2.0.sql',
            '007_1.2.1.sql',
            '008_1.3.0.sql',
            '009_1.3.1.sql',
            '010_1.3.2.sql',
            '011_1.3.3.sql',
            '012_1.4.0.sql',
            '013_1.4.1.sql',
            '014_1.4.2.sql',
            '015_1.4.3.sql',
            '016_1.4.4.sql',
            '017_1.4.5.sql',
            '018_1.4.6.sql',
            '019_1.4.7.sql',
            '020_1.4.8.sql',
            '021_1.4.9.sql',
            '022_1.4.10.sql',
            '023_1.5.0.sql',
            '024_1.5.1.sql',
            '025_1.5.2.sql',
            '026_1.5.3.sql',
            '027_1.5.4.sql',
            '028_1.5.5.sql',
            '029_1.5.6.sql',
            '030_1.5.7.sql',
            '031_1.5.8.sql',
            '032_1.5.9.sql',
            '033_1.5.10.sql',
            '034_1.5.11.sql',
            '035_1.5.12.sql',
            '036_1.6.sql',
            '037_1.6.1.sql',
            '038_1.6.2.sql',
            '039_1.6.3.sql',
            '040_1.6.4.sql',
            '041_1.7.0.sql',
            '042_1.7.1.sql',
            '043_1.7.2.sql',
        ];

        // version_date_applied is the install time, as in Mdl_setup::save_version.
        $appliedAt = (string) time();

        DB::table('ip_versions')->insert(array_map(
            fn (string $file, int $index) => ['version_id' => $index + 1, 'version_date_applied' => $appliedAt, 'version_file' => $file, 'version_sql_errors' => 0],
            $versionFiles,
            array_keys($versionFiles),
        ));
    }

    public function down(): void
    {
        foreach (['ip_invoice_groups', 'ip_payment_methods', 'ip_settings', 'ip_versions'] as $table) {
            DB::table($table)->truncate();
        }
    }
};
