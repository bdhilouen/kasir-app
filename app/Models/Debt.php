<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOwner;

class Debt extends Model
{
    use BelongsToOwner;
    protected $fillable = [
        'owner_id',
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
