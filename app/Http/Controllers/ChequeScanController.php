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
use Exception;
use Illuminate\Support\Facades\Log;

class ChequeScanController extends Controller
{

    // public function detectCheque(Request $request)
    // {
    //     try {
    //         $request->validate(['image' => 'required|image']);
    //         $propertyID = $request->input('propertyID');
    //         $imageContent = file_get_contents($request->file('image')->getRealPath());
    //         $client = new DocumentProcessorServiceClient([
    //             'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    //         ]);
    //         $rawDocument = (new RawDocument())
    //             ->setContent($imageContent)
    //             ->setMimeType($request->file('image')->getMimeType());

    //         $processRequest = (new ProcessRequest())
    //             ->setName("projects/cloud-vision-438307/locations/us/processors/1a6eb05828f2b041")
    //             ->setRawDocument($rawDocument);

    //         $response = $client->processDocument($processRequest);
    //         $document = $response->getDocument();
    //         $entities = $document->getEntities();

    //         $userName = null;
    //         $amount = null;
    //         $entitiesArray = [];

    //         foreach ($entities as $entity) {
    //             $entitiesArray[] = [
    //                 'type' => $entity->getType(),
    //                 'mention_text' => $entity->getMentionText(),
    //             ];
    //         }

    //         // $entitiesArray= json_decode('[
    //         //     {
    //         //         "type": "scan-amount",
    //         //         "mention_text": "50,25,000"
    //         //     },
    //         //     {
    //         //         "type": "scan-name",
    //         //         "mention_text": "Prateek Agrawal"
    //         //     },
    //         //     {
    //         //         "type": "scan-name",
    //         //         "mention_text": "DEEPAK CHOUDHARY"
    //         //     }
    //         // ]',true);

    //         $results = [];
    //         $allLeads = Lead::where('property_id', $propertyID)->get();

    //         foreach ($entitiesArray as $entity) {

    //             if ($entity['type'] == 'scan-amount' && preg_match('/\d/', $entity['mention_text'])) {
    //                 $amount = $entity['mention_text'];
    //             } elseif ($entity['type'] == 'scan-name') {
    //                 $name = $entity['mention_text'];
    //                 $nameParts = explode(' ', $name);

    //                 foreach ($nameParts as $part) {
    //                     $queryResults = Lead::where('property_id', $propertyID)->where('name', 'LIKE', '%' . $part . '%')->get();
    //                     $results = array_merge($results, $queryResults->toArray());
    //                 }
    //                 $results = array_unique($results, SORT_REGULAR);
    //             }
    //         }

    //         $client->close();

    //         return response()->json([
    //             'matchedLeads' => $results ?? null,
    //             'allLeads' => $allLeads ?? null,
    //             'amount' => $amount ?? null,
    //             'status' => 'success',

    //         ]);
    //     } catch (Exception $e) {

    //         return response()->json([
    //             'matchedLeads' => $results ?? null,
    //             'allLeads' => $allLeads ?? null,
    //             'amount' => $amount ?? null,
    //             'status' => 'error',
    //         ]);
    //     }
    // }


    public function detectCheque(Request $request)
    {
        // Handle the image input (base64 or file upload)
        $imagePath = $this->handleImageInput($request);

        // Check if the image contains a cheque
        $isCheque = $this->isChequeImage($imagePath);
      return $isCheque;
        return response()->json([
            'is_cheque' => $isCheque,
        ]);
    }

    private function handleImageInput(Request $request)
    {
        if ($request->has('base64_image')) {
            $imageData = $request->input('base64_image');
            $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
            $imagePath = 'uploads/' . uniqid() . '.png';
            Storage::put($imagePath, $image);
        } elseif ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('uploads');
        }

        return $imagePath;
    }

    private function isChequeImage($imagePath)
    {
        // try {
            // Initialize the Vision client
            $imageAnnotator = new ImageAnnotatorClient();
    
            // Load the image
            $imageContents = Storage::get($imagePath);
    
            // Perform text detection (OCR)
            $response = $imageAnnotator->textDetection($imageContents);
            if ($response->getError()) {
                return response()->json([
                    'error' => 'Error occurred while detecting text',
                    'message' => $response->getError()->getMessage()
                ], 500);
            }
    
            // Get text annotations
            $texts = $response->getTextAnnotations();
    
            // If no text is detected, return an empty response
            if (empty($texts)) {
                return response()->json([
                    'message' => 'No text detected in the image',
                    'texts' => []
                ], 200);
            }
    
            // Extract the full text from the first text annotation
            $detectedText = $texts[0]->getDescription();
            $lines = explode("\n", $detectedText);
       
            // Return the detected text
            $payeeName =[];
            $amount = [];
    
            // Identify the price and payee name using keyword matching and regex
            foreach ($lines as $index => $line) {
                // Detect lines that might contain the amount (e.g., a dollar sign or numbers with commas/periods)
                if (preg_match('/\₹\s?[\d,]+(\.\d{2})?/', $line, $matches)) {
                    $amount[] = $matches[0]; // Capture the amount
                }
    
                // Look for the line containing "Please sign above" and capture the preceding text for the name
                if (stripos($line, 'Please sign above') !== false) {
                    $previousIndex = array_search($line, $lines) - 1;
                    if ($previousIndex >= 0) {
                        $payeeName[] = $lines[$previousIndex];
                    }
                }
                if (stripos($line, 'sign above') !== false) {
                    $previousIndex = array_search($line, $lines) - 1;
                    if ($previousIndex >= 0) {
                        $payeeName[] = $lines[$previousIndex];
                    }
                }
    
                // Look for the keyword 'Pay' or 'Pay to' to capture the payee name
                if (preg_match('/^pay\b/i', $line)) {
                    // Check if the next line exists and use it as the payee name
                    if (isset($lines[$index + 1])) {
                        $payeeName[] = $lines[$index + 1];
                    }
                }

                if (preg_match('/\b(?:Rupees|INR)\b\s+(.+? only)$/i', $line, $matches)) {
                    $amountInWords = $matches[1]; // Capture the amount in words
                    $amount[] = $amountInWords;
                }
                
                
            }

    
            // Return the detected text details
            return response()->json([
                'detected_text' => $detectedText,
                'payee_name' => $payeeName,
                'amount' => $amount,
            ], 200);
    
        // } catch (\Exception $e) {
          
        //     Log::error('Vision API error: ' . $e->getMessage());
        //     return response()->json([
        //         'error' => 'An error occurred while processing the image',
        //         'message' => $e->getMessage()
        //     ], 500);
        // }
    }

    
}
