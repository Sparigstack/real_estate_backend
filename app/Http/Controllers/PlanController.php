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

        $planDetails = PlanDetail::where('id', $planId)->first();

        $userPlan = new UserPlanDetail();
        $userPlan->user_id = $userId;
        $userPlan->plan_id = $planId;
        $userPlan->save();

        $planUsageLog = new PlanUsageLog();
        $planUsageLog->user_id = $userId;
        $planUsageLog->plan_id = $planId;
        $planUsageLog->property_count = $planDetails->property_count;
        $planUsageLog->unit_count = $planDetails->unit_count;
        $planUsageLog->lead_count = $planDetails->lead_count;
        $planUsageLog->email_count = $planDetails->email_count;
        $planUsageLog->whatsapp_count = $planDetails->whatsapp_count;
        $planUsageLog->cheque_scan_count = $planDetails->cheque_scan_count;
        $planUsageLog->status = 2;

        return 'sucess';
    }

    public function checkPlanUsage($uid, $pid)
    {
        $checkUser = User::where('id', $uid)->first();
        if ($checkUser) {
            return $checkUser;
        }
    }

    public function addPlanUsageLog($uid, $pid, $flag)
{
    $checkDetails = PlanUsageLog::with('planDetails')
        ->where('user_id', $uid)
        ->where('plan_id', $pid)
        ->where('status', 1)
        ->first();

    if ($checkDetails) {
        $planDetails = $checkDetails->plan_details;

        switch ($flag) {
            case 1: // property_count
                if ($checkDetails->property_count < $planDetails->property_count) {
                    $propertCount = $checkDetails->property_count + 1;
                    PlanUsageLog::where('user_id', $uid)
                        ->where('plan_id', $pid)
                        ->where('status', 1)
                        ->update(['property_count' => $propertCount]);
                } else {
                    return 'Property count limit reached';
                }
                break;

            case 2: // unit_count
                if ($checkDetails->unit_count < $planDetails->unit_count) {
                    $unitCount = $checkDetails->unit_count + 1;
                    PlanUsageLog::where('user_id', $uid)
                        ->where('plan_id', $pid)
                        ->where('status', 1)
                        ->update(['unit_count' => $unitCount]);
                } else {
                    return 'Unit count limit reached';
                }
                break;

            case 3: // lead_count
                if ($checkDetails->lead_count < $planDetails->lead_count) {
                    $leadCount = $checkDetails->lead_count + 1;
                    PlanUsageLog::where('user_id', $uid)
                        ->where('plan_id', $pid)
                        ->where('status', 1)
                        ->update(['lead_count' => $leadCount]);
                } else {
                    return 'Lead count limit reached';
                }
                break;

            case 4: // email_count
                if ($checkDetails->email_count < $planDetails->email_count) {
                    $emailCount = $checkDetails->email_count + 1;
                    PlanUsageLog::where('user_id', $uid)
                        ->where('plan_id', $pid)
                        ->where('status', 1)
                        ->update(['email_count' => $emailCount]);
                } else {
                    return 'Email count limit reached';
                }
                break;

            case 5: // whatsapp_count
                if ($checkDetails->whatsapp_count < $planDetails->whatsapp_count) {
                    $whatsappCount = $checkDetails->whatsapp_count + 1;
                    PlanUsageLog::where('user_id', $uid)
                        ->where('plan_id', $pid)
                        ->where('status', 1)
                        ->update(['whatsapp_count' => $whatsappCount]);
                } else {
                    return 'WhatsApp count limit reached';
                }
                break;

            case 6: // cheque_scan_count
                if ($checkDetails->cheque_scan_count < $planDetails->cheque_scan_count) {
                    $chequeScanCount = $checkDetails->cheque_scan_count + 1;
                    PlanUsageLog::where('user_id', $uid)
                        ->where('plan_id', $pid)
                        ->where('status', 1)
                        ->update(['cheque_scan_count' => $chequeScanCount]);
                } else {
                    return 'Cheque scan count limit reached';
                }
                break;

            default:
                return 'Invalid flag';
        }

        return 'success';
    } else {
        return 'error';
    }
}

}