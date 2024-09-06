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



class AuthController extends Controller
{
    public function processUser(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'email' => 'required|string|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
            $otpExist = UserOtp::where('email',$validatedData['email'])->where('expire_at','>=',now())->first();
            if($otpExist)
            {
            $otpExist->delete();
            $this->generateAndSendOtp($validatedData['email']);
            }
            else{
                $this->generateAndSendOtp($validatedData['email']);
            }
            return response()->json([
                'status' => 'status',
            ],200);
        } catch (\Exception $e) {
            $errorFrom = 'processUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }
    public function verifyUser(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'email' => 'required|string|email',
                'verification_code' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
                $userOtpExist = UserOtp::where('email', $validatedData['email'])->first();
                if(!$userOtpExist){
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'user not found',
                        'user_status' => null
                    ],400);
                }
                else{
                    $correctOtp = UserOtp::where('email', $validatedData['email'])->where('otp', $validatedData['verification_code'])->first();
                if (!$correctOtp) {
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'invalid otp or user not found',
                        'user_status' => null
                    ],400);
                } else {
                    if ($correctOtp->expire_at >= now()) {
                        $correctOtp->verified = true;
                        $correctOtp->save();
                        $correctOtp->delete();
                        $userExist = User::where('email', $validatedData['email'])->first();
                        if($userExist)
                        {
                            $token = $userExist->createToken('access_token')->accessToken;
                        }
                        else{
                            $newUser = new User();
                            $newUser->email = $validatedData['email'];
                            $newUser->save();
                            $token = $newUser->createToken('access_token')->accessToken;
                        }
                        return response()->json([
                            'status' => 'success',
                            'msg' => $token,
                            'user_status' => $userExist ? 1 : 0
                        ]);
                    } else {
                        $correctOtp->delete();
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'otp has expired try again with new code',
                            'user_status' => null
                        ],400);
                    }
                }
                }
        } catch (\Exception $e) {
            $errorFrom = 'verifyUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }

    public function generateAndSendOtp($email)
    {
        try{
            $otp = rand(100000, 999999);
            $userOtp = new UserOtp();
            $userOtp->otp = $otp;
            $userOtp->email = $email;
            $userOtp->verified = false;
            $userOtp->expire_at = now()->addMinutes(5);
            $userOtp->save();
            Mail::to($email)->send(new GetOtpMail($otp));
        }
        catch(\Exception $e)
        {
            $errorFrom = 'userInfo';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }
    public function userInfo()
    {
        try {
            $user = auth()->user();
            return $user->properties;
        } catch (\Exception $e) {
            $errorFrom = 'userInfo';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }
    public function test()
    {
        return 'test';
    }
}
