<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;
    protected $fillable = [
        'property_id', 'name', 'email', 'contact_no', 'source_id','budget','type','status'
    ];

    public function userproperty()
    {
        return $this->belongsTo(UserProperty::class,'property_id','id');
    }

    public function messages()
    {
        return $this->hasMany(LeadMessage::class);
    }
    public function leadSource()
    {
        return $this->belongsTo(LeadSource::class, 'source_id', 'id');
    }
 

    public function leadUnits()
    {
        return $this->hasMany(LeadUnit::class, 'allocated_lead_id');
    }
    public function customerUnits()
    {
        return $this->hasMany(LeadUnit::class, 'allocated_customer_id');
    }
}
