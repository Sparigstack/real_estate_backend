<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    protected $table = 'custom_fields_values';

    // Relationships
    public function leadCustomer()
    {
        return $this->belongsTo(LeadCustomer::class, 'leads_customers_id');
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function typeValue()
    {
        return $this->belongsTo(CustomFieldsTypeValue::class, 'custom_fields_type_values_id');
    }
}
