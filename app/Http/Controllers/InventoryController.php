<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryDetail;
use App\Models\InventoryLog;

class InventoryController extends Controller
{

    public function allInventories($skey, $sort, $sortbykey, $offset, $limit)
    {
        $inventoryData = InventoryDetail::with('InventoryLogDetails.Vendor');
        if ($skey != 'null') 
        {
            $inventoryData->where(function ($query) use ($skey) {
                $query->where('name', 'like', "%{$skey}%")
                    ->orWhere('price_per_quantity', 'like', "%{$skey}%");
            });
        }

        if ($sortbykey && in_array($sortbykey, ['name', 'price_per_quantity', 'id'])) 
        {
            $inventoryData->orderBy($sortbykey, $sort);
        }
        $allVendors = $inventoryData->paginate($limit, ['*'], 'page', $offset);
        return $allVendors;
    }
    public function addOrEditInventories(Request $request)
    {
        try {
            $addUpdateFlag = $request->input('addUpdateFlag');
            $name = $request->input('name');
            $currentStock = $request->input('stock');
            $minStock = $request->input('minStock');
            $unitPrice = $request->input('unitPrice');
            $propertyId = $request->input('propertyId');
            $inventoryId = $request->input('inventoryId');
            $vendorId = $request->input('vendorId');

            if ($addUpdateFlag == 0) {
                $newInventory = new InventoryDetail();
                $newInventory->name = $name;
                $newInventory->property_id = $propertyId;
                $newInventory->price_per_quantity = $unitPrice;
                $newInventory->current_quantity = $currentStock;
                $newInventory->reminder_quantity = $minStock;
                $newInventory->save();

                $newInventoryDetails = new InventoryLog();
                $newInventoryDetails->inventory_id = $newInventory->id;
                $newInventoryDetails->vendor_id = $vendorId;
                $newInventoryDetails->quantity = $currentStock;
                $newInventoryDetails->price_per_quantity = $unitPrice;
                $newInventoryDetails->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Item added successfully!',
                ], 200);

            } else {
                $inventoryExists = InventoryDetail::where('id', $inventoryId)->first();
                if ($inventoryExists) {
                    $inventoryExists->update([
                        'name' => $name,
                        'current_quantity' => $currentStock,
                        'reminder_quantity' => $minStock,
                        'price_per_quantity' => $unitPrice,
                    ]);

                    InventoryLog::where('inventory_id', $inventoryExists->id)->update(['vendor_id' => $vendorId, 'quantity' => $currentStock, 'price_per_quantity' => $unitPrice]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Item updated successfully!',
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Item with this id not found!',
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something Went Wrong',
            ], 400);
        }
 
 
    }

    public function getInventoryData($id)
    {
        $getInventoryDetail = InventoryDetail::with('InventoryLogDetails.Vendor')->where('id', $id)->first();
        if ($getInventoryDetail) {
            return $getInventoryDetail;
        } else {
            return null;
        }
    }
}