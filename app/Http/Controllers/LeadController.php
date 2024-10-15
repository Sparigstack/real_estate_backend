<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Mail\ManageLeads;
use App\Models\Lead;
use App\Models\LeadSource;
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

    public function getLeads($pid, $skey, $sort, $sortbykey, $offset, $limit)
    {

        try {
            if ($pid != 'null') {
                $allLeads = Lead::with('userproperty', 'leadSource')->where('property_id',$pid);


                //search query
                if ($skey != 'null') {
                    $allLeads->where(function ($q) use ($skey) {
                        $q->where('name', 'like', "%{$skey}%")
                            ->orWhere('email', 'like', "%{$skey}%")
                            ->orWhereHas('leadSource', function ($q) use ($skey) {
                                $q->where('name', 'like', "%{$skey}%");
                            })
                            ->orWhereHas('userproperty', function ($q) use ($skey) {
                                $q->where('name', 'like', "%{$skey}%");
                            });
                    });
                }

                //sortby key
                if ($sortbykey != 'null') {
                    if (in_array($sortbykey, ['name', 'email', 'budget', 'contact_no'])) {
                        $allLeads->orderBy($sortbykey, $sort);
                    } elseif ($sortbykey == 'source') {
                        $allLeads->orderBy(LeadSource::select('name')->whereColumn('lead_sources.id', 'leads.source_id'), $sort);
                    } elseif ($sortbykey == 'property') {
                        $allLeads->orderBy(UserProperty::select('name')->whereColumn('user_properties.id', 'leads.property_id'), $sort);
                    }
                }

                $allLeads = $allLeads->paginate($limit, ['*'], 'page', $offset);

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

    public function fetchLeadDetail(Request $request, $uid, $lid)
    {
        try {
            if ($uid != 'null' && $lid != 'null') {
                $fetchLeadDetail = Lead::with('userproperty', 'leadSource')->whereHas('userproperty', function ($query) use ($uid) {
                    $query->where('user_id', $uid);
                })->where('id', $lid)->first();
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
                'email' => 'required|email|max:255',       // Email is required and must be valid
                'contactno' => 'required|string|max:15',   // Contact number is required, can be a string
                'source' => 'required|integer',            // Source ID is required (1-reference, 2-social media, etc.)
                'budget' => 'required|numeric',            // Budget is optional and must be a number if provided
                'leadid' => 'required|numeric',
            ]);

            // Retrieve validated data from the request
            $propertyid = $validatedData['propertyinterest'];
            $name = $validatedData['name'];
            $email = $validatedData['email'];
            $contactno = $validatedData['contactno'];
            $sourceid = $validatedData['source'];
            $budget = $request->input('budget'); // Budget remains nullable
            $leadid = $request->input('leadid');
            // $status = $request->input('status'); // 0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
            // $type = $request->input('type', 0); // 0-manual, 1-csv, 2-web form

            if ($leadid == 0) {

                // Check if the same email and property combination already exists
                $existingLead = Lead::where('email', $email)
                    ->where('property_id', $propertyid)
                    ->first();


                if (!$existingLead) {
                    // Create a new lead record for manual or web form entry //0 or 2
                    $lead = Lead::create([
                        'property_id' => $propertyid,
                        'name' => $name,
                        'email' => $email,
                        'contact_no' => $contactno,
                        'source_id' => $sourceid,
                        'budget' => $budget,
                        'status' => 0, //0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
                        'type' => 0 //manual
                    ]);

                    // Return success response
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead added successfully.',
                        'data' => $lead
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead already exists.',
                        'data' => null
                    ], 200);
                }
            } else {
                // update a lead record for manual or web form entry //0 or 2
                // Find existing lead by ID
                $lead = Lead::find($leadid);

                if (!$lead) {
                    // Return error if lead not found
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Lead not found.',
                        'data' => null
                    ], 200);
                }

                // Check if another lead with the same email and updated property_id exists
                $duplicateLead = Lead::where('email', $email)
                    ->where('property_id', $propertyid)
                    ->where('id', '!=', $leadid)  // Exclude the current lead
                    ->first();

                if ($duplicateLead) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Lead already exists.',
                        'data' => null
                    ], 200);
                }

                // Update the existing lead record
                $lead->update([
                    'property_id' => $propertyid,
                    'name' => $name,
                    'email' => $email,
                    'contact_no' => $contactno,
                    'source_id' => $sourceid,
                    'budget' => $budget,
                    'status' => 0, // You can change this to another value if needed
                    'type' => 0 // 0 - manual, modify if necessary
                ]);

                // Return success response for updating the lead
                return response()->json([
                    'status' => 'success',
                    'message' => 'Lead updated successfully.',
                    'data' => $lead
                ], 200);
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
            $expectedHeaders = ['name', 'email', 'contact', 'source', 'budget'];
            $escapedHeader = [];

            foreach ($header as $key => $value) {
                $escapedHeader[] = preg_replace('/[^a-z]/', '', strtolower($value));
            }

            // Validate CSV headers
            if (array_diff($expectedHeaders, $escapedHeader)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid CSV headers.',
                ], 200);
            }

            // $leads = [];

            $leadsAdded = [];
            $leadsIssues =[];


            // Process CSV rows
            while (($columns = fgetcsv($csvFile)) !== false) {
                $data = array_combine($escapedHeader, $columns);

                // Debug: print the current row
                Log::info('CSV row: ', $data);


                if (empty($data['name']) && empty($data['email']) && empty($data['contact']) && empty($data['source']) && empty($data['budget'])) {
                    // Skip this row entirely if all fields are empty
                    continue;
                }

                // Validate that required fields are not empty
                if (empty($data['name']) || empty($data['email']) || empty($data['contact']) || empty($data['source']) || empty($data['budget'])) {
                    // Add to failed leads with reason
                    $errorFrom = 'addLeadDetailsfailed';
                    $errorMessage = 'Missing required field(s)';
                    $priority = 'high';
                    Helper::errorLog($errorFrom, $errorMessage, $priority);

                    $leadsIssues[] = [
                        'name' => $data['name'] ?? 'N/A',
                        'email' => $data['email'] ?? 'N/A',
                        'contact' => $data['contact'] ?? 'N/A',
                        'source' => $data['source'] ?? 'N/A',
                        'budget' => $data['budget'] ?? 'N/A',
                        'reason' => 'Missing required field(s)',
                    ];
                    continue; // Skip this row and move to the next one
                }

                // Additional validation: email format
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errorFrom = 'addLeadDetailsfailed';
                    $errorMessage = 'Invalid email format';
                    $priority = 'high';
                    Helper::errorLog($errorFrom, $errorMessage, $priority);

                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'contact' => $data['contact'],
                        'source' => $data['source'],
                        'budget' => $data['budget'],
                        'reason' => 'Invalid email format',
                    ];
                    continue;
                }

                try {
                    // Case-insensitive check if source exists, insert if not
                    $source = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($data['source'])])->first();
                    if (!$source) {
                        $source = LeadSource::find(5); // Assign to "others"
                    }


                    // Uniqueness check: If the same email and property_id exist, skip
                    $existingLead = Lead::where('email', $data['email'])
                        ->where('property_id', $propertyId)
                        ->first();


                    if ($existingLead) {
                        Log::info('leads skipped for property id: ' . $propertyId, $data);

                        // Skip row if the combination of email and property_id already exists
                        $leadsIssues[] = [
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'contact' => $data['contact'],
                            'source' => $data['source'],
                            'budget' => $data['budget'],
                            'reason' => 'Duplicate entry',
                        ];
                        continue;
                    }


                    // Create lead record

                    $lead = Lead::create([
                        'property_id' => $propertyId,
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'contact_no' => $data['contact'],
                        'source_id' => $source->id,
                        'budget' => $data['budget'],
                        'status' => 0, // New lead
                        'type' => 1, // From CSV
                    ]);

                    // $leadsAdded[] = $lead;
                } catch (\Exception $e) {
                    // If something goes wrong for this row, log it as a failed lead
                    $leadsFailed[] = [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'contact' => $data['contact'],
                        'source' => $data['source'],
                        'budget' => $data['budget'],
                        'reason' => "Something went wrong"
                    ];

                    $errorFrom = 'addLeadDetailsfailed';
                    $errorMessage = $e->getMessage();
                    $priority = 'high';
                    Helper::errorLog($errorFrom, $errorMessage, $priority);
                }
            }

            fclose($csvFile); // Close the file after processing

            if (count($leadsIssues) > 0) {
                // Create a temporary CSV file for skipped/failed leads
                $csvFilePath = storage_path('app/leads_issues_' . time() . '.csv');
                $csvHandle = fopen($csvFilePath, 'w');
                fputcsv($csvHandle, ['Name', 'Email', 'Contact', 'Source', 'Budget', 'Reason']);
    
                foreach ($leadsIssues as $leadIssue) {
                    fputcsv($csvHandle, [
                        $leadIssue['name'],
                        $leadIssue['email'],
                        $leadIssue['contact'],
                        $leadIssue['source'],
                        $leadIssue['budget'],
                        $leadIssue['reason']
                    ]);
                }
    
                fclose($csvHandle);
    
                // Send the email with the CSV attachment
                Mail::to($propertyUserEmail)->send(new ManageLeads($property, $leadsIssues, $csvFilePath));
    
                // Delete the temporary file after sending the email
                if (file_exists($csvFilePath)) {
                    unlink($csvFilePath); // This removes the temporary CSV file
                }
            }

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
            $client_id = $request->header('client_id');
            $client_secret_key = $request->header('client_secret_key');

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
                'leads.*.email' => 'required|email|max:255',
                'leads.*.contact' => 'required|string|max:15',
                'leads.*.budget' => 'required|numeric', // Ensure budget is required and numeric
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
                $existingLead = Lead::where('email', $leadData['email'])
                    ->where('property_id', $property->id)
                    ->first();

                if ($existingLead) {
                    $existingLeads[] = $leadData['email']; // Collect existing leads
                    continue; // Skip to the next lead
                }

                // Find source ID
                $sourceId = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($leadData['source'])])->value('id');

                // Create the new lead
                $newLead = Lead::create([
                    'property_id' => $property->id,
                    'name' => $leadData['name'],
                    'email' => $leadData['email'],
                    'contact_no' => $leadData['contact'],
                    'source_id' => $sourceId,
                    'budget' => $leadData['budget'],
                    'status' => 0,  // Default to new lead
                    'type' => 2, // 0 for manual, 1 CSV, 2 REST API
                ]);

                $createdLeads[] = $newLead; // Collect newly created leads
            }

            // Prepare the response
            return response()->json([
                'status' => 'success',
                'message' => 'Leads created  successfully.',
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
            $allowedDomains = ['127.0.0.1', 'localhost'];

            if (!$referer || !in_array(parse_url($referer, PHP_URL_HOST), $allowedDomains)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized domain.',
                ], 403);
            }

            // Step 3: Validate form inputs
            $validatedData = $request->validate([
                'propertyinterest' => 'required|integer',  // Assuming propertyinterest is an integer (property_id)
                'name' => 'required|string|max:255',       // Name is required and must be a string
                'email' => 'required|email|max:255',       // Email is required and must be valid
                'contactno' => 'required|string|max:15',   // Contact number is required, can be a string
                'source' => 'required|integer',            // Source ID is required (1-reference, 2-social media, etc.)
                'budget' => 'required|numeric',            // Budget is optional and must be a number if provided
            ]);

            // Retrieve validated data from the request
            $propertyid = $validatedData['propertyinterest'];
            $name = $validatedData['name'];
            $email = $validatedData['email'];
            $contactno = $validatedData['contactno'];
            $sourceid = $validatedData['source'];
            $budget = $request->input('budget'); // Budget remains nullable

            // Check if the same email and property combination already exists
            $existingLead = Lead::where('email', $email)
                ->where('property_id', $propertyid)
                ->first();


            if (!$existingLead) {
                // Create a new lead record for manual or web form entry //0 or 2
                $lead = Lead::create([
                    'property_id' => $propertyid,
                    'name' => $name,
                    'email' => $email,
                    'contact_no' => $contactno,
                    'source_id' => $sourceid,
                    'budget' => $budget,
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
                    'status' => 'success',
                    'message' => 'Lead already exists.',
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

    public function updateLeadNotes(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'leadid' => 'required|integer|exists:leads,id', // Make sure leadid exists in leads table
            'notes' => 'required|string|max:500',
        ]);

        // If validation fails, return the error messages
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
            ], 400);
        }

        try {
            // Find the lead by id and update the notes
            $lead = Lead::find($request->input('leadid'));
            $lead->notes = $request->input('notes');
            $lead->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Lead notes updated successfully',
            ], 200);
        } catch (\Exception $e) {
            // Log the error and return a response
            $errorFrom = 'updateLeadNotes';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }
}
