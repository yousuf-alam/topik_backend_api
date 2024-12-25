<?php

use App\Http\Controllers\Admin\MockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Mock\QuestionController;
use App\Http\Controllers\Payment\PackageController;
use App\Http\Controllers\Payment\PaymentSubmissionController;
use App\Http\Controllers\SuperAdmin\PermissionController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\User\Homecontroller;
use App\Http\Controllers\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::prefix('admin')->group(function() {
    Route::post('/import-question', [QuestionController::class, 'importQuestion']);



    Route::post('/submit-answer', [MockController::class, 'submitAnswer']);


    Route::post('login', [App\Http\Controllers\Auth\AuthController::class,'login']);
    Route::post('register', [App\Http\Controllers\Auth\AuthController::class,'register']);




    Route::middleware('auth:sanctum')->group(function () {

        Route::group(['middleware' => ['permission:manage roles']], function () {

            Route::get('roles', [RoleController::class,'index']);
            Route::get('roles/{id}', [RoleController::class,'getSingleRole']);
            Route::post('roles', [RoleController::class,'createRole']);
            Route::put('roles/{id}', [RoleController::class,'updateRole']);
            Route::delete('roles/{id}', [RoleController::class,'deleteRole']);
            Route::get('permissions', [PermissionController::class,'index']);
            Route::get('users', [UserController::class,'index']);
            Route::get('users/{id}', [UserController::class,'singleUser']);
            Route::post('users', [UserController::class,'create']);
            Route::put('users/{id}', [UserController::class,'update']);
            Route::delete('users/{id}', [UserController::class,'delete']);
            Route::post('user/assignroles', [UserController::class,'assignRolesToUser']);
            Route::get('user/{id}/permissions', [UserController::class,'allPermissionsOfAUser']);
            Route::post('update-profile', [UserController::class,'updateProfile']);
            Route::delete('users/{id}', [UserController::class,'deleteUser']);

        });


        Route::get('mocks',[MockController::class,'getAllMocks']);
        Route::post('create-mock', [MockController::class, 'createMock']);
        Route::get('get-mock-by-id',[MockController::class,'getMockById']);
        Route::get('update-mock',[MockController::class,'updateMock']);

        //question
//        Route::post('/import-question', [QuestionController::class, 'importQuestion']);
        Route::get('all-questions',[QuestionController::class,'allQuestion']);
        Route::get('question-by-id',[QuestionController::class,'questionById']);
        Route::get('update-question',[QuestionController::class,'updateQuestion']);

        //package
        Route::get('all-packages',[PackageController::class,'allPackages']);
        Route::get('create-package',[PackageController::class,'createPackage']);
        Route::get('package-by-id',[PackageController::class,'packageById']);
        Route::get('update-package',[PackageController::class,'updatePackage']);


    });

});

    Route::prefix('user')->group(function () {
        Route::post('register', [AuthController::class, 'userRegister']);
        Route::post('login', [AuthController::class, 'userLogin']);
        Route::get('all-packages', [PackageController::class, 'getUserPackage']);
        Route::get('get-image-url', function () {
            $imageUrl = asset('images/topic_logo.jpeg');

            return $imageUrl;

        });


        Route::middleware('auth:sanctum')->group(function () {

            Route::get('available-mock', [UserController::class, 'availableMock']);
            Route::post('mock-play', [MockController::class, 'mockPlay']);
            Route::post('submit-answer', [MockController::class, 'submitAnswer']);
            Route::get('profile', [UserController::class, 'userProfile']);
            Route::post('payment-submission', [PaymentSubmissionController::class, 'paymentSubmission']);
            Route::post('logout', [AuthController::class, 'userLogout']);
        });
    });

