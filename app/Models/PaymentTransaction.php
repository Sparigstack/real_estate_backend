<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    // protected $table="unit_details";
    protected $fillable = [
        'customer_id', 'unit_id', 'property_id', 
        'booking_date', 'payment_due_date', 
        'token_amt', 'amount', 'payment_type', 
        'transaction_notes'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitDetail::class);
    }

    public function property()
    {
        return $this->belongsTo(UserProperty::class);
    }
}
