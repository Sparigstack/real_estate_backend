<?php
namespace App;
use App\Models\ErrorLog;
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


     public static function generateOtp($user) {
             $otp = rand(100000, 999999);
             $user->otp = $otp;
             $user->save();
           Helper::sendOtp($user->contact_no, $otp);

        return $otp;
     }

     public static function sendOtp($contactno, $otp)
        {
            $response = Http::get(getenv('GUPSHUP_API_URL'), [
                'apikey' => getenv('GUPSHUP_API_KEY'),
                'message' => "Your OTP is: $otp",
                'to' => $contactno,
            ]);
            // $response = Http::withHeaders([
            //     'apikey' => ,
            // ])->get(getenv('GUPSHUP_API_URL'), [
            //     'channel' => '',
            //     'destination' => $contactno,
            //     'message' => "Your OTP is: $otp",
            // ]);

            return $response->json();
        }
}
