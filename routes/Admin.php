<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Product\RatingController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FoodTypeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DriverPriceController;
use App\Http\Controllers\OrderDriverController;

use App\Services\Driver\DriverServic;

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








Route::get('dashboard', [AdminController::class, 'adminDashboard']);






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


    Route::prefix('foodtype')->group(function () {
        Route::get('index/', [FoodTypeController::class, 'index']);
        Route::post('store/', [FoodTypeController::class, 'store']);
        Route::get('show/{coupon}', [FoodTypeController::class, 'show']);
        Route::put('update/{coupon}', [FoodTypeController::class, 'update']);
        Route::delete('delete/{coupon}', [FoodTypeController::class, 'destroy']);

    });





    Route::prefix('provider_service')->group(function () {

        Route::post('update_status/{vendor_id}', [AdminController::class, 'update_P_S_Status']);
        Route::get('/get_by_status', [AdminController::class, 'get_P_S_ByStatus']);
        Route::get('/show_info/{vendor_id}', [AdminController::class, 'get_P_S_Info']);
        Route::get('get_dashboard_vendor/{vendor_id}', [AdminController::class, 'Provider_service_dash']);

    });


    Route::prefix('profile')->group(function () {

    Route::post('/update/{user_id}', [ProfileController::class, 'updateProfile']);
    Route::put('/info/update/{user_id}', [ProfileController::class, 'UpdateInfo']);
    Route::get('/info/get/{user_id}', [ProfileController::class, 'getUserInfo']);
    Route::get('/get_All', [ProfileController::class, 'getAllUsers']);
    Route::put('/status/update/{user_id}', [ProfileController::class, 'updateUserStatus']);

    });


    Route::prefix('driver')->group(function () {

        Route::post('update_status/{vendor_id}', [DriverController::class, 'update_driver_status']);
        Route::get('/get_by_status', [DriverController::class, 'getDriverByStatus']);
        Route::get('/show_info/{vendor_id}', [DriverController::class, 'get_driver_info']);
       // Route::get('get_dashboard_vendor/{vendor_id}', [DriverController::class, 'Provider_service_dash']);


        Route::get('all_my_order', [OrderDriverController::class, 'getDriverOrders']);
        Route::get('order/show/{id}', [OrderDriverController::class, 'showDriverOrder']);

    Route::get('dashboard', [OrderDriverController::class, 'driverStatistics']);



    });





    Route::prefix('orders')->group(function () {
        Route::get('get_all/ByVendor/{id}', [AdminController::class, 'getVendorOrders']);
        Route::get('get_all_by_status', [AdminController::class, 'getOrdersByStatus']);
        Route::get('get_all_by_price', [AdminController::class, 'getOrdersByPriceRange']);
        Route::get('/get_all_by_produt_id/{product_id}', [AdminController::class, 'getOrdersByProduct']);
        Route::get('/get_all_by_user_id/{user_id}', [AdminController::class, 'getOrdersByUser']);
        Route::get('/get_all_by_category/{category_id}', [AdminController::class, 'getOrdersByCategory']);
    });



    Route::prefix('reservation')->group(function () {
        Route::get('get_all/ByVendor/{id}', [AdminController::class, 'getVendorResr']);
        Route::get('get_all_by_status', [AdminController::class, 'getReserByStatus']);
        Route::get('get_all_by_price', [AdminController::class, 'getReserByPriceRange']);
        Route::get('/get_all_by_produt_id/{product_id}', [AdminController::class, 'getreserByProduct']);
        Route::get('/get_all_by_user_id/{user_id}', [AdminController::class, 'getresersByUser']);
    });


    Route::prefix('rate')->group(function () {
        Route::get('/product/{product_id}', [RatingController::class, 'admin_getRateProduct']);
        Route::delete('/delete/{rate_id}', [RatingController::class, 'admin_delete_rate']);
        Route::delete('answer/delete/{answer_id}', [RatingController::class, 'admin_delete_answer']);
    });


    Route::prefix('commissions')->group(function () {
        Route::get('calculate/{vendor_id}', [CommissionController::class, 'getVendorCommission']);
        Route::get('calculate/Vendor_Product/{poduct_id}', [CommissionController::class, 'calculateByProduct']);
        Route::post('reset/{vendor_id}', [CommissionController::class, 'markVendorOrdersAsDone']);

    });


    Route::prefix('product')->group(function () {
        Route::get('/get_all_latest', [ProductController::class, 'latest_product']);
        Route::post('/By_Type', [ProductController::class, 'Get_By_Type']);
        Route::post('/product_by_category/{id}', [ProductController::class, 'Get_By_Category']);

        Route::post('/product_by_service_provider/{id}', [ProductController::class, 'Get_By_Service']);
        Route::post('/product_by_product_provider/{id}', [ProductController::class, 'Get_By_Product']);
        Route::get('/show/{id}', [ProductController::class, 'getProductById']);
        Route::get('/all_rating/{product_id}', [ProductController::class, 'getProductRatings']);

        Route::post('/store', [ProductController::class, 'store']);
        Route::post('/update/{product_id}', [ProductController::class, 'update']);
    });


    Route::prefix('coupons')->group(function () {
        Route::get('index/', [CouponController::class, 'index']);
        Route::post('store/', [CouponController::class, 'store']);
        Route::get('show/{coupon}', [CouponController::class, 'show']);
        Route::put('update/{coupon}', [CouponController::class, 'update']);
        Route::patch('update_status/{coupon}', [CouponController::class, 'update_status']);
        Route::delete('delete/{coupon}', [CouponController::class, 'destroy']);
    });


    Route::prefix('driver_price')->group(function () {
        Route::get('index/', [DriverPriceController::class, 'index']);
        Route::post('store/', [DriverPriceController::class, 'store']);
        Route::get('show/{coupon}', [DriverPriceController::class, 'show']);
        Route::put('update/{coupon}', [DriverPriceController::class, 'update']);
        Route::delete('delete/{coupon}', [DriverPriceController::class, 'destroy']);
    });





});
