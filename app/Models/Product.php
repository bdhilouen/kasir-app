<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock',
        'min_stock',
        'description'
    ];

    public function transactionDetails() {
        return $this->hasMany(TransactionDetail::class);
    }
}
