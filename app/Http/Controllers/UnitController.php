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
use App\Models\LeadUnit;
use App\Models\PaymentTransaction;
use App\Models\State;
use Exception;




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
                                'budget' => $lead->budget,
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
                $allLeads = Lead::where('property_id', $pid)->get();

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



    public function addUnitBookingInfo(Request $request)
    {

        try {
            $unitId = $request->input('unit_id');
            $propertyId = $request->input('property_id');
            $leadId = $request->input('lead_id');
            $contactName = $request->input('contact_name');
            $contactEmail = $request->input('contact_email');
            $contactNumber = $request->input('contact_number');
            $bookingDate = $request->input('booking_date');
            $tokenAmt = $request->input('token_amt');
            $paymentDueDate = $request->input('payment_due_date');
            $nextPayableAmt = $request->input('next_payable_amt');
            $totalAmt = $request->input('total_amt');
    
            // Check if lead_unit entry exists for the given unit_id
            $leadUnit = LeadUnit::where('unit_id', $unitId)->first();
    
            if ($leadUnit) {
                // Check if allocated_lead_id or allocated_customer_id is already filled
                if (!is_null($leadUnit->allocated_lead_id) && is_null($leadId)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This unit is already allocated to a lead. ',
                    ], 200);
                }

                if (!is_null($leadUnit->allocated_lead_id) && !is_null($leadId)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This unit is already allocated to a lead.',
                    ], 200);
                }

                if (!is_null($leadUnit->allocated_customer_id) && is_null($leadId)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This unit is already allocated to a customer.',
                    ], 200);
                }
    
                if (!is_null($leadUnit->allocated_customer_id) && !is_null($leadId)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This unit is already allocated to a customer.',
                    ], 200);
                }
            }
    
            if (is_null($leadId)) {
                // Lead ID is null, add new customer or update existing allocated_customer_id
                $customer = Customer::firstOrCreate([
                    'property_id' => $propertyId,
                    'unit_id' => $unitId,
                    'email' => $contactEmail,
                    'name' => $contactName,
                    'contact_no' => $contactNumber,
                ]);
    
                if ($leadUnit) {
                    // Update existing lead_unit with allocated_customer_id
                    $leadUnit->allocated_customer_id = $customer->id;
                    $leadUnit->booking_status = 3; // assuming 3 means booked without payment
                    $leadUnit->save();
                } else {
                    // Create new lead_unit if none exists
                    $leadUnit = new LeadUnit();
                    $leadUnit->unit_id = $unitId;
                    $leadUnit->allocated_customer_id = $customer->id;
                    $leadUnit->booking_status = 3;
                    $leadUnit->save();
                }
            } else {
                // Lead ID is provided, verify it exists
                $lead = Lead::find($leadId);
                if (!$lead) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Lead not found',
                    ], 200);
                }
    
                if ($leadUnit) {
                    // Update existing lead_unit with allocated_lead_id
                    $leadUnit->allocated_lead_id = $leadId;
                    $leadUnit->booking_status = 3;
                    $leadUnit->save();
                } else {
                    // Create new lead_unit if none exists
                    $leadUnit = new LeadUnit();
                    $leadUnit->unit_id = $unitId;
                    $leadUnit->allocated_lead_id = $leadId;
                    $leadUnit->booking_status = 3;
                    $leadUnit->save();
                }
            }
    
            // Insert into payment_transaction regardless of booking details
            $paymentTransaction = new PaymentTransaction();
            $paymentTransaction->unit_id = $unitId;
            $paymentTransaction->property_id = $propertyId;
            $paymentTransaction->booking_date = $bookingDate ?? null;
            $paymentTransaction->payment_due_date = $paymentDueDate ?? null;
            $paymentTransaction->token_amt = $tokenAmt ?? null;
            $paymentTransaction->amount = $totalAmt ?? null;
            $paymentTransaction->next_payable_amt = $nextPayableAmt ?? null; // Set to 0 if null
            $paymentTransaction->payment_type = '0'; // assuming manual for now
            $paymentTransaction->transaction_notes = 'Booking entry created';
            $paymentTransaction->save();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Unit booking information saved successfully',
            ]);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addUnitBookingInfo';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }
}
