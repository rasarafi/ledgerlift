<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLegacyCrud;
use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;

/** modules/payment_methods/controllers/Payment_methods.php */
class PaymentMethodController extends Controller
{
    use HandlesLegacyCrud;

    protected string $module = 'payment_methods';

    /** @var class-string<PaymentMethod> */
    protected string $model = PaymentMethod::class;

    protected string $formRequest = PaymentMethodRequest::class;

    /**
     * Mdl_payment_methods declares order_by() where the others declare default_order_by(),
     * so legacy never sorts this list: it comes in table order.
     */
    protected ?string $orderBy = null;

    protected ?array $uniqueGuard = ['column' => 'payment_method_name', 'requires' => ['payment_method_name'], 'message' => 'payment_method_already_exists'];
}
