<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    // protected $table = 'custom_fields';
    protected $fillable = [
        'property_id',
        'name',
        'custom_fields_type_values_id',
        'contact_no',
        'is_required',
    ];
    // Relationships
    public function property()
    {
        return $this->belongsTo(UserProperty::class, 'property_id');
    }

    public function typeValue()
    {
        return $this->belongsTo(CustomFieldsTypeValue::class, 'custom_fields_type_values_id');
    }

    public function customFieldValues()
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_id');
    }

    public function customFieldStructures()
    {
        return $this->hasMany(CustomFieldsStructure::class, 'custom_field_id');
    }
}
