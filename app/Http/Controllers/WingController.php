<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FloorDetail;
use App\Models\PropertyDetail;
use App\Models\UnitDetail;
use App\Models\UserProperty;
use App\Models\WingDetail;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\PaymentTransaction;






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
                    'leadCustomerUnits',
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
                    $unitLeads = $unit->leadCustomerUnits;

                    // Calculate total interested leads count
                    $unit->interested_lead_count = $unitLeads->sum(function ($leadCustomerUnits) {
                        return count(explode(',', $leadCustomerUnits->interested_lead_id));
                    });

                    $unit->booking_status = $unitLeads->pluck('booking_status')->first();

                    // Initialize total paid amount
                    $totalPaidAmount = 0;

                    // Check payment transactions for this unit
                    $paymentTransactions = $unit->paymentTransactions;

                    if ($paymentTransactions->isNotEmpty()) {
                        // Get the first transaction
                        $filteredTransactions  = $paymentTransactions->where('payment_status', 2);

                        // Add token_amt from the first transaction if it exists
                        $firstTransaction = $filteredTransactions->first();

                        // Add token_amt from the first transaction if it exists
                        if ($firstTransaction && $firstTransaction->token_amt) {
                            $totalPaidAmount += $firstTransaction->token_amt;
                        }

                        // Sum next_payable_amt from the first transaction and all subsequent ones
                        foreach ($filteredTransactions as $index => $transaction) {
                            if ($index == 0 && $firstTransaction && $firstTransaction->next_payable_amt) {
                                $totalPaidAmount += $firstTransaction->next_payable_amt; // Add next payable amt from the first
                            } elseif ($index > 0 && $transaction->next_payable_amt) {
                                $totalPaidAmount += $transaction->next_payable_amt; // Add next payable amt from subsequent transactions
                            }
                        }
                    }

                    // Assign total paid amount to the unit
                    $unit->total_paid_amount = $totalPaidAmount;

                    // Set lead_units as an object with allocated_name


                    $allocatedEntities = [];
                    foreach ($unitLeads as $leadCustomerUnits) {
                        // Retrieve and format leads
                        if ($leadCustomerUnits->leads_customers_id) {
                            $leadIds = explode(',', $leadCustomerUnits->leads_customers_id);
                            $allocatedLeads = LeadCustomer::whereIn('id', $leadIds)->get(['id', 'name']);
                            foreach ($allocatedLeads as $lead) {
                                $allocatedEntities[] = [
                                    'allocated_lead_id' => $lead->id,
                                    'allocated_name' => $lead->name
                                ];
                            }
                        }

                        // // Retrieve and format customers
                        // if ($leadUnit->allocated_customer_id) {
                        //     $customerIds = explode(',', $leadUnit->allocated_customer_id);
                        //     $allocatedCustomers = Customer::whereIn('id', $customerIds)->get(['id', 'name']);
                        //     foreach ($allocatedCustomers as $customer) {
                        //         $allocatedEntities[] = [
                        //             'allocated_customer_id' => $customer->id,
                        //             'allocated_lead_id' => null,
                        //             'allocated_name' => $customer->name
                        //         ];
                        //     }
                        // }
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
    
            // Check if there are any existing floors
            $lastFloor = FloorDetail::where('wing_id', $wingId)
                ->orderBy('id', 'desc')
                ->first();
    
            $lastUnitNumber = 0;
            $unitGap = 10; // Default gap (for first time)
    
            $lastFloorexists=false;
            if ($lastFloor) {
                $lastFloorexists=true;
                // Retrieve the last floor's unit details if any exist
                $lastFloorUnits = UnitDetail::where('floor_id', $lastFloor->id)->get();
                
               
                if (count($lastFloorUnits) > 0) {
                    // Get the last unit number of the previous floor
                    $lastUnitName = $lastFloorUnits->first()->name;
                  
                    $lastUnitNumber = (int) filter_var($lastUnitName, FILTER_SANITIZE_NUMBER_INT);
    
                    // Calculate the gap based on the number of digits in the last unit number
                    $lastUnitLength = strlen($lastUnitNumber);
                    switch ($lastUnitLength) {
                        case 1:
                        case 2:
                            $unitGap = 10;
                            break;
                        case 3:
                            $unitGap = 100; //Default gap in case of unexpected scenarios
                            break;
                        case 4:
                            $unitGap = 1000;
                            break;
                        default:
                            $unitGap = 10; 
                            break;
                    }
                }
            }
    
            // Add the floors
            for ($floorNumber = 1; $floorNumber <= $numberOfFloors; $floorNumber++) {
                $floorDetail = new FloorDetail();
                $floorDetail->property_id = $propertyId;
                $floorDetail->wing_id = $wingId;
                $floorDetail->save();
    
                $startingUnitNumber = $lastUnitNumber + $unitGap;
    
                if ($sameUnitsFlag == 1) {
                    // Add units if the flag is set for same units on each floor
                    for ($unitIndex = 1; $unitIndex <= $sameUnitCount; $unitIndex++) {
                        $unitDetail = new UnitDetail();
                        $unitDetail->property_id = $propertyId;
                        $unitDetail->wing_id = $wingId;
                        $unitDetail->floor_id = $floorDetail->id;
                        if($lastFloorexists==false){
                            $unitDetail->name = sprintf('%d%02d', $floorCountOfWing, $unitIndex);
                        }else{
                            $unitDetail->name = ($startingUnitNumber + $unitIndex - 1);
                        }
                        $unitDetail->save();
                    }
                } else {
                    // Add units based on provided unit details for each floor
                    foreach ($unitDetails as $unit) {
                        if ($unit['floorNo'] == $floorNumber) {
                            for ($unitIndex = 1; $unitIndex <= $unit['unitCount']; $unitIndex++) {
                                $unitDetail = new UnitDetail();
                                $unitDetail->property_id = $propertyId;
                                $unitDetail->wing_id = $wingId;
                                $unitDetail->floor_id = $floorDetail->id;
                                if($lastFloorexists==false){
                                    $unitDetail->name = sprintf('%d%02d', $floorCountOfWing, $unitIndex);
                                }else{
                                    $unitDetail->name = ($startingUnitNumber + $unitIndex - 1);
                                }
                                $unitDetail->save();
                            }
                        }
                    }
                }
    
                $floorCountOfWing++;
                $lastUnitNumber = $startingUnitNumber + $unitIndex - 1;
            }
    
            return response()->json([
                'status' => 'success',
                'message' => 'Floor details added successfully.',
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

    public function addSimilarWingDetails(Request $request)
    {
        try {
            // Retrieve request data
            $selectedWingId = $request->input('selectedwingId');
            $propertyId = $request->input('propertyid');
            $wingId = $request->input('currentwingid');

            // Step 1: Get the details of the selected wing
            $selectedWing = WingDetail::where('id', $selectedWingId)
                ->where('property_id', $propertyId)
                ->first();

            if (!$selectedWing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected wing not found.',
                ], 200);
            }

            // Step 2: Duplicate floor details
            $floorDetails = FloorDetail::where('wing_id', $selectedWingId)
                ->where('property_id', $propertyId)
                ->get();

            $newFloors = [];
            foreach ($floorDetails as $floor) {
                $newFloor = FloorDetail::create([
                    'property_id' => $propertyId,
                    'wing_id' => $wingId,
                    'floor_size' => $floor->floor_size,
                    'pent_house_status' => $floor->pent_house_status, // if you have this field
                ]);
                $newFloors[$floor->id] = $newFloor; // Map old floor ID to new floor
            }

            // Step 3: Duplicate unit details for each new floor
            foreach ($newFloors as $oldFloorId => $newFloor) {
                // Get the units for the current old floor ID
                $unitDetails = UnitDetail::where('floor_id', $oldFloorId)
                    ->where('property_id', $propertyId)
                    ->get();

                foreach ($unitDetails as $unit) {
                    UnitDetail::create([
                        'property_id' => $propertyId,
                        'wing_id' => $wingId,
                        'floor_id' => $newFloor->id, // Assign the new floor ID here
                        'name' => $unit->name,
                        'status_id' => $unit->status_id,
                        'square_feet' => $unit->square_feet,
                        'price' => $unit->price,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Step 4: Update the total floors in the wing_details table
            $updateCurrentWing = WingDetail::where('id', $wingId)
                ->where('property_id', $propertyId)
                ->first();
            $updateCurrentWing->update([
                'total_floors' => $selectedWing->total_floors
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Wing details added successfully with new floors and units.',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addSimilarWingDetails';
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
            $flagforvilla=$request->input('flagforvilla'); //0 means commercials floors and wings , 1 means without wings floors
            $wingDetails = $request->input('wingDetails');
            $unitDetails= $request->input('unitDetails');
            $propertyId=$request->input('propertyId');

            if ($propertyId) {
                if($flagforvilla==0){
                    foreach ($wingDetails as $data) {
                        foreach ($data['unit_details'] as $unitData) {
                            UnitDetail::where('property_id',$propertyId)->where('id', $unitData['unitId'])->update([ 'square_feet' => $unitData['square_feet'], 'price' => $unitData['price']]);
                        }
                    }
                }elseif($flagforvilla==1){
                    foreach ($unitDetails as $unitData) {
                        UnitDetail::where('property_id',$propertyId)->where('id', $unitData['unitId'])->update(['square_feet' => $unitData['square_feet'], 'price' => $unitData['price']]);
                    }
                }
                return response()->json([
                    'status' => 'success',
                    'message' => null,
                ], 200);
            }else{
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid property id',
                ], 200);
            }
         
            

           
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
            // $actionId = $request->input('actionId');
            $unitId = $request->input('unitId');
            // $floorId = $request->input('floorId');
            // $name = $request->input('name');
            $unitSize = $request->input('unitSize');
            $price = $request->input('price');
            UnitDetail::where('id', $unitId)->update(['square_feet' => $unitSize, 'price' => $price]);



            // Retrieve all payment transactions for the unit
            $paymentTransactions = PaymentTransaction::where('unit_id', $unitId)
                ->where('payment_status', 2)
                ->get();

            // Calculate the total for next_payable_amt
            $totalNextPayableAmt = $paymentTransactions->sum('next_payable_amt');

            // Retrieve the first payment transaction to include token_amt
            $firstPaymentTransaction = $paymentTransactions->first();
            if ($firstPaymentTransaction) {
                // Add the token_amt of the first entry to the total next_payable_amt
                $totalNextPayableAmt += $firstPaymentTransaction->token_amt;
            }

            $unitdata = UnitDetail::where('id', $unitId)->first();
            $leadUnit = LeadCustomerUnit::where('unit_id', $unitId)->first();


            // Update LeadUnit booking status if totalNextPayableAmt reaches or exceeds the required amount
            if ($unitdata->price != '' && $leadUnit != '') {
                // if ($lastPaymentTransaction && $totalNextPayableAmt >= $lastPaymentTransaction->amount) {
                //     $leadUnit->booking_status = 3; // Mark as confirmed
                //     $leadUnit->save();
                // }

                // if ($totalNextPayableAmt > $unitdata->price) {
                //     $leadUnit->booking_status = 4;// Mark as pending                  
                // }elseif($totalNextPayableAmt < $unitdata->price){
                //     $leadUnit->booking_status = 4; // Mark as pending   
                // }else if($unitdata->price = $totalNextPayableAmt ){
                //     $leadUnit->booking_status = 3;// Mark as booked  
                // }
                if ($unitdata->price == 0) {
                    // If unit price is 0, set booking status to pending
                    $leadUnit->booking_status = 4; // Mark as pending
                } elseif ($totalNextPayableAmt >= $unitdata->price) {
                    // If totalNextPayableAmt is greater than or equal to unit price, mark as confirmed
                    if ($unitdata->price <= 0) {
                        $leadUnit->booking_status = 4; // Mark as pending
                    } else {
                        $leadUnit->booking_status = 3; // Mark as confirmed
                    }
                } elseif ($totalNextPayableAmt < $unitdata->price) {
                    // If totalNextPayableAmt is less than unit price

                    $leadUnit->booking_status = 4; // Mark as pending

                }
                $leadUnit->save();
            }
            // if ($actionId == 1) // unit update
            // {
            //     UnitDetail::where('id', $unitId)->update(['square_feet' => $unitSize, 'price' => $price]);
            // } elseif ($actionId == 2) // unit delete
            // {
            //     // UnitDetail::where('id', $unitId)->forceDelete();


            //     //actual code
            //     $deletedUnit = UnitDetail::where('id', $unitId)->first();
            //     if ($deletedUnit) {
            //         $deletedUnitNumber = (int)$deletedUnit->name; // Assuming the name is a string number like '302'
            //         $floorId = $deletedUnit->floor_id;

            //         // Delete the unit
            //         $deletedUnit->forceDelete();

            //         // Fetch the units on the same floor with unit numbers greater than the deleted one
            //         $unitsToShift = UnitDetail::where('floor_id', $floorId)
            //             ->where('name', '>', $deletedUnitNumber) // Get units with names (numbers) greater than the deleted one
            //             ->orderBy('name') // Order by name to handle shifts sequentially
            //             ->get();

            //         // Shift the unit numbers
            //         foreach ($unitsToShift as $unit) {
            //             $oldUnitNumber = (int)$unit->name;
            //             $newUnitNumber = $oldUnitNumber - 1; // Decrement the unit number
            //             $unit->update(['name' => $newUnitNumber]);
            //             // echo "   " .$unit->id."-".$newUnitNumber ." ";
            //         }
            //     }
            // end actual code

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
            // } elseif ($actionId == 3) // floor delete
            // {

            //     // actual code
            //     // First, get the Wing ID associated with the floor
            //     $wingId = FloorDetail::where('id', $floorId)->value('wing_id');

            //     $floors = FloorDetail::where('wing_id', $wingId)
            //         ->orderBy('id') // Assuming 'id' is a unique identifier for sorting
            //         ->pluck('id')
            //         ->toArray();

            //     // Find the index of the floor to be deleted
            //     $floorIndex = array_search($floorId, $floors);


            //     // Calculate the position of the floor being deleted
            //     if ($floorIndex !== false) {
            //         $deletingfloorPosition = $floorIndex + 1; // Convert to 1-based index

            //         $newUnitNumberPrefix = sprintf('%d', ($deletingfloorPosition) * 100); // Adjust unit prefix accordingly
            //         // Loop through the remaining floors starting from the deleted floor's position
            //         for ($i = $floorIndex + 1; $i < count($floors); $i++) {
            //             $currentFloorId = $floors[$i];
            //             // Calculate new unit number prefix based on deleting floor position

            //             // Update unit names for this floor
            //             $units = UnitDetail::where('floor_id', $currentFloorId)->get();
            //             $unitIndex = 1;
            //             foreach ($units as $unit) {
            //                 $oldUnitNumber = (int)$unit->name; // Assuming name is stored as a string of integers
            //                 //   echo $oldUnitNumber;
            //                 $newUnitNumber = sprintf('%d%02d', $i, $unitIndex);
            //                 // $newUnitNumber = sprintf('%d%02d', (int)($newUnitNumberPrefix / 100), ($oldUnitNumber % 100)); // Keep the last two digits the same
            //                 $unit->update(['name' => $newUnitNumber]);
            //                 $unitIndex++;
            //                 // echo "   " .$unit->id."-".$newUnitNumber ." ".$i;

            //             }
            //         }

            //         WingDetail::where('id', $wingId)->decrement('total_floors');
            //         UnitDetail::where('floor_id', $floorId)->forceDelete();
            //         FloorDetail::where('id', $floorId)->forceDelete();
            //     }
            // }
            // end actual code
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

    public function getWingsWithUnitsAndFloors($pid)
    {
        try {
            if ($pid !== 'null') {
                $wings = WingDetail::where('property_id', $pid)
                    ->whereHas('unitDetails')  // Ensure that there are related unit_details
                    ->whereHas('floorDetails') // Ensure that there are related floor_details
                    ->get(['id', 'name']);  // Only select id and name columns

                return  $wings;
            }else{
                return null;
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getWingsWithUnitsAndFloors';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }
}
