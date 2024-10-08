<?php

use App\Http\Controllers\Admin\MockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Mock\QuestionController;
use App\Http\Controllers\Payment\PackageController;
use App\Http\Controllers\Payment\PaymentSubmissionController;
use App\Http\Controllers\User\Homecontroller;
use App\Http\Controllers\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('admin')->group(function(){

    Route::post('/import-question', [QuestionController::class, 'importQuestion']);
    Route::post('/create-mock',[MockController::class,'createMock']);
    Route::post('/submit-answer',[MockController::class,'submitAnswer']);


});

Route::prefix('user')->group(function () {
    Route::post('register', [AuthController::class, 'userRegister']);
    Route::post('login', [AuthController::class, 'userLogin']);
    Route::get('all-packages',[PackageController::class,'getUserPackage']);
    Route::get('get-image-url',function (){
        $imageUrl = asset('images/topic_logo.jpeg');

        return $imageUrl;

    });


    Route::middleware('auth:sanctum')->group(function () {

        Route::get('available-mock',[UserController::class,'availableMock']);
        Route::post('mock-play',[MockController::class,'mockPlay']);
        Route::post('submit-answer',[MockController::class,'submitAnswer']);
        Route::get('profile', [UserController::class,'userProfile']);
        Route::post('payment-submission', [PaymentSubmissionController::class,'paymentSubmission']);
        Route::post('logout', [AuthController::class,'userLogout']);
    });
});
