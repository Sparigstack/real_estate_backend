<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerProperty extends Model
{
    use HasFactory;

    public function customer()
    {
        return $this->belongsTo(Customer::class,'customer_id','id');
    }
    public function property()
    {
        return $this->belongsTo(CustomerProperty::class,'property_id','id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
}
