<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCustomer  extends Model
{
    use HasFactory;
    protected $table="leads_customers";
    protected $fillable = [
        'property_id', 'name', 'email', 'contact_no', 'source_id','type','status','notes','entity_type','agent_name','agent_contact'
    ];

    public function userproperty()
    {
        return $this->belongsTo(UserProperty::class,'property_id','id');
    }

    public function leadSource()
    {
        return $this->belongsTo(LeadSource::class, 'source_id', 'id');
    }

    public function leadCustomerUnits()
    {
        return $this->hasMany(LeadCustomerUnit::class, 'leads_customers_id');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'leads_customers_id');
    }

    public function getEntityTypeLabelAttribute()
    {
        return $this->entity_type === 1 ? 'Lead' : 'Customer';
    }
   

}
