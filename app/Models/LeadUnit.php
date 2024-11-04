<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadUnit extends Model
{
    use HasFactory;

    protected $table="lead_unit";
    protected $fillable = ['interested_lead_id', 'unit_id', 'booking_status','allocated_lead_id','allocated_customer_id'];

  

    public function unit()
    {
        return $this->belongsTo(UnitDetail::class, 'unit_id'); // Ensure unit_id is used here
    }

    public function paymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class, 'unit_id', 'unit_id');
    }
    // public function allocatedLead()
    // {
    //     return $this->belongsTo(Lead::class, 'allocated_lead_id');
    // }

    // public function allocatedCustomer()
    // {
    //     return $this->belongsTo(Customer::class, 'allocated_customer_id');
    // }

    public function allocatedLeads()
    {
        // Explode the comma-separated string into an array and fetch the leads
        return Lead::whereIn('id', explode(',', $this->allocated_lead_id))->get();
    }

    // Method to retrieve allocated customers as a collection
    public function allocatedCustomers()
    {
        // Explode the comma-separated string into an array and fetch the customers
        return Customer::whereIn('id', explode(',', $this->allocated_customer_id))->get();
    }

    public function interestedLeads()
    {
        return Lead::whereIn('id', explode(',', $this->interested_lead_id))->get();
    }
}
