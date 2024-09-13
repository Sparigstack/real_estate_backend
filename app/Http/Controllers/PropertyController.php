<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FloorDetail;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\UserProperty;
use App\Models\WingDetail;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Status;
use App\Models\Amenity;



class PropertyController extends Controller
{
 public function getPropertyTypes($typeFlag)
 {
    $get = Property::with('subProperties')->where('id',$typeFlag)->where('parent_id',0)->first(); //typeflag : 1=>residential ,2=>commercial
    return $get;
 }

 public function propertyDetailsFirstStep(Request $request)
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
                'msg' => 'Property with this registered number already exist.',
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
        $errorFrom = 'propertyDetailsFirstStep';
        $errorMessage = $e->getMessage();
        $priority = 'high';
        Helper::errorLog($errorFrom, $errorMessage, $priority);
        return response()->json([
            'status' => 'error',
            'msg' => 'something went wrong',
        ],400);
    }
 }
 public function getPropertyStatues($statusFlag)
 {
    $get = Status::with('subStatuses')->where('id',$statusFlag)->where('parent_id',0)->first(); //statusFlag : 1=>Construction Status ,2=>Electrical Status, 3=>Pipline Status
    return $get;
 }


 public function getPropertyAmenities()
 {
    $get = Amenity::get();
    return $get;
 }

 public function propertyDetailsSecondStep(Request $request)
 {
    try
    {
    $wingName = $request->input('wingName');
    $wingSize = $request->input('wingSize');
    $sameUnitFlag = $request->input('sameUnitFlag'); // 1 =>yes , 0 => no
    $numberOfFloors = $request->input('numberOfFloors');
    $propertyId = $request->input('propertyId');

        if($sameUnitFlag == 1)
        {
        $checkWing = WingDetail::where('user_property_id',$propertyId)->where('name',$wingName)->first();
        if(isset($checkWing))
        {
            return response()->json([
                'status' => 'error',
                'msg' => 'Same wing name exist.',
                'wingId' => null
            ], 400);
        }
        else{
            $wingDetail = new WingDetail();
            $wingDetail->user_property_id = $propertyId;
            $wingDetail->name = $wingName;
            $wingDetail->total_floors = $numberOfFloors;
            $wingDetail->wing_size = $wingSize;
            $wingDetail->save();

            for($i =1 ;$i <= $numberOfFloors; $i++)
            {
                $floorDetail = new FloorDetail();
                $floorDetail->user_property_id = $propertyId;
                $floorDetail->wing_id = $wingDetail->id;
                $floorDetail->save();
            }
            return response()->json([
                'status' => 'success',
                'msg' => null,
                'wingId' => $wingDetail->id
            ], 200);
        }
        }
    }
    catch (\Exception $e) {
        $errorFrom = 'propertyDetailsSecondStep';
        $errorMessage = $e->getMessage();
        $priority = 'high';
        Helper::errorLog($errorFrom, $errorMessage, $priority);
        return response()->json([
            'status' => 'error',
            'msg' => 'something went wrong',
        ],400);
    }
 }

//  public function propertyDetailsThirdStep(Request $request)
//  {
//     // try
//     // {
//     $propertyId = $request->input('propertyId');
//     $wingId = $request->input('wingId');
//     $foyerAreaSize = $request->input('foyerAreaSize');
//     $numberOfUnits = $request->input('numberOfUnits');
//     $sameUnitSizeFlag = $request->input('sameUnitSizeFlag'); // 1 =>yes , 0 => no
//     $unitStartingNumber = $request->input('unitStartingNumber');

//         if($sameUnitSizeFlag == 1)
//         {
//         $checkFloorDetails = FloorDetail::where('user_property_id',$propertyId)->where('wing_id',$wingId)->get();
//         if(count($checkFloorDetails) > 0)
//         {
//             foreach($checkFloorDetails as $floorDetail)
//             {
//                 $floorDetail->update([
//                     ''
//                 ]);
//             }
//         }
//         else
//         {
//             return response()->json([
//                 'status' => 'error',
//                 'msg' => 'No floordetails found on this property'
//                 // 'wingId' => null
//             ], 400);
//         }

//         }
//     // }
//     // catch (\Exception $e) {
//     //     $errorFrom = 'propertyDetailsThirdStep';
//     //     $errorMessage = $e->getMessage();
//     //     $priority = 'high';
//     //     Helper::errorLog($errorFrom, $errorMessage, $priority);
//     //     return response()->json([
//     //         'status' => 'error',
//     //         'msg' => 'something went wrong',
//     //     ],400);
//     // }
//  }
}
