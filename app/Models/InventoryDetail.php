<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'price_per_quantity',
        'current_quantity',
        'reminder_quantity'
    ];

    public function InventoryLogDetails()
    {
        return $this->hasOne(InventoryLog::class, 'inventory_id', 'id');
    }

    public function PropertyDetails()
    {
        return $this->belongsTo(UserProperty::class,'property_id','id')->with('user');
    }
}