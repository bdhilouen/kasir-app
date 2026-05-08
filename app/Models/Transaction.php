<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOwner;

class Transaction extends Model
{
    use BelongsToOwner;
    protected $fillable = [
        'owner_id',
        'invoice_number',
        'transaction_date',
        'customer_name',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'is_voided',
        'voided_at',
        'void_reason',
        'voided_by'
    ];

    protected $casts = [
        'is_voided'        => 'boolean',
        'voided_at'        => 'datetime',
        'transaction_date' => 'datetime'
    ];

    public function transactionDetails() {
        return $this->hasMany(TransactionDetail::class);
    }

    public function debt() {
        return $this->hasOne(Debt::class);
    }

    public function voidedBy() {
        return $this->belongsTo(User::class, 'voided_by');
    }


}
