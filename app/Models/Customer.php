<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model
{
    use HasFactory;
    protected $fillable = ['property_id', 'unit_id', 'name', 'email', 'contact_no', 'profile_pic'];


    public function property()
    {
        return $this->belongsTo(UserProperty::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitDetail::class);
    }
    public function paymentTransactions()
    {
        return $this->morphMany(PaymentTransaction::class, 'allocated');
    }
}
