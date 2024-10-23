<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class UnitDetail extends Model
{
    use HasApiTokens, HasFactory;

    protected $table="unit_details";
    protected $fillable = [
        'property_id',
        'wing_id',
        'floor_id',
        'name',
        'status_id',
        'square_feet'
    ];
    public function userProperty()
    {
        return $this->belongsTo(UserProperty::class,'property_id','id');
    }
    public function wingDetail()
    {
        return $this->belongsTo(WingDetail::class,'wing_id','id');
    }
    public function floorDetail()
    {
        return $this->belongsTo(FloorDetail::class,'floor_id','id');
    }
    public function leadUnits()
    {
        return $this->hasMany(LeadUnit::class, 'unit_id'); // Ensure unit_id is used here
    }
    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class,'unit_id');
    }
}
