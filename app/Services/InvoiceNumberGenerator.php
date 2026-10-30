<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mdl_invoice_groups::generate_invoice_number() (Mdl_invoice_groups.php:68-131): the group's
 * identifier format with its {{{tokens}}} filled in, after which the group's next id goes up by
 * one. Like legacy, reading and incrementing are two statements, not one atomic step.
 */
final class InvoiceNumberGenerator
{
    public function generate(int $invoiceGroupId): string
    {
        $group = DB::table('ip_invoice_groups')->where('invoice_group_id', $invoiceGroupId)->first();

        if ($group === null) {
            throw new RuntimeException("Invoice group $invoiceGroupId does not exist.");
        }

        $number = $this->parse(
            (string) $group->invoice_group_identifier_format,
            (string) $group->invoice_group_next_id,
            (int) $group->invoice_group_left_pad,
        );

        // set_next_invoice_number(), :88-93: SET invoice_group_next_id = invoice_group_next_id+1.
        DB::table('ip_invoice_groups')->where('invoice_group_id', $invoiceGroupId)->increment('invoice_group_next_id');

        return $number;
    }

    /** parse_identifier_format(), :102-131. Dates come from PHP date(), in the app's time zone. */
    private function parse(string $format, string $nextId, int $leftPad): string
    {
        if (preg_match_all('/{{{([^{|}]*)}}}/', $format, $matches)) {
            foreach ($matches[1] as $token) {
                $replace = match ($token) {
                    'year' => date('Y'),
                    'yy' => date('y'),
                    'month' => date('m'),
                    'day' => date('d'),
                    'id' => mb_str_pad($nextId, $leftPad, '0', STR_PAD_LEFT),
                    // :122-123, an unknown token is dropped.
                    default => '',
                };

                $format = str_replace('{{{'.$token.'}}}', $replace, $format);
            }
        }

        return $format;
    }
}
