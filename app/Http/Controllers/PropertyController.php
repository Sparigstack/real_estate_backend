<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FloorDetail;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\UnitDetail;
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
        $get = Property::with('subProperties')->where('id', $typeFlag)->where('parent_id', 0)->first(); //typeflag : 1=>residential ,2=>commercial
        return $get;
    }

    public function addPropertyDetails(Request $request)
    {
        try {
            $name = $request->input('name');
            $reraRegisteredNumber = $request->input('reraRegisteredNumber');
            $propertyTypeFlag = $request->input('propertyTypeFlag');
            $propertySubTypeFlag = $request->input('propertySubTypeFlag');
            $address = $request->input('address');
            $propertyImg  = $request->input('property_img'); //base64
            $description = $request->input('description');
            $userId = $request->input('userId');
            $pincode = $request->input('pincode');


                if ($reraRegisteredNumber) {
                    // $checkRegisterNumber = UserProperty::where('user_id', $userId)
                    //     ->where('rera_registered_no', $reraRegisteredNumber)
                    //     ->first();

                    $checkRegisterNumber = UserProperty::where('rera_registered_no', $reraRegisteredNumber)
                        ->first();


                    if ($checkRegisterNumber) {
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'Property with this registered number already exists.',
                            'propertyId' => null
                        ], 400);
                    }
                }

                $userProperty = new UserProperty();
                $userProperty->user_id = $userId;
                $userProperty->property_id = $propertySubTypeFlag;
                $userProperty->name = $name;
                $userProperty->description = $description;
                $userProperty->rera_registered_no = $reraRegisteredNumber;
                $userProperty->address = $address;
                $userProperty->pincode = $pincode;
                $userProperty->property_img = $propertyImg;
                $userProperty->property_step_status = 1;
                $userProperty->save();


                return response()->json([
                    'status' => 'success',
                    'msg' => null,
                    'propertyId' => $userProperty->id
                ], 200);

        } catch (\Exception $e) {
            $errorFrom = 'addPropertyDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ], 400);
        }
    }
    public function getPropertyStatues($statusFlag)
    {
        $get = Status::with('subStatuses')->where('id', $statusFlag)->where('parent_id', 0)->first(); //statusFlag : 1=>Construction Status ,2=>Electrical Status, 3=>Pipline Status
        return $get;
    }


    public function getPropertyAmenities()
    {
        $get = Amenity::get();
        return $get;
    }

    public function addWingDetails(Request $request)
    {
        try {
            $wingName = $request->input('wingName');
            $numberOfFloors = $request->input('numberOfFloors');
            $propertyId = $request->input('propertyId');
            $wingId = $request->input('wingId');
            $sameUnitFlag = $request->input('sameUnitFlag');
            $numberOfUnits = $request->input('numberOfUnits');
            $floorUnitCounts = $request->input('floorUnitCounts');

            if ($sameUnitFlag == 1) {
                $checkWing = WingDetail::where('user_property_id', $propertyId)->where('name', $wingName)->first();
            if (isset($checkWing)) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Same wing name exist.',
                    'wingId' => null,
                    'floorUnitDetails' => null
                ], 400);
            }
                $wingDetail = new WingDetail();
                $wingDetail->user_property_id = $propertyId;
                $wingDetail->name = $wingName;
                $wingDetail->total_floors = $numberOfFloors;
                $wingDetail->save();
                    $floorUnitDetails = [];

                    for ($i = 1; $i <= $numberOfFloors; $i++) {
                        $floorDetail = new FloorDetail();
                        $floorDetail->user_property_id = $propertyId;
                        $floorDetail->wing_id = $wingDetail->id;
                        $floorDetail->total_units = $numberOfUnits;
                        $floorDetail->save();

                        $unitDetails = [];
                        for ($j = 1; $j <= $numberOfUnits; $j++) {
                            $unitDetail = new UnitDetail();
                            $unitDetail->user_property_id = $propertyId;
                            $unitDetail->wing_id = $wingDetail->id;
                            $unitDetail->floor_id = $floorDetail->id;
                            $unitDetail->save();

                            $unitDetails[] = ['unitId' => $unitDetail->id];
                        }

                        $floorUnitDetails[] = ['floorId' => $floorDetail->id, 'unitDetails' => $unitDetails];
                    }
                    return response()->json([
                        'status' => 'success',
                        'msg' => null,
                        'wingId' => $wingDetail->id,
                        'floorUnitDetails' => $floorUnitDetails,
                        'floorUnitCounts' => null
                    ], 200);
            }
            elseif($sameUnitFlag == 2){
                $checkWing = WingDetail::where('user_property_id', $propertyId)->where('name', $wingName)->first();
            if (isset($checkWing)) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Same wing name exist.',
                    'wingId' => null,
                    'floorUnitDetails' => null
                ], 400);
            }
                $wingDetail = new WingDetail();
                $wingDetail->user_property_id = $propertyId;
                $wingDetail->name = $wingName;
                $wingDetail->total_floors = $numberOfFloors;
                $wingDetail->save();
                $floorUnitCounts = [];

                 for ($i = 1; $i <= $numberOfFloors; $i++) {
                    $floorDetail = new FloorDetail();
                    $floorDetail->user_property_id = $propertyId;
                    $floorDetail->wing_id = $wingDetail->id;
                    $floorDetail->save();
                    $floorUnitCounts[] = ['floorId' => $floorDetail->id,'unit' => null];
            }
            return response()->json([
                'status' => 'success',
                'msg' => null,
                'wingId' => $wingDetail->id,
                'floorUnitDetails' => null,
                'floorUnitCounts' => $floorUnitCounts
            ], 200);
        }
        else{
            $floorUnitDetails= [];
            foreach($floorUnitCounts as $floorUnitCount)
            {
                $floorDetail = FloorDetail::where('id',$floorUnitCount['floorId'])->update(['total_units' => $floorUnitCount['unit']]);
                $unitDetails = [];
                for ($j = 1; $j <= $floorUnitCount['unit']; $j++) {
                    $unitDetail = new UnitDetail();
                    $unitDetail->user_property_id = $propertyId;
                    $unitDetail->wing_id = $wingId;
                    $unitDetail->floor_id = $floorUnitCount['floorId'];
                    $unitDetail->save();
                    $unitDetails[] = ['unitId' => $unitDetail->id];
            }
            $floorUnitDetails[] = ['floorId' => $floorUnitCount['floorId'], 'unitDetails' => $unitDetails];
        }

            return response()->json([
                'status' => 'success',
                'msg' => null,
                'wingId' => $wingId,
                'floorUnitDetails' => $floorUnitDetails,
                'floorUnitCounts' => null
            ], 200);
    }
    }catch (\Exception $e) {
            $errorFrom = 'addWingDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ], 400);
        }
    }
    public function addUnitDetails(Request $request)
    {
        try
        {
        $unitStartNumber = $request->input('unitStartNumber');
        $floorDetailsArray = $request->input('floorUnitDetails');


            foreach($floorDetailsArray as $index => $floorDetail)
            {
                $currentStartNumber = (string)$unitStartNumber;
                $unitLength = strlen($currentStartNumber);
                if($unitLength == 3)
                {
                    $currentFloorStartNumber = $index * 100 +$unitStartNumber;
                    foreach($floorDetail['unitDetails'] as $UnitDetail)
                {
                    UnitDetail::where('id',$UnitDetail['unitId'])->update(['name'=>$currentFloorStartNumber,'unit_size'=>$UnitDetail['unitSize']]);
                    $currentFloorStartNumber++;
                }
                }
                elseif($unitLength == 4)
                {
                    $currentFloorStartNumber = $index * 1000 + $unitStartNumber;
                    foreach($floorDetail['unitDetails'] as $UnitDetail)
                {
                    UnitDetail::where('id',$UnitDetail['unitId'])->update(['name'=>$currentFloorStartNumber,'unit_size'=>$UnitDetail['unitSize']]);
                    $currentFloorStartNumber++;
                }
                }
                else{
                    $currentFloorStartNumber =  $unitStartNumber;
                    foreach($floorDetail['unitDetails'] as $UnitDetail)
                {
                    UnitDetail::where('id',$UnitDetail['unitId'])->update(['name'=>$currentFloorStartNumber,'unit_size'=>$UnitDetail['unitSize']]);
                    $currentFloorStartNumber++;
                }
                $unitStartNumber = $currentFloorStartNumber;
                }
            }
            return response()->json([
                'status' => 'success',
                'msg' => 'Unit details added successfully.',
            ],200);
        }
        catch (\Exception $e) {
            $errorFrom = 'addUnitDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ],400);
        }
    }

    public function getWingDetails($propertyId)
    {
        $WingDetails = WingDetail::with('floorDetails.unitDetails')->where('user_property_id',$propertyId)->get();
        return $WingDetails;
    }

}
