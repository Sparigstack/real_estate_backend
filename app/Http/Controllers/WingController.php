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
use App\Models\Country;
use App\Models\State;


class WingController extends Controller
{
    public function getWingsBasicDetails($wid)
    {
        $fetchWings = WingDetail::with(['unitDetails', 'floorDetails'])
            ->withCount(['unitDetails', 'floorDetails'])
            ->where('id', $wid)
            ->get();
        return $fetchWings->makeHidden(['property_id', 'created_at', 'updated_at']);
    }

    public function AddWingsFloorDetails(Request $request)
    {
        // try {
            $numberOfFloors = $request->input('numberOfFloors');
            $sameUnitsFlag = $request->input('sameUnitsFlag');
            $unitDetails = $request->input('unitDetails');
            $wingId = $request->input('wingId');
            $propertyId = $request->input('propertyId');
            $sameUnitCount = $request->input('sameUnitCount');
            
            for ($floorNumber = 1; $floorNumber <= $numberOfFloors; $floorNumber++) {
                // Create floor detail
                $floorDetail = new FloorDetail();
                $floorDetail->property_id = $propertyId;
                $floorDetail->wing_id = $wingId;
                $floorDetail->save();
            
                if ($sameUnitsFlag == 1) {
                    // If the same unit count flag is set, add the same number of units to each floor
                    for ($unitIndex = 1; $unitIndex <= $sameUnitCount; $unitIndex++) {
                        $unitDetail = new UnitDetail();
                        $unitDetail->property_id = $propertyId;
                        $unitDetail->wing_id = $wingId;
                        $unitDetail->floor_id = $floorDetail->id;
                        $unitDetail->save();
                    }
                } else {
                    // If the units are different for each floor
                    foreach ($unitDetails as $unit) {
                        if ($unit['floorNo'] == $floorNumber) {
                            for ($unitIndex = 1; $unitIndex <= $unit['unitCount']; $unitIndex++) {
                                $unitDetail = new UnitDetail();
                                $unitDetail->property_id = $propertyId;
                                $unitDetail->wing_id = $wingId;
                                $unitDetail->floor_id = $floorDetail->id;
                                $unitDetail->save();
                            }
                        }
                    }
                }
            }
            
            return response()->json([
                'status' => 'success',
                'message' => null,
            ], 200);
            
        // } catch (\Exception $e) {
        //     $errorFrom = 'AddWingsFloorDetails';
        //     $errorMessage = $e->getMessage();
        //     $priority = 'high';
        //     Helper::errorLog($errorFrom, $errorMessage, $priority);
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'something went wrong',
        //     ], 400);
        // }
    }

}