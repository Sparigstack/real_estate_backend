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
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\PaymentTransaction;
use App\Models\State;
use Exception;
use Illuminate\Support\Facades\Log;




class VillaBunglowController extends Controller
{



    public function addVillaBunglowDetails(Request $request)
    {

        try {
            // Get the incoming request data
            $propertyId = $request->input('propertyId');
            $totalUnits = $request->input('totalUnits');

            $unitSize = $request->input('unitSize', null); // Default to null if not provided

            $units = [];
            $startingUnitName = 101; // Start unit names from 101

            for ($i = 0; $i < $totalUnits; $i++) {
                $units[] = [
                    'property_id' => $propertyId,
                    'name' => (string)($startingUnitName + $i), // Incrementing unit name
                    'square_feet' => ($unitSize !== null && $unitSize !== '') ? $unitSize : null, // Assign unitSize if available
                    'wing_id' => null, // Wing ID is null as per the requirements
                    'floor_id' => null, // Floor ID is null as per the requirements
                    'status_id' => 1, // Default status ID
                    'price' => null, // Default price
                ];
            }

            // Insert units data into the database
            UnitDetail::insert($units); // This assumes the `Unit` model handles the database insertion

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Data added successfully',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addVillaBunglowDetails';
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
