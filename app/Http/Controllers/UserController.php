<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use Illuminate\Http\Request;
use App\Models\User;
use App\Helper;
use Illuminate\Support\Facades\Storage;


class UserController extends Controller
{
    public function userProfile($uid)
    {
    $companyDetails = CompanyDetail::with('user')->where('user_id', $uid)->first();
    if ($companyDetails) {
        return response()->json([
            'msg' => $companyDetails
        ], 200);
    } else {
        return response()->json([
            'msg' => null,
        ], 200);
    }
}

    public function addUpdateUserProfile(Request $request)
    {
        try
        {
            $validator = validator($request->all(), [
                'userName' => 'required|string|max:255',
                'contactNum' => 'required|string|max:15',
                'companyEmail' => 'required|string|email|max:255',
                'companyName'=> 'required|string|max:255',
                'companyContactNum' => 'nullable|string|max:15',
                'companyAddress' => 'nullable|string',
                'companyLogo' => 'nullable|string',
                'userId' => 'required|integer'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
            $checkUser = User::where('id',$validatedData['userId'])->first();

            if(isset($checkUser))
            {
                $checkUser->update(['name' => $validatedData['userName'],'contact_no' => $validatedData['contactNum']]);
                $checkCompanyDetails = CompanyDetail::where('user_id',$checkUser->id)->first();
                if($checkCompanyDetails)
                {
                    $checkCompanyDetails->update(['name' => $validatedData['companyName'],
                    'contact_no' => $validatedData['companyContactNum'],
                    'email' => $validatedData['companyEmail'],
                    'address' => $validatedData['companyAddress'],'logo' => $validatedData['companyLogo']]);
                }
                else{
                    CompanyDetail::create(['user_id' => $validatedData['userId'],'name' => $validatedData['companyName'],
                    'contact_no' => $validatedData['companyContactNum'],
                    'email' => $validatedData['companyEmail'],
                    'address' => $validatedData['companyAddress'],'logo' => $validatedData['companyLogo']]);
                }
                return response()->json([
                    'status'=>'success',
                    'message' => 'Profile update successfully',
                ],200);
            }
            else{
                return response()->json([
                    'status'=>'error',
                    'message' => 'user not found',
                ],400);
            }
        }
        catch (\Exception $e) {
            $errorFrom = 'addUpdateUserProfile';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
            'status'=>'error',
            'message' => 'something went wrong',
        ],400);
        }

    }
}
