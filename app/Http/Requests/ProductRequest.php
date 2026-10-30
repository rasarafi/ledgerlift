<?php

namespace App\Http\Requests;

use App\Rules\LegacyNumeric;

/** Mdl_products::validation_rules() */
class ProductRequest extends LegacyFormRequest
{
    public function rules(): array
    {
        return [
            'product_sku' => '',
            'product_name' => 'required',
            'product_description' => '',
            'product_price' => 'required',
            'purchase_price' => '',
            'provider_name' => '',
            'family_id' => [new LegacyNumeric],
            'unit_id' => [new LegacyNumeric],
            'tax_rate_id' => [new LegacyNumeric],
            'product_tariff' => '',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_sku' => __('ip.product_sku'),
            'product_name' => __('ip.product_name'),
            'product_description' => __('ip.product_description'),
            'product_price' => __('ip.product_price'),
            'purchase_price' => __('ip.purchase_price'),
            'provider_name' => __('ip.provider_name'),
            'family_id' => __('ip.family'),
            'unit_id' => __('ip.unit'),
            'tax_rate_id' => __('ip.tax_rate'),
            'product_tariff' => __('ip.product_tariff'),
        ];
    }
}
