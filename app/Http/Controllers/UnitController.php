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

    public function addEntityAttachWithUnitsUsingCheque(Request $request)
    {
        try {
            $unitId = $request->input('unit_id');
            $propertyId = $request->input('property_id');
            $leadId = $request->input('lead_id');
            $contactName = $request->input('contact_name');
            $contactEmail = $request->input('contact_email');
            $contactNumber = $request->input('contact_number');
            $nextPayableAmt = $request->input('next_payable_amt');
            $totalAmt = $request->input('total_amt');
            $flag = $request->input('flag');

            // Check if lead_unit entry exists for the given unit_id
            $leadUnit = LeadUnit::where('unit_id', $unitId)->first();

            $allocatedLeadIds = $leadUnit && $leadUnit->allocated_lead_id ? explode(',', $leadUnit->allocated_lead_id) : [];
            $allocatedCustomerIds = $leadUnit && $leadUnit->allocated_customer_id ? explode(',', $leadUnit->allocated_customer_id) : [];

            // Flag-specific logic
            if ($flag == 1) {
                // If the unit has any associated lead or customer, return a matched status
                if (!empty($allocatedLeadIds) || !empty($allocatedCustomerIds)) {
                    $names = !empty($allocatedCustomerIds)
                        ? Customer::whereIn('id', $allocatedCustomerIds)->pluck('name')->toArray()
                        : Lead::whereIn('id', $allocatedLeadIds)->pluck('name')->toArray();

                    return response()->json([
                        'status' => 'matched',
                        'name' => $names,
                    ], 200);
                }
            }

            // Determine allocation based on leadId
            $allocatedType = is_null($leadId) ? 2 : 1; // 2 for customer, 1 for lead
            $allocatedId = null;

            if (is_null($leadId)) {
                // Handle new customer logic
                $customer = Customer::where('property_id', $propertyId)
                    ->where('unit_id', $unitId)
                    ->where('email', $contactEmail)
                    ->first();

                if ($customer) {
                    $customer->name = $contactName;
                    $customer->contact_no = $contactNumber;
                    $customer->save();
                } else {
                    $customer = Customer::create([
                        'property_id' => $propertyId,
                        'unit_id' => $unitId,
                        'email' => $contactEmail,
                        'name' => $contactName,
                        'contact_no' => $contactNumber,
                    ]);
                }

                if (!in_array($customer->id, $allocatedCustomerIds)) {
                    $allocatedCustomerIds[] = $customer->id;
                    $leadUnit->allocated_customer_id = implode(',', $allocatedCustomerIds);
                }
                $allocatedId = $customer->id;
            } else {
                // Handle existing lead logic
                $lead = Lead::find($leadId);
                if (!$lead) {
                    return response()->json(['status' => 'error', 'message' => 'Lead not found'], 200);
                }

                if (!in_array($leadId, $allocatedLeadIds)) {
                    $allocatedLeadIds[] = $leadId;
                    $leadUnit->allocated_lead_id = implode(',', $allocatedLeadIds);
                }
                $allocatedId = $leadId;
            }

            // Update lead_unit and set booking status
            $leadUnit = $leadUnit ?: new LeadUnit();
            $leadUnit->unit_id = $unitId;
            $leadUnit->booking_status = 4; // Set booking status to 4
            $leadUnit->save();

            // Check and update unit price
            $unit = UnitDetail::find($unitId);
            if ($unit && !is_null($totalAmt)) {
                $unit->price = $totalAmt;
                $unit->save();
            }

            // Log payment transactions
            $this->logEntity([
                'unit_id' => $unitId,
                'property_id' => $propertyId,
                'allocated_id' => $allocatedId,
                'allocated_type' => $allocatedType,
                'next_payable_amt' => $nextPayableAmt,
                'amount' => $totalAmt
            ]);

            return response()->json([
                'status' => 'success',
                'name' => null
            ], 200);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'addEntityAttachWithUnitsUsingCheque';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function addMatchedEntityUsingCheque(Request $request)
    {

        try {

            // Initialize variables
            $propertyId = $request->input('property_id');
            $unitId = $request->input('unit_id');
            $wingId = $request->input('wing_id');
            $leadId = $request->input('id'); // Lead or Customer ID
            $leadType = $request->input('lead_type');
            $amount = $request->input('amount');
            $flag = $request->input('flag');

            // Fetch the associated LeadUnit record based on property and unit
            $associatedEntity = LeadUnit::where('unit_id', $unitId)
                ->with(['unit']) // Eager load the unit relation to access property_id
                ->first();



            if ($flag == 1) {
                // Check if there are any entities attached with the same property and unit
                if ($associatedEntity) {
                    $matchedNames = [];
                    $nameExists = false;

                    // Check for leads
                    $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
                    foreach ($allocatedLeadIds as $allocatedLeadId) {
                        $lead = Lead::where('id', $allocatedLeadId)
                            ->where('property_id', $propertyId)
                            ->first();
                        if ($lead) {
                            $matchedNames[] = $lead->name; // Collecting names of leads
                            if ($allocatedLeadId == $leadId) {
                                $nameExists = true;
                            }
                        }
                    }

                    // Check for customers
                    $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
                    foreach ($allocatedCustomerIds as $allocatedCustomerId) {
                        $customer = Customer::where('id', $allocatedCustomerId)
                            ->where('property_id', $propertyId)
                            ->first();
                        if ($customer) {
                            $matchedNames[] = $customer->name; // Collecting names of customers
                            if ($allocatedCustomerId == $leadId) {
                                $nameExists = true;
                            }
                        }
                    }


                    if ($nameExists) {
                        // Directly log the entity in PaymentTransaction if name exists
                        $this->logEntity([
                            'unit_id' => $associatedEntity->unit_id,
                            'property_id' => $propertyId,
                            'allocated_id' => $leadId,
                            'allocated_type' => $leadType, // 1 for lead, 2 for customer
                            'next_payable_amt' => $amount,
                            'amount' => null,
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'names' => null,
                        ], 200);
                    }

                    // If any matched names are found, return them
                    if (!empty($matchedNames) ) {
                        return response()->json([
                            'status' => 'matched',
                            'names' => implode(', ', $matchedNames) // Ensures names are unique
                        ], 200);
                    }
                }


                // If no match found, either update existing or create new LeadUnit entry
                if ($associatedEntity) {
                    // Update allocated IDs if entry exists
                    if ($leadType == 'lead') {
                        $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
                        if (!in_array($leadId, $allocatedLeadIds)) {
                            $allocatedLeadIds[] = $leadId;
                            $associatedEntity->allocated_lead_id = implode(',', $allocatedLeadIds);
                        }
                    } elseif ($leadType == 'customer') {
                        $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
                        if (!in_array($leadId, $allocatedCustomerIds)) {
                            $allocatedCustomerIds[] = $leadId;
                            $associatedEntity->allocated_customer_id = implode(',', $allocatedCustomerIds);
                        }
                    }
                } else {
                    // Create a new LeadUnit entry if it doesn't exist
                    $associatedEntity = new LeadUnit();
                    $associatedEntity->unit_id = $unitId;
                    $associatedEntity->booking_status = 4; // Set an appropriate default status
                    if ($leadType == 'lead') {
                        $associatedEntity->allocated_lead_id = $leadId;
                    } elseif ($leadType == 'customer') {
                        $associatedEntity->allocated_customer_id = $leadId;
                    }
                    $associatedEntity->save();
                }


                // If no match found, log the entity
                $this->logEntity([
                    'unit_id' => $associatedEntity->unit_id,
                    'property_id' => $propertyId,
                    'allocated_id' => $leadId,
                    'allocated_type' => $leadType, // 1 for lead, 2 for customer
                    'next_payable_amt' => $amount,
                    'amount' =>  null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'names' => null,
                ], 200);
            } elseif ($flag == 2 ) { //2 means yes  call 
                // Check if there are any entities attached as a lead or customer with this unit
                if ($associatedEntity) {
                    if ($leadType == 'lead') {
                        $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
                        if (!in_array($leadId, $allocatedLeadIds)) {
                            // Add lead ID to allocated_lead_id
                            $allocatedLeadIds[] = $leadId;
                            $associatedEntity->allocated_lead_id = implode(',', $allocatedLeadIds);
                        }
                    } elseif ($leadType == 'customer') {
                        $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
                        if (!in_array($leadId, $allocatedCustomerIds)) {
                            // Check if `allocated_customer_id` is empty
                            if (empty($associatedEntity->allocated_customer_id)) {
                                $associatedEntity->allocated_customer_id = $leadId;
                            } else {
                                $associatedEntity->allocated_customer_id .= ',' . $leadId;
                            }
                        }
                    }

                    // Save the updates
                    $associatedEntity->save();
                } else {
                    // No associated entity found; create a new LeadUnit entry
                    $associatedEntity = new LeadUnit();
                    $associatedEntity->unit_id = $unitId; // Set the unit ID
                    $associatedEntity->booking_status = 4; // Set an appropriate default status

                    if ($leadType == 'lead') {
                        $associatedEntity->allocated_lead_id = $leadId; // Allocate the lead ID
                    } elseif ($leadType == 'customer') {
                        $associatedEntity->allocated_customer_id = $leadId; // Allocate the customer ID
                    }

                    // Save the new LeadUnit entry
                    $associatedEntity->save();
                }

                // Log the transaction
                $this->logEntity([
                    'unit_id' => $associatedEntity->unit_id,
                    'property_id' => $propertyId,
                    'allocated_id' => $leadId,
                    'allocated_type' => $leadType, // 1 for lead, 2 for customer
                    'next_payable_amt' => $amount,
                    'amount' => null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'names' => null,
                ], 200);
            }elseif($flag == 3) {//and 3 means no call
                 // Check if there are any entities attached as a lead or customer with this unit
                 $matchedNames = [];
                 if ($associatedEntity) {


                    $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
                   // Check for leads
                   $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
                   foreach ($allocatedLeadIds as $allocatedLeadId) {
                       $lead = Lead::where('id', $allocatedLeadId)
                           ->where('property_id', $propertyId)
                           ->first();
                       if ($lead) {
                           $matchedNames[] = $lead->name; // Collecting names of leads
                         
                       }
                   }
 
                   // Check for customers
                   $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
                   foreach ($allocatedCustomerIds as $allocatedCustomerId) {
                       $customer = Customer::where('id', $allocatedCustomerId)
                           ->where('property_id', $propertyId)
                           ->first();
                       if ($customer) {
                           $matchedNames[] = $customer->name; // Collecting names of customers
                         
                       }
                   }
 

                   if (!empty($matchedNames) ) {
                    return response()->json([
                        'status' => 'matched',
                        'names' => implode(', ', $matchedNames) // Ensures names are unique
                    ], 200);
                }


                if ($leadType == 'lead') {
                    $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
                
                    if (!in_array($leadId, $allocatedLeadIds)) {
                        // Add lead ID to allocated_lead_id
                        if (empty($associatedEntity->allocated_lead_id)) {
                            // If it's the first entry, set without a comma
                            $associatedEntity->allocated_lead_id = $leadId;
                        } else {
                            // Append with a comma for subsequent entries
                            $associatedEntity->allocated_lead_id .= ',' . $leadId;
                        }
                    }
                } elseif ($leadType == 'customer') {
                    $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
                
                    if (!in_array($leadId, $allocatedCustomerIds)) {
                        if (empty($associatedEntity->allocated_customer_id)) {
                            // If it's the first entry, set without a comma
                            $associatedEntity->allocated_customer_id = $leadId;
                        } else {
                            // Append with a comma for subsequent entries
                            $associatedEntity->allocated_customer_id .= ',' . $leadId;
                        }
                    }
                }

                    // Save the updates
                    $associatedEntity->save();
                } else {
                    // No associated entity found; create a new LeadUnit entry
                    $associatedEntity = new LeadUnit();
                    $associatedEntity->unit_id = $unitId; // Set the unit ID
                    $associatedEntity->booking_status = 4; // Set an appropriate default status

                    if ($leadType == 'lead') {
                        $associatedEntity->allocated_lead_id = $leadId; // Allocate the lead ID
                    } elseif ($leadType == 'customer') {
                        $associatedEntity->allocated_customer_id = $leadId; // Allocate the customer ID
                    }

                    // Save the new LeadUnit entry
                    $associatedEntity->save();
                }

                // Log the transaction
                $this->logEntity([
                    'unit_id' => $associatedEntity->unit_id,
                    'property_id' => $propertyId,
                    'allocated_id' => $leadId,
                    'allocated_type' => $leadType, // 1 for lead, 2 for customer
                    'next_payable_amt' => $amount,
                    'amount' => null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'names' => null,
                ], 200);
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'addMatchedEntityUsingCheque';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    private function logEntity(array $data)
    {

        $unitId = $data['unit_id'];
        $propertyId = $data['property_id'];
        $leadId = $data['allocated_id'];
        $leadType = $data['allocated_type'];
        $amount = $data['next_payable_amt'];
        $totalamt = $data['amount'] ?? null;

        // $addSecondTransactionOnly = $data['add_second_transaction_only'] ?? false;

        Log::info('Parsed Variables:', [
            'unitId' => $unitId,
            'propertyId' => $propertyId,
            'leadId' => $leadId,
            'leadType' => $leadType,
            'amount' => $amount,
            'totalamt' => $totalamt,
        ]);

        $existingTransaction = PaymentTransaction::where('unit_id', $unitId)
            ->where('property_id', $propertyId)
            ->exists();


        Log::info('Existing transaction found:', ['exists' => $existingTransaction]);



        $amount = str_replace(',', '', $amount); // Remove all commas

        // Add only the second transaction if an entry exists and flag is set
        if ($existingTransaction) {
            Log::info('logEntity called with data:', $data);
            $transaction2 = new PaymentTransaction();
            $transaction2->unit_id = $unitId;
            $transaction2->property_id = $propertyId;
            $transaction2->allocated_id = $leadId;
            $transaction2->allocated_type = ($leadType == 'lead') ? 1 : 2;
            $transaction2->payment_status = 2; // Final payment status
            $transaction2->payment_due_date = today();
            $transaction2->booking_date = today();
            $transaction2->next_payable_amt = $amount;
            $transaction2->created_at = now();
            $transaction2->updated_at = now();
            $transaction2->save();
            // return;
        }

        if (!$existingTransaction) {
            // Log the payment transaction entries
            $transaction1 = new PaymentTransaction();
            $transaction1->unit_id = $unitId;
            $transaction1->property_id = $propertyId;
            $transaction1->allocated_id = $leadId; // Lead or Customer ID
            $transaction1->allocated_type = ($leadType == 'lead') ? 1 : 2; // 1 for lead, 2 for customer
            $transaction1->payment_status = 2; // Initial payment status
            $transaction1->payment_due_date = today();
            $transaction1->booking_date = today();
            $transaction1->created_at = now();
            $transaction1->updated_at = now();
            $transaction1->save();

            // Create the second transaction entry
            $transaction2 = new PaymentTransaction();
            $transaction2->unit_id = $unitId;
            $transaction2->property_id = $propertyId;
            $transaction2->allocated_id = $leadId; // Lead or Customer ID
            $transaction2->allocated_type = ($leadType == 'lead') ? 1 : 2; // 1 for lead, 2 for customer
            $transaction2->payment_status = 2; // Final payment status
            $transaction2->payment_due_date = today();
            $transaction2->booking_date = today();
            $transaction2->next_payable_amt = $amount;
            $transaction2->created_at = now();
            $transaction2->updated_at = now();
            $transaction2->save();
        }
    }
}
