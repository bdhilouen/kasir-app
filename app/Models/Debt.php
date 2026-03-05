<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    protected $fillable = [
        'transaction_id',
        'customer_name',
        'total_debt',
        'paid_amount',
        'remaining_debt',
        'status',
        'due_date',
        'notes'
    ];

    protected $casts = [
        'due_date' => 'date'
    ];

    public function transaction() {
        return $this->belongsTo(Transaction::class);
    }

}
