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
        $fetchWings = WingDetail::with([
            'floorDetails' => function ($query) {
                $query->orderBy('id', 'desc');
                $query->with('unitDetails');
            }
        ])
            ->withCount(['unitDetails', 'floorDetails'])
            ->where('id', $wid)
            ->first();

        return $fetchWings ? $fetchWings->makeHidden(['property_id', 'created_at', 'updated_at']) : null;
    }


    public function addWingDetails(Request $request)
    {

        try {
            // Validate the incoming request
            $request->validate([
                'totalWings' => 'required|integer|min:1',
                'propertyId' => 'required|integer|exists:user_properties,id',
                'wingsArray' => 'required|array',
                'wingsArray.*.wingName' => 'required|string|max:255',
            ]);

            // Retrieve the property ID from the user_properties table
            $userProperty = UserProperty::findOrFail($request->propertyId);
            $propertyId = $userProperty->id;

            // Insert wings into the wing_details table
            foreach ($request->wingsArray as $wing) {
                $newWing =   WingDetail::create([
                    'property_id' => $propertyId,
                    'name' => $wing['wingName'],
                    'total_floors' => 0, // Set default or handle as needed
                ]);

                // Collect the details of the added wing
                $addedWings[] = [
                    'wingId' => $newWing->id,
                    'wingName' => $newWing->name,
                    'totalFloors' => $newWing->total_floors,
                ];
            }

            // Update the property_details table with total wings
            $propertyDetails = PropertyDetail::firstOrCreate(
                ['property_id' => $request->propertyId],
                ['total_wings' => 0] // Initialize total_wings if not exists
            );

            // Increment total_wings by the number of wings added
            $propertyDetails->total_wings += $request->totalWings;
            $propertyDetails->save();

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Wings added successfully',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addWingDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }

    public function addWingsFloorDetails(Request $request)
    {
        try {
            $numberOfFloors = $request->input('numberOfFloors');
            $sameUnitsFlag = $request->input('sameUnitsFlag');
            $unitDetails = $request->input('unitDetails');
            $wingId = $request->input('wingId');
            $propertyId = $request->input('propertyId');
            $sameUnitCount = $request->input('sameUnitCount');

            for ($floorNumber = 1; $floorNumber <= $numberOfFloors; $floorNumber++) {
                $floorDetail = new FloorDetail();
                $floorDetail->property_id = $propertyId;
                $floorDetail->wing_id = $wingId;
                $floorDetail->total_floors = $numberOfFloors;
                $floorDetail->save();

                if ($sameUnitsFlag == 1) {
                    for ($unitIndex = 1; $unitIndex <= $sameUnitCount; $unitIndex++) {
                        $unitDetail = new UnitDetail();
                        $unitDetail->property_id = $propertyId;
                        $unitDetail->wing_id = $wingId;
                        $unitDetail->floor_id = $floorDetail->id;
                        $unitDetail->name = sprintf('%d%02d', $floorNumber, $unitIndex);
                        $unitDetail->save();
                    }
                } else {
                    foreach ($unitDetails as $unit) {
                        if ($unit['floorNo'] == $floorNumber) {
                            for ($unitIndex = 1; $unitIndex <= $unit['unitCount']; $unitIndex++) {
                                $unitDetail = new UnitDetail();
                                $unitDetail->property_id = $propertyId;
                                $unitDetail->wing_id = $wingId;
                                $unitDetail->floor_id = $floorDetail->id;
                                $unitDetail->name = sprintf('%d%02d', $floorNumber, $unitIndex);
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

        } catch (\Exception $e) {
            $errorFrom = 'AddWingsFloorDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }


    public function bulkUpdatesForWingsDetails(Request $request)
    {
        try {
            $wingDetails = $request->input('wingDetails');
            $wingId = $request->input('wingId');
            foreach ($wingDetails as $data) {
                foreach ($data['unit_details'] as $unitData) {
                    UnitDetail::where('id', $unitData['unitId'])->update(['name' => $unitData['name'], 'square_feet' => $unitData['square_feet'], 'price' => $unitData['price']]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => null,
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'bulkUpdatesForWingsDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }

    public function updateWingDetails(Request $request)
    {
        try {
            $actionId = $request->input('actionId');
            $unitId = $request->input('unitId');
            $floorId = $request->input('floorId');
            $name = $request->input('name');
            $unitSize = $request->input('unitSize');
            $price = $request->input('price');
            if ($actionId == 1) // unit update
            {
                UnitDetail::where('id', $unitId)->update(['name' => $name, 'square_feet' => $unitSize, 'price' => $price]);
            } elseif ($actionId == 2) // unit delete
            {
                UnitDetail::where('id', $unitId)->forceDelete();
            } elseif ($actionId == 3) // wing delete
            {
                UnitDetail::where('floor_id', $floorId)->forceDelete();
                FloorDetail::where('id', $floorId)->forceDelete();
            }

            return response()->json([
                'status' => 'success',
                'message' => null,
            ], 200);

        } catch (\Exception $e) {
            $errorFrom = 'updateWingDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }


    public function getunitBasicDetails($uid)
    {
        $fetchUnitDetails = UnitDetail::where('id', $uid)->first();
        return $fetchUnitDetails;
    }
}