<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLegacyCrud;
use App\Http\Requests\InvoiceGroupRequest;
use App\Models\InvoiceGroup;

/** modules/invoice_groups/controllers/Invoice_groups.php */
class InvoiceGroupController extends Controller
{
    use HandlesLegacyCrud;

    protected string $module = 'invoice_groups';

    /** @var class-string<InvoiceGroup> */
    protected string $model = InvoiceGroup::class;

    protected string $formRequest = InvoiceGroupRequest::class;

    protected ?string $orderBy = 'invoice_group_name';

    /** @var array{column: string, requires: list<string>, message: string}|null */
    protected ?array $uniqueGuard = null;

    /**
     * Invoice_groups::form() sets these on every pass of a new group's form, so a failed save
     * shows the defaults again instead of what was posted.
     */
    protected function newFormValues(array $form): array
    {
        return array_merge($form, ['invoice_group_left_pad' => 0, 'invoice_group_next_id' => 1]);
    }
}
