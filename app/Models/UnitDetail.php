<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_property_id',
        'wing_id',
        'floor_id',
        'name',
        'status_id',
        'square_feet'
    ];
    public function userProperty()
    {
        return $this->belongsTo(UserProperty::class,'user_property_id','id');
    }
    public function wingDetail()
    {
        return $this->belongsTo(WingDetail::class,'wing_id','id');
    }
    public function floorDetail()
    {
        return $this->belongsTo(FloorDetail::class,'floor_id','id');
    }
}
