<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verifyOtp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:api')->group(function (){
    Route::get('/userInfo', [AuthController::class, 'userInfo']);
});
