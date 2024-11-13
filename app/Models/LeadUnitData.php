<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadUnitData extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_unit_id', 'lead_id', 'budget'
    ];

    // Define the relationship to LeadUnit
    public function leadUnit()
    {
        return $this->belongsTo(LeadUnit::class);
    }

    // Define the relationship to Lead
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
