<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::post('/register-user', [AuthController::class, 'registerUser']);
Route::post('/check-user-otp', [AuthController::class, 'checkUserOtp']);

Route::middleware('auth:api')->group(function (){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/get-user-details/{uid}', [UserController::class, 'getUserDetails']);
    Route::get('/user-profile/{uid}', [UserController::class, 'userProfile']);
    Route::post('/add-update-user-profile', [UserController::class, 'addUpdateUserProfile']);
});
