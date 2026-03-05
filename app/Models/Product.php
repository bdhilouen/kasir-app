<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillabale = [
        'name',
        'sku',
        'price',
        'stock',
        'min_stock',
        'description'
    ];

    public function transanctionDetais() {
        return $this->hasMany(TransactionDetail::class);
    }
}
