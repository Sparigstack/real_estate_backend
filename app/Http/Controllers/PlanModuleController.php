<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Feature;
use App\Models\Module;
use App\Models\ModulePlanPricing;
use App\Models\Plan;

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
                    'id'=> $module->id,
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
}
