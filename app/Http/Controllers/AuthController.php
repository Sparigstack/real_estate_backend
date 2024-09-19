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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{

    public function generateAndSendOtp($email,$username)
    {
        try {
            $otp = rand(100000, 999999);
            $checkUserOtp = UserOtp::where('email', $email)->where('expire_at', '>', now())->first();
            if ($checkUserOtp) {
                try {
                    // Mail::to($email)->send(new GetOtpMail($checkUserOtp->otp));
                } catch (\Exception $e) {
                    Log::error("Mail sending failed: " . $e->getMessage());
                }
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
                'username'=> 'required|string|max:45'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
            $response = $this->generateAndSendOtp($validatedData['email'],$validatedData['username']);
            if ($response == 'success') {
                return response()->json([
                    'status' => 'success',
                    'msg' => 'otp sent successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'something went wrong',
                ], 400);
            }

        } catch (\Exception $e) {
            $errorFrom = 'RegisterUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ], 400);
        }
    }


    public function checkUserOtp(Request $request)
    {
        try
        {
        $otp = $request->input('otp');
        $email = $request->input('email');

            $checkUserDetails = UserOtp::where('email', $email)->where('otp', $otp)->first();
            if ($checkUserDetails) {
                if ($checkUserDetails->expire_at > now()) {
                    $checkUserDetails->update(['verified' => 1]);
                    $userExist = User::where('email', $email)->first();
                    if ($userExist) {
                            if($userExist->tokens())
                            {
                                $userExist->tokens()->delete();
                            }
                            $token = $userExist->createToken('access_token')->accessToken;
                            $userId = $userExist->id;
                    } else {
                        $newUser = new User();
                        $newUser->email = $email;
                        $newUser->name = $checkUserDetails->username;
                        $newUser->save();
                        $userId = $newUser->id;
                        $token = $newUser->createToken('access_token')->accessToken;
                    }
                    $checkUserDetails->delete();
                    return response()->json([
                        'status' => 'success',
                        'msg' => null,
                        'token' => $token,
                        'userId' => $userId
                    ], 200);
                } else {
                    $checkUserDetails->delete();
                    return response()->json([
                        'status' => 'error',
                        'msg' => null,
                        'token' => null,
                        'userId' => null
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Invalid Otp. Please try again.',
                    'token' => null,
                    'userId' => null
                ], 400);
            }
        }
        catch(\Exception $e)
        {
            $errorFrom = 'CheckUserOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ],400);
        }
    }

    public function logout(Request $request)
    {
        try{
            if($request->user())
        {
            $request->user()->token()->delete();
            return response()->json([
                'status'=>'success',
                'message' => 'Logout Successfully',
            ],200);
        }
        else{
            return response()->json([
                'status'=>'error',
                'message' => 'something went wrong',
            ],401);
        }
        }
        catch(\Exception $e)
        {
            $errorFrom = 'logout';
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
