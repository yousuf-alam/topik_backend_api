<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Payment\PaymentSubmissionController;
use App\Http\Controllers\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->group(function () {
    Route::post('register', [AuthController::class, 'userRegister']);
    Route::post('login', [AuthController::class, 'userLogin']);


    Route::middleware('auth:sanctum')->group(function () {

        Route::get('user-profile', [UserController::class,'userProfile']);
        Route::post('payment-submission', [PaymentSubmissionController::class,'paymentSubmission']);
        Route::post('logout', [AuthController::class,'userLogout']);
    });
});
