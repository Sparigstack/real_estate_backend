<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model
{
    use HasFactory;
    protected $fillable = ['property_id', 'lead_unit_id', 'name', 'email', 'contact_no', 'profile_pic'];


    public function property()
    {
        return $this->belongsTo(UserProperty::class);
    }

    public function leadUnit()
    {
        return $this->belongsTo(LeadUnit::class);
    }
}
