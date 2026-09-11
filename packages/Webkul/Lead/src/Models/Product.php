<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Product as ProductContract;
use Webkul\Product\Models\ProductProxy;
use Webkul\Tenant\Traits\BelongsToTenant;

class Product extends Model implements ProductContract
{
    use BelongsToTenant;

    protected $table = 'lead_products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'quantity',
        'price',
        'amount',
        'product_id',
        'lead_id',
    ];

    /**
     * Get the product owns the lead product.
     */
    public function product()
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    /**
     * Get the lead that owns the lead product.
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the customer full name.
     */
    public function getNameAttribute()
    {
        return $this->product->name;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['name'] = $this->name;

        return $array;
    }
}
