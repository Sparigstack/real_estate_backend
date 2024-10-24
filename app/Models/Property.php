<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    public function subProperties()
    {
        return $this->hasMany(Property::class,'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }

}
