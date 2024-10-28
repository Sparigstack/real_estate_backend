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

            // Check if there is already an allocated lead or customer
            $allocatedLeadIds = $leadUnit && $leadUnit->allocated_lead_id ? explode(',', $leadUnit->allocated_lead_id) : [];
            $allocatedCustomerIds = $leadUnit && $leadUnit->allocated_customer_id ? explode(',', $leadUnit->allocated_customer_id) : [];

            // Determine if we need to add a new customer or update an existing one
            if (is_null($leadId)) {
                $customer = Customer::where('property_id', $propertyId)
                    ->where('unit_id', $unitId)
                    ->where('email', $contactEmail)
                    ->first();

                if ($customer) {
                    // Update existing customer information
                    $customer->name = $contactName;
                    $customer->contact_no = $contactNumber;
                    $customer->save();
                } else {
                    // Create new customer
                    $customer = Customer::create([
                        'property_id' => $propertyId,
                        'unit_id' => $unitId,
                        'email' => $contactEmail,
                        'name' => $contactName,
                        'contact_no' => $contactNumber,
                    ]);
                }

                // Update lead_unit with allocated_customer_id
                $leadUnit = $leadUnit ?: new LeadUnit();
                $leadUnit->unit_id = $unitId;
                // $leadUnit->allocated_customer_id = $leadUnit->allocated_customer_id ? $leadUnit->allocated_customer_id . ',' . $customer->id : $customer->id;
                if (!in_array($customer->id, $allocatedCustomerIds)) {
                    $allocatedCustomerIds[] = $customer->id;
                    $leadUnit->allocated_customer_id = implode(',', $allocatedCustomerIds);
                }
                $leadUnit->booking_status = 4; // Update booking status to 4
                $leadUnit->save();

                // Set allocated_id and allocated_type for PaymentTransaction
                $allocatedId = $customer->id;
                $allocatedType = 2; // Customer
            } else {
                // Lead ID is provided, verify it exists
                $lead = Lead::find($leadId);
                if (!$lead) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Lead not found',
                    ], 200);
                }

                // Update lead_unit with allocated_lead_id
                $leadUnit = $leadUnit ?: new LeadUnit();
                $leadUnit->unit_id = $unitId;
                // $leadUnit->allocated_lead_id = $leadUnit->allocated_lead_id ? $leadUnit->allocated_lead_id . ',' . $leadId : $leadId;
               
                if (!in_array($leadId, $allocatedLeadIds)) {
                    $allocatedLeadIds[] = $leadId;
                    $leadUnit->allocated_lead_id = implode(',', $allocatedLeadIds);
                } $leadUnit->booking_status = 4; // Update booking status to 4
                $leadUnit->save();

                // Set allocated_id and allocated_type for PaymentTransaction
                $allocatedId = $leadId;
                $allocatedType = 1; // Lead
            }

            // Check and update unit price if necessary
            $unit = UnitDetail::find($unitId);
            if ($unit && (is_null($unit->price) || $unit->price == 0) && !is_null($totalAmt)) {
                $unit->price = $totalAmt;
                $unit->save();
            }

            // Create the first payment transaction entry
            $paymentTransaction = new PaymentTransaction();
            $paymentTransaction->unit_id = $unitId;
            $paymentTransaction->property_id = $propertyId;
            $paymentTransaction->allocated_id = $allocatedId; // Set allocated ID
            $paymentTransaction->allocated_type = $allocatedType; // Set allocated type
            $paymentTransaction->booking_date = $bookingDate; // Use provided booking date
            // For the first entry, don't set payment_due_date and next_payable_amt
            $paymentTransaction->payment_due_date = null;
            $paymentTransaction->token_amt = $tokenAmt; // Set to provided token amount
            $paymentTransaction->amount = $totalAmt ?? null;
            $paymentTransaction->next_payable_amt = null; // Set to null for the first entry
            $paymentTransaction->payment_status = 2; // Set payment status to 1
            $paymentTransaction->payment_type = 1; // Assuming manual for now
            $paymentTransaction->transaction_notes = 'Booking entry created';
            $paymentTransaction->save();

            // Create the second payment transaction entry
            $paymentTransactionSecond = new PaymentTransaction();
            $paymentTransactionSecond->unit_id = $unitId;
            $paymentTransactionSecond->property_id = $propertyId;
            $paymentTransactionSecond->allocated_id = $allocatedId; // Set allocated ID
            $paymentTransactionSecond->allocated_type = $allocatedType; // Set allocated type
            $paymentTransactionSecond->booking_date = $bookingDate; // Use provided booking date
            $paymentTransactionSecond->payment_due_date = $paymentDueDate; // Set to provided payment due date
            $paymentTransactionSecond->token_amt = $tokenAmt; // Set to provided token amount
            $paymentTransactionSecond->amount = $totalAmt ?? null;
            $paymentTransactionSecond->next_payable_amt = $nextPayableAmt; // Set to provided next payable amount
            // $paymentTransactionSecond->payment_status = 1; // Set payment status to 2
            if ($paymentDueDate) {
                // Check if payment_due_date is in the future or the past
                $paymentDueDateObj = \Carbon\Carbon::parse($paymentDueDate);
                $currentDate = \Carbon\Carbon::today();

                // Set payment_status based on whether the due date is in the future or past
                if ($paymentDueDateObj->isFuture()) {
                    $paymentTransactionSecond->payment_status = 1; // Future date, set payment status to 1
                } else {
                    $paymentTransactionSecond->payment_status = 2; // Past date, set payment status to 2
                }
            } else {
                $paymentTransactionSecond->payment_status = 1; // Set payment status to 2
            }

            $paymentTransactionSecond->payment_type = 1; // Assuming manual for now
            $paymentTransactionSecond->transaction_notes = 'Booking entry created';
            $paymentTransactionSecond->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Unit booking information saved successfully',
            ], 200);
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

    public function addInterestedLeads(Request $request)
    {
        try {
            $unit = UnitDetail::with('leadUnits')->where('id', $request->unit_id)->first();

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

            // Initialize an array to hold all interested lead IDs
            $existingLeadIds = [];
            $existingLeadUnit = $unit->leadUnits->first();

            // If there are existing interested leads, retrieve them
            if ($existingLeadUnit) {
                // Get the current interested_lead_id (comma-separated)
                $existingLeadIds = explode(',', $existingLeadUnit->interested_lead_id);
                // Convert existing lead IDs to integers for easier manipulation
                $existingLeadIds = array_map('intval', $existingLeadIds);
            }

            // Iterate through the leads_array from the request
            foreach ($request->leads_array as $lead) {
                // If lead_id is not already in the existingLeadIds array, add it
                if (!in_array($lead['lead_id'], $existingLeadIds)) {
                    $existingLeadIds[] = $lead['lead_id'];
                }
            }

            // Convert the updated lead IDs back to a comma-separated string
            $updatedInterestedLeadIds = implode(',', $existingLeadIds);

            // Update the interested_lead_id and booking_status in the existing lead unit
            if ($existingLeadUnit) {
                $existingLeadUnit->interested_lead_id = $updatedInterestedLeadIds;
                $existingLeadUnit->booking_status = 2; // Set booking_status to 2
                // $existingLeadUnit->updated_at = now(); // Update timestamp
                $existingLeadUnit->save();
            } else {
                // If no existing lead unit, create a new one
                $newLeadUnit = new LeadUnit();
                $newLeadUnit->interested_lead_id = $updatedInterestedLeadIds;
                // $newLeadUnit->allocated_lead_id = $unit->allocated_lead_id; // Optional, adjust as needed
                // $newLeadUnit->allocated_customer_id = $unit->allocated_customer_id; // Optional, adjust as needed
                $newLeadUnit->unit_id = $request->unit_id;
                $newLeadUnit->booking_status = 2; // Set booking_status to 2
                $newLeadUnit->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Interested leads updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addInterestedLeads';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }

    public function getBookedUnitDetail($uid, $bid, $type)
    {

        //uid ->unit detail id,bid-> customer/lead id, type-> lead/customer
        try {
            // Check if uid and type are not null
            if ($uid === 'null' || $type === 'null') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid parameters provided.',
                ], 200);
            }

            // Initialize the response data
            $responseData = [];

            // Retrieve the LeadUnit with the necessary relationships
            $leadUnit = LeadUnit::with(['paymentTransaction' => function ($query) {
                $query->orderBy('id', 'asc'); // Order by transaction ID in ascending order
            }])
                ->where('unit_id', $uid)
                ->first();

            if (!$leadUnit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found for the provided unit ID.',
                ], 404);
            }

            // Retrieve the payment transactions
            $paymentTransactions = $leadUnit->paymentTransaction()->get();

            // Initialize variables for total paid amount and response data for contacts
            $totalPaidAmount = 0;
            $contactId = null;

            // Loop through payment transactions to get contact details based on allocated_id
            foreach ($paymentTransactions as $index => $transaction) {
                if ($transaction->allocated_type == 1) { // If it's a lead
                    $contact = Lead::find($transaction->allocated_id);
                } elseif ($transaction->allocated_type == 2) { // If it's a customer
                    $contact = Customer::find($transaction->allocated_id);
                }

                if ($contact) {
                    // Populate contact details only once
                    if (empty($responseData['contact_name'])) {
                        $responseData['contact_name'] = $contact->name;
                        $responseData['contact_email'] = $contact->email;
                        $responseData['contact_number'] = $contact->contact_no;
                    }
                }

                // Sum up the total paid amount for completed transactions
                if ($transaction->payment_status == 2) {
                    $totalPaidAmount += $transaction->next_payable_amt;
                }

                // Prepare payment schedule
                if ($index == 0) { // For the first transaction
                    $responseData['payment_schedule'][] = [
                        'payment_due_date' => $transaction->booking_date,
                        'next_payable_amt' => $transaction->token_amt,
                        'payment_status' => $transaction->payment_status, // Status: 1 for pending, 2 for completed
                    ];
                } else { // For subsequent transactions
                    $responseData['payment_schedule'][] = [
                        'payment_due_date' => $transaction->payment_due_date,
                        'next_payable_amt' => $transaction->next_payable_amt,
                        'payment_status' => $transaction->payment_status, // Status: 1 for pending, 2 for completed
                    ];
                }
            }

            $responseData['total_paid_amount'] = $totalPaidAmount;

            // Fetch unit details if they exist
            if ($leadUnit->unit) {
                $unitDetail = $leadUnit->unit;
                $responseData['unit_details'] = [
                    'wing_name' => $unitDetail->wingDetail->name ?? null,
                    'unit_name' => $unitDetail->name ?? null,
                    'unit_size' => $unitDetail->square_feet ?? null,
                    'unit_price' => $unitDetail->price ?? null,
                ];
            } else {
                $responseData['unit_details'] = null;
            }

            // Add total amount from the latest transaction if it exists
            $latestTransaction = $paymentTransactions->last();
            $responseData['total_amt'] = $latestTransaction->amount ?? null;

            return response()->json([
                'status' => 'success',
                'data' => $responseData,
            ], 200);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getBookedUnitDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getUnitInterestedLeads($uid)
    {
        try {
            if ($uid != 'null') {
                // Fetch lead unit by unit ID
                $leadUnit = LeadUnit::where('unit_id', $uid)->first();

                // Check if the lead unit exists
                if (!$leadUnit) {
                    return []; // Return an empty array if no lead unit is found
                }

                // Get the interested lead IDs from the lead unit
                $interestedLeadIds = explode(',', $leadUnit->interested_lead_id); // Convert comma-separated string to array

                // Fetch details of interested leads
                $interestedLeads = Lead::whereIn('id', $interestedLeadIds)->get();

                // Return an empty array if no leads were found
                return $interestedLeads->isEmpty() ? [] : $interestedLeads->toArray();
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

    public function addLeadsAttachWithUnitsUsingCheque(Request $request) {}
}
