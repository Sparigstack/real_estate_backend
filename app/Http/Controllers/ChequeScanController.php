<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Storage;
use App\Helper;
use App\Models\Customer;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use App\Models\Lead;
use App\Models\LeadUnit;
use App\Models\PaymentTransaction;
use App\Models\UnitDetail;
use App\Models\WingDetail;
use Exception;

class ChequeScanController extends Controller
{

    public function detectCheque(Request $request)
    {
        try {
            $request->validate(['image' => 'required|image']);
            $propertyID = $request->input('propertyID');

            $imageContent = file_get_contents($request->file('image')->getRealPath());
            $client = new DocumentProcessorServiceClient([
                'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            ]);
            $rawDocument = (new RawDocument())
                ->setContent($imageContent)
                ->setMimeType($request->file('image')->getMimeType());

            $processRequest = (new ProcessRequest())
                ->setName("projects/cloud-vision-438307/locations/us/processors/1a6eb05828f2b041")
                ->setRawDocument($rawDocument);

            $response = $client->processDocument($processRequest);
            $document = $response->getDocument();
            $entities = $document->getEntities();
           

            $userName = null;
            $amount = null;
            $entitiesArray = [];

            foreach ($entities as $entity) {
                $entitiesArray[] = [
                    'type' => $entity->getType(),
                    'mention_text' => $entity->getMentionText(),
                ];
            }

            // $entitiesArray = json_decode('[
            //     {
            //         "type": "scan-amount",
            //         "mention_text": "50,25,000"
            //     },
            //     {
            //         "type": "scan-name",
            //         "mention_text": "riya"
            //     },
            //      {
            //         "type": "scan-name",
            //         "mention_text": "prateek CHOUDHARY"
            //     }
            // ]', true);

            // ,
            //     {
            //         "type": "scan-name",
            //         "mention_text": "parin CHOUDHARY"
            //     },
            //      {
            //         "type": "scan-name",
            //         "mention_text": "deram CHOUDHARY"
            //     },
            //     {
            //         "type": "scan-name",
            //         "mention_text": "yira CHOUDHARY"
            //     }

          //  $propertyID = 1;

            

            // Fetch all leads with a type flag
            // Fetch all leads that are either allocated or interested for the specified property
            // Fetch all LeadUnits for the given property ID
            $leadUnits = LeadUnit::whereHas('unit', function ($query) use ($propertyID) {
                $query->where('property_id', $propertyID);
            })
                ->get(['id', 'interested_lead_id', 'allocated_lead_id', 'allocated_customer_id', 'unit_id', 'booking_status', 'created_at', 'updated_at']);

            $entities = [];

            foreach ($leadUnits as $unit) {
                if ($unit->interested_lead_id) {
                    $interestedLeadIds = explode(',', $unit->interested_lead_id);
                    $interestedLeads = Lead::whereIn('id', $interestedLeadIds)
                        ->get(['id', 'property_id', 'name', 'email', 'contact_no', 'source_id', 'budget', 'status', 'type', 'notes', 'created_at', 'updated_at'])
                        ->map(function ($lead) use ($unit) {
                            return array_merge($lead->toArray(), [
                                'type' => 'interested',
                                'unit_id' => $unit->unit_id,
                                'unitMatches' => []
                            ]);
                        })
                        ->toArray();
                    $entities = array_merge($entities, $interestedLeads);
                }

                if ($unit->allocated_lead_id) {
                    $allocatedLeadIds = explode(',', $unit->allocated_lead_id);
                    $allocatedLeads = Lead::whereIn('id', $allocatedLeadIds)
                        ->get(['id', 'property_id', 'name', 'email', 'contact_no', 'source_id', 'budget', 'status', 'type', 'notes', 'created_at', 'updated_at'])
                        ->map(function ($lead) use ($unit) {
                            return array_merge($lead->toArray(), [
                                'type' => 'lead',
                                'unit_id' => $unit->unit_id,
                                'unitMatches' => []  // Initialize with empty unitMatches
                            ]);
                        })
                        ->toArray();
                    $entities = array_merge($entities, $allocatedLeads);
                }

                if ($unit->allocated_customer_id) {
                    $allocatedCustomerIds = explode(',', $unit->allocated_customer_id);
                    $allocatedCustomers = Customer::whereIn('id', $allocatedCustomerIds)
                        ->get(['id', 'property_id', 'unit_id', 'name', 'email', 'contact_no', 'profile_pic', 'created_at', 'updated_at'])
                        ->map(function ($customer) use ($unit) {
                            return array_merge($customer->toArray(), [
                                'type' => 'customer',
                                'unit_id' => $unit->unit_id,
                                'unitMatches' => []
                            ]);
                        })
                        ->toArray();
                    $entities = array_merge($entities, $allocatedCustomers);
                }
            }

            // Avoid duplicate entries by using unique IDs
            $entities = collect($entities)->unique('id')->values()->all();
            $allLeads = Lead::where('property_id', $propertyID)->get();
            $validEntities = []; // Array to hold entities that match the name criteria

            // foreach ($entitiesArray as $entityamt) {

            //     if ($entityamt['type'] == 'scan-amount' && preg_match('/\d/', $entityamt['mention_text'])) {
            //         echo "here";
            //         $amount = $entityamt['mention_text'];
            //     } 
            // }
            foreach ($entities as &$entity) {
                // Initialize variables to store amount and name
                $amount = null;
                $name = null;
            
                // Check entity type
                if ($entity['type'] === 'interested') {
                    continue; // Skip interested types
                }
            
                // Check for scan-amount and scan-name types
                foreach ($entitiesArray as $scanEntity) {
                   
                    if ($scanEntity['type'] == 'scan-amount' && preg_match('/\d/', $scanEntity['mention_text'])) {
                        $amount = $scanEntity['mention_text']; // Store amount if it contains digits
                    } elseif ($scanEntity['type'] == 'scan-name') {
                        $name = $scanEntity['mention_text'];
                        $nameParts = explode(' ', $name); // Split name into parts
            
                        // For each part of the name, search for matching leads
                        foreach ($nameParts as $part) {
                            // Fetch matching leads for each part
                            $leadResults = Lead::where('property_id', $propertyID)
                                ->where('name', 'LIKE', '%' . $part . '%')
                                ->get();
            
                            $customerResults = Customer::where('property_id', $propertyID)
                                ->where('name', 'LIKE', '%' . $part . '%')
                                ->get();
            
                            // Combine both results
                            $queryResults = $leadResults->merge($customerResults);
            
                            // Check for matches and append unit matches if found
                            foreach ($queryResults as $result) {
                                // Append unit matches only if the name matches
                                if (strcasecmp($result->name, $entity['name']) === 0) {
                                    $unitDetail = UnitDetail::where('id', $entity['unit_id'])->first();
            
                                    if ($unitDetail) {
                                        // Assuming you have a Wing model with a relationship to UnitDetail
                                        $wing = WingDetail::find($unitDetail->wing_id); // Fetch wing by wing_id
            
                                        $entity['unitMatches'][] = [
                                            'unit_id' => $unitDetail->id,
                                            'unit_name' => $unitDetail->name,
                                            'wing_id' => $unitDetail->wing_id,
                                            'wing_name' => $wing ? $wing->name : null // Safely get wing name
                                        ];
            
                                        // Add entity to valid entities if a match is found
                                        $validEntities[] = $entity;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Update $results to contain only valid entities that matched names
            $results = array_values($validEntities);



            $client->close();

            return response()->json([
                'matchedLeads' => $results ?? null,
                'allLeads' => $allLeads ?? null,
                'amount' => $amount ?? null,
                'status' => 'success',

            ]);
        } catch (Exception $e) {

            return response()->json([
                'matchedLeads' => $results ?? null,
                'allLeads' => $allLeads ?? null,
                'amount' => $amount ?? null,
                'status' => 'error',
            ]);
        }
    }
}
