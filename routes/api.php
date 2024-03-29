<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BillController;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Group;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
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

Route::put('bill/pay/{id}', [BillController::class, 'pay'])->name('bill.pay')->middleware('auth:sanctum');
Route::put('bill/pay-bills', [BillController::class, 'payBills'])->name('bills.pay')->middleware('auth:sanctum');

Route::apiResource('bill', BillController::class)->parameters(['bill' => 'id'])->middleware('auth:sanctum');
Route::apiResource('supplier', SupplierController::class)->parameters(['supplier' => 'id'])->middleware('auth:sanctum');
Route::apiResource('category', CategoryController::class)->parameters(['category' => 'id'])->middleware('auth:sanctum');

Route::controller(UserController::class)->group(function (){
    Route::apiResource('user', UserController::class)->parameters(['user' => 'id'])->middleware('auth:sanctum');

    Route::put('/user/{id}/change-password', 'changePassword')->name('user.change.password')->middleware('auth:sanctum');
    Route::post('/user/reset-password', 'resetPassword')->name('user.reset.password');
    Route::post('/user/validate-token', 'validateToken')->name('user.validate.token');
    Route::post('/user/forgot-password', 'forgotPassword')->name('user.forgot.password');
    Route::post('/user/login', 'login')->name('user.login');
    Route::post('/user/register', 'register')->name('user.register');
    Route::post('/user/me', 'me')->name('user.me');

});

Route::post('/file/upload', [FileController::class, 'upload'])->name('file.upload');

Route::post('/create-suppliers', function () {
    $suppliers = request()->suppliers;

    $group = Group::where('created_at', '>=', Carbon::now()->startOfDay()->format('Y-m-d H:i:s'))->first();

    foreach ($suppliers as $name) {
        Supplier::create([
            'name' => $name,
            'group_id' => $group->id,
        ]);
    }
});

Route::post('/create-bills', function () {
    $bills = request()->bills;

    $group = Group::where('created_at', '>=', Carbon::now()->startOfDay()->format('Y-m-d H:i:s'))->first();

    $user = User::where('email', 'lucas.kaiut@gmail.com')->first();

    foreach ($bills as $billData) {
        $supplier = Supplier::where('name', $billData['supplier'])->first();

        $category = Category::where('name', $billData['category'])->first();

        unset($billData['category']);

        unset($billData['supplier']);

        $billData['category_id'] = $category->id;

        $billData['supplier_id'] = $supplier->id;

        $billData['group_id'] = $group->id;

        $billData['is_paid'] = $billData['is_paid'] == 'Sim' ? true : false;

        $billData['is_credit_card'] = $billData['is_credit_card'] == 'Sim' ? true : false;

        $billData['user_id'] = $user->id;

        Bill::create($billData);
    }
});