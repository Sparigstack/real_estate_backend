<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helper;
use App\Models\Feature;
use App\Models\Module;
use App\Models\ModulePlanFeature;
use App\Models\ModulePlanPricing;
use App\Models\Plan;
use App\Models\UserCapability;

class CheckFeatureAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    // public function handle(Request $request, Closure $next): Response
    // {
    //     return $next($request);
    // }
    public function handle(Request $request, Closure $next)
    {
        try {
            $userId =$request->input('userId') ?? null; // Assuming user is authenticated
            $actionName = $request->input('userCapabilities');
            $moduleId = Helper::getModuleIdFromAction($actionName); // Fetch module ID based on action name
            
            // 1. Fetch Feature ID by action name and module ID
            $feature = Feature::where('action_name', $actionName)
                ->where('module_id', $moduleId)
                ->first();

            if (!$feature) {
                return response()->json(
                    data: 
                    ['status' => 'error', 
                    'message' => 'Feature not found'], 
                    status: 200);
            }

            // 2. Check User's active plan capabilities for this feature
            $userCapability = UserCapability::where('user_id', $userId)
                ->where('module_id', $moduleId)
                ->where('feature_id', $feature->id)
                ->first();

            if (!$userCapability) {
                // Fetch the user’s active plan for module
                $activePlan = UserCapability::where('user_id', $userId)
                    ->where('module_id', $moduleId)
                    ->first();

                $planName = Plan::where('id', $activePlan->plan_id ?? null)->value('name');

                return response()->json(data: [
                    'status' => 'upgradeplan',
                    'moduleid' => $moduleId,
                    'activeplanname' => $planName ?? 'unknown',
                ], status: 200);
            }

            // // 3. Check feature limits for specific actions like lead entry
            // if ($actionName == 'manual_entry_csv_import') {
            //     return $this->checkFeatureLimits($userId, $moduleId, $feature);
            // }

            // Proceed with the request
            return $next($request);

        } catch (\Exception $e) {
            $errorFrom = 'checkfeatureaccess';
            $errorMessage = $e->getMessage().$e->getLine();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    
}
