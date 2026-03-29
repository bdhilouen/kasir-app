<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'price',
        'stock',
        'min_stock',
        'description'
    ];

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function transactionDetails() {
        return $this->hasMany(TransactionDetail::class);
    }
}
