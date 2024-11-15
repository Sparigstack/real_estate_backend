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
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\PaymentTransaction;
use App\Models\State;
use Exception;
use Illuminate\Support\Facades\Log;




class UnitController extends Controller
{

    public function getAllUnitLeadDetails($uid)
    {
        try {
            if ($uid !== 'null') {
                // Fetch the unit based on the provided uid
                $unit = UnitDetail::with('leadUnits.allottedLead') // Eager load lead units and their allotted leads
                    ->find($uid);

                // Check if the unit exists
                if (!$unit) {
                    return null;
                }

                // Prepare the response data
                $leadDetails = $unit->leadUnits->flatMap(function ($leadUnit) {
                    // Get the interested lead IDs and split them into an array
                    $interestedLeadIds = explode(',', $leadUnit->interested_lead_id);

                    // Fetch details for each interested lead
                    $leads = Lead::whereIn('id', $interestedLeadIds)
                        ->get()
                        ->map(function ($lead) use ($leadUnit) {
                            return [
                                'id' => $lead->id,
                                'name' => $lead->name,
                                'email' => $lead->email,
                                'contact_no' => $lead->contact_no,
                                'booking_status' => $leadUnit->booking_status,
                                // Add any additional lead fields you need
                            ];
                        });

                    return $leads; // Return the detailed leads for this lead unit
                });

                return $leadDetails;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getAllUnitLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getLeadNames($pid)
    {

        try {
            if ($pid != 'null') {
                $allLeads = LeadCustomer::where('property_id', $pid)->where('entity_type',1)->get();

                return $allLeads;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadNameDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }
    public function getLeadCustomerNames($pid)
    {
        try {
            if ($pid != 'null') {
                $allLeadsCustomers = LeadCustomer::where('property_id', $pid)->get();

                return $allLeadsCustomers;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadNameDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function addLeadsAttachingWithUnits(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'unit_id' => 'required|integer',
                'lead_id' => 'required|integer',
                'booking_date' => 'nullable|date',
                'next_payable_amt' => 'nullable|numeric',
                'payment_due_date' => 'nullable|date',
                'token_amt' => 'nullable|numeric'
            ]);

            // Initialize variables
            $unitId = $request->input('unit_id');
            $leadId = $request->input('lead_id');
            $bookingDate = $request->input('booking_date');
            $nextPayableAmt = $request->input('next_payable_amt') == 0 ? null : $request->input('next_payable_amt');
            $paymentDueDate = $request->input('payment_due_date');
            $tokenAmt = $request->input('token_amt') == 0 ? null : $request->input('token_amt');

            // First, add the entry to `lead_units`
            $leadUnit = LeadUnit::create([
                'lead_id' => $leadId,
                'unit_id' => $unitId,
                'booking_status' => 0, // Default status, update as needed
            ]);

            $unit = UnitDetail::find($unitId);
            if (!$unit) {
                throw new \Exception("Unit not found.");
            }

            // If any of the payment fields are not null, add entry to `payment_transactions`
            if ($bookingDate || $nextPayableAmt || $paymentDueDate || $tokenAmt) {
                // Fetch property_id based on the unit

                PaymentTransaction::create([
                    'unit_id' => $unitId,
                    'property_id' => $unit->property_id, // Assuming unit has property_id
                    'booking_date' => $bookingDate,
                    'payment_due_date' => $paymentDueDate,
                    'token_amt' => $tokenAmt,
                    'amount' => $nextPayableAmt,
                    'payment_type' => 0, // You can modify this as needed
                    'transaction_notes' => 'New transaction', // Example notes
                ]);
                $unit->status_id = 3;
            } else {
                // Update unit status to 1 (no payment transaction)
                $unit->status_id = 1;
            }

            $unit->save();

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead and unit information saved successfully',
            ], 201);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addLeadsAttachingWithUnits';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }


    public function addInterestedLeads(Request $request)
    {
        // try {
            $unit = UnitDetail::with('leadCustomerUnits')->where('id', $request->unit_id)->first();

            // Check if the unit exists
            if (!$unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found.',
                ], 200);
            }

            // Check if the unit is associated with the provided property_id
            if ($unit->property_id != $request->property_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This unit is not associated with the specified property.',
                ], 200);
            }

            // Initialize variables
            $existingLeadIds = [];
            $existingLeadUnit = $unit->leadCustomerUnits->first();

            // If no existing lead unit, create a new one
            if (!$existingLeadUnit) {
                $newLeadUnit = new LeadCustomerUnit();
                $newLeadUnit->interested_lead_id = ''; // Will be updated later
                $newLeadUnit->unit_id = $request->unit_id;
                $newLeadUnit->booking_status = 2;
                $newLeadUnit->save();

                // Use $newLeadUnit as the current lead unit
                $leadUnitId = $newLeadUnit->id;
            } else {
                // If there is an existing lead unit, use its ID
                $leadUnitId = $existingLeadUnit->id;
                $existingLeadIds = explode(',', $existingLeadUnit->interested_lead_id);
                $existingLeadIds = array_map('intval', $existingLeadIds);
            }

            // Iterate through the leads_array from the request
            foreach ($request->leads_array as $lead) {
                $leadId = $lead['lead_id'];
                $budget = $lead['budget'];

                // Add lead ID if not already present
                if (!in_array($leadId, $existingLeadIds)) {
                    $existingLeadIds[] = $leadId;
                }

                // Check if there's an existing record in LeadUnitData
                $leadUnitData = LeadCustomerUnitData::where('leads_customers_unit_id', $leadUnitId)
                    ->where('leads_customers_id', $leadId)
                    ->first();

                if ($leadUnitData) {
                    // Update budget if different
                    if ($leadUnitData->budget != $budget) {
                        $leadUnitData->budget = $budget;
                        $leadUnitData->save();
                    }
                } else {
                    // Create new LeadUnitData entry if not exists
                    LeadCustomerUnitData::create([
                        'leads_customers_unit_id' => $leadUnitId,
                        'leads_customers_id' => $leadId,
                        'budget' => $budget,
                    ]);
                }
            }

            // Convert updated lead IDs back to a comma-separated string
            $updatedInterestedLeadIds = implode(',', $existingLeadIds);

            // Update the interested_lead_id in the lead unit
            if ($existingLeadUnit) {
                $existingLeadUnit->interested_lead_id = $updatedInterestedLeadIds;
                $existingLeadUnit->save();
            } else {
                $newLeadUnit->interested_lead_id = $updatedInterestedLeadIds;
                $newLeadUnit->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Interested leads updated successfully.',
            ], 200);
        // } catch (\Exception $e) {
        //     // Log the error
        //     $errorFrom = 'addInterestedLeads';
        //     $errorMessage = $e->getMessage();
        //     $priority = 'high';
        //     Helper::errorLog($errorFrom, $errorMessage, $priority);

        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'An error occurred while saving the data',
        //     ], 400);
        // }
    }



