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
use App\Models\LeadCustomer;
use App\Models\State;



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
            $propertyImg = $request->input('property_img'); //base64
            $description = $request->input('description');
            $userId = $request->input('userId');
            $pincode = $request->input('pincode');
            $stateId = $request->input('state');
            $cityId = $request->input('city');
            $area = $request->input('area');


            if ($reraRegisteredNumber) {
                // $checkRegisterNumber = UserProperty::where('user_id', $userId)
                //     ->where('rera_registered_no', $reraRegisteredNumber)
                //     ->first();

                $checkRegisterNumber = UserProperty::where('rera_registered_no', $reraRegisteredNumber)
                    ->first();


                if ($checkRegisterNumber) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Property with this registered number already exists.',
                        'propertyId' => null,
                        'propertyName' => null
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
            $userProperty->state_id = $stateId;
            $userProperty->city_id = $cityId;
            $userProperty->area = $area;
            // $userProperty->property_img = $propertyImg;
            $userProperty->property_step_status = 1;
            $userProperty->save();

            // Handle image saving
            if ($propertyImg) {
                // Define the base properties folder path
                $baseFolderPath = public_path("properties");

                // Check if the base properties folder exists, if not, create it
                if (!file_exists($baseFolderPath)) {
                    mkdir($baseFolderPath, 0777, true);
                }

                // Define user-specific folder path
                $folderPath = public_path("properties/$userId/{$userProperty->id}");

                // Ensure user-specific directory exists
                if (!file_exists($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }

                // Decode base64 image
                $image_parts = explode(";base64,", $propertyImg);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);

                // Create unique file name
                $fileName = uniqid() . '.' . $image_type;

                // Save the image in the defined folder
                $filePath = $folderPath . '/' . $fileName;
                file_put_contents($filePath, $image_base64);

                // Save file path relative to the public folder
                $userProperty->property_img = "properties/$userId/{$userProperty->id}/$fileName";
                $userProperty->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => null,
                'propertyId' => $userProperty->id,
                'propertyName' => $userProperty->name
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addPropertyDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
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


    public function getPropertyWingsBasicDetails($pid)
    {

        try {
            // Fetch wings associated with the property
            $fetchWings = WingDetail::with(['unitDetails', 'floorDetails']) // Eager load unit details
                ->where('property_id', $pid)
                ->get();

            // Prepare the response structure
            $response = [
                'building_wings_count' => 0,
                'total_units' => 0,
                'wings' => [],
            ];

            if ($fetchWings->isNotEmpty()) {
                // Update the response structure with actual data
                $response['building_wings_count'] = $fetchWings->count();
                $response['total_units'] = $fetchWings->sum(function ($wing) {
                    return $wing->unitDetails->count(); // Count of units for each wing
                });
                $response['wings'] = $fetchWings->map(function ($wing) {
                    return [
                        'wing_id' => $wing->id,
                        'wing_name' => $wing->name,
                        'total_floors' => $wing->total_floors,
                        'total_units' => $wing->unitDetails->count(), // Count units in this wing
                    ];
                });
            }

            return response()->json($response);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getPropertyWingsBasicDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            // Return a consistent response structure with an error message
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching wings details',
            ], 400); // Return 500 status code for internal server error
        }
    }


    // public function addWingDetails(Request $request)
    // {
    //     try {
    //         $wingName = $request->input('wingName');
    //         $numberOfFloors = $request->input('numberOfFloors');
    //         $propertyId = $request->input('propertyId');
    //         $wingId = $request->input('wingId');
    //         $sameUnitFlag = $request->input('sameUnitFlag');
    //         $numberOfUnits = $request->input('numberOfUnits');
    //         $floorUnitCounts = $request->input('floorUnitCounts');

    //         if ($sameUnitFlag == 1) {
    //             $checkWing = WingDetail::where('user_property_id', $propertyId)->where('name', $wingName)->first();
    //             if (isset($checkWing)) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Same wing name exist.',
    //                     'wingId' => null,
    //                     'floorUnitDetails' => null
    //                 ], 400);
    //             }
    //             $wingDetail = new WingDetail();
    //             $wingDetail->user_property_id = $propertyId;
    //             $wingDetail->name = $wingName;
    //             $wingDetail->total_floors = $numberOfFloors;
    //             $wingDetail->save();
    //             $floorUnitDetails = [];

    //             for ($i = 1; $i <= $numberOfFloors; $i++) {
    //                 $floorDetail = new FloorDetail();
    //                 $floorDetail->user_property_id = $propertyId;
    //                 $floorDetail->wing_id = $wingDetail->id;
    //                 $floorDetail->total_units = $numberOfUnits;
    //                 $floorDetail->save();

    //                 $unitDetails = [];
    //                 for ($j = 1; $j <= $numberOfUnits; $j++) {
    //                     $unitDetail = new UnitDetail();
    //                     $unitDetail->user_property_id = $propertyId;
    //                     $unitDetail->wing_id = $wingDetail->id;
    //                     $unitDetail->floor_id = $floorDetail->id;
    //                     $unitDetail->save();

    //                     $unitDetails[] = ['unitId' => $unitDetail->id];
    //                 }

    //                 $floorUnitDetails[] = ['floorId' => $floorDetail->id, 'unitDetails' => $unitDetails];
    //             }
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => null,
    //                 'wingId' => $wingDetail->id,
    //                 'floorUnitDetails' => $floorUnitDetails,
    //                 'floorUnitCounts' => null
    //             ], 200);
    //         } elseif ($sameUnitFlag == 2) {
    //             $checkWing = WingDetail::where('user_property_id', $propertyId)->where('name', $wingName)->first();
    //             if (isset($checkWing)) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Same wing name exist.',
    //                     'wingId' => null,
    //                     'floorUnitDetails' => null
    //                 ], 400);
    //             }
    //             $wingDetail = new WingDetail();
    //             $wingDetail->user_property_id = $propertyId;
    //             $wingDetail->name = $wingName;
    //             $wingDetail->total_floors = $numberOfFloors;
    //             $wingDetail->save();
    //             $floorUnitCounts = [];

    //             for ($i = 1; $i <= $numberOfFloors; $i++) {
    //                 $floorDetail = new FloorDetail();
    //                 $floorDetail->user_property_id = $propertyId;
    //                 $floorDetail->wing_id = $wingDetail->id;
    //                 $floorDetail->save();
    //                 $floorUnitCounts[] = ['floorId' => $floorDetail->id, 'unit' => null];
    //             }
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => null,
    //                 'wingId' => $wingDetail->id,
    //                 'floorUnitDetails' => null,
    //                 'floorUnitCounts' => $floorUnitCounts
    //             ], 200);
    //         } else {
    //             $floorUnitDetails = [];
    //             foreach ($floorUnitCounts as $floorUnitCount) {
    //                 $floorDetail = FloorDetail::where('id', $floorUnitCount['floorId'])->update(['total_units' => $floorUnitCount['unit']]);
    //                 $unitDetails = [];
    //                 for ($j = 1; $j <= $floorUnitCount['unit']; $j++) {
    //                     $unitDetail = new UnitDetail();
    //                     $unitDetail->user_property_id = $propertyId;
    //                     $unitDetail->wing_id = $wingId;
    //                     $unitDetail->floor_id = $floorUnitCount['floorId'];
    //                     $unitDetail->save();
    //                     $unitDetails[] = ['unitId' => $unitDetail->id];
    //                 }
    //                 $floorUnitDetails[] = ['floorId' => $floorUnitCount['floorId'], 'unitDetails' => $unitDetails];
    //             }

    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => null,
    //                 'wingId' => $wingId,
    //                 'floorUnitDetails' => $floorUnitDetails,
    //                 'floorUnitCounts' => null
    //             ], 200);
    //         }
    //     } catch (\Exception $e) {
    //         $errorFrom = 'addWingDetails';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'something went wrong',
    //         ], 400);
    //     }
    // }
    public function addUnitDetails(Request $request)
    {

        try {
            $unitStartNumber = $request->input('unitStartNumber');
            $floorDetailsArray = $request->input('floorUnitDetails');


            foreach ($floorDetailsArray as $index => $floorDetail) {
                $currentStartNumber = (string) $unitStartNumber;
                $unitLength = strlen($currentStartNumber);
                if ($unitLength == 3) {
                    $currentFloorStartNumber = $index * 100 + $unitStartNumber;
                    foreach ($floorDetail['unitDetails'] as $UnitDetail) {
                        UnitDetail::where('id', $UnitDetail['unitId'])->update(['name' => $currentFloorStartNumber, 'unit_size' => $UnitDetail['unitSize']]);
                        $currentFloorStartNumber++;
                    }
                } elseif ($unitLength == 4) {
                    $currentFloorStartNumber = $index * 1000 + $unitStartNumber;
                    foreach ($floorDetail['unitDetails'] as $UnitDetail) {
                        UnitDetail::where('id', $UnitDetail['unitId'])->update(['name' => $currentFloorStartNumber, 'unit_size' => $UnitDetail['unitSize']]);
                        $currentFloorStartNumber++;
                    }
                } else {
                    $currentFloorStartNumber = $unitStartNumber;
                    foreach ($floorDetail['unitDetails'] as $UnitDetail) {
                        UnitDetail::where('id', $UnitDetail['unitId'])->update(['name' => $currentFloorStartNumber, 'unit_size' => $UnitDetail['unitSize']]);
                        $currentFloorStartNumber++;
                    }
                    $unitStartNumber = $currentFloorStartNumber;
                }
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Unit details added successfully.',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addUnitDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }

    public function getWingDetails($propertyId)
    {
        $WingDetails = WingDetail::with('floorDetails.unitDetails')->where('user_property_id', $propertyId)->get();
        $WingDetails = WingDetail::with('floorDetails.unitDetails')->where('user_property_id', $propertyId)->get();
        return $WingDetails;
    }

    public function getPropertyDetails($pid)
    {
        try {
            if ($pid != 'null') {

                $fetchWings = WingDetail::with(['unitDetails', 'floorDetails']) // Eager load unit details
                    ->where('property_id', $pid)
                    ->get();



                $propertyDetails = UserProperty::where('id', $pid)->first();
                // return $propertyDetails;

                // Fetch the property details along with wings
                $propertyDetails = UserProperty::with([
                    'wingDetails',
                    'property',
                    'unitDetails' => function ($query) {
                        $query->with([
                            'leadCustomerUnits',
                            'paymentTransactions' // Ensure payment transactions are loaded
                        ]);
                    }
                ])->where('id', $pid)->first();

                if ($propertyDetails) {
                    // Check if the property has any wings
                    $wingsflag = $propertyDetails->wingDetails->isNotEmpty() ? 1 : 0;

                    // Add wingsflag to the property details
                    $propertyDetails->wingsflag = $wingsflag;
                    $propertyDetails->property_name = $propertyDetails->property->name ?? null;


                    if ($fetchWings->isNotEmpty()) {
                        // Update the response structure with actual data
                        $propertyDetails->building_wings_count = $fetchWings->count();
                        $propertyDetails->total_units = $fetchWings->sum(function ($wing) {
                            return $wing->unitDetails->count(); // Count of units for each wing
                        });
                    } else {

                        if ($propertyDetails->unitDetails) {
                            $propertyDetails->building_wings_count = 0;
                            // $propertyDetails->total_units=0;
                            $propertyDetails->total_units = $propertyDetails->unitDetails->count();
                        } else {
                            $propertyDetails->building_wings_count = 0;
                            $propertyDetails->total_units = 0;
                        }
                    }



                    // Process unit details to include total_paid_amount
                    $propertyDetails->unitDetails = $propertyDetails->unitDetails->map(function ($unit) {
                        $unitLeads = $unit->leadCustomerUnits;

                        // Calculate total interested leads count
                        $unit->interested_lead_count = $unitLeads->filter(function ($leadCustomerUnits) {
                            return !empty($leadCustomerUnits->interested_lead_id); // Exclude empty or null `interested_lead_id`
                        })->sum(function ($leadCustomerUnits) {
                            return count(explode(',', $leadCustomerUnits->interested_lead_id)); // Count valid IDs
                        });
 
                        $unit->booking_status = $unitLeads->pluck('booking_status')->first();
                        $totalPaidAmount = 0;

                        // Check payment transactions for this unit
                        $paymentTransactions = $unit->paymentTransactions;

                        if ($paymentTransactions->isNotEmpty()) {
                            // Filter transactions with payment_status = 2
                            $filteredTransactions = $paymentTransactions->where('payment_status', 2);

                            // Get the first transaction
                            $firstTransaction = $filteredTransactions->first();

                            // Add token_amt from the first transaction if it exists
                            if ($firstTransaction && $firstTransaction->token_amt) {
                                $totalPaidAmount += $firstTransaction->token_amt;
                            }

                            // Add next_payable_amt from all transactions
                            foreach ($filteredTransactions as $index => $transaction) {
                                if ($index == 0 && $firstTransaction && $firstTransaction->next_payable_amt) {
                                    $totalPaidAmount += $firstTransaction->next_payable_amt;
                                } elseif ($index > 0 && $transaction->next_payable_amt) {
                                    $totalPaidAmount += $transaction->next_payable_amt;
                                }
                            }
                        }

                        // Add total_paid_amount to the unit details
                        $unit->total_paid_amount = $totalPaidAmount;



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
    
                        }
    
                        // Assign the aggregated allocated entities array to each unit
                        $unit->allocated_entities = $allocatedEntities;

                        // return $unit;
                    });

                    $propertyDetails['wing_details'] = $fetchWings->map(function ($wing) {
                        return [
                            'id' => $wing->id,
                            'name' => $wing->name,
                            'property_id' => $wing->property_id,
                            'total_floors' => $wing->total_floors,
                            'total_units_in_wing' => $wing->unitDetails->count(), // Count units in this wing
                        ];
                    });

                    unset($propertyDetails->wingDetails);
                    return $propertyDetails;
                } else {
                    return null;
                }
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getPropertyDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getUserPropertyDetails($uid)
    {

        try {
            if ($uid != 'null') {
                $userProperties = UserProperty::where('user_id', $uid)->get();

                // Get IDs of Commercial and Residential properties (and their subtypes)
                $commercialPropertyIds = Property::where('parent_id', 1)->orWhere('id', 1)->pluck('id')->toArray(); // '1' for Commercial and its subtypes
                $residentialPropertyIds = Property::where('parent_id', 2)->orWhere('id', 2)->pluck('id')->toArray(); // '2' for Residential and its subtypes

                // Separate properties into Commercial and Residential
                $commercialProperties = $userProperties->whereIn('property_id', $commercialPropertyIds)->values(); // Reset keys
                $residentialProperties = $userProperties->whereIn('property_id', $residentialPropertyIds)->values(); // Reset keys
                return response()->json([
                    'commercial_properties' => $commercialProperties,
                    'residential_properties' => $residentialProperties,
                ], 200);
            } else {
                return response()->json([
                    'commercial_properties' => null,
                    'residential_properties' => null,
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getUserPropertyDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getStateDetails()
    {
        $getAllState = State::get();
        return $getAllState;
    }

    public function getStateWithCities($id)
    {
        $getStateWithCities = State::with('cities')->where('id', $id)->first();
        return $getStateWithCities;
    }


    public function getAreaWithCities($uid, $cid)
    {
        $getAreaWithStates = UserProperty::where('user_id', $uid)->where('city_id', $cid)
            ->distinct('area')
            ->pluck('area');
        return $getAreaWithStates;
    }

    public function getAllProperties($uid, $stateid, $cityid, $area)
    {
        try {

            if ($uid != 'null') {
                // Base queries for all Commercial and Residential properties
                $commercialQuery = UserProperty::where('user_id', $uid)
                    ->whereIn('property_id', Property::where('parent_id', 1)->pluck('id'));

                $residentialQuery = UserProperty::where('user_id', $uid)
                    ->whereIn('property_id', Property::where('parent_id', 2)->pluck('id'));

                // Apply filters if provided
                if ($stateid != 'null') {
                    $commercialQuery->where('state_id', $stateid);
                    $residentialQuery->where('state_id', $stateid);
                }

                if ($cityid != 'null') {
                    $commercialQuery->where('city_id', $cityid);
                    $residentialQuery->where('city_id', $cityid);
                }

                if ($area != 'null') {
                    $commercialQuery->where('area', 'like', '%' . $area . '%');
                    $residentialQuery->where('area', 'like', '%' . $area . '%');
                }

                // Execute the queries and get the results
                $commercialProperties = $commercialQuery->get();
                $residentialProperties = $residentialQuery->get();

                // Return combined result arrays
                return response()->json([
                    'commercialProperties' => $commercialProperties, // Contains both filtered/unfiltered
                    'residentialProperties' => $residentialProperties // Contains both filtered/unfiltered
                ], 200);
            } else {
                return response()->json([
                    'commercialProperties' => null, // Contains both filtered/unfiltered
                    'residentialProperties' => null // Contains both filtered/unfiltered
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'filterProperties';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Error filtering properties',
            ], 400);
        }
    }
}
