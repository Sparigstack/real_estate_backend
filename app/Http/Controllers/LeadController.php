<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Mail\ManageLeads;
use App\Models\Lead;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\LeadSource;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\UserProperty;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade as PDF;







class LeadController extends Controller
{

    public function getLeads($pid, $flag, $skey, $sort, $sortbykey, $offset, $limit)
    {
        // flag  1->allleads,2->members(customers),3->non members(interested leads)

        try {
            if ($pid != 'null') {
                // Base query
                $allLeads = LeadCustomer::with(['userproperty', 'leadSource', 'leadCustomerUnits.unit.wingDetail'])
                    ->where('property_id', $pid);

                // Apply filtering based on flag
                if ($flag == 2) {
                    // Flag 2: Customers (entity_type = 2)
                    $allLeads->where('entity_type', 2);
                } elseif ($flag == 3) {
                    $allLeads->where('entity_type', 1);
                    // Flag 3: Interested leads only (interested_lead_id not null in LeadCustomerUnits)
                    //     $fetchLeadCustomerUnit = LeadCustomerUnit::with('unit')
                    //     ->whereHas('unit', function ($query) use ($pid) {
                    //         $query->where('property_id', $pid); // Filter based on property_id in UnitDetails table
                    //     })
                    //     ->whereNotNull('interested_lead_id') // Interested lead condition
                    //     ->get();

                    // // Iterate over the fetched LeadCustomerUnits and extract the comma-separated interested_lead_ids
                    // $interestedLeadIds = $fetchLeadCustomerUnit->pluck('interested_lead_id')
                    //     ->map(function($interestedLeadId) {
                    //         return explode(',', $interestedLeadId); // Convert comma-separated string to an array
                    //     })
                    //     ->flatten() // Flatten the array of arrays into a single array
                    //     ->unique(); // Ensure IDs are unique

                    // // Filter allLeads by interested lead IDs
                    // $allLeads->whereIn('id', $interestedLeadIds);
                }

                // Apply search filter
                if ($skey != 'null') {
                    $allLeads->where(function ($q) use ($skey) {
                        $q->where('name', 'like', "%{$skey}%")
                            ->orWhere('email', 'like', "%{$skey}%")
                            ->orWhere('contact_no', 'like', "%{$skey}%")
                            ->orWhereHas('leadSource', function ($q) use ($skey) {
                                $q->where('name', 'like', "%{$skey}%");
                            });
                    });
                }

                // Apply sorting
                if ($sortbykey != 'null') {
                    if (in_array($sortbykey, ['name', 'email', 'contact_no'])) {
                        $allLeads->orderBy($sortbykey, $sort);
                    } elseif ($sortbykey == 'source') {
                        $allLeads->join('lead_sources', 'leads_customers.source_id', '=', 'lead_sources.id')
                            ->orderBy('lead_sources.name', $sort);
                    }
                }

                // Paginate results
                $allLeads = $allLeads->paginate($limit, ['*'], 'page', $offset);

                // Modify the response to include unit name and wing name at the top level
                foreach ($allLeads as $lead) {
                    $lead->unit_name = null; // Default value
                    $lead->wing_name = null; // Default value

                    if ($lead->leadCustomerUnits->isNotEmpty()) {
                        foreach ($lead->leadCustomerUnits as $leadUnit) {
                            if ($leadUnit->allocated_lead_id) {
                                $unit = $leadUnit->unit; // Get the related unit details
                                if ($unit) {
                                    $lead->unit_name = $unit->name; // Assign unit name to lead
                                    $lead->wing_name = $unit->wingDetail->name ?? null; // Assign wing name to lead if exists
                                }
                            }
                        }
                    }
                }

                return $allLeads;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getUserProperties($uid)
    {
        try {
            if ($uid != 'null') {
                $allUserProperties = UserProperty::where('user_id', $uid)->get();
                return $allUserProperties;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getUserproperty';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getSources(Request $request)
    {

        try {
            $allSources = LeadSource::all();
            return $allSources;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getSources';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function fetchLeadDetail(Request $request, $pid, $lid)
    {
        try {
            if ($pid != 'null' && $lid != 'null') {
                $fetchLeadDetail = LeadCustomer::with('userproperty', 'leadSource')->where('property_id', $pid)->where('id', $lid)->first();
                return $fetchLeadDetail;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'fetchParticularLead';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function addOrEditLeads(Request $request)
    {
        try {

            // Validate inputs
            $validatedData = $request->validate([
                'propertyinterest' => 'required|integer',  // Assuming propertyinterest is an integer (property_id)
                'name' => 'required|string|max:255',       // Name is required and must be a string
                'contactno' => 'required|string|max:15',   // Contact number is required, can be a string
                'source' => 'required|integer',            // Source ID is required (1-reference, 2-social media, etc.)
                'budget' => 'nullable|numeric',            // Budget is optional and must be a number if provided
                'leadid' => 'required|numeric',
                'notes' => 'nullable|string',
                'flag' => 'required|in:1,2',                // Flag to determine lead type
                'unitId' => 'nullable|integer',           // Unit ID will be provided for flag 2
            ]);

            // Retrieve validated data from the request
            $propertyid = $validatedData['propertyinterest'];
            $name = $validatedData['name'];
            $contactno = $validatedData['contactno'];
            $sourceid = $validatedData['source'];
            $budget = $request->input('budget'); // Budget remains nullable
            $leadid = $request->input('leadid');
            $flag = $validatedData['flag'];  // New flag parameter
            $unit_id = $request->input('unitId'); // Optional unit ID
            $email = $request->input('email');
            $notes = $request->input('notes');



            if ($flag == 1) {
                // Flag 1: Normal lead add

                if ($leadid == 0) { //if new lead
                    // Check if the same contact number and property combination already exists
                    $existingLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->first();

                    if (!$existingLead) {
                        // Create a new lead record for manual or web form entry
                        $lead = LeadCustomer::create([
                            'property_id' => $propertyid,
                            'name' => $name,
                            'contact_no' => $contactno,
                            'email' => $email,
                            'source_id' => $sourceid,
                            'status' => 0, // 0-new
                            'type' => 0, // manual,
                            'notes' => $notes,
                            'entity_type' => 1
                        ]);

                        // Return success response
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Lead added successfully.',
                            'data' => $lead
                        ], 200);
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => $existingLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }
                } else {
                    // Update an existing lead record
                    $lead = LeadCustomer::find($leadid);

                    if (!$lead) {
                        // Return error if lead not found
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Lead/customer not found.',
                            'data' => null
                        ], 200);
                    }

                    // Check if another lead with the same contact number and updated property_id exists
                    $duplicateLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->where('id', '!=', $leadid)  // Exclude the current lead
                        ->first();


                    if ($duplicateLead) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $duplicateLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }

                    // Update the existing lead record
                    $lead->update([
                        'property_id' => $propertyid,
                        'name' => $name,
                        'contact_no' => $contactno,
                        'email' => $email,
                        'source_id' => $sourceid,
                        'status' => 0, // You can change this to another value if needed
                        'type' => 0, // 0 - manual, modify if necessary
                        'notes' => $notes,
                        'entity_type' => 1
                    ]);

                    // Return success response for updating the lead
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead updated successfully.',
                        'data' => $lead
                    ], 200);
                }
            } elseif ($flag == 2) {
                // Flag 2: Add new lead with attached unit

                if ($leadid == 0) { //if new lead
                    // Check if the same contact number and property combination already exists
                    $existingLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->first();


                    if (!$existingLead) {
                        // Create a new lead record
                        $lead = LeadCustomer::create([
                            'property_id' => $propertyid,
                            'name' => $name,
                            'contact_no' => $contactno,
                            'email' => $email,
                            'source_id' => $sourceid,
                            'status' => 0, // 0-new
                            'type' => 0,  // manual
                            'notes' => $notes,
                            'entity_type' => 1
                        ]);

                        // Now handle the LeadUnit entry
                        $existingUnit = LeadCustomerUnit::where('unit_id', $unit_id)->first();

                        if ($existingUnit) {
                            // Append the new lead ID to the interested_lead_id (comma-separated)
                            // Convert the comma-separated string of IDs to an array
                            $interestedLeadIds = explode(',', $existingUnit->interested_lead_id);

                            // Check if the current lead ID is already in the array
                            if (!in_array($lead->id, $interestedLeadIds)) {
                                // Append the new lead ID only if it's not already in the array
                                $interestedLeadIds[] = $lead->id;
                                $existingUnit->interested_lead_id = implode(',', $interestedLeadIds);

                                // Update the lead_unit entry
                                $existingUnit->save();
                            }
                        } else {
                            // Create a new lead_unit entry if no existing entry for the unit
                            $existingUnit = LeadCustomerUnit::create([
                                'interested_lead_id' => $lead->id,
                                'leads_customers_id' => null,
                                'unit_id' => $unit_id,
                                'booking_status' => 2,
                            ]);
                        }



                        // Now handle the LeadUnitData entry
                        $leadUnitData = LeadCustomerUnitData::where('leads_customers_unit_id', $existingUnit->id)
                            ->where('leads_customers_id', $lead->id)
                            ->first();


                        if ($leadUnitData) {
                            // Update the budget if LeadUnitData exists
                            $leadUnitData->update([
                                'budget' => $budget,
                            ]);
                        } else {
                            // Create a new LeadUnitData entry if it doesn't exist
                            $leadcustomerunitdata = new LeadCustomerUnitData();
                            $leadcustomerunitdata->leads_customers_unit_id = $existingUnit->id;
                            $leadcustomerunitdata->leads_customers_id = $lead->id;
                            $leadcustomerunitdata->budget = $budget;
                            $leadcustomerunitdata->save();
                            // LeadCustomerUnitData::create([
                            //     'leads_customers_unit_id' => $existingUnit->id,
                            //     'leads_customers_id' => $lead->id,
                            //     'budget' => $budget,
                            // ]);
                        }

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Lead added with unit successfully.',
                            'data' => $lead
                        ], 200);
                    } else {
                        // If the lead exists, don't create a new lead, but pass it to the LeadUnit table
                        // $lead = $existingLead;

                        return response()->json([
                            'status' => 'error',
                            'message' => $existingLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }




                    // Return success response
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead added with unit successfully.',
                        'data' => $lead
                    ], 200);
                } else {
                    $existingLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->first();

                    if (!$existingLead) {
                        // Create a new lead record
                        $lead = LeadCustomer::create([
                            'property_id' => $propertyid,
                            'name' => $name,
                            'contact_no' => $contactno,
                            'email' => $email,
                            'source_id' => $sourceid,
                            'status' => 0, // 0-new
                            'type' => 0, // manual
                            'notes' => $notes,
                            'entity_type' => 1
                        ]);

                        // Now handle the LeadUnit entry
                        $existingUnit = LeadCustomerUnit::where('unit_id', $unit_id)->first();

                        if ($existingUnit) {
                            // Append the new lead ID to the interested_lead_id (comma-separated)
                            // Convert the comma-separated string of IDs to an array
                            $interestedLeadIds = explode(',', $existingUnit->interested_lead_id);

                            // Check if the current lead ID is already in the array
                            if (!in_array($lead->id, $interestedLeadIds)) {
                                // Append the new lead ID only if it's not already in the array
                                $interestedLeadIds[] = $lead->id;
                                $existingUnit->interested_lead_id = implode(',', $interestedLeadIds);

                                // Update the lead_unit entry
                                $existingUnit->save();
                            }
                        } else {
                            // Create a new lead_unit entry if no existing entry for the unit
                            $existingUnit = LeadCustomerUnit::create([
                                'interested_lead_id' => $lead->id,
                                'leads_customers_id' => null,
                                'unit_id' => $unit_id,
                                'booking_status' => 2,
                            ]);
                        }


                        // Now handle the LeadUnitData entry
                        $leadUnitData = LeadCustomerUnitData::where('leads_customers_unit_id', $existingUnit->id)
                            ->where('leads_customers_id', $lead->id)
                            ->first();

                        if ($leadUnitData) {
                            // Update the budget if LeadUnitData exists
                            $leadUnitData->update([
                                'budget' => $budget,
                            ]);
                        } else {
                            // Create a new LeadUnitData entry if it doesn't exist
                            $leadcustomerunitdata = new LeadCustomerUnitData();
                            $leadcustomerunitdata->leads_customers_unit_id = $existingUnit->id;
                            $leadcustomerunitdata->leads_customers_id = $lead->id;
                            $leadcustomerunitdata->budget = $budget;
                            $leadcustomerunitdata->save();
                            // LeadCustomerUnitData::create([
                            //     'leads_customers_unit_id' => $existingUnit->id,
                            //     'leads_customers_id' => $lead->id,
                            //     'budget' => $budget,
                            // ]);
                        }

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Lead added with unit successfully',
                            'data' => null
                        ], 200);
                    } else {
                        // If the lead exists, don't create a new lead, but pass it to the LeadUnit table
                        // $lead = $existingLead;
                        return response()->json([
                            'status' => 'error',
                            'message' => $existingLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }



                    // Return success response
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead added with unit successfully.',
                        'data' => $lead
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addEditLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }

    public function addLeadsCsv(Request $request)
    {
        try {
            // Check if a single CSV file is uploaded
            $file = $request->file('file');
            $propertyId = $request->input('propertyid');

            // Fetch property user email based on property id
            $property = UserProperty::find($propertyId);
            $propertyUserEmail = $property->user->email; // Assuming `user` is the relationship to the user


            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CSV file not found.',
                ], 200);
            }

            // Open the CSV file
            $csvFile = fopen($file, 'r');
            $header = fgetcsv($csvFile);
            $expectedHeaders = ['name', 'email(optional)', 'contact', 'source', 'notes(optional)'];
            $escapedHeader = [];

            foreach ($header as $value) {
                // Normalize headers by removing spaces and setting to lowercase
                $normalizedHeader = strtolower(str_replace([' ', '(optional)'], '', $value));
                $escapedHeader[] = $normalizedHeader;
            }

            // Define the expected headers in the same format
            $normalizedExpectedHeaders = ['name', 'email', 'contact', 'source', 'notes'];

            // Validate CSV headers
            if (array_diff($normalizedExpectedHeaders, $escapedHeader)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid CSV headers.',
                ], 200);
            }

            // $leads = [];

            $leadsAdded = [];
            $leadsIssues = [];


            // Process CSV rows
            while (($columns = fgetcsv($csvFile)) !== false) {
                $data = array_combine($escapedHeader, $columns);

                if (empty($data['name']) && empty($data['contact']) && empty($data['source'])) {
                    continue;
                }

                // Validate required fields (name, contact, source, budget)
                if (empty($data['name']) || empty($data['contact']) || empty($data['source'])) {
                    Helper::errorLog('addLeadDetailsfailed', 'Missing required field(s)', 'high');

                    $leadsIssues[] = [
                        'name' => $data['name'] ?? 'N/A',
                        'email' => $data['email'] ?? 'N/A',
                        'notes' => $data['notes'] ?? 'N/A',
                        'contact' => $data['contact'] ?? 'N/A',
                        'source' => $data['source'] ?? 'N/A',
                        'reason' => 'Missing required field(s)',
                    ];
                    continue;
                }

                // Check if the phone number is 10 digits
                if (!preg_match('/^\d{10}$/', $data['contact'])) {
                    Log::info('Skipping due to invalid phone number', $data);
                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'email' => $data['email'] ?? 'N/A',
                        'notes' => $data['notes'] ?? 'N/A',
                        'contact' => $data['contact'],
                        'source' => $data['source'],
                        'reason' => 'Invalid phone number (must be 10 digits)',
                    ];
                    continue;
                }

                // Validate email format if email is provided
                if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    Helper::errorLog('addLeadDetailsfailed', 'Invalid email format', 'high');

                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'email' => $data['email'] ?? 'N/A',
                        'contact' => $data['contact'],
                        'source' => $data['source'],
                        'notes' => $data['notes'] ?? 'N/A',
                        'reason' => 'Invalid email format',
                    ];
                    continue;
                }

