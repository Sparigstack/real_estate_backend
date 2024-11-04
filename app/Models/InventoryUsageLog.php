<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'utilized_quantity',
        'date'
    ];

    public function Inventory()
    {
        return $this->belongsTo(InventoryDetail::class,'inventory_id','id');
    }

    
}