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
    //Route::get('/user-profile/{uid}', [UserController::class, 'userProfile']);
    Route::post('/add-update-user-profile', [UserController::class, 'addUpdateUserProfile']);

    //properties call
    Route::get('/get-property-types/{typeFlag}', [PropertyController::class, 'getPropertyTypes']);
    Route::post('/add-property-details', [PropertyController::class, 'addPropertyDetails']);
    Route::get('/get-property-details/{pid}', [PropertyController::class, 'getPropertyDetails']);
    
    
    //remove this call later
    Route::get('/get-user-property-details/{uid}', [PropertyController::class, 'getUserPropertyDetails']);
    
    // Route::get('/get-property-statuses/{statusFlag}', [PropertyController::class, 'getPropertyStatues']);
    // Route::get('/get-property-amenities', [PropertyController::class, 'getPropertyAmenities']);
    Route::post('/add-wing-details', [PropertyController::class, 'addWingDetails']);

    // Route::post('/add-unit-details', [PropertyController::class, 'addUnitDetails']);
    // Route::get('/get-wing-details/{propertyId}', [PropertyController::class, 'getWingDetails']);
  //  Route::post('/add-similar-wing', [PropertyController::class, 'addSimilarWing']);

    Route::get('/get-all-properties/{uid}&{stateid}&{cityid}&{area}', [PropertyController::class, 'getAllProperties']);
    Route::get('/get-state-details', [PropertyController::class, 'getStateDetails']); 
    Route::get('/get-state-with-cities-details/{id}', [PropertyController::class, 'getStateWithCities']); 
    Route::get('/get-area-with-cities-details/{uid}/{cid}', [PropertyController::class, 'getAreaWithCities']); 

    

    //leads call
    Route::get('/get-leads/{pid}&{skey}&{sort}&{sortbykey}&{offset}&{limit}', [LeadController::class, 'getLeads']);  
    Route::get('/fetch-lead-detail/{pid}/{lid}', [LeadController::class, 'fetchLeadDetail']); 
    Route::post('/add-edit-leads', [LeadController::class, 'addOrEditLeads']); 
    Route::post('/add-leads-csv', [LeadController::class, 'addLeadsCsv']); 
    Route::post('/update-lead-notes', [LeadController::class, 'updateLeadNotes']);

    Route::post('/lead-messages/send', [LeadController::class, 'sendBulkMessages']); 
// });



//apis without auth
Route::get('/get-sources', [LeadController::class, 'getSources']); 

//remove this call later
Route::get('/get-user-properties/{uid}', [LeadController::class, 'getUserProperties']); 


//rest api/webform api for leads
Route::post('/generate-lead', [LeadController::class, 'generateLead']); 
Route::post('/web-form-lead', [LeadController::class, 'webFormLead']); 




