<?php
namespace App;
use App\Mail\GetOtpMail;
use App\Models\ErrorLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;


class Helper{

   public static function ErrorLog($errorfrom,$errormsg,$priority){
        $error = new ErrorLog();
        $error->error_from = $errorfrom;
        $error->error_msg = $errormsg;
        $error->error_priority = $priority;
        $error->error_created_date = now()->format('Y-m-d');
        $error->save();
    }
     public static function sendOtpcopy($contactno, $otp)
        {
            //wp
            // $response = Http::get(getenv('GUPSHUP_API_URL'), [
            //     'apikey' => getenv('GUPSHUP_API_KEY'),
            //     'message' => "Your OTP is: $otp",
            //     'to' => $contactno,
            // ]);

            //sms
            // $response = Http::withHeaders([
            //     'apikey' =>  getenv('GUPSHUP_API_KEY'),
            // ])->get(getenv('GUPSHUP_API_URL'), [
                // 'channel' => 'sms',
                // 'source' => 'MySenderID', // Replace this with your actual Sender ID
                // 'destination' => $contactno,
                // 'message' => "Your OTP is: $otp",
            // ]);


            // return $response->json();
        }
}
