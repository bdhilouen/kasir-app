<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOwner;

class Product extends Model
{
    use BelongsToOwner;
    protected $fillable = [
        'owner_id',
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
