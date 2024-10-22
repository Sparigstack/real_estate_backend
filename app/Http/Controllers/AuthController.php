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
use App\Models\UserProperty;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PlanController;

class AuthController extends Controller
{

    public function generateAndSendOtp($email, $username)
    {
        try {
            $otp = rand(100000, 999999);
            $checkUserOtp = UserOtp::where('email', $email)->where('expire_at', '>', now())->first();
           
            // Remove expired OTPs
            UserOtp::where('email', $email)
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
                    Log::error("Mail sending failed: " . $e->getMessage());
                }

                User::where('email', $email)->update(['name' =>$username]);
            } else {
                $otpExpire = UserOtp::where('email', $email)->where('expire_at', '<', now())->first();
                if ($otpExpire) {
                    $otpExpire->delete();
                }
                $userOtp = new UserOtp();
                $userOtp->otp = $otp;
                $userOtp->email = $email;
                $userOtp->verified = false;
                $userOtp->username = $username;
                $userOtp->expire_at = now()->addMinutes(3);
                $userOtp->save();
                User::where('email', $email)->update(['name' =>$username]);
                try {
                    // Mail::to($email)->send(new GetOtpMail($otp));
                } catch (\Exception $e) {
                    Log::error("Mail sending failed: " . $e->getMessage());
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
            $validator = validator($request->all(), [
                'email' => 'required|string|email|max:255',
                'username' => 'required|string|max:45'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
            $checkUser = User::where('email', $validatedData['email'])->first();

            // if ($checkUser && $checkUser->name !== strtolower($validatedData['username'])) {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'Username and email do not match',
            //     ], 400);
            // }


            $response = $this->generateAndSendOtp($validatedData['email'], $validatedData['username']);
            if ($response == 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'otp sent successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'something went wrong',
                ], 400);
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
        try
        {
        $otp = $request->input('otp');
        $email = $request->input('email');
        $flag=0;

            $checkUserDetails = UserOtp::where('email', $email)->where('otp', $otp)->first();
            if ($checkUserDetails) {
                if ($checkUserDetails->expire_at > now()) {
                    $checkUserDetails->update(['verified' => 1]);
                    $userExist = User::where('email', $email)->first();
                    if ($userExist) {
                        if ($userExist->tokens()) {
                            $userExist->tokens()->delete();
                        }
                        $userExist->update(['name' =>$checkUserDetails->username]);
                        $token = $userExist->createToken('access_token')->accessToken;
                        $userId = $userExist->id;
                    } else {

                        $newUser = new User();
                        $newUser->email = $email;
                        $newUser->name = $checkUserDetails->username;
                        $newUser->save();
                        $userId = $newUser->id;

                        $requestData = [
                            'userId' => $userId,
                            'planId' => 1
                        ];
                
                        // Create a new Request instance and pass the $requestData array
                        $purchasePlanRequest = new Request($requestData);
                
                        // Call the PlanController's purchasePlan function
                        $planController = new PlanController();
                        $response = $planController->purchasePlan($purchasePlanRequest);
                        $token = $newUser->createToken('access_token')->accessToken;
                    }
                    $checkUserDetails->delete();

                    
                    //check if this user have any property if commercial or residential then send flag =1
                    $userPropertyCount=UserProperty::where('user_id',$userId)->count();
                    if($userPropertyCount>0){
                        $flag=1;
                    }

                    return response()->json([
                        'status' => 'success',
                        'message' => null,
                        'token' => $token,
                        'userId' => $userId,
                        'userProperty'=> $flag,
                    ], 200);
                } else {
                    $checkUserDetails->delete();
                    return response()->json([
                        'status' => 'error',
                        'message' => null,
                        'token' => null,
                        'userId' => null,
                        'userProperty'=> $flag,
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Otp. Please try again.',
                    'token' => null,
                    'userId' => null,
                    'userProperty'=> $flag,
                ], 400);
            }
        } catch (\Exception $e) {
            $errorFrom = 'CheckUserOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ],400);
        }
    }

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
