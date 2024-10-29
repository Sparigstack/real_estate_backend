<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'vendor_id',
        'quantity',
        'price_per_quantity'
    ];

    public function Inventory()
    {
        return $this->belongsTo(InventoryDetail::class,'inventory_id','id');
    }

    public function Vendor()
    {
        return $this->belongsTo(VendorDetail::class,'vendor_id','id');
    }
    
}