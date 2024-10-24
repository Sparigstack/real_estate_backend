<?php

namespace App\Http\Controllers;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Illuminate\Http\Request;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\ProcessResponse;
use Google\Cloud\DocumentAI\V1\Document;
use PDF;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\InputConfig;
use GuzzleHttp\Psr7\MimeType;
use Google\Cloud\DocumentAI\V1\DocumentUnderstandingServiceClient;

require base_path('vendor/autoload.php');

class imgDetectionController extends Controller
{
   
public function processDocument(Request $request)
{
    $request->validate(['image' => 'required|image']);
    $imageContent = file_get_contents($request->file('image')->getRealPath());
    $client = new DocumentProcessorServiceClient([
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ]);
    $rawDocument = (new RawDocument())
        ->setContent($imageContent)
        ->setMimeType($request->file('image')->getMimeType());

    $processRequest = (new ProcessRequest())
        ->setName("projects/cloud-vision-438307/locations/us/processors/ad688e1369a6b169")
        ->setRawDocument($rawDocument);
        
    $response = $client->processDocument($processRequest);
    $tables = [];

    foreach ($response->getDocument()->getPages() as $page) {
        foreach ($page->getTables() as $table) {
            $tableData = [];
            $columnNames = [];
            $headerRow = $table->getHeaderRows() ? $table->getHeaderRows()[0] : null;
            if ($headerRow) {
                foreach ($headerRow->getCells() as $cell) {
                    $layout = $cell->getLayout();
                    $textAnchor = $layout->getTextAnchor();
                    $textSegments = $textAnchor->getTextSegments();
                    $headerText = '';

                    foreach ($textSegments as $segment) {
                        $startIndex = $segment->getStartIndex();
                        $endIndex = $segment->getEndIndex();
                        $fullText = $response->getDocument()->getText();
                        $headerText .= substr($fullText, $startIndex, $endIndex - $startIndex);
                    }
                    $columnNames[] = $this->cleanText(trim($headerText));
                }
            }
            foreach ($table->getBodyRows() as $row) {
                $rowData = [];
                foreach ($row->getCells() as $cell) {
                    $layout = $cell->getLayout();
                    $textAnchor = $layout->getTextAnchor();
                    $textSegments = $textAnchor->getTextSegments();
                    $text = '';

                    foreach ($textSegments as $segment) {
                        $startIndex = $segment->getStartIndex();
                        $endIndex = $segment->getEndIndex();
                        $fullText = $response->getDocument()->getText();
                        $text .= substr($fullText, $startIndex, $endIndex - $startIndex);
                    }

                    $rowData[] = $this->cleanText(trim($text));
                }
                $tableData[] = $rowData;
            }

            $tables[] = [
                'column_names' => $columnNames,
                'data' => $tableData,
            ];
        }
    }

    return response()->json([
        'status' => 'success',
        'data' => $tables,
    ]);
}

function cleanText($text) {
    $text = preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', '', $text);
    return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
}
     
   


}

