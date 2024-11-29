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
use Illuminate\Support\Facades\Http;


class AuthController extends Controller
{

    public function generateAndSendOtp($mobile_number, $flag)
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
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $otp);
                    // Mail::to($email)->send(new GetOtpMail($checkUserOtp->otp));
                } catch (\Exception $e) {
                    Log::error("Otp message sending failed: " . $e->getMessage());
                }

                // User::where('contact_no', $contact_no)->update(['name' =>$username]);
            } else {
                $otpExpire = UserOtp::where('contact_no', $mobile_number)->where('expire_at', '<', now())->first();
                $fetchotpuser = UserOtp::where('contact_no', $mobile_number)->first();
                if ($otpExpire) {
                    $otpExpire->delete();
                }
                $userOtp = new UserOtp();
                $userOtp->otp = $otp;
                $userOtp->contact_no = $mobile_number;
                $userOtp->verified = false;
                if ($flag == 1) { //first setp when phone number adds then add minutes otherwise in resend time flag==2 dont add minutes
                    $userOtp->expire_at = now()->addMinutes(15);
                } else {
                    if ($fetchotpuser == "" && $flag == 2) {
                        $userOtp->expire_at = now()->addMinutes(15);
                    }
                }

                $userOtp->save();


                // User::where('email', $email)->update(['name' =>$username]);
                try {
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $otp);
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
            $flag = 0; //0 means first tym, 1 means exists

            $contact_no = $request->mobile_number;
            // $checkUser = User::where('contact_no', $validatedData['contact_no'])->first();
            $checkUserDetails = UserOtp::withTrashed()->where('contact_no', $contact_no)->first();
            $ifUser = User::where('contact_no', $contact_no)->first();

            if ($checkUserDetails == "") {
                $flag = 0;
            } else if ($checkUserDetails) {
                $verifiedStatus = $checkUserDetails->verified;
                if ($verifiedStatus == 1 || $ifUser) {
                    $flag = 1;
                } else {
                    $flag = 0;
                }
            }

            // $isValid = $this->isValidWhatsappNumber($contact_no);
            // if (!$isValid['isWhatsApp']) {
            //     return response()->json([
            //         'status' => 'error', 
            //         'userExists'=>null,
            //         'message' => 'Invalid WhatsApp number'
            //     ]);
            // }
            $response = $this->generateAndSendOtp($contact_no, $request->flag);


            if ($response == 'success') {
                return response()->json([
                    'status' => 'success',
                    'userExists' => $flag,
                    'message' => 'otp sent successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'userExists' => $flag,
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


    public function sendOtpToWhatsapp($contact_no, $otp)
    {


        $apiUrl = 'https://api.gupshup.io/wa/api/v1/template/msg';
        $apiKey = 'x7pcbvdpvzxjfdnc1qelyqja4slvu9va'; // Replace with your actual API key
        $sourceNumber = '916359506160'; // Your WhatsApp source number
        $appName = 'Superbuildup'; // Your application name
        $templateId = 'ca17eedb-8261-4fe0-8bd9-6f82c2bccb9c'; // Replace with your template ID
        $destination = $contact_no; // Recipient's WhatsApp number
        $params = [$otp]; 

        // API request payload
        $payload = [
            'channel' => 'whatsapp',
            'source' => $sourceNumber,
            'destination' => $destination,
            'src.name' => $appName,
            'template' => json_encode([
                'id' => $templateId,
                'params' => $params, // Dynamic parameters for the template
            ]),
        ];
        // $apiUrl = env('GUPSHUP_API_URL'); // Example: 'https://api.gupshup.io/sm/api/v1/msg'
        // $apiKey = env('GUPSHUP_API_KEY');
        // $officialNumber = env('GUPSHUP_NUMBER');
    
        // $payload = [
        //     'channel' => 'whatsapp',
        //     'source' => $officialNumber, // Your registered number in Gupshup
        //     'destination' => $contact_no,
        //     'template' => 'otp_verification',
        //     'template_id' => 'ca17eedb-8261-4fe0-8bd9-6f82c2bccb9c',
        //     'params' => [$otp], // Maps to the dynamic placeholder in your template
        // ];
    
        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, $payload);
    
            if ($response->successful()) {
                return $response->json(); // Response from Gupshup
            } else {
                Log::error("Gupshup OTP send failed", ['response' => $response->body()]);
                return ['error' => 'Failed to send OTP'];
            }
        } catch (\Exception $e) {
            Log::error("Exception in sending OTP via Gupshup: " . $e->getMessage());
            return ['error' => 'Exception occurred while sending OTP'];
        }
    }


    public function isValidWhatsappNumber($contact_no)
    {
        $apiUrl = "https://api.gupshup.io/wa/phone/verify"; // Example endpoint
        $apiKey = config('services.gupshup.api_key');

        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
        ])->get($apiUrl, ['phone' => $contact_no]);

        return $response->json();
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



    public function sendBulkMessages(Request $request)
    {
        $apiUrl = 'https://api.gupshup.io/wa/api/v1/msg'; // Gupshup API endpoint
        $apiKey = 'x7pcbvdpvzxjfdnc1qelyqja4slvu9va'; // Replace with your actual API key
        $sourceNumber = '916359506160'; // Your Gupshup source number
        $destinationNumber = '+918320064478'; // The number to send the message to 918780496028
        $messageText = "hi"; // The message content

        // Prepare the message payload
        $payload = [
            'source' => $sourceNumber,
            'destination' => $destinationNumber,
            'src.name' => 'Superbuildup', // You can change this as needed
            'message' => json_encode([
                'type' => 'text',
                'text' => $messageText,
                'previewUrl' => true, // Optional: Show preview for links
            ]),
        ];

        try {
            // Send the POST request to Gupshup API
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($apiUrl, $payload);

            // Check the response status
            if ($response->successful()) {
                // Log::info("Message sent successfully to {$destinationNumber}: " . $response->json());
                return response()->json([
                    'status' => 'success',
                    'message' => 'Message sent successfully!',
                    'data' => $response->json(),
                ]);
            } else {
                Log::error("Failed to send message to {$destinationNumber}: " . $response->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send message.',
                    'error' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while sending the message.',
                'error' => $e->getMessage() . $e->getLine(),
            ]);
        }
    }


    function sendGupshupTemplateMessage()
    {
        $otp="123456";
        $apiUrl = 'https://api.gupshup.io/wa/api/v1/template/msg';
        $apiKey = 'x7pcbvdpvzxjfdnc1qelyqja4slvu9va'; // Replace with your actual API key
        $sourceNumber = '916359506160'; // Your WhatsApp source number
        $appName = 'Superbuildup'; // Your application name
        $templateId = 'ca17eedb-8261-4fe0-8bd9-6f82c2bccb9c'; // Replace with your template ID
        $destination = '+918320064478'; // Recipient's WhatsApp number
        $params = [$otp]; 

        // API request payload
        $payload = [
            'channel' => 'whatsapp',
            'source' => $sourceNumber,
            'destination' => $destination,
            'src.name' => $appName,
            'template' => json_encode([
                'id' => $templateId,
                'params' => $params, // Dynamic parameters for the template
            ]),
        ];

        // Send POST request to Gupshup API
        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($apiUrl, $payload);

        // Check response and return
        if ($response->successful()) {
            return [
                'success' => true,
                'response' => $response->json(),
            ];
        } else {
            return [
                'success' => false,
                'error' => $response->body(),
            ];
        }
    }
}
