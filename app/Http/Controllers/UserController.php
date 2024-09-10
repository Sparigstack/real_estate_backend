<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use Illuminate\Http\Request;
use App\Models\User;
use App\Helper;


class UserController extends Controller
{
    public function userProfile($uid)
    {
        $user = User::with('company_detail')->where('id',$uid)->first();
        $userDetails = [
            'userName' => $user ? $user->name : 'null',
            'contactNum' => $user ? $user->contact_no : 'null',
            'companyName' => $user->company_detail ? $user->company_detail->name : null,
            'companyEmail' => $user->company_detail ? $user->company_detail->email : null,
            'companyContactNum' => $user->company_detail ? $user->company_detail->contact_no : null,
            'companyAddress' => $user->company_detail ? $user->company_detail->address : null,
            'companyLogo' => $user->company_detail ? $user->company_detail->logo : null,
        ];
        return $userDetails;
        // try{

        // }
        // catch (\Exception $e) {
        //     $errorFrom = 'userProfile';
        //     $errorMessage = $e->getMessage();
        //     $priority = 'high';
        //     Helper::errorLog($errorFrom, $errorMessage, $priority);
        //     return 'Something Went Wrong';
        // }

    }

}
