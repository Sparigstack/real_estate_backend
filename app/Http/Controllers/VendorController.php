<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Helper;
use Illuminate\Support\Facades\Storage;
use App\Models\VendorDetail;

class VendorController extends Controller
{

    public function allVendors($skey, $sort, $sortbykey, $offset, $limit)
    {
        $getVendors = VendorDetail::query();
        if ($skey != 'null') {
            $getVendors->where(function ($query) use ($skey) {
                $query->where('name', 'like', "%{$skey}%")
                    ->orWhere('email', 'like', "%{$skey}%")
                    ->orWhere('company_name', 'like', "%{$skey}%");
            });
        }
        if ($sortbykey && in_array($sortbykey, ['name', 'email', 'company_name', 'id'])) {
            $getVendors->orderBy($sortbykey, $sort);
        }
        $allVendors = $getVendors->paginate($limit, ['*'], 'page', $offset);
        return $allVendors;
    }


    public function addOrEditVendors(Request $request)
    {
        try {
            $name = $request->input('name');
            $companyName = $request->input('companyName');
            $email = $request->input('email');
            $contactNum = $request->input('contactNum');
            $addUpdateFlag = $request->input('addUpdateFlag');
            $vendorId = $request->input('vendorId');

            if ($addUpdateFlag == 0) {
                $checkEmailExists = VendorDetail::where('email', $email)->first();
                if ($checkEmailExists) {

                    return response()->json([
                        'status' => 'error',
                        'message' => 'This email is already exists with some vendor!',
                    ], 200);
                }
                $newVendor = new VendorDetail();
                $newVendor->name = $name;
                $newVendor->contact = $contactNum;
                $newVendor->email = $email;
                $newVendor->company_name = $companyName;
                $newVendor->save();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Vendor details added successfully!',
                ], 200);
            } else {
                $vendorExists = VendorDetail::where('id', $vendorId)->first();
                if ($vendorExists) {
                    $checkEmailExists = VendorDetail::where('email', $email)->where('id', '!=', $vendorExists->id)->first();
                    if ($checkEmailExists) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'This email is already exists with other vendor!',
                        ], 200);
                    }
                    $vendorExists->update([
                        'name' => $name,
                        'contact_num' => $contactNum,
                        'email' => $email,
                        'company_name' => $companyName,
                    ]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Vendor details updated successfully',
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vendor with this id do not exist!',
                    ], 200);

                }
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something Went Wrong!',
            ], 400);

        }

    }

    public function getVendorData($vid)
    {
        $getVendorDetail = VendorDetail::where('id', $vid)->first();
        if ($getVendorDetail) {
            return $getVendorDetail;
        } else {
            return null;
        }
    }

     public function fetchAllVendorName(){
        $getVendorDetail = VendorDetail::select('name','id')->get();
        if ($getVendorDetail) {
            return $getVendorDetail;
        } else {
            return null;
        }
     }
}