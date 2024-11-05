<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'property_id',
        'inventory_id',
        'quantity',
        'price',
        'status',
        'attachment',
    ];

    public function InventoryDetails()
    {
        return $this->belongsTo(InventoryDetail::class, 'inventory_id', 'id');
    }

    public function PropertyDetails()
    {
        return $this->belongsTo(UserProperty::class,'property_id','id');
    }

    public function VendorDetails()
    {
        return $this->belongsTo(VendorDetail::class,'vendor_id','id');
    }

}