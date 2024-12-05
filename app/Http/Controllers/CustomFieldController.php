<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\CustomField;
use App\Models\CustomFieldsStructure;
use App\Models\CustomFieldsTypeValue;
use App\Models\CustomFieldTypeValue;
use App\Models\CustomFieldValue;
use App\Models\LeadCustomer;
use App\Models\LeadsCustomersTag;
use App\Models\Property;

use App\Models\UserProperty;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;






class CustomFieldController extends Controller
{


    // public function addCustomFields(Request $request)
    // {
    //     try {

    //         // Validate incoming request data
    //         $validatedData = $request->validate([
    //             'propertyId' => 'required',  // Ensure property exists
    //             'fieldname' => 'required|string|max:255',
    //             'fieldtype' => 'required|integer',
    //             'fieldrequired' => 'required|in:1,2',  // 1 = required, 2 = not required
    //             'singleselection' => 'array',
    //             'multiselection' => 'array',
    //             'fieldId'=>'nuallable',
    //         ]);

    //         // Extract input data
    //         $propertyId = $validatedData['propertyId'];
    //         $fieldName = $validatedData['fieldname'];
    //         $fieldType = $validatedData['fieldtype'];
    //         $isRequired = $validatedData['fieldrequired'];
    //         $singleSelection = $validatedData['singleselection'];
    //         $multiSelection = $validatedData['multiselection'];

    //         // Check if the custom field already exists for the given property
    //         $existingField = CustomField::where('property_id', $propertyId)
    //             ->where('name', $fieldName)
    //             ->first();

    //         if ($existingField) {
    //             // If the field already exists, return a response with an error message
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Custom field with the same name already exists for this property.',
    //             ], 200);
    //         }

    //         // Save the custom field in the custom_fields table
    //         $customField = CustomField::create([
    //             'property_id' => $propertyId,
    //             'name' => $fieldName,
    //             'custom_fields_type_values_id' => $fieldType,
    //             'is_required' => $isRequired,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         // Save the single and multi-selection values in the custom_fields_structures table
    //         if (!empty($singleSelection)) {
    //             foreach ($singleSelection as $value) {
    //                 CustomFieldsStructure::create([
    //                     'custom_field_id' => $customField->id,
    //                     'value_type' => 'single',  // single selection type
    //                     'value' => $value,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         }

    //         if (!empty($multiSelection)) {
    //             foreach ($multiSelection as $value) {
    //                 CustomFieldsStructure::create([
    //                     'custom_field_id' => $customField->id,
    //                     'value_type' => 'multi',  // multi selection type
    //                     'value' => $value,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         }

    //         // Return success response
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Custom field added successfully.',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         Helper::errorLog('addCustomFields', $e->getLine() . $e->getMessage(), 'high');
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Something went wrong.',
    //         ], 400);
    //     }
    // }

    public function addCustomFields(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'propertyId' => 'required',  // Ensure property exists
                'fieldname' => 'required|string|max:255',
                'fieldtype' => 'required|integer',
                'fieldrequired' => 'required|in:1,2',  // 1 = required, 2 = not required
                'singleselection' => 'array',
                'multiselection' => 'array',
                'fieldId' => 'nullable|integer', // fieldid can be nullable
            ]);

            // Extract input data
            $propertyId = $validatedData['propertyId'];
            $fieldName = $validatedData['fieldname'];
            $fieldType = $validatedData['fieldtype'];
            $isRequired = $validatedData['fieldrequired'];
            $singleSelection = $validatedData['singleselection'];
            $multiSelection = $validatedData['multiselection'];
            $fieldId = $validatedData['fieldId'];

            // Case: If fieldid is provided and not 0, it's an edit request
            if ($fieldId != null && $fieldId != 0) {
                // Find the custom field by id
                $customField = CustomField::find($fieldId);

                // Check if the field exists and belongs to the provided property
                if (!$customField || $customField->property_id != $propertyId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Custom field not found for this property.',
                    ], 200);
                }

                // Check if the field name is being changed, ensure no other field with the same name exists
                if ($customField->name != $fieldName && CustomField::where('property_id', $propertyId)->where('id','!=',$fieldId)->where('name', $fieldName)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Custom field with the same name already exists for this property.',
                    ], 200);
                }

                // Update the custom field
                $customField->update([
                    'name' => $fieldName,
                    'custom_fields_type_values_id' => $fieldType,
                    'is_required' => $isRequired,
                    'updated_at' => now(),
                ]);

                // Clear previous structure and re-save new structure
                CustomFieldsStructure::where('custom_field_id', $customField->id)->delete();

                // Save new single and multi-selection values
                $this->saveCustomFieldStructure($customField, $singleSelection, 'single');
                $this->saveCustomFieldStructure($customField, $multiSelection, 'multi');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Custom field updated successfully.',
                ], 200);
            }

            // Case: Create new custom field (fieldid is 0 or not provided)
            // Check if the custom field already exists for the given property
            $existingField = CustomField::where('property_id', $propertyId)
                ->where('name', $fieldName)
                ->first();

            if ($existingField) {
                // If the field already exists, return a response with an error message
                return response()->json([
                    'status' => 'error',
                    'message' => 'Custom field with the same name already exists for this property.',
                ], 200);
            }

            // Create a new custom field
            $customField = CustomField::create([
                'property_id' => $propertyId,
                'name' => $fieldName,
                'custom_fields_type_values_id' => $fieldType,
                'is_required' => $isRequired,
            ]);

            // Save the single and multi-selection values in the custom_fields_structures table
            $this->saveCustomFieldStructure($customField, $singleSelection, 'single');
            $this->saveCustomFieldStructure($customField, $multiSelection, 'multi');

            return response()->json([
                'status' => 'success',
                'message' => 'Custom field added successfully.',
            ], 200);
        } catch (\Exception $e) {
            Helper::errorLog('addCustomFields', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    // Common method to save custom field structure
    protected function saveCustomFieldStructure($customField, $values, $valueType)
    {
        if (!empty($values)) {
            foreach ($values as $value) {
                // Check if the value already exists
                if (CustomFieldsStructure::where('custom_field_id', $customField->id)
                    ->where('value', $value)
                    ->exists()
                ) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Duplicate value detected in ' . $valueType . ' selection.',
                    ], 200);
                }

                // If not, create the structure entry
                CustomFieldsStructure::create([
                    'custom_field_id' => $customField->id,
                    'value' => $value,
                ]);
            }
        }
    }


    public function getCustomFields($pid)
    {
        try {
            if ($pid != 'null') {
                $customFields = CustomField::where('property_id', $pid)
                    ->with('customFieldStructures', 'typeValue')  // Eager load custom field structures (if needed)
                    ->get();

                // Check if custom fields are found
                if ($customFields->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No custom fields found for this property.',
                    ], 200);
                }

                // Return success response with the fetched custom fields
                return $customFields;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            Helper::errorLog('getCustomFields', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }


    public function fetchCustomField($cfid)
    {
        try {
            if ($cfid != 'null') {
                $customFieldDetail = CustomField::with('customFieldStructures', 'typeValue') // Eager load custom field structures (if needed)
                    ->where('id', $cfid)
                    ->first();


                if ($customFieldDetail) {
                    // Transform tags to include only names
                    $customFieldStructure = $customFieldDetail->customFieldStructures->pluck('value')->toArray();
                    $customFieldDetail = $customFieldDetail->toArray(); // Convert to array
                    $customFieldDetail['custom_field_structures'] = $customFieldStructure;
                }
                // Return success response with the fetched custom fields
                return $customFieldDetail;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            Helper::errorLog('fetchCustomField', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }
}
