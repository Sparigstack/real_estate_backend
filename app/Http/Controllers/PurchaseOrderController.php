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
use App\Models\PurchaseOrderDetail;
class PurchaseOrderController extends Controller
{
    public function generatePo(Request $request)
    {
        try {
        $inventoryId = $request->input('inventoryId');
        $vendorId = $request->input('vendorId');
        $propertyId = $request->input('propertyId');
        $quantity = $request->input('quantity');
        $price = $request->input('price');
       
        $newPo = new PurchaseOrderDetail();
        $newPo->property_id = $propertyId;
        $newPo->inventory_id = $inventoryId;
        $newPo->vendor_id = $vendorId;
        $newPo->quantity = $quantity;
        $newPo->price = $price;
        $newPo->status = 1;
        $newPo->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order generate successfully!',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Something Went Wrong!',
        ], 400);

    }
    }
    
    public function getPoDetails($pid, $skey, $sort, $sortbykey, $offset, $limit)
    {
        $PoData = PurchaseOrderDetail::with('VendorDetails','PropertyDetails','InventoryDetails')->where('property_id',$pid);
        if ($skey != 'null') 
        {
            $PoData->where(function ($query) use ($skey) {
                $query->where('price', 'like', '%' . $skey . '%')
                    ->orWhereHas('VendorDetails', function ($subQuery) use ($skey) {
                        $subQuery->where('company_name', 'like', '%' . $skey . '%')
                         ;
                    });
            });
        }
        
        $getDetails = $PoData->paginate($limit, ['*'], 'page', $offset);
        return $getDetails;
    }
}