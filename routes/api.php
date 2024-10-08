<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/RegisterUser', [AuthController::class, 'RegisterUser']);
Route::post('/CheckUserOtp', [AuthController::class, 'CheckUserOtp']);

Route::middleware('auth:api')->group(function (){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/userInfo', [AuthController::class, 'userInfo']);
});
