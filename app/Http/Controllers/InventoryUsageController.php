<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryDetail;
use App\Models\InventoryLog;
use App\Models\InventoryUsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowQuantityReminderMail;
class InventoryUsageController extends Controller
{

    public function AddInventoryUsage(Request $request)
    {
        try {
            $inventoryId = $request->input('inventoryId');
            $utilizedQuantity = $request->input('utilizedQuantity');
            $date = $request->input('date');
            $notes = $request->input('notes');

            $newUsageLog = new InventoryUsageLog();
            $newUsageLog->inventory_id = $inventoryId;
            $newUsageLog->utilized_quantity = $utilizedQuantity;
            $newUsageLog->date = $date;
            $newUsageLog->note = $notes;
            $newUsageLog->save();

            $inventoryData = InventoryDetail::with('PropertyDetails.user')->where('id', $inventoryId)->first();
            $availableQuantity = $inventoryData->current_quantity - $utilizedQuantity;
            InventoryDetail::where('id', $inventoryId)->update(['current_quantity' => $availableQuantity]);
            /// check quantity nd update mail 
            $data = [
                'userName' => $inventoryData->PropertyDetails->user->name,
                'inventoryName' => $inventoryData->name,
                'currentStock' => $availableQuantity,
                'reminderStock' => $inventoryData->reminder_quantity,

            ];

            try {
                Mail::to($inventoryData->PropertyDetails->user->email)->send(new LowQuantityReminderMail($data));
            } catch (\Exception $e) {
                Log::error("Mail sending failed: " . $e->getMessage());
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Usage details added successfully!',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something Went Wrong!',
            ], 400);

        }
    }

    public function GetInventoryUsage($id)
    {
        $getUsageLog = InventoryUsageLog::where('inventory_id', $id)->first();
        if ($getUsageLog) {
            return $getUsageLog;
        } else {
            return null;
        }
    }

}