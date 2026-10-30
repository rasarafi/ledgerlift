<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLegacyCrud;
use App\Http\Requests\TaxRateRequest;
use App\Models\TaxRate;
use App\Support\NumberFormat;

/** modules/tax_rates/controllers/Tax_rates.php */
class TaxRateController extends Controller
{
    use HandlesLegacyCrud;

    protected string $module = 'tax_rates';

    /** @var class-string<TaxRate> */
    protected string $model = TaxRate::class;

    protected string $formRequest = TaxRateRequest::class;

    protected ?string $orderBy = 'tax_rate_percent';

    protected ?array $uniqueGuard = null;

    /**
     * Tax_rates::form() stores the percent through standardize_amount(), so the decimal
     * point of the user's settings becomes SQL's (IPT-310).
     */
    protected function normalised(array $data): array
    {
        $data['tax_rate_percent'] = app(NumberFormat::class)->standardize($data['tax_rate_percent']);

        return $data;
    }
}
