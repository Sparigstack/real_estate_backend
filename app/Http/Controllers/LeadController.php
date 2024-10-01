<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\UserProperty;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class LeadController extends Controller
{

    public function getLeads($uid,$skey,$sort,$sortbykey,$offset,$limit)
    { 
     
        try {
            if ($uid != 'null') {
                $allLeads = Lead::with('userproperty', 'leadSource')->whereHas('userproperty', function ($query) use ($uid) {
                    $query->where('user_id', $uid);
                });

                //search query
                if($skey != 'null'){
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
                if($sortbykey != 'null'){
                    if (in_array($sortbykey, ['name', 'email', 'budget'])) {
                        $allLeads->orderBy($sortbykey, $sort);
                    } elseif ($sortbykey === 'source') {
                        $allLeads->orderBy(LeadSource::select('name')->whereColumn('lead_sources.id', 'leads.source_id'), $sort);
                    } elseif ($sortbykey === 'property') {
                        $allLeads->orderBy(UserProperty::select('name')->whereColumn('user_properties.id', 'leads.property_id'), $sort);
                    }
                }
                
                //page offset
                if($offset != 'null'){
                    $allLeads->skip($offset);
                }

                //limit page vise
                if($limit != 'null'){
                    $allLeads->take($limit);
                }
                $allLeads = $allLeads->get();
    
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
                'msg' => 'Not found',
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
                'msg' => 'Not found',
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
                'msg' => 'Not found',
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
                'msg' => 'Not found',
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
                        'msg' => 'Lead added successfully.',
                        'data' => $lead
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'success',
                        'msg' => 'Lead already exists.',
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
                        'msg' => 'Lead not found.',
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
                        'msg' => 'Lead already exists.',
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
                    'msg' => 'Lead updated successfully.',
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
                'msg' => 'Something went wrong',
            ], 400);
        }
    }

    public function addLeadsCsv(Request $request)
    {
        try {
            // Check if a single CSV file is uploaded
            $file = $request->file('file');
            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CSV file not found.',
                    'data' => null
                ], 200);
            }

            // Open the CSV file
            $csvFile = fopen($file, 'r');
            $header = fgetcsv($csvFile);
            $expectedHeaders = ['name', 'email', 'contact', 'property', 'source', 'budget'];
            $escapedHeader = [];

            foreach ($header as $key => $value) {
                $escapedHeader[] = preg_replace('/[^a-z]/', '', strtolower($value));
            }

            // Validate CSV headers
            if (array_diff($expectedHeaders, $escapedHeader)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid CSV headers.',
                    'data' => null
                ], 200);
            }

            $leads = [];

            // Process CSV rows
            while (($columns = fgetcsv($csvFile)) !== false) {
                $data = array_combine($escapedHeader, $columns);

                // Debug: print the current row
                Log::info('CSV row: ', $data);
                // Case-insensitive check if property interest exists in user_properties
                $property = UserProperty::whereRaw('LOWER(name) = ?', [strtolower($data['property'])])->first();
                if (!$property) {
                    // Skip row if property doesn't match
                    continue;
                }

                // Case-insensitive check if source exists, insert if not
                $source = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($data['source'])])->first();
                if (!$source) {
                    $source = LeadSource::find(5); // Assign to "others"
                }


                // Uniqueness check: If the same email and property_id exist, skip
                $existingLead = Lead::where('email', $data['email'])
                    ->where('property_id', $property->id)
                    ->first();

                if ($existingLead) {
                    // Skip row if the combination of email and property_id already exists
                    continue;
                }


                // Create lead record
                $lead = Lead::create([
                    'property_id' => $property->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'contact_no' => $data['contact'],
                    'source_id' => $source->id,
                    'budget' => $data['budget'],
                    'status' => 0, // New lead
                    'type' => 1, // From CSV
                ]);

                $leads[] = $lead;
            }

            fclose($csvFile); // Close the file after processing

            return response()->json([
                'status' => 'success',
                'msg' => 'Leads added successfully from CSV file.',
                'data' => $leads
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'msg' => 'Something went wrong',
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
                    'message' => 'Client ID and Client Secret are required.'
                ], 200);
            }

            // Find the user with the given client_id and client_secret
            $user = User::where('client_id', $client_id)
                ->where('client_secret_key', $client_secret_key)
                ->first();


            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Client ID or Client Secret.'
                ], 200);
            }



            // Validate JSON input for lead creation
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'contact' => 'required|string|max:15',
                'budget' => 'required|numeric',
                'source' => 'required|string|max:255', // Example: "call"
                'property' => 'required|string|max:255', // Property could be validated more specifically if needed
            ]);

            // Find property ID by property name (assuming 'property' is a name, not ID)
            $property = UserProperty::whereRaw('LOWER(name) = ?', [strtolower($validatedData['property'])])->first();

            if (!$property) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Property not found.'
                ], 200);
            }

            // Check if the lead with the same email and property already exists
            $existingLead = Lead::where('email', $validatedData['email'])
                ->where('property_id', $property->id)
                ->first();

            if ($existingLead) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lead already exists.'
                ], 200); // Conflict HTTP status code
            }

            $sourceId = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($validatedData['source'])])->value('id');
            // Create the new lead
            $lead = Lead::create([
                'property_id' => $property->id,
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'contact_no' => $validatedData['contact'],
                'source_id' => $sourceId,
                'budget' => $validatedData['budget'],
                'status' => 0,  // Default to new lead
                'type' => 2, // 0 for manual,1 csv, 2 rest api
            ]);

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead created successfully.',
                'data' => $lead
            ], 200); // Created HTTP status code

        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'restapidetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'msg' => 'Something went wrong',
            ], 400);
        }
    }



    //web form api
    public function webFormLead(Request $request)
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
                    'msg' => 'Lead added successfully.',
                    'data' => $lead
                ], 200);
            } else {
                return response()->json([
                    'status' => 'success',
                    'msg' => 'Lead already exists.',
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
                'msg' => 'Something went wrong',
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
