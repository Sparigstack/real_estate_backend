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


class LeadController extends Controller
{

    public function getLeads($uid)
    {
        try {
            if(isset($uid)){
                $allLeads = Lead::with('userproperty', 'leadSource')->whereHas('userproperty', function ($query) use ($uid) {
                    $query->where('user_id', $uid);
                })->get();
                return $allLeads;
            }
            else{
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
            if(isset($uid)){
                $allUserProperties = UserProperty::where('user_id', $uid)->get();
                 return $allUserProperties;
            }
            else{
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
            if(isset($uid) && isset($lid)){
                $fetchLeadDetail = Lead::with('userproperty', 'leadSource')->whereHas('userproperty', function ($query) use ($uid) {
                    $query->where('user_id', $uid);
                })->where('id', $lid)->first();
                return $fetchLeadDetail;
            }
            else{
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

    public function addOrEditLeads(Request $request, $lid)
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
            // $status = $request->input('status'); // 0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
            // $type = $request->input('type', 0); // 0-manual, 1-csv, 2-web form


            if ($lid == 0) {
                // Create a new lead record for manual or web form entry //0 or 2
                $lead = Lead::create([
                    'property_id' => $propertyid,
                    'name' => $name,
                    'email' => $email,
                    'contact_no' => $contactno,
                    'source_id' => $sourceid,
                    'budget' => $budget,
                    'status' => "0", //0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
                    'type' => "0" //manual
                ]);

                // Return success response
                return response()->json([
                    'status' => 'success',
                    'msg' => 'Lead added successfully.',
                    'data' => $lead
                ], 200);
            } else {
                // update a lead record for manual or web form entry //0 or 2
                // Find existing lead by ID
                $lead = Lead::find($lid);

                if (!$lead) {
                    // Return error if lead not found
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'Lead not found.',
                        'data' => null
                    ], 404);
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
                ], 400);
            }

            // Open the CSV file
            $csvFile = fopen($file, 'r');
            $header = fgetcsv($csvFile);
            $expectedHeaders = ['name', 'email', 'contactno', 'property', 'source', 'budget'];
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
                ], 400);
            }

            $leads = [];

            // Process CSV rows
            while (($columns = fgetcsv($csvFile)) !== false) {
                $data = array_combine($escapedHeader, $columns);

                // Case-insensitive check if property interest exists in user_properties
                $property = UserProperty::whereRaw('LOWER(name) = ?', [strtolower($data['property'])])->first();
                if (!$property) {
                    // Skip row if property doesn't match
                    continue;
                }

                // Case-insensitive check if source exists, insert if not
                $source = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($data['source'])])->first();
                if (!$source) {
                    $source = LeadSource::create(['name' => ucfirst($data['source'])]);
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
                    'contact_no' => $data['contactno'],
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
}
