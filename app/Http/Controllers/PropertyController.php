<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\UserProperty;
use App\Models\WingDetail;
use Illuminate\Http\Request;
use App\Helper;


class PropertyController extends Controller
{
 public function getPropertyTypes($typeFlag)
 {
    $get = Property::with('subProperties')->where('id',$typeFlag)->where('parent_id',0)->first(); //typeflag : 1=>residential ,2=>commercial
    return $get;
 }

 public function addPropertyDetailsFirstStep(Request $request)
 {
    try
    {
    $name = $request->input('name');
    $reraRegisteredNumber = $request->input('reraRegisteredNumber');
    $propertyTypeFlag = $request->input('propertyTypeFlag');
    $propertySubTypeFlag = $request->input('propertySubTypeFlag');
    $address = $request->input('address');
    $numberOfWings = $request->input('numberOfWings');
    $description = $request->input('description');
    $userId = $request->input('userId');
    $pincode = $request->input('pincode');
    $minPrice = $request->input('minPrice');
    $maxPrice = $request->input('maxPrice');
    $propertyPlan = $request->input('propertyPlan');

    if($propertyTypeFlag == 1)
    {
        $checkProperty = UserProperty::where('user_id',$userId)->where('rera_registered_no',$reraRegisteredNumber)->first();
        if(isset($checkProperty))
        {
            return response()->json([
                'status' => 'error',
                'msg' => 'property with this registered number already exist',
                'propertyId' => null
            ], 400);
        }
        else
        {
        $userProperty = new UserProperty();
        $userProperty->user_id = $userId;
        $userProperty->property_id = $propertySubTypeFlag;
        $userProperty->name = $name;
        $userProperty->description = $description;
        $userProperty->rera_registered_no = $reraRegisteredNumber;
        $userProperty->address = $address;
        $userProperty->pincode = $pincode;
        $userProperty->property_step_status = 1;
        $userProperty->save();

        $propertyDetails = new PropertyDetail();
        $propertyDetails->user_property_id = $userProperty->id;
        $propertyDetails->total_wings = $numberOfWings;
        $propertyDetails->min_price = $minPrice;
        $propertyDetails->max_price= $maxPrice;
        $propertyDetails->property_plan = $propertyPlan;
        $propertyDetails->save();
            return response()->json([
                'status' => 'success',
                'msg' => null,
                'propertyId' => $userProperty->id
            ], 200);
            }
        }
    }

    catch (\Exception $e) {
        $errorFrom = 'addPropertyDetailsFirstStep';
        $errorMessage = $e->getMessage();
        $priority = 'high';
        Helper::errorLog($errorFrom, $errorMessage, $priority);
        return response()->json([
            'status' => 'error',
            'msg' => 'something went wrong',
        ],400);
    }
 }
}
