<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::apiResource('supplier', SupplierController::class)->parameters(['supplier' => 'id'])->middleware('auth:sanctum');
Route::apiResource('category', CategoryController::class)->parameters(['category' => 'id'])->middleware('auth:sanctum');

Route::controller(UserController::class)->group(function (){
    Route::apiResource('user', UserController::class)->parameters(['user' => 'id'])->middleware('auth:sanctum');

    Route::put('/user/{id}/change-password', 'changePassword')->name('user.change.password')->middleware('auth:sanctum');
    Route::post('/user/reset-password', 'resetPassword')->name('user.reset.password');
    Route::post('/user/forgot-password', 'forgotPassword')->name('user.forgot.password');
    Route::post('/user/login', 'login')->name('user.login');
    Route::post('/user/register', 'register')->name('user.register');
});

Route::post('/file/upload', [FileController::class, 'upload'])->name('file.upload');