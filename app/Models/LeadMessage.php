<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadMessage extends Model
{
    use HasFactory;
    protected $fillable = [
        'lead_id', 'property_id', 'template_id', 'message_type', 'status'
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
