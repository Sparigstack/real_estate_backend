<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Mail\ManageLeads;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Property;
use App\Models\UserProperty;
use App\Models\User;
use App\Models\PlanDetail;
use App\Models\PlanUsageLog;
use App\Models\UserPlanDetail;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade as PDF;



class PlanController extends Controller
{

    public function purchasePlan(Request $request)
    {
        $userId = $request->input('userId');
        $planId = $request->input('planId');

       $userPlan = new UserPlanDetail();
       $userPlan->user_id = $userId;
       $userPlan->plan_id = $planId;
       $userPlan->save();

       return 'sucess';
    }

    public function checkPlanUsage($uid,$pid)
    {
              
    }
}