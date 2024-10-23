<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadUnit extends Model
{
    use HasFactory;

    protected $table="lead_unit";
    protected $fillable = ['lead_id', 'unit_id', 'booking_status'];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitDetail::class, 'unit_id'); // Ensure unit_id is used here
    }

    public function paymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class, 'unit_id', 'unit_id');
    }
}
