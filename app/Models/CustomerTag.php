<?php

namespace App\Models;

class CustomerTag extends TenantModel
{
    const UPDATED_AT = null;

    protected $table = 'customer_tags';

    protected $fillable = [
        'name',
        'color',
    ];

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_tag_pivot', 'tag_id', 'customer_id');
    }
}
