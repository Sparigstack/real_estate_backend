<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\UserProperty;
use Exception;
use Illuminate\Support\Facades\Validator;


class LeadController extends Controller
{


    public function getLeads($uid)
    {
        try{
            $allLeads = Lead::with('userproperty','leadSource')->whereHas('userproperty', function ($query) use ($uid) {
                $query->where('user_id', $uid);
            })->get();
            return $allLeads;
        }catch(Exception $e){
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
        try{
            $allUserProperties = UserProperty::where('user_id',$uid)->get();
            return $allUserProperties;
        }catch(Exception $e){
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

        try{
            $allSources = LeadSource::all();
            return $allSources;
        }catch(Exception $e){
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
    public function addOrEditLeads(Request $request)
    {
        try {
            // Retrieve inputs from the request
            $propertyid = $request->input('propertyId');
            $name = $request->input('name');
            $email = $request->input('email');
            $contactno = $request->input('contactNo');
            $source = $request->input('source_id'); // 1-reference, 2-social media, 3-call, 4-in person
            $budget = $request->input('budget');
            // $status = $request->input('status'); // 0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
            $type = $request->input('type', 0); // 0-manual, 1-csv, 2-web form

            // Check if the source is CSV (type 1)
            if ($type == 1) {
                // Handle multiple CSV files
                $files = $request->file('file'); // Retrieve an array of files

                if (!$files || !is_array($files)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'CSV files not found.',
                        'data' => null
                    ], 400);
                }

                $leads = [];

                foreach ($files as $file) {
                    // Open each CSV file
                    $csvFile = fopen($file, 'r');
                    $header = fgetcsv($csvFile);
                    $expectedHeaders = ['name', 'email', 'contactno', 'propertyid', 'source', 'budget', 'status'];
                    $escapedHeader = [];

                    foreach ($header as $key => $value) {
                        $escapedHeader[] = preg_replace('/[^a-z]/', '', strtolower($value));
                    }

                    if (array_diff($expectedHeaders, $escapedHeader)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid CSV headers in one of the files.'
                        ], 400);
                    }

                    // Process CSV rows
                    while (($columns = fgetcsv($csvFile)) !== false) {
                        $data = array_combine($escapedHeader, $columns);

                        $lead = Lead::create([
                            'property_id' => $data['propertyid'],
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'contact_no' => $data['contactno'],
                            'source' => $data['source'],
                            'budget' => $data['budget'],
                            'status' => "2",
                            'type' => $type,
                        ]);

                        $leads[] = $lead;
                    }

                    fclose($csvFile); // Close each file after processing
                }

                return response()->json([
                    'status' => 'success',
                    'msg' => 'Leads added successfully from CSV files.',
                    'data' => $leads
                ], 200);
            } else {
                // Create a new lead record for manual or web form entry //0 or 2
                $lead = Lead::create([
                    'property_id' => $propertyid,
                    'name' => $name,
                    'email' => $email,
                    'contact_no' => $contactno,
                    'source' => $source,
                    'budget' => $budget,
                    'status' => "2",
                    'type' => $type
                ]);

                // Return success response
                return response()->json([
                    'status' => 'success',
                    'msg' => 'Lead added successfully.',
                    'data' => $lead
                ], 200);
            }
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
