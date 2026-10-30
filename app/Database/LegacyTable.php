<?php

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * Storage settings of InvoicePlane v1.7.2's tables (application/modules/setup/sql/).
 *
 * Almost every table is MyISAM with 3-byte utf8: no transactions, no foreign keys.
 * The custom-field values and sessions rebuilt by upgrade 023 and the login log are
 * InnoDB with utf8mb4. Both are reproduced as-is so the same SQL behaves the same.
 */
final class LegacyTable
{
    public static function myIsam(Blueprint $table): void
    {
        $table->engine = 'MyISAM';
        $table->charset = 'utf8mb3';
        $table->collation = 'utf8mb3_general_ci';
    }

    public static function innoDb(Blueprint $table): void
    {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';
    }
}
