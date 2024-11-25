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
                $wingsArray = $request->input('wingsArray');
                $unitSize = $request->input('unitSize', null); // Default to null if not provided
        
                // Process the wingsArray to add units
                $units = [];
                foreach ($wingsArray as $wing) {
                    $units[] = [
                        'property_id' => $propertyId,
                        'name' => $wing['wingName'],
                        'square_feet' => ($unitSize !== null && $unitSize !== '') ? $unitSize : null, // Assign unitSize if available
                        'floor_id' => null,  // As mentioned, floor is null,
                        'wing_id'=>null
                    ];
                }
        
                // Insert units data into the database
                // Assuming you have a Unit model, this is how you can insert the units
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
