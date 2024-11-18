<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserOtp;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use App\Helper;
use Twilio\Rest\Client;
use App\Mail\GetOtpMail;
use App\Models\CompanyDetail;
use App\Models\UserProperty;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{

    public function generateAndSendOtp($mobile_number,$flag)
    {
        try {
            $otp = rand(100000, 999999);
            $checkUserOtp = UserOtp::where('contact_no', $mobile_number)->where('expire_at', '>', now())->first();
           
            // Remove expired OTPs
            UserOtp::where('contact_no', $mobile_number)
            ->where(function ($query) {
                $query->where('expire_at', '<', now())
                      ->orWhere('verified', '1')
                      ->orWhereNotNull('deleted_at');
            })
            ->forceDelete();
            if ($checkUserOtp) {
                try {
                    // Mail::to($email)->send(new GetOtpMail($checkUserOtp->otp));
                } catch (\Exception $e) {
                    Log::error("Otp message sending failed: " . $e->getMessage());
                }

                // User::where('contact_no', $contact_no)->update(['name' =>$username]);
            } else {
                $otpExpire = UserOtp::where('contact_no', $mobile_number)->where('expire_at', '<', now())->first();
                $fetchotpuser=UserOtp::where('contact_no', $mobile_number)->first();
                if ($otpExpire) {
                    $otpExpire->delete();
                }
                $userOtp = new UserOtp();
                $userOtp->otp = $otp;
                $userOtp->contact_no = $mobile_number;
                $userOtp->verified = false;
                if($flag==1){ //first setp when phone number adds then add minutes otherwise in resend time flag==2 dont add minutes
                    $userOtp->expire_at = now()->addMinutes(3);
                }else{
                  if($fetchotpuser=="" && $flag==2){
                    $userOtp->expire_at = now()->addMinutes(3);
                  }
                   
                }
                
                $userOtp->save();

               
                // User::where('email', $email)->update(['name' =>$username]);
                try {
                    // Mail::to($email)->send(new GetOtpMail($otp));
                } catch (\Exception $e) {
                    Log::error("Otp message sending failed: " . $e->getMessage());
                }
            }

            return 'success';
        } catch (\Exception $e) {
            $errorFrom = 'generateAndSendOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }


    public function registerUser(Request $request)
    {
        try {
            $flag=0;//0 means first tym, 1 means exists
          
            $contact_no=$request->mobile_number;
            // $checkUser = User::where('contact_no', $validatedData['contact_no'])->first();
            $checkUserDetails = UserOtp::withTrashed()->where('contact_no', $contact_no)->first();
            $ifUser=User::where('contact_no', $contact_no)->first();
          
            if($checkUserDetails==""){
                $flag=0;
            }else if($checkUserDetails){
                $verifiedStatus = $checkUserDetails->verified;
                if( $verifiedStatus==1 || $ifUser){
                    $flag=1;
                }else{
                    $flag=0;
                }
            }
            // return $flag;
           
            $response = $this->generateAndSendOtp($contact_no,$request->flag);


            if ($response == 'success') {
                return response()->json([
                    'status' => 'success',
                    'userExists'=> $flag,
                    'message' => 'otp sent successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'userExists'=> $flag,
                    'message' => 'something went wrong',
                ], 200);
            }

        } catch (\Exception $e) {
            $errorFrom = 'RegisterUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }
    public function checkUserOtp(Request $request)
    {
        try {
            $otp = $request->input('otp');
            $contact_no = $request->input('mobile_number');
            $userexitsflag = $request->input('flag');
            
            // Default value for user property flag
            $flag = 0;
    
            // Check OTP and expiration
            $checkUserDetails = UserOtp::where('contact_no', $contact_no)->where('otp', $otp)->first();

            if ($checkUserDetails) {           
                if ($checkUserDetails->expire_at > now()) {
                    $checkUserDetails->update(['verified' => 1]);
    
                    // Handle existing user scenario
                    if ($userexitsflag == 1) {
                        // User exists, fetch user details
                        $userExist = User::where('contact_no', $contact_no)->first();
    
                        if ($userExist) {
                            // Clear any existing tokens
                            if ($userExist->tokens()) {
                                $userExist->tokens()->delete();
                            }
                            // Generate new token for existing user
                            $token = $userExist->createToken('access_token')->accessToken;
                            $userId = $userExist->id;
    
                            // Check if the user has any properties and set the flag
                            $userPropertyCount = UserProperty::where('user_id', $userId)->count();
                            if ($userPropertyCount > 0) {
                                $flag = 1;
                            }
                        }
                    } else {
                        // Handle new user scenario
                        $company_name = $request->input('company_name');
                        $user_name = $request->input('user_name');
    
                        // Create a new user and token
                        $newUser = new User();
                        $newUser->name = $user_name;
                        $newUser->contact_no = $contact_no;
                        $newUser->save();
    
                        $userId = $newUser->id;
                        $token = $newUser->createToken('access_token')->accessToken;
    
                        // Create new company details for the user
                        $newCompany = new CompanyDetail();
                        $newCompany->user_id = $userId;
                        $newCompany->name = $company_name;
                        $newCompany->save();
                    }
    
                    // Delete the OTP record after successful verification
                    $checkUserDetails->delete();
    
                    return response()->json([
                        'status' => 'success',
                        'message' => null,
                        'token' => $token,
                        'userId' => $userId,
                        'userProperty' => $flag,
                    ], 200);
    
                } else {
                    // OTP expired, delete it and return error
                    $checkUserDetails->delete();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'OTP expired. Please try again.',
                        'token' => null,
                        'userId' => null,
                        'userProperty' => $flag,
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP. Please try again.',
                    'token' => null,
                    'userId' => null,
                    'userProperty' => $flag,
                ], 200);
            }
        } catch (\Exception $e) {
            $errorFrom = 'CheckUserOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }
    

    // public function checkUserOtp(Request $request)
    // {
    //     try
    //     {
    //     $otp = $request->input('otp');
    //     $email = $request->input('email');
    //     $flag=0;

    //         $checkUserDetails = UserOtp::where('email', $email)->where('otp', $otp)->first();
    //         if ($checkUserDetails) {
    //             if ($checkUserDetails->expire_at > now()) {
    //                 $checkUserDetails->update(['verified' => 1]);
    //                 $userExist = User::where('email', $email)->first();
    //                 if ($userExist) {
    //                     if ($userExist->tokens()) {
    //                         $userExist->tokens()->delete();
    //                     }
    //                     $userExist->update(['name' =>$checkUserDetails->username]);
    //                     $token = $userExist->createToken('access_token')->accessToken;
    //                     $userId = $userExist->id;
    //                 } else {
    //                     $newUser = new User();
    //                     $newUser->email = $email;
    //                     $newUser->name = $checkUserDetails->username;
    //                     $newUser->save();
    //                     $userId = $newUser->id;
    //                     $token = $newUser->createToken('access_token')->accessToken;
    //                 }
    //                 $checkUserDetails->delete();

                    
    //                 //check if this user have any property if commercial or residential then send flag =1
    //                 $userPropertyCount=UserProperty::where('user_id',$userId)->count();
    //                 if($userPropertyCount>0){
    //                     $flag=1;
    //                 }

    //                 return response()->json([
    //                     'status' => 'success',
    //                     'message' => null,
    //                     'token' => $token,
    //                     'userId' => $userId,
    //                     'userProperty'=> $flag,
    //                 ], 200);
    //             } else {
    //                 $checkUserDetails->delete();
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => null,
    //                     'token' => null,
    //                     'userId' => null,
    //                     'userProperty'=> $flag,
    //                 ], 400);
    //             }
    //         } else {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Invalid Otp. Please try again.',
    //                 'token' => null,
    //                 'userId' => null,
    //                 'userProperty'=> $flag,
    //             ], 400);
    //         }
    //     } catch (\Exception $e) {
    //         $errorFrom = 'CheckUserOtp';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'something went wrong',
    //         ],400);
    //     }
    // }

    

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->token()->delete();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Logout Successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'something went wrong',
                ], 401);
            }
        } catch (\Exception $e) {
            $errorFrom = 'logout';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }
}
