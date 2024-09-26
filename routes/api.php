<?php

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LeadController;


Route::post('/register-user', [AuthController::class, 'registerUser']);
Route::post('/check-user-otp', [AuthController::class, 'checkUserOtp']);

// Route::middleware('auth:api')->group(function (){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/get-user-details/{uid}', [UserController::class, 'getUserDetails']);
    Route::get('/user-profile/{uid}', [UserController::class, 'userProfile']);
    Route::post('/add-update-user-profile', [UserController::class, 'addUpdateUserProfile']);
    Route::get('/get-property-types/{typeFlag}', [PropertyController::class, 'getPropertyTypes']);
    Route::post('/add-property-details', [PropertyController::class, 'addPropertyDetails']);
    Route::get('/get-property-statuses/{statusFlag}', [PropertyController::class, 'getPropertyStatues']);
    Route::get('/get-property-amenities', [PropertyController::class, 'getPropertyAmenities']);
    Route::post('/add-wing-details', [PropertyController::class, 'addWingDetails']);
    Route::post('/add-unit-details', [PropertyController::class, 'addUnitDetails']);
    Route::get('/get-wing-details/{propertyId}', [PropertyController::class, 'getWingDetails']);

    //leads call
    Route::get('/get-leads/{uid}', [LeadController::class, 'getLeads']); 
    Route::get('/get-user-properties/{uid}', [LeadController::class, 'getUserProperties']); 
    Route::get('/get-sources', [LeadController::class, 'getSources']); 
    Route::get('/fetch-lead-detail/{uid}/{lid}', [LeadController::class, 'fetchLeadDetail']); 
    Route::post('/add-edit-leads', [LeadController::class, 'addOrEditLeads']); 
    Route::post('/add-leads-csv', [LeadController::class, 'addLeadsCsv']); 

    Route::post('/lead-messages/send', [LeadController::class, 'sendBulkMessages']); 
// });


//rest api/webform api for leads
Route::post('/generate-lead/{source}', [LeadController::class, 'generateLead']); 


