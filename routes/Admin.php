<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Product\RatingController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('categories/get_all', [CategoryController::class, 'index']);
    Route::post('categories/store', [CategoryController::class, 'store']);
    Route::post('categories/update/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/delete/{id}', [CategoryController::class, 'destroy']);
    Route::get('/categories/show/{id}', [CategoryController::class, 'show']);

    Route::get('/web_subscribe/get_all', [DashboardController::class, 'getAllWebSubs']);
    Route::post('web_subscribe/store', [DashboardController::class, 'storeWebSub']);
    Route::put('web_subscribe/update/{id}', [DashboardController::class, 'updateWebSub']);



    Route::post('subscribe/get_all', [DashboardController::class, 'getSubscriptionsByStatus']);
    Route::put('subscribe/update_status/{id}', [DashboardController::class, 'updateSubscriptionStatus']);



    Route::prefix('vendores')->group(function () {

        Route::post('update_status/{vendor_id}', [AdminController::class, 'updateVendorStatus']);
        Route::get('/get_by_status', [AdminController::class, 'getVendorsByStatus']);
        Route::get('/show_info/{vendor_id}', [AdminController::class, 'getVendorInfo']);
        Route::get('get_dashboard_vendor/{vendor_id}', [AdminController::class, 'VendorDashboard']);

    });


    Route::prefix('orders')->group(function () {
        Route::get('get_all/ByVendor/{id}', [AdminController::class, 'getVendorOrders']);
        Route::get('get_all_by_status', [AdminController::class, 'getOrdersByStatus']);
        Route::get('get_all_by_price', [AdminController::class, 'getOrdersByPriceRange']);
        Route::get('/get_all_by_produt_id/{product_id}', [AdminController::class, 'getOrdersByProduct']);
        Route::get('/get_all_by_user_id/{user_id}', [AdminController::class, 'getOrdersByUser']);
        Route::get('/get_all_by_category/{category_id}', [AdminController::class, 'getOrdersByCategory']);
    });


    Route::prefix('rate')->group(function () {
        Route::get('/product/{product_id}', [RatingController::class, 'admin_getRateProduct']);
        Route::delete('/delete/{rate_id}', [RatingController::class, 'admin_delete_rate']);
        Route::delete('answer/delete/{answer_id}', [RatingController::class, 'admin_delete_answer']);
    });


});
