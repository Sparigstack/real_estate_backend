<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Feature;
use App\Models\Module;
use App\Models\ModulePlanFeature;
use App\Models\ModulePlanPricing;
use App\Models\Plan;
use App\Models\UserCapability;



use App\Models\Property;
use App\Models\PaymentTransaction;
use App\Models\UserProperty;
use App\Models\User;
use Exception;








class PlanModuleController extends Controller
{


    public function getModulesWithPricing()
    {

        try {
            $modules = Module::with(['plans' => function ($query) {
                $query->select('plans.id', 'module_plan_pricing.module_id', 'monthly_price')
                    ->where('monthly_price', '>', 0) // Exclude prices equal to 0
                    ->orderBy('monthly_price', 'asc');
            }])->get();

            // Format the data to show the module name and its starting monthly price
            $formattedModules = $modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'module_name' => $module->name,
                    'starting_price' => $module->plans->isNotEmpty()
                        ? $module->plans->first()->pivot->monthly_price
                        : null,
                ];
            });

            return $formattedModules;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getModulesWithPricing';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function getModulePlanDetails($uid, $mid)
    {
        try {
            $plans = ModulePlanPricing::where('module_id', $mid)
                ->join('plans', 'plans.id', '=', 'module_plan_pricing.plan_id')
                ->select('plans.id as plan_id', 'plans.name as plan_name', 'module_plan_pricing.monthly_price', 'module_plan_pricing.yearly_price')
                ->get();

            // Prepare the dynamic pricing details for each plan
            $planDetails = [];
            foreach ($plans as $plan) {
                $planDetails[strtolower(str_replace(' ', '_', $plan->plan_name)) . 'id'] = $plan->plan_id;
                $planDetails[strtolower(str_replace(' ', '_', $plan->plan_name)) . 'monthly_price'] = $plan->monthly_price;
                $planDetails[strtolower(str_replace(' ', '_', $plan->plan_name)) . 'yearly_price'] = $plan->yearly_price;
            }

            // Get the active plan for the user and the module from user_capabilities
            $activePlan = UserCapability::where('user_id', $uid)
                ->where('module_id', $mid)
                ->first(['plan_id']);

            // Add active plan information to the response
            $planDetails['active_plan_id'] = $activePlan ? $activePlan->plan_id : null;

            // Return the JSON response
            return  $planDetails;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getModulePlanDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function addUserModulePlan(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'module_id' => 'required|exists:modules,id',
                'plan_id' => 'required|exists:plans,id',
            ]);

            $userId = $validated['user_id'];
            $moduleId = $validated['module_id'];
            $planId = $validated['plan_id'];

            // Fetch all features for the given module and plan
            $features = ModulePlanFeature::where('module_id', $moduleId)
                ->where('plan_id', $planId)
                ->with('feature') // Eager load feature to get action_name
                ->get();

            if ($features->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No features found for the given module and plan.',
                ], 200);
            }

            // Remove previous user capabilities for the same module
            UserCapability::where('user_id', $userId)
                ->where('module_id', $moduleId)
                ->delete();

            // Add new user capabilities based on the features of the selected plan
            $newCapabilities = [];
            foreach ($features as $feature) {
                $newCapabilities[] = [
                    'user_id' => $userId,
                    'plan_id' => $planId,
                    'module_id' => $moduleId,
                    'feature_id' => $feature->feature_id,
                    'limit' => $feature->limit,
                    'object_name' => $feature->feature->action_name, // Map action_name to object_name
                ];
            }

            // Insert new capabilities
            UserCapability::insert($newCapabilities);

            return response()->json([
                'status' => 'success',
                'message' => 'Plan and features successfully assigned to the user.',
            ], 200);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'addUserModulePlan';
            $errorMessage = $e->getMessage() . $e->getLine();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }
}
