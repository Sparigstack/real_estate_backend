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
use App\Models\Customer;
use App\Models\Lead;
use App\Models\State;



class WingController extends Controller
{
    public function getWingsBasicDetails($wid)
    {
        // $fetchWings = WingDetail::with([
        //     'floorDetails' => function ($query) {
        //         $query->orderBy('id', 'desc');
        //         $query->with('unitDetails');
        //     }
        // ])
        //     ->withCount(['unitDetails', 'floorDetails'])
        //     ->where('id', $wid)
        //     ->first();


        // 'floorDetails' => function ($query) {
        //     // Order by 'id' in descending order
        //     $query->orderBy('id', 'desc')
        //           ->with(['unitDetails' => function ($query) {
        //               // Eager load the related lead units and their associated leads
        //               $query->with(['leadUnits.lead' => function ($query) {
        //                   // Select necessary fields from the lead
        //                   $query->select('id', 'name');
        //               }]);
        //           }]);
        // }

        // 'floorDetails.unitDetails.leadUnits' => function ($query) {
        //     $query->select('id', 'lead_id', 'unit_id');
        // },
        // 'floorDetails.unitDetails.paymentTransactions' => function ($query) {
        //     $query->select('id', 'unit_id', 'amount', 'payment_type','booking_status'); // Include relevant fields
        // }

        $fetchWings = WingDetail::with([
            'floorDetails.unitDetails' => function ($query) {
                $query->with([
                    'leadUnits',
                    'paymentTransactions' // Ensure payment transactions are loaded
                ]);
            }
        ])
        ->withCount(['unitDetails', 'floorDetails'])
        ->where('id', $wid)
        ->first();
    
        // Prepare the response
        if ($fetchWings) {
            foreach ($fetchWings->floorDetails as $floor) {
                foreach ($floor->unitDetails as $unit) {
                    $unitLeads = $unit->leadUnits;
    
                    // Calculate total interested leads count
                    $unit->interested_lead_count = $unitLeads->sum(function ($leadUnit) {
                        return count(explode(',', $leadUnit->interested_lead_id));
                    });
    
                    $unit->booking_status = $unitLeads->pluck('booking_status')->first();
    
                    // Initialize total paid amount
                    $totalPaidAmount = 0;
    
                    // Check payment transactions for this unit
                    $paymentTransactions = $unit->paymentTransactions;
    
                    if ($paymentTransactions->isNotEmpty()) {
                        // Get the first transaction
                        $firstTransaction = $paymentTransactions->first();
    
                        // Add token_amt from the first transaction if it exists
                        if ($firstTransaction->token_amt) {
                            $totalPaidAmount += $firstTransaction->token_amt;
                        }
    
                        // Sum next_payable_amt from the first transaction and all subsequent ones
                        foreach ($paymentTransactions as $index => $transaction) {
                            if ($index === 0 && $firstTransaction->next_payable_amt) {
                                $totalPaidAmount += $firstTransaction->next_payable_amt; // Add next payable amt from first
                            } elseif ($index > 0 && $transaction->next_payable_amt) {
                                $totalPaidAmount += $transaction->next_payable_amt; // Add next payable amt from subsequent transactions
                            }
                        }
                    }
    
                    // Assign total paid amount to the unit
                    $unit->total_paid_amount = $totalPaidAmount;

                    // Set lead_units as an object with allocated_name


                    $allocatedEntities = [];
                    foreach ($unitLeads as $leadUnit) {
                        // Retrieve and format leads
                        if ($leadUnit->allocated_lead_id) {
                            $leadIds = explode(',', $leadUnit->allocated_lead_id);
                            $allocatedLeads = Lead::whereIn('id', $leadIds)->get(['id', 'name']);
                            foreach ($allocatedLeads as $lead) {
                                $allocatedEntities[] = [
                                    'allocated_lead_id' => $lead->id,
                                    'allocated_name' => $lead->name
                                ];
                            }
                        }
        
                        // Retrieve and format customers
                        if ($leadUnit->allocated_customer_id) {
                            $customerIds = explode(',', $leadUnit->allocated_customer_id);
                            $allocatedCustomers = Customer::whereIn('id', $customerIds)->get(['id', 'name']);
                            foreach ($allocatedCustomers as $customer) {
                                $allocatedEntities[] = [
                                    'allocated_customer_id' => $customer->id,
                                    'allocated_name' => $customer->name
                                ];
                            }
                        }
                    }
        
                    // Assign the aggregated allocated entities array to each unit
                    $unit->allocated_entities = $allocatedEntities;
    
                   
                    unset($unit->leadUnits);
                }
            }
    
            // Return the modified result without hidden fields
            return $fetchWings->makeHidden(['property_id', 'created_at', 'updated_at']);
        }
    
        return null;
        // return $fetchWings ? $fetchWings->makeHidden(['property_id', 'created_at', 'updated_at']) : null;
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
                // UnitDetail::where('id', $unitId)->forceDelete();

                $deletedUnit = UnitDetail::where('id', $unitId)->first();
                if ($deletedUnit) {
                    $deletedUnitNumber = (int)$deletedUnit->name; // Assuming the name is a string number like '302'
                    $floorId = $deletedUnit->floor_id;

                    // Delete the unit
                    $deletedUnit->forceDelete();

                    // Fetch the units on the same floor with unit numbers greater than the deleted one
                    $unitsToShift = UnitDetail::where('floor_id', $floorId)
                        ->where('name', '>', $deletedUnitNumber) // Get units with names (numbers) greater than the deleted one
                        ->orderBy('name') // Order by name to handle shifts sequentially
                        ->get();

                    // Shift the unit numbers
                    foreach ($unitsToShift as $unit) {
                        $oldUnitNumber = (int)$unit->name;
                        $newUnitNumber = $oldUnitNumber - 1; // Decrement the unit number
                        $unit->update(['name' => $newUnitNumber]);
                        // echo "   " .$unit->id."-".$newUnitNumber ." ";
                    }
                }

                // // Fetch the current floor's units in ascending order
                // $units = UnitDetail::where('floor_id', $floorId)
                //     ->orderBy('id', 'asc')
                //     ->get();


                // // Find the unit to be deleted
                // $unitToDelete = UnitDetail::find($unitId);

                // if ($unitToDelete) {
                //     $deletedUnitNumber = (int)$unitToDelete->name;

                //     // Delete the unit
                //     // $unitToDelete->forceDelete();

                //      // Re-number the remaining units
                //      $unitIndex = 1;
                //     foreach ($units as $unit) {
                //         // Only renumber units that come after the deleted unit
                //         if ((int)$unit->name > $deletedUnitNumber) {
                //             // $newUnitNumber = sprintf('%d%02d', $floorId, $unitIndex);
                //             $newUnitNumber = sprintf('%d%02d', (int)($deletedUnitNumber / 100), $unitIndex);
                //             // $unit->update(['name' => $newUnitNumber]);
                //             $unitIndex++;
                //             echo "   " .$unit->id."-".$newUnitNumber ." ";
                //         }
                //     }
                // }
            } elseif ($actionId == 3) // floor delete
            {
                // First, get the Wing ID associated with the floor
                $wingId = FloorDetail::where('id', $floorId)->value('wing_id');

                $floors = FloorDetail::where('wing_id', $wingId)
                    ->orderBy('id') // Assuming 'id' is a unique identifier for sorting
                    ->pluck('id')
                    ->toArray();

                // Find the index of the floor to be deleted
                $floorIndex = array_search($floorId, $floors);


                // Calculate the position of the floor being deleted
                if ($floorIndex !== false) {
                    $deletingfloorPosition = $floorIndex + 1; // Convert to 1-based index

                    $newUnitNumberPrefix = sprintf('%d', ($deletingfloorPosition) * 100); // Adjust unit prefix accordingly
                    // Loop through the remaining floors starting from the deleted floor's position
                    for ($i = $floorIndex + 1; $i < count($floors); $i++) {
                        $currentFloorId = $floors[$i];
                        // Calculate new unit number prefix based on deleting floor position

                        // Update unit names for this floor
                        $units = UnitDetail::where('floor_id', $currentFloorId)->get();
                        $unitIndex = 1;
                        foreach ($units as $unit) {
                            $oldUnitNumber = (int)$unit->name; // Assuming name is stored as a string of integers
                            //   echo $oldUnitNumber;
                            $newUnitNumber = sprintf('%d%02d', $i, $unitIndex);
                            // $newUnitNumber = sprintf('%d%02d', (int)($newUnitNumberPrefix / 100), ($oldUnitNumber % 100)); // Keep the last two digits the same
                            $unit->update(['name' => $newUnitNumber]);
                            $unitIndex++;
                            // echo "   " .$unit->id."-".$newUnitNumber ." ".$i;

                        }
                    }

                    WingDetail::where('id', $wingId)->decrement('total_floors');
                    UnitDetail::where('floor_id', $floorId)->forceDelete();
                    FloorDetail::where('id', $floorId)->forceDelete();
                }
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
