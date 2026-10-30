<?php

namespace App\Services;

use App\Support\DateFormat;
use App\Support\LegacyCrypt;
use App\Support\NumberFormat;
use App\Support\XssClean;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * What the invoices module writes, in legacy's order: Mdl_invoices::create(), Ajax::save() with
 * Mdl_items::save(), Mdl_items::delete(), Mdl_invoice_tax_rates::save(),
 * Invoices::delete_invoice_tax() and Invoices::delete(). Only the LEGACY_CALCULATION=true path
 * exists (config.php:21; `einvoicing` stays unset). Validation and the flash messages are the
 * controllers'.
 */
final class InvoiceService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly InvoiceNumberGenerator $numbers,
        private readonly NumberFormat $numberFormat,
        private readonly DateFormat $dates,
        private readonly XssClean $xss,
        private readonly LegacyCrypt $crypt,
        private readonly OrphanCleaner $orphans,
    ) {}

    /**
     * Mdl_invoices::create() (Mdl_invoices.php:219-265) with db_array() (:424-459). `$input` holds
     * the posted fields that validation_rules() names; a post without `user_id` takes `$userId`.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, int $userId): int
    {
        $data = $input;
        $data['user_id'] ??= $userId;
        $data['invoice_date_created'] = $this->dates->toMysql((string) ($data['invoice_date_created'] ?? ''));
        // get_date_due(): an unparsed date ('') is today, as `new DateTime('')` is.
        $data['invoice_date_due'] = (new DateTime($data['invoice_date_created']))
            ->add(new DateInterval('P'.get_setting('invoices_due_after').'D'))->format('Y-m-d');
        $data['invoice_terms'] = get_setting('default_invoice_terms');
        // invoice_status_id is not a validation_rules() field, so it is always the draft's 1 here (:438-450).
        $data['invoice_status_id'] = 1;
        $data['invoice_number'] = get_setting('generate_invoice_number_for_draft') == 1
            ? $this->numbers->generate((int) $data['invoice_group_id'])
            : '';
        $data['payment_method'] = empty($data['payment_method']) ? 0 : $data['payment_method'];
        $data['invoice_url_key'] = bin2hex(random_bytes(16)); // get_url_key(): generate_secure_token(16)

        if (array_key_exists('invoice_password', $data)) {
            $data['invoice_password'] = $this->crypt->encryptPassword(self::stringOrNull($data['invoice_password']));
        }

        // MY_Model::save(): Mdl_invoices has a date_modified_field and no date_created_field.
        $data['invoice_date_modified'] = date('Y-m-d H:i:s');
        $invoiceId = (int) DB::table('ip_invoices')->insertGetId($data);

        DB::table('ip_invoice_amounts')->insert(['invoice_id' => $invoiceId]);

        if (get_setting('default_invoice_tax_rate')) {
            DB::table('ip_invoice_tax_rates')->insert([
                'invoice_id' => $invoiceId,
                'tax_rate_id' => get_setting('default_invoice_tax_rate'),
                'include_item_tax' => get_setting('default_include_item_tax', 0),
                'invoice_tax_rate_amount' => 0,
            ]);
        }

        // :231-262 the group is read back through get_by_id()'s inner joins; a sumex group makes a sumex invoice.
        $groupName = DB::table('ip_invoices')
            ->join('ip_clients', 'ip_clients.client_id', '=', 'ip_invoices.client_id')
            ->join('ip_users', 'ip_users.user_id', '=', 'ip_invoices.user_id')
            ->join('ip_invoice_groups', 'ip_invoice_groups.invoice_group_id', '=', 'ip_invoices.invoice_group_id')
            ->where('ip_invoices.invoice_id', $invoiceId)
            ->value('ip_invoice_groups.invoice_group_name');

        if (preg_match('/sumex/i', (string) $groupName)) {
            DB::table('ip_invoice_sumex')->insert(['sumex_invoice' => $invoiceId]);
        }

        return $invoiceId;
    }

    /**
     * Ajax::save() (Ajax.php:21-235) once validation_rules_save_invoice() has passed. Legacy's
     * json_encode_ajax() echoes without ending the request, so a rejected item or number adds a
     * document and the save goes on: the answer is every document echoed, in order.
     *
     * @param  array<string, mixed>  $input  The posted fields, as the input filter left them.
     * @return list<array<string, mixed>>
     */
    public function save(int $invoiceId, array $input): array
    {
        $documents = [];
        $items = json_decode(self::stringOrNull($input['items'] ?? null) ?? '');

        // :37-43 cast before standardize_amount() (so "10,5" is 10), and the percent wins over the amount.
        $discountPercent = (float) self::stringOrNull($input['invoice_discount_percent'] ?? null);
        $discountAmount = (float) self::stringOrNull($input['invoice_discount_amount'] ?? null);

        if ($discountPercent && $discountAmount) {
            $discountAmount = 0.0;
        }

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_object($item)) {
                continue;
            }

            $row = (array) $item;

            if (! empty($row['item_name'])) {
                $this->saveItem($row);
            } elseif (! empty($row['item_quantity']) || ! empty($row['item_price'])) {
                // :97-111 form_validation's required on a field that is not posted; form_error()'s empty
                // delimiters fall back to the default <p> ones.
                $documents[] = ['success' => 0, 'validation_errors' => ['item_name' => '<p>'.__('The :attribute field is required.', ['attribute' => __('ip.item')]).'</p>']];
            }
        }

        $status = $input['invoice_status_id'] ?? null;
        $number = $input['invoice_number'] ?? null;

        // :123-134 the error is echoed, and the number is still saved.
        if (is_string($number) && $number !== '' && ! preg_match('/^[^\x00-\x1F\x7F<>"\']+$/', $number)) {
            // ip_lang.php has no `invoice_number` line, so trans() answers the key.
            $documents[] = ['success' => 0, 'validation_errors' => ['invoice_number' => 'invoice_number '.__('ip.contains_invalid_characters')]];
        }

        if (empty($number) && $status != 1) {
            $number = $this->generateNumber($invoiceId);
        }

        $data = [
            'invoice_number' => $number,
            'invoice_status_id' => $status,
            'invoice_date_created' => $this->dates->toMysql((string) self::stringOrNull($input['invoice_date_created'] ?? null)),
            'invoice_date_due' => $this->dates->toMysql((string) self::stringOrNull($input['invoice_date_due'] ?? null)),
            // :152-154 xss_clean() once more, over the input filter's pass.
            'invoice_password' => $this->crypt->encryptPassword($this->clean($input['invoice_password'] ?? null)),
            'invoice_terms' => $this->clean($input['invoice_terms'] ?? null),
            'payment_method' => $this->clean($input['payment_method'] ?? null),
            'invoice_discount_amount' => (string) $discountAmount,
            'invoice_discount_percent' => (string) $discountPercent,
        ];

        // :160-162 a strict `=== false` on the flag, a loose == on the setting.
        if (config('ledgerlift.disable_read_only') === false && $status == get_setting('read_only_toggle')) {
            $data['is_read_only'] = 1;
        }

        DB::table('ip_invoices')->where('invoice_id', $invoiceId)->update($data + ['invoice_date_modified' => date('Y-m-d H:i:s')]);

        // :183-187 the items were saved with the old discounts; this picks up the new ones.
        $this->calculator->recalculateInvoice($invoiceId);

        $documents[] = ['success' => 1];

        return $documents;
    }

    /**
     * Ajax::delete_item() with Mdl_items::delete() (Mdl_items.php:125-152): the item and its
     * amounts go, then the item's own invoice is recalculated. Only an invoice found through
     * get_by_id()'s inner joins lets an item be deleted.
     */
    public function deleteItem(int $invoiceId, int $itemId): bool
    {
        $item = DB::table('ip_invoice_items')->where('item_id', $itemId)->first(['invoice_id']);

        if ($item === null || ! $this->found($invoiceId)) {
            return false;
        }

        DB::table('ip_invoice_items')->where('item_id', $itemId)->delete();
        DB::table('ip_invoice_item_amounts')->where('item_id', $itemId)->delete();
        $this->calculator->recalculateInvoice((int) $item->invoice_id);

        return true;
    }

    /**
     * Ajax::save_invoice_tax_rate() with Mdl_invoice_tax_rates::save(): the posted row as
     * db_array() keeps it, then the posted invoice recalculated.
     *
     * @param  array<string, mixed>  $input  The posted fields that validation_rules() names.
     * @return array<string, mixed>
     */
    public function addTaxRate(array $input): array
    {
        DB::table('ip_invoice_tax_rates')->insert($input);

        if (! empty($input['invoice_id'])) {
            $this->calculator->recalculateInvoice((int) $input['invoice_id']);
        }

        return ['success' => 1];
    }

    /** Invoices::delete_invoice_tax() (Invoices.php:355-370). */
    public function deleteTaxRate(int $invoiceId, int $invoiceTaxRateId): void
    {
        DB::table('ip_invoice_tax_rates')->where('invoice_tax_rate_id', $invoiceTaxRateId)->delete();
        $this->calculator->recalculateInvoice($invoiceId);
    }

    /**
     * Invoices::delete() (Invoices.php:245-257): a draft, or any invoice with
     * ENABLE_INVOICE_DELETION, is deleted with Mdl_invoices::delete() and delete_orphans(); the
     * tasks it billed go back to complete. False when the invoice may not be deleted.
     */
    public function delete(int $invoiceId): bool
    {
        $status = DB::table('ip_invoices')->where('invoice_id', $invoiceId)->value('invoice_status_id');

        if ($status != 1 && config('ledgerlift.enable_invoice_deletion') !== true) {
            return false;
        }

        // Mdl_tasks::update_on_invoice_delete()
        $taskIds = DB::table('ip_tasks')->join('ip_invoice_items', 'ip_invoice_items.item_task_id', '=', 'ip_tasks.task_id')
            ->where('ip_invoice_items.invoice_id', $invoiceId)->pluck('ip_tasks.task_id');

        foreach ($taskIds as $taskId) {
            $this->updateTaskStatus(3, $taskId);
        }

        DB::table('ip_invoices')->where('invoice_id', $invoiceId)->delete();
        $this->orphans->run();

        return true;
    }

    /**
     * Ajax.php:69-96 and Mdl_items::save() (Mdl_items.php:103-120): the posted item is stored
     * field for field (its JSON keys are the columns), its amounts recalculated, then those of
     * the invoice its own `invoice_id` names, with the discounts still as stored.
     *
     * @param  array<string, mixed>  $row
     */
    private function saveItem(array $row): void
    {
        $row['item_quantity'] = ($row['item_quantity'] ?? null) ? $this->standardize($row['item_quantity']) : 0.0;
        $row['item_price'] = ($row['item_price'] ?? null) ? $this->standardize($row['item_price']) : 0.0;
        $row['item_discount_amount'] = ($row['item_discount_amount'] ?? null) ? $this->standardize($row['item_discount_amount']) : null;
        $row['item_product_id'] = ($row['item_product_id'] ?? null) ?: null;
        $row['item_product_unit_id'] = ($row['item_product_unit_id'] ?? null) ?: null;
        $row['item_product_unit'] = $this->unitName($row['item_product_unit_id'], $row['item_quantity']);

        if (array_key_exists('item_date', $row)) {
            $row['item_date'] = $row['item_date'] ? $this->dates->toMysql((string) self::stringOrNull($row['item_date'])) : null;
        }

        $itemId = ($row['item_id'] ?? null) ?: null;
        unset($row['item_id']);

        if (! ($row['item_task_id'] ?? null)) {
            unset($row['item_task_id']);
        } else {
            $this->updateTaskStatus(4, $row['item_task_id']);
        }

        // CodeIgniter's escape(): a bool is 1 or 0, a float its string at precision 14.
        $row = array_map(fn (mixed $value) => match (true) {
            is_bool($value) => (int) $value,
            is_float($value) => (string) $value,
            default => $value,
        }, $row);

        if ($itemId) {
            DB::table('ip_invoice_items')->where('item_id', $itemId)->update($row);
        } else {
            // MY_Model::save(): the created date of Mdl_items (over any posted one), a DATE column that keeps the day.
            $row['item_date_added'] = date('Y-m-d H:i:s');
            $itemId = DB::table('ip_invoice_items')->insertGetId($row);
        }

        $this->calculator->recalculateItem((int) $itemId);

        if (isset($row['invoice_id'])) {
            $this->calculator->recalculateInvoice((int) $row['invoice_id']);
        }
    }

    /** Mdl_units::get_name(): the plural for a quantity below -1 or above 1, nothing for an unknown unit. */
    private function unitName(mixed $unitId, mixed $quantity): ?string
    {
        if (! $unitId) {
            return null;
        }

        $unit = DB::table('ip_units')->orderBy('unit_name')->get()->first(fn (object $unit) => $unit->unit_id == $unitId);

        if ($unit === null) {
            return null;
        }

        return $quantity < -1 || $quantity > 1 ? $unit->unit_name_plrl : $unit->unit_name;
    }

    /**
     * Ajax.php:136-139: get_invoice_group_id() reads the invoice through get_by_id()'s inner
     * joins; legacy's generator, handed no group, gives a NULL number.
     */
    private function generateNumber(int $invoiceId): ?string
    {
        $groupId = $this->found($invoiceId) ? DB::table('ip_invoices')->where('invoice_id', $invoiceId)->value('invoice_group_id') : null;

        return $groupId === null ? null : $this->numbers->generate((int) $groupId);
    }

    /** Mdl_invoices::get_by_id() finds the invoice only with its client and user. */
    private function found(int $invoiceId): bool
    {
        return DB::table('ip_invoices')
            ->join('ip_clients', 'ip_clients.client_id', '=', 'ip_invoices.client_id')
            ->join('ip_users', 'ip_users.user_id', '=', 'ip_invoices.user_id')
            ->where('ip_invoices.invoice_id', $invoiceId)
            ->exists();
    }

    /** Mdl_tasks::update_status() for the statuses Mdl_tasks::statuses() knows. */
    private function updateTaskStatus(int $status, mixed $taskId): void
    {
        DB::table('ip_tasks')->where('task_id', $taskId)->update(['task_status' => $status]);
    }

    private function standardize(mixed $amount): mixed
    {
        return is_string($amount) || is_int($amount) || is_float($amount) ? $this->numberFormat->standardize($amount) : $amount;
    }

    /** xss_clean() of a missing field is '' (remove_invisible_characters(null)). */
    private function clean(mixed $value): string
    {
        return $this->xss->clean((string) self::stringOrNull($value));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
