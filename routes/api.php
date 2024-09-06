<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/processUser', [AuthController::class, 'processUser']);
Route::post('/verifyUser', [AuthController::class, 'verifyUser']);

Route::middleware('auth:api')->group(function (){
    Route::get('/userInfo', [AuthController::class, 'userInfo']);
});
