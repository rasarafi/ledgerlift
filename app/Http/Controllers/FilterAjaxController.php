<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** modules/filter/controllers/Ajax.php */
class FilterAjaxController extends Controller
{
    /** Ajax::filter_families(): every keyword has to appear in the lower-cased name. */
    public function filterFamilies(Request $request): View
    {
        $families = Family::query()->orderBy('family_name');

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $families->whereRaw("CONCAT_WS('^',LOWER(family_name)) LIKE ? ESCAPE '!'", ['%'.$this->escapeLike(mb_strtolower($keyword)).'%']);
            }
        }

        return view('families.partial_families_table', ['rows' => $families->get()]);
    }

    /** Ajax::filter_products(): every keyword has to appear in the SKU, family, name, description, price or tariff. */
    public function filterProducts(Request $request): View
    {
        $products = Product::query()->listing();

        foreach (explode(' ', (string) $request->post('filter_query')) as $keyword) {
            if ($keyword) {
                $products->whereRaw(
                    "CONCAT_WS('^',product_sku,LOWER(family_name),LOWER(product_name),LOWER(product_description),product_price,product_tariff) LIKE ? ESCAPE '!'",
                    ['%'.$this->escapeLike(mb_strtolower($keyword)).'%'],
                );
            }
        }

        return view('products.partial_products_table', ['rows' => $products->get()]);
    }

    /** CI's escape_like_str(): `!` is the escape character of its LIKE clauses. */
    private function escapeLike(string $keyword): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
    }
}
