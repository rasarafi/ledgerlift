<?php

namespace App\Http\Controllers;

use App\Http\Responses\LegacyJson;
use App\Models\Family;
use App\Models\Product;
use App\Support\XssClean;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** modules/products/controllers/Ajax.php */
class ProductAjaxController extends Controller
{
    /** The product lookup modal; a filter or a reset answers the table alone. */
    public function modalProductLookups(Request $request, XssClean $xss): View
    {
        // $this->input->get($key, true) is xss_clean'd, and the display value is cleaned again.
        $filterProduct = $this->cleaned($request, 'filter_product', $xss);
        $filterFamily = $this->cleaned($request, 'filter_family', $xss);
        $resetTable = $this->cleaned($request, 'reset_table', $xss);

        $products = Product::query()->listing();

        if (! empty($filterFamily)) {
            $products->where('ip_products.family_id', $filterFamily);
            $filterFamily = $xss->clean($filterFamily);
        }

        if (! empty($filterProduct)) {
            $like = '%'.$this->escapeLike($filterProduct).'%';
            $products->where(fn ($group) => $group
                ->whereRaw("ip_products.product_sku LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("ip_products.product_name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("ip_products.product_description LIKE ? ESCAPE '!'", [$like]));
            $filterProduct = $xss->clean($filterProduct);
        }

        $data = [
            'products' => $products->get(),
            'families' => Family::query()->orderBy('family_name')->get(),
            'filterProduct' => $filterProduct,
            'filterFamily' => $filterFamily,
            // Ajax.php:41-42: `!== '' ?: 0` gives true or 0, not the setting; the script writes it as the default tax rate id.
            'defaultItemTaxRate' => get_setting('default_item_tax_rate') !== '' ?: 0,
        ];

        return view($filterProduct || $filterFamily || $resetTable ? 'products.partial_product_table_modal' : 'products.modal_product_lookups', $data);
    }

    /** The rows of the chosen products, as the script of the modal copies them into invoice items. */
    public function processProductSelections(Request $request): Response
    {
        $ids = $request->post('product_ids');

        // where_in() takes an array only; legacy fails with a TypeError (HTTP 500) otherwise.
        abort_unless(is_array($ids), 500);

        $products = Product::query()->listing()->whereIn('ip_products.product_id', $ids)->get();

        return LegacyJson::make($products->map(function (Product $product) {
            // mysqli hands every value over as a string.
            $row = array_map(fn (mixed $value) => $value === null ? null : (string) $value, $product->getAttributes());
            $row['product_price'] = format_amount($row['product_price']);

            return $row;
        })->all());
    }

    private function cleaned(Request $request, string $key, XssClean $xss): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $xss->clean($value) : null;
    }

    /** CI's escape_like_str(): `!` is the escape character of its LIKE clauses. */
    private function escapeLike(string $keyword): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
    }
}