                try {
                    // Check if source exists; if not, assign it to "others" (source_id = 5)
                    $source = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($data['source'])])->first();
                    if (!$source) {
                        $source = LeadSource::find(5);
                    }

                    // Check uniqueness based on contact number and property ID
                    $existingLead = LeadCustomer::where('contact_no', $data['contact'])
                        ->where('property_id', $propertyId)
                        ->first();

                    if ($existingLead) {
                        Log::info('Lead skipped for property id: ' . $propertyId, $data);

                        $leadsIssues[] = [
                            'name' => $data['name'],
                            'email' => $data['email'] ?? 'N/A',
                            'notes' => $data['notes'] ?? 'N/A',
                            'contact' => $data['contact'],
                            'source' => $data['source'],
                            'reason' => 'Duplicate entry based on contact number',
                        ];
                        continue;
                    }

                    // Create lead record
                    $lead = LeadCustomer::create([
                        'property_id' => $propertyId,
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'contact_no' => $data['contact'],
                        'notes' => $data['notes'] ?? null,
                        'source_id' => $source->id,
                        'status' => 0, // New lead
                        'type' => 1, // From CSV
                    ]);

                    $leadsAdded[] = $lead;
                } catch (\Exception $e) {
                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'email' => $data['email'] ?? 'N/A',
                        'notes' => $data['notes'] ?? 'N/A',
                        'contact' => $data['contact'],
                        'source' => $data['source'],
                        'reason' => "Error: " . $e->getMessage(),
                    ];

                    Helper::errorLog('addLeadDetailsfailed', $e->getMessage(), 'high');
                }
            }

            fclose($csvFile); // Close the file after processing

            // if (count($leadsIssues) > 0) {
            //     // Create a temporary CSV file for skipped/failed leads
            //     $csvFilePath = storage_path('app/leads_issues_' . time() . '.csv');
            //     $csvHandle = fopen($csvFilePath, 'w');
            //     fputcsv($csvHandle, ['Name', 'Email(optional)', 'Contact', 'Source','Reason']);

            //     foreach ($leadsIssues as $leadIssue) {
            //         fputcsv($csvHandle, [
            //             $leadIssue['name'],
            //             $leadIssue['email'],
            //             $leadIssue['contact'],
            //             $leadIssue['source'],
            //             $leadIssue['reason']
            //         ]);
            //     }

            //     fclose($csvHandle);

            //     // Send the email with the CSV attachment
            //     Mail::to($propertyUserEmail)->send(new ManageLeads($property, $leadsIssues, $csvFilePath));

            //     // Delete the temporary file after sending the email
            //     if (file_exists($csvFilePath)) {
            //         unlink($csvFilePath); // This removes the temporary CSV file
            //     }
            // }

            return response()->json([
                'status' => 'success',
                'message' => 'Leads added successfully from CSV file.',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }


    //rest api
    public function generateLead(Request $request)
    {
        try {
            // Validate client_id and client_secret
            $client_id = $request->query('client_id');
            $client_secret_key = $request->query('client_secret_key');

            // Check if client_id and client_secret are provided
            if (!$client_id || !$client_secret_key) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing or Invalid Data.'
                ], 200);
            }

            // Find the user with the given client_id and client_secret
            $user = User::where('client_id', $client_id)
                ->where('client_secret_key', $client_secret_key)
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Authentication.'
                ], 200);
            }

            // Validate JSON input for lead creation
            $validatedData = $request->validate([
                'leads' => 'required|array', // Expect an array of leads
                'leads.*.name' => 'required|string|max:255',
                'leads.*.email' => 'nullable|email|max:255',
                'leads.*.contact' => 'required|string|max:15',
                'leads.*.notes' => 'nullable|string', // Ensure budget is required and numeric
                'leads.*.source' => 'required|string|max:255', // Example: "call"
                'leads.*.property' => 'required|string|max:255', // Property could be validated more specifically if needed
            ]);

            $createdLeads = [];
            $existingLeads = [];

            foreach ($validatedData['leads'] as $leadData) {
                // Find property ID by property name
                $property = UserProperty::whereRaw('LOWER(name) = ?', [strtolower($leadData['property'])])->first();

                if (!$property) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Property not found for ' . $leadData['property'],
                    ], 200);
                }

                // Check if the lead with the same email and property already exists
                $existingLead = LeadCustomer::where('contact_no', $leadData['contact'])
                    ->where('property_id', $property->id)
                    ->first();

                if ($existingLead) {
                    $existingLeads[] = $leadData['contact']; // Collect existing leads
                    continue; // Skip to the next lead
                }

                // Find source ID
                $sourceId = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($leadData['source'])])->value('id');

                // Create the new lead
                $newLead = LeadCustomer::create([
                    'property_id' => $property->id,
                    'name' => $leadData['name'],
                    'email' => $leadData['email'],
                    'contact_no' => $leadData['contact'],
                    'source_id' => $sourceId,
                    'status' => 0,  // Default to new lead
                    'type' => 2, // 0 for manual, 1 CSV, 2 REST API,
                    'notes' => $leadData['notes'],
                ]);

                $createdLeads[] = $newLead; // Collect newly created leads
            }

            // Prepare the response
            return response()->json([
                'status' => 'success',
                'message' => 'Leads created successfully.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors and return a proper response
            return response()->json([
                'status' => 'error',
                'message' => 'Missing or Invalid Data.',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'restapidetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }




    //web form api
    public function webFormLead(Request $request)
    {
        try {

            // Step 1: Validate Google reCAPTCHA
            $recaptchaResponse = $request->input('grecaptcha');
            $secretKey = env('recaptcha_secret'); // Make sure to store your secret key in env

            // Make an API request to verify the reCAPTCHA response
            $recaptcha = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $recaptchaResponse,
            ]);

            $recaptchaResult = json_decode($recaptcha->body());

            // If reCAPTCHA validation fails
            if (!$recaptchaResult->success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'reCAPTCHA validation failed.',
                ], 200);
            }

            // Step 2: Referrer validation
            $referer = $request->header('referer'); // Get referer from headers

            // $referer="http://superbuildup.s3-website.ap-south-1.amazonaws.com/";
            //env('APP_FRONTEND_URL')= http://superbuildup.s3-website.ap-south-1.amazonaws.com/
            // $allowedDomains = [env('APP_FRONTEND_URL'), '127.0.0.1', 'localhost'];


            $frontendUrl = env('APP_FRONTEND_URL');

            // Normalize the domain by extracting the host part
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $frontendHost = parse_url($frontendUrl, PHP_URL_HOST);

            $allowedDomains = [$frontendHost, '127.0.0.1', 'localhost'];

            if (!$referer || !in_array($refererHost, $allowedDomains)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized domain.',
                ], 200);
            }

            if (!$referer || !in_array(parse_url($referer, PHP_URL_HOST), $allowedDomains)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized domain.',
                ], 200);
            }

            // Step 3: Validate form inputs
            $validatedData = $request->validate([
                'propertyinterest' => 'required|integer',  // Assuming propertyinterest is an integer (property_id)
                'name' => 'required|string|max:255',       // Name is required and must be a string
                'email' => 'nullable|email|max:255',       // Email is required and must be valid
                'contactno' => 'required|string|max:15',   // Contact number is required, can be a string
                'source' => 'required|integer',            // Source ID is required (1-reference, 2-social media, etc.)      // Budget is optional and must be a number if provided
                'notes' => 'nullable|string',
            ]);

            // Retrieve validated data from the request
            $propertyid = $validatedData['propertyinterest'];
            $name = $validatedData['name'];
            $email = $validatedData['email'];
            $contactno = $validatedData['contactno'];
            $sourceid = $validatedData['source'];
            $notes = $request->input('notes'); // notes remains nullable

            // Check if the same email and property combination already exists
            $existingLead = LeadCustomer::where('contact_no', $contactno)
                ->where('property_id', $propertyid)
                ->first();


            if (!$existingLead) {
                // Create a new lead record for manual or web form entry //0 or 2
                $lead = LeadCustomer::create([
                    'property_id' => $propertyid,
                    'name' => $name,
                    'email' => $email,
                    'contact_no' => $contactno,
                    'source_id' => $sourceid,
                    'notes' => $notes,
                    'status' => 0, //0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
                    'type' => 3 //web form
                ]);

                // Return success response
                return response()->json([
                    'status' => 'success',
                    'message' => 'Lead added successfully.',
                    'data' => $lead
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $existingLead->name . ' is already added with this contact no.',
                    'data' => null
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'webformdetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }

    public function fetchLeadInterestedBookedDetail($pid, $lid)
    {
        try {
            if ($pid != 'null') {

                // Fetch Lead Customer details
                // Get the lead customer details based on property ID and lead ID
                $leadcustomerdetails = LeadCustomer::with(['leadCustomerUnits.unit', 'leadCustomerUnits.paymentTransaction', 'leadCustomerUnits.leadCustomer', 'leadCustomerUnits.leadCustomerUnitData'])
                    ->where('property_id', $pid)
                    ->where('id', $lid)
                    ->first();

                if ($leadcustomerdetails) {
                    // Initialize arrays for interested and booked units
                    $interestedLeads = [];
                    $bookedDetails = [];

                    // Fetch interested leads where this lead is marked as interested_lead_id
                    $interestedUnits = LeadCustomerUnit::where(function ($query) use ($lid) {
                        $query->where('interested_lead_id', $lid)
                            ->orWhereRaw('FIND_IN_SET(?, interested_lead_id)', [$lid]);
                    })->with(['unit.wingDetail', 'leadCustomerUnitData'])->get();

                    foreach ($interestedUnits as $unit) {
                        $interestedLeads[] = [
                            'wing_name' => $unit->unit->wingDetail->name ?? null,
                            'unit_name' => $unit->unit->name ?? null,
                            'lead_name' => $unit->leadCustomer->name ?? null,
                            'budget' => $unit->leadCustomerUnitData->pluck('budget')->first() ?? null,
                        ];
                    }

                    // Loop through the lead customer's units
                    foreach ($leadcustomerdetails->leadCustomerUnits as $unit) {
                        // Booked units (booking details)
                        $paymentTransactions = PaymentTransaction::where('unit_id', $unit->unit_id)
                        ->where('leads_customers_id', $unit->leadCustomer->id)
                        ->orderBy('id', 'asc') // Ensure the first transaction is first in the results
                        ->get();
                
                    // Calculate the total paid amount
                    $totalPaidAmount = 0;
                
                    foreach ($paymentTransactions as $index => $transaction) {
                        if ($index === 0) {
                            // Add the token amount from the first transaction
                            $totalPaidAmount += $transaction->token_amt;
                        } else {
                            // Add the next payable amount from subsequent transactions
                            $totalPaidAmount += $transaction->next_payable_amt;
                        }
                    }
                       
                        if ($unit->paymentTransaction) {
                            $bookedDetails[] = [
                                'wing_name' => $unit->unit->wingDetail->name,
                                'unit_name' => $unit->unit->name,
                                'customer_name' => $unit->leadCustomer->name,
                                'unit_price' => $unit->unit->price ?? 0,
                                'total_paid_amount' => $totalPaidAmount ?? 0,
                                'booking_date' => $unit->paymentTransaction->booking_date ?? null,
                            ];
                        }
                    }

                    // Return the response with the lead customer details, interested units, and booked units inside one object
                    return response()->json([
                        'leadcustomerdetails' => [
                            'id' => $leadcustomerdetails->id,
                            'property_id' => $leadcustomerdetails->property_id,
                            'name' => $leadcustomerdetails->name,
                            'email' => $leadcustomerdetails->email ?? null,
                            'contact_no' => $leadcustomerdetails->contact_no,
                            'source_id' => $leadcustomerdetails->source_id,
                            'source_name' => $leadcustomerdetails->leadSource->name ?? null, // Add lead source name
                            'status' => $leadcustomerdetails->status,
                            'type' => $leadcustomerdetails->type,
                            'entity_type' => $leadcustomerdetails->entity_type,
                            'notes' => $leadcustomerdetails->notes ??  null,
                            'created_at' => $leadcustomerdetails->created_at,
                            'updated_at' => $leadcustomerdetails->updated_at,
                            'interested_units' => $interestedLeads,
                            'booked_units' => $bookedDetails,
                        ],
                    ], 200);
                } else {
                    return response()->json([
                        'leadcustomerdetails' => null,
                    ], 200);
                }
            } else {
                return response()->json([
                    'leadcustomerdetails' => null,
                ], 200);
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'fetchLeadInterestedBookedDetail';
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
