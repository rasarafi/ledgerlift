<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $table = 'ip_products';

    protected $primaryKey = 'product_id';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Mdl_products' default_join(), default_select() and default_order_by(): `SELECT *` over
     * the products and their left-joined family, unit and tax rate, by family then product name.
     * A column several tables have (family_id, unit_id, tax_rate_id) takes the value of the last
     * table, so a product whose family is gone reads NULL there, as in legacy (survey trap 11).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeListing(Builder $query): void
    {
        $query->leftJoin('ip_families', 'ip_families.family_id', '=', 'ip_products.family_id')
            ->leftJoin('ip_units', 'ip_units.unit_id', '=', 'ip_products.unit_id')
            ->leftJoin('ip_tax_rates', 'ip_tax_rates.tax_rate_id', '=', 'ip_products.tax_rate_id')
            ->select('*')
            ->orderBy('ip_families.family_name')
            ->orderBy('ip_products.product_name');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }
}
