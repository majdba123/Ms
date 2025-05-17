<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product_Provider\CategoryVendorController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\RatingController;
use App\Http\Controllers\Vendor\AnswerRatingController;
use App\Http\Controllers\ProviderProductController;
use App\Http\Controllers\CommissionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::middleware(['auth:sanctum' , 'product_provider'])->group(function () {

    Route::get('/categories/get_all', [CategoryController::class, 'category_provider']);

    Route::get('/product/show/{product_id}', [ProductController::class, 'show']);
    Route::get('/product/get_all_By', [ProductController::class, 'getProviderProducts']);
    Route::post('/product/store', [ProductController::class, 'store']);
    Route::post('/product/update/{product_id}', [ProductController::class, 'update']);
    Route::delete('/product/delete/{product_id}', [ProductController::class, 'destroy']);

    Route::get('/product/rating/{product_id}', [RatingController::class, 'getRateProduct']);


    Route::post('/answer_rating/store/{rate_id}', [AnswerRatingController::class, 'store']);
    Route::get('/answer_rating/get_all/{rate_id}', [AnswerRatingController::class, 'getAnswersByRating']);
    Route::put('/answer_rating/update/{answer_rate_id}', [AnswerRatingController::class, 'update']);
    Route::delete('/answer_rating/delete/{answer_rate_id}', [AnswerRatingController::class, 'destroy']);



    Route::prefix('orders')->group(function () {
        Route::get('get_all', [ProviderProductController::class, 'getVendorOrders']);
        Route::get('get_all_by_status', [ProviderProductController::class, 'getVendorOrdersByStatus']);
        Route::get('/get_all_by_produt_id/{product_id}', [ProviderProductController::class, 'getOrdersByProductId']);
        Route::get('/get_all_by_user_id/{user_id}', [ProviderProductController::class, 'getVendorOrdersByOrderProductStatus']);
    });


    Route::prefix('commissions')->group(function () {
        Route::get('calculate', [CommissionController::class, 'getVendorCommission']);
        Route::get('calculate/Product/{poduct_id}', [CommissionController::class, 'calculateByProduct']);
    });

    Route::prefix('profile')->group(function () {
        Route::post('/update', [ProviderProductController::class, 'updateProfile']);
        Route::get('/my_info', [ProviderProductController::class, 'getProfile']);
    });



});
