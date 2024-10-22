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
use App\Http\Controllers\PlanController;

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
        $userProperty->property_step_status = 1;

        $planController = new PlanController();
        $response = $planController->addPlanUsageLog($userId, 1);
        if ($response == 'success') {
            $userProperty->save();
            if ($propertyImg) {
                // Define folder path
                $folderPath = public_path("properties/$userId/{$userProperty->id}");

                // Ensure directory exists
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
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Limit reached! Upgrade your plan to create more properties.',
                'propertyId' => null,
                'propertyName' => null
            ], 200);
        }
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
            $errorFrom = 'getPropertyWingsBasicDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching wings details',
            ], 400); 
        }
    }

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
                $propertyDetails = UserProperty::where('id', $pid)->first();

                $propertyDetails = UserProperty::with('wingDetails')->where('id', $pid)->first();

                if ($propertyDetails) {
                    $wingsflag = $propertyDetails->wingDetails->isNotEmpty() ? 1 : 0;
                    $propertyDetails->wingsflag = $wingsflag;
                    return $propertyDetails;
                } else {
                    return null;
                }
            }
        } catch (\Exception $e) {
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
