<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_id',
        'name',
        'description',
        'rera_registered_no',
        'address',
        'pincode',
        'property_step_status'
    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
    public function property()
    {
        return $this->belongsTo(Property::class,'property_id','id');
    }
    public function wingDetails()
    {
        return $this->hasMany(WingDetail::class,'user_property_id','id');
    }

    public function propertyDetails()
    {
        return $this->hasMany(PropertyDetail::class,'user_property_id','id');
    }

    public function floorDetails()
    {
        return $this->hasMany(FloorDetail::class,'user_property_id','id');
    }
    public function unitDetails()
    {
        return $this->hasMany(UnitDetail::class,'user_property_id','id');
    }
}
