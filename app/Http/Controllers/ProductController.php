<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLegacyCrud;
use App\Http\Requests\ProductRequest;
use App\Models\Family;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Support\NumberFormat;
use Illuminate\Database\Eloquent\Builder;

/** modules/products/controllers/Products.php */
class ProductController extends Controller
{
    use HandlesLegacyCrud;

    protected string $module = 'products';

    /** @var class-string<Product> */
    protected string $model = Product::class;

    protected string $formRequest = ProductRequest::class;

    /** @var array{column: string, requires: list<string>, message: string}|null */
    protected ?array $uniqueGuard = null;

    /** @return Builder<Product> */
    protected function query(): Builder
    {
        return Product::query()->listing();
    }

    /** @return array<string, mixed> */
    protected function indexData(): array
    {
        return ['filterDisplay' => true, 'filterPlaceholder' => __('ip.filter_products'), 'filterMethod' => 'filter_products'];
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [
            'families' => Family::query()->orderBy('family_name')->get(),
            'units' => Unit::query()->orderBy('unit_name')->get(),
            'taxRates' => TaxRate::query()->orderBy('tax_rate_percent')->get(),
        ];
    }

    /**
     * Mdl_products::db_array(): prices go through standardize_amount() and an empty price,
     * family, unit or tax rate is NULL; the keys are set even when the post lacks them.
     */
    protected function normalised(array $data): array
    {
        $number = app(NumberFormat::class);

        foreach (['product_price', 'purchase_price'] as $price) {
            $data[$price] = empty($data[$price]) ? null : $number->standardize($data[$price]);
        }

        foreach (['family_id', 'unit_id', 'tax_rate_id'] as $lookup) {
            $data[$lookup] = empty($data[$lookup]) ? null : $data[$lookup];
        }

        return $data;
    }
}