    public function getUnitInterestedLeads($uid)
    {
        try {
            if ($uid != 'null') {
                
                // Fetch lead unit by unit ID
                $leadUnit = LeadCustomerUnit::where('unit_id', $uid)->first();
             

                // Check if the lead unit exists
                if (!$leadUnit) {
                    return []; // Return an empty array if no lead unit is found
                }
                

                // Get the interested lead IDs from the lead unit
                $interestedLeadIds = explode(',', $leadUnit->interested_lead_id); // Convert comma-separated string to array

                // Fetch details of interested leads
                $interestedLeads = LeadCustomer::whereIn('id', $interestedLeadIds)->get();
               

                // Fetch budget details from LeadUnitData based on lead_unit_id
                $budgets = LeadCustomerUnitData::where('leads_customers_unit_id', $leadUnit->id)
                    ->whereIn('leads_customers_id', $interestedLeadIds)
                    ->get()
                    ->keyBy('leads_customers_id'); // Index by lead_id for easy access

                // Map the budget to each lead
                $leadsWithBudgets = $interestedLeads->map(function ($lead) use ($budgets) {
                    $lead->budget = $budgets->get($lead->id)->budget ?? null; // Assign budget if available
                    return $lead;
                });

                // Return the leads with budget details
                return $leadsWithBudgets->isEmpty() ? [] : $leadsWithBudgets->toArray();
            } else {
                return []; // Return an empty array if uid is 'null'
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getUnitInterestedLeads';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function getUnitsBasedOnWing($wid)
    {
        try {
            // Fetch the wing details along with its associated units
            if ($wid) {
                // Extract the unit IDs and names
                $units = UnitDetail::where('wing_id', $wid)
                    ->select('id', 'name') // Select only the unit ID and name
                    ->get();
            } else {
                $units = []; // Return an empty array if the wing is not found
            }

            // Return the units directly as an array
            return response()->json($units);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getUnitsBasedOnWing';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function sendReminderToBookedPerson($uid)
    {
        try {
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'sendReminderOfDueDate';
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
