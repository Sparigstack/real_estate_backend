<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Storage;
use App\Helper;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use App\Models\Lead;
class ChequeScanController extends Controller
{

public function detectCheque(Request $request)
{
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

    // $entitiesArray= json_decode('[
    //     {
    //         "type": "scan-amount",
    //         "mention_text": "50,25,000"
    //     },
    //     {
    //         "type": "scan-name",
    //         "mention_text": "Prateek Agrawal"
    //     },
    //     {
    //         "type": "scan-name",
    //         "mention_text": "DEEPAK CHOUDHARY"
    //     }
    // ]',true);

    $results = []; 
 $allLeads = Lead::where('property_id',$propertyID)->get();

    foreach ($entitiesArray as $entity) {
        
        if ($entity['type'] == 'scan-amount' && preg_match('/\d/', $entity['mention_text'])) {
            $amount = $entity['mention_text'];
        } elseif ($entity['type'] == 'scan-name') {
            $name = $entity['mention_text'];
            $nameParts = explode(' ', $name);
         
            foreach ($nameParts as $part) {
                $queryResults = Lead::where('property_id',$propertyID)->where('name', 'LIKE', '%' . $part . '%')->get();
                $results = array_merge($results, $queryResults->toArray());
            }
            $results = array_unique($results, SORT_REGULAR);
        }
    }
    
    $client->close();
   
        return response()->json([
           'matchedLeads'=>$results ?? null,
           'allLeads'=>$allLeads ?? null,
           'amount'=>$amount ?? null

        ]);
   
}

    

}