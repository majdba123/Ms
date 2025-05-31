<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Subscribe\SubscribeController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\RatingController;
use App\Http\Controllers\Vendor\AnswerRatingController;
use App\Http\Controllers\ProviderServiceController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DisccountController;


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

Route::middleware(['auth:sanctum' , 'services_provider'])->group(function () {


    Route::get('dashboard', [AdminController::class, 'Provider_service_dash']);



    Route::get('/categories/get_all', [CategoryController::class, 'category_provider']);



    Route::get('/web_subscribe/get_all', [SubscribeController::class, 'getAllWebSubs']);
    Route::get('/subscribe/get_all_my', [SubscribeController::class, 'my_subscribe']);
    Route::post('/subscribe/store', [SubscribeController::class, 'store']);


    Route::get('/product/get_all_By', [ProductController::class, 'getProviderProducts']);
    Route::get('/product/show/{product_id}', [ProductController::class, 'show']);
    Route::post('/product/store', [ProductController::class, 'store']);
    Route::post('/product/update/{product_id}', [ProductController::class, 'update']);
    Route::delete('/product/delete/{product_id}', [ProductController::class, 'destroy']);


    Route::get('/product/rating/{product_id}', [RatingController::class, 'getRateProduct']);




    Route::post('/answer_rating/store/{rate_id}', [AnswerRatingController::class, 'store']);
    Route::get('/answer_rating/get_all/{rate_id}', [AnswerRatingController::class, 'getAnswersByRating']);
    Route::put('/answer_rating/update/{answer_rate_id}', [AnswerRatingController::class, 'update']);
    Route::delete('/answer_rating/delete/{answer_rate_id}', [AnswerRatingController::class, 'destroy']);



    Route::prefix('reservation')->group(function () {
        Route::get('get_all', [ProviderServiceController::class, 'getVendorOrders']);
        Route::get('get_all_by_status', [ProviderServiceController::class, 'getVendorOrdersByStatus']);
        Route::get('/get_all_by_produt_id/{product_id}', [ProviderServiceController::class, 'getOrdersByProductId']);
    });

    Route::prefix('profile')->group(function () {
        Route::post('/update', [ProviderServiceController::class, 'updateProfile']);
        Route::get('/my_info', [ProviderServiceController::class, 'getProfile']);
    });



    Route::prefix('discount')->group(function () {
        Route::post('/store/{product_id}', [DisccountController::class, 'store']); // POST /products/1/discount
        Route::put('/update/{product_id}', [DisccountController::class, 'update']); // POST /products/1/discount
        Route::post('/changeStatus/{product_id}', [DisccountController::class, 'changeStatus']); // تغيير الحالة
        Route::delete('/destroy/{product_id}', [DisccountController::class, 'destroy']); // حذف الخصم
    });




});
