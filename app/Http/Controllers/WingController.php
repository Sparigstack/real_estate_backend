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

            $floorCountOfWing = WingDetail::where('id', $wingId)->pluck('total_floors')->first();
            WingDetail::where('id', $wingId)->update(['total_floors' => $floorCountOfWing + $numberOfFloors]);


            $floorCountOfWing = $floorCountOfWing + 1;

            for ($floorNumber = 1; $floorNumber <= $numberOfFloors; $floorNumber++) {
                $floorDetail = new FloorDetail();
                $floorDetail->property_id = $propertyId;
                $floorDetail->wing_id = $wingId;
                $floorDetail->save();


                if ($sameUnitsFlag == 1) {
                    for ($unitIndex = 1; $unitIndex <= $sameUnitCount; $unitIndex++) {
                        $unitDetail = new UnitDetail();
                        $unitDetail->property_id = $propertyId;
                        $unitDetail->wing_id = $wingId;
                        $unitDetail->floor_id = $floorDetail->id;
                        $unitDetail->name = sprintf('%d%02d', $floorCountOfWing, $unitIndex);
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
                                $unitDetail->name = sprintf('%d%02d', $floorCountOfWing, $unitIndex);
                                $unitDetail->save();
                            }
                        }
                    }
                }
                $floorCountOfWing++;
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
            // $name = $request->input('name');
            $unitSize = $request->input('unitSize');
            $price = $request->input('price');
            if ($actionId == 1) // unit update
            {
                UnitDetail::where('id', $unitId)->update(['square_feet' => $unitSize, 'price' => $price]);
            } elseif ($actionId == 2) // unit delete
            {
                UnitDetail::where('id', $unitId)->forceDelete();
            } elseif ($actionId == 3) // floor delete
            {
                // First, get the Wing ID associated with the floor
                $wingId = FloorDetail::where('id', $floorId)->value('wing_id');
               
                WingDetail::where('id', $wingId)->decrement('total_floors');


                $floors = FloorDetail::where('wing_id', $wingId)
                    ->orderBy('id') // Assuming 'id' is a unique identifier for sorting
                    ->pluck('id')
                    ->toArray();

                // Find the index of the floor to be deleted
                $floorIndex = array_search($floorId, $floors);


                // Calculate the position of the floor being deleted
                if ($floorIndex !== false) {
                    $deletingfloorPosition = $floorIndex + 1; // Convert to 1-based index


                    // Loop through the remaining floors starting from the deleted floor's position
                    for ($i = $floorIndex + 1; $i < count($floors); $i++) {
                        $currentFloorId = $floors[$i];
                        // Calculate new unit number prefix based on deleting floor position
                        $newUnitNumberPrefix = sprintf('%d', ($deletingfloorPosition) * 100); // Adjust unit prefix accordingly

                        // Update unit names for this floor
                        $units = UnitDetail::where('floor_id', $currentFloorId)->get();

                        foreach ($units as $unit) {
                            $oldUnitNumber = (int)$unit->name; // Assuming name is stored as a string of integers
                            //   echo $oldUnitNumber;
                            $newUnitNumber = sprintf('%d%02d', (int)($newUnitNumberPrefix / 100), ($oldUnitNumber % 100)); // Keep the last two digits the same
                            $unit->update(['name' => $newUnitNumber]);
                        }
                    }

                    UnitDetail::where('floor_id', $floorId)->forceDelete();
                    FloorDetail::where('id', $floorId)->forceDelete();  
                }

                // // Fetch all floors for the wing in ascending order
                // $floors = FloorDetail::where('wing_id', $wingId)
                // ->orderBy('id') // Assuming 'id' is a unique identifier for sorting
                // ->pluck('id')
                // ->toArray();

                // // Find the index of the floor to be deleted
                // $floorIndex = array_search($floorId, $floors);

                // // If the floor index is found, you can calculate its position (1-based index)
                // if ($floorIndex !== false) {
                //     $deletingfloorPosition = $floorIndex + 1; // Convert to 1-based index

                //     // Update unit names for floors above the deleted floor
                // for ($i = $floorIndex + 1; $i < count($floors); $i++) {
                //     $currentFloorId = $floors[$i];

                //     // Fetch units for the current floor
                //     $units = UnitDetail::where('floor_id', $currentFloorId)->get();

                //     // Update the unit names to shift them down
                //     foreach ($units as $unit) {
                //         // Assuming the unit names follow the format 'FloorNumberUnitIndex'
                //         // We need to adjust the name based on the new position
                //         $newUnitName = sprintf('%d%02d', $deletingfloorPosition, (intval(substr($unit->name, -2)) + 1)); // Shift unit numbers down
                //         $unit->name = $newUnitName;
                //         $unit->save();
                //     }
                // }


                // return  $deletingfloorPosition;


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


    public function addNewUnitForFloor(Request $request)
    {
        try {
            $floorId = $request->input('floorId');

            // Retrieve floor details to get property_id and wing_id
            $floor = FloorDetail::findOrFail($floorId);
            $propertyId = $floor->property_id;
            $wingId = $floor->wing_id;

            // Find the maximum unit name (number) for this floor
            $maxUnit = UnitDetail::where('floor_id', $floorId)
                ->where('property_id', $propertyId)
                ->where('wing_id', $wingId)
                ->max('name');

            // Increment the unit number to get the new unit name
            $newUnitName = $maxUnit ? (int)$maxUnit + 1 : ($floorId * 100 + 1); // assuming units start at floorId*100+1

            // Create the new unit
            $unit = new UnitDetail();
            $unit->property_id = $propertyId;
            $unit->wing_id = $wingId;
            $unit->floor_id = $floorId;
            $unit->name = (string)$newUnitName; // Cast to string if necessary
            // $unit->status_id = 1; // Set default status, adjust as needed
            $unit->square_feet = 0; // Default square feet, adjust as needed
            $unit->price = 0; // Default price, adjust as needed
            $unit->save();

            return response()->json([
                'status' => 'success',
                'message' => null,
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addNewUnitFloor';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }
}
