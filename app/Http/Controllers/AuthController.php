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

    public function generateAndSendOtp($email)
    {
        try{
            $otp = rand(100000, 999999);
            $checkUserOtp = UserOtp::where('email',$email)->where('expire_at','>',now())->first();
            if($checkUserOtp){
                try {
                    Mail::to($email)->send(new GetOtpMail($checkUserOtp->otp));
                } catch (\Exception $e) {
                    Log::error("Mail sending failed: ".$e->getMessage());
                }
            }else{
                $otpExpire = UserOtp::where('email',$email)->where('expire_at','<',now())->first();
                if($otpExpire)
                {
                    $otpExpire->delete();
                }
                $userOtp = new UserOtp();
                $userOtp->otp =$otp;
                $userOtp->email = $email;
                $userOtp->verified = false;
                $userOtp->expire_at = now()->addMinutes(2);
                $userOtp->save();
                try {
                    Mail::to($email)->send(new GetOtpMail($otp));
                } catch (\Exception $e) {
                    Log::error("Mail sending failed: ".$e->getMessage());
                }
            }

            return 'success';
        }
        catch(\Exception $e)
        {
            $errorFrom = 'generateAndSendOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }


     public function RegisterUser(Request $request)
     {
        try {
            $validator = validator($request->all(), [
                'email' => 'required|string|email|max:255',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
            $response = $this->generateAndSendOtp($validatedData['email']);
            if($response == 'success'){
                return response()->json([
                    'status' => 'success',
                    'msg' => 'otp sent successfully',
                ],200);
            }else{
                return response()->json([
                    'status' => 'error',
                    'msg' => 'something went wrong',
                ],400);
            }

        } catch (\Exception $e) {
            $errorFrom = 'RegisterUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ],400);
        }
     }


     public function CheckUserOtp(Request $request)
     {
        try
        {
        $otp = $request->input('otp');
        $email = $request->input('email');

        $userOtpExist = UserOtp::where('email',$email)->first();
                if(!$userOtpExist){
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'user not found'
                    ],400);
                }
                else{
                    $checkUserDetails = UserOtp::where('email', $userOtpExist->email)->where('otp', $otp)->first();
                    if($checkUserDetails)
                    {
                        if($checkUserDetails->expire_at > now())
                        {
                            UserOtp::where('email',$email)->where('otp',$otp)->update(['verified' => 1]);
                            $userExist = User::where('email',$email)->first();
                                        if($userExist)
                                        {
                                            $userExist->tokens()->delete();
                                            $token = $userExist->createToken('access_token')->accessToken;
                                        }
                                        else{
                                            $newUser = new User();
                                            $newUser->email = $email;
                                            $newUser->save();
                                            $token = $newUser->createToken('access_token')->accessToken;
                                        }
                            $checkUserDetails->delete();
                            return response()->json([
                                'status' => 'success',
                                'token' => $token
                            ],200);
                        }
                        else
                        {
                            $checkUserDetails->delete();
                            // return response()->json([
                            //     'status' => 'error',
                            //     'msg' => 'OTP expired, please request a new code.'
                            // ],400);
                        }
                    }
                    else
                    {
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'Invalid Otp. Please try again.'
                        ],400);
                    }
                }

        }
        catch(\Exception $e)
        {
            $errorFrom = 'CheckUserOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'msg' => 'something went wrong',
            ],400);
        }
     }
}
