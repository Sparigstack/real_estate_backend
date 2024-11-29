<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadStatus extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function leadscustomers()
    {
        return $this->hasMany(LeadCustomer::class, 'status_id', 'id');
    }
}