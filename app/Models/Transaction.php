<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_number',
        'transaction_date',
        'customer_name',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status'
    ];

    protected $casts = [
        'transaction_date' => 'datetime'
    ];

    public function transactionDetails() {
        return $this->hasMany(TransactionDetail::class);
    }

    public function debt() {
        return $this->hasOne(Debt::class);
    }


}
