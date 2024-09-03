<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use App\Helper;
use Twilio\Rest\Client;



class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'email' => 'required|unique:users'
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $validatedData = $validator->validated();

            $userExist = User::where('contact_no', $validatedData['contact_number'])->first();
            if (!$userExist) {
                $newUser = new User();
                $newUser->email = $validatedData['email'];
                $newUser->save();
                return response()->json([
                    'user' => $newUser
                ]);
            } else {
                return response()->json([
                    'user' => $userExist
                ]);
            }
        } catch (\Exception $e) {
            $errorFrom = 'register';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::ErrorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }
    public function verifyOtp(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'contact_number' => 'required|regex:/^\+(?:\d{1,3})\s?(?:\d{1,4})?\s?\d{1,14}(?:\s?\d{1,13})?$/',
                'verification_code' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $validatedData = $validator->validated();

            $token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_sid = getenv("TWILIO_SID");
            $twilio_verify_sid = getenv("TWILIO_VERIFY_SID");
            $twilio = new Client($twilio_sid, $token);

            $verification = $twilio->verify->v2->services($twilio_verify_sid)
                ->verificationChecks
                ->create([
                    'code' => $validatedData['verification_code'],
                    'to' => $validatedData['contact_number']
                ]);

            if ($verification->valid) {
                $userExist = User::where('contact_no', $validatedData['contact_number'])->first();
                $token = $userExist->createToken($userExist->name)->accessToken;
                return response()->json([
                    'status' => 'success',
                    'token' => $token
                ]);
            }
            return response()->json([
                'status' => 'error',
                'msg' => 'invalid  verification code'
            ]);

        } catch (\Exception $e) {
            $errorFrom = 'verifyOtp';
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
}
