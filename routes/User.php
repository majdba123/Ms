<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\RatingController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\RseevationController;
use App\Http\Controllers\FavouriteUserController;
use App\Http\Controllers\ProfileController;

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

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('categories/get_all', [CategoryController::class, 'index']);
    Route::get('/categories/show/{id}', [CategoryController::class, 'show']);


    Route::get('/product/get_all_latest', [ProductController::class, 'latest_product']);
    Route::post('/product/By_Type', [ProductController::class, 'Get_By_Type']);
    Route::post('/product/product_by_category/{id}', [ProductController::class, 'Get_By_Category']);

    Route::post('/product/product_by_service_provider/{id}', [ProductController::class, 'Get_By_Service']);
    Route::post('/product/product_by_product_provider/{id}', [ProductController::class, 'Get_By_Product']);
    Route::get('/product/show/{id}', [ProductController::class, 'getProductById']);
    Route::get('/product/all_rating/{product_id}', [ProductController::class, 'getProductRatings']);



    Route::get('/rate/get_all', [RatingController::class, 'getUserRatings']);
    Route::post('/rate/store/{Product_id}', [RatingController::class, 'rateProduct']);
    Route::put('/rate/update/{Rate_id}', [RatingController::class, 'Update']);
    Route::delete('/rate/delete/{Rate_id}', [RatingController::class, 'destroy']);



    Route::post('/orders/store', [OrderController::class, 'createOrder']);
    Route::post('/reservation/store', [RseevationController::class, 'createOrder']);
    Route::get('/orders/ByStatus', [OrderController::class, 'getUserOrders']);
    Route::get('/orders/get_product/{order_id}', [OrderController::class, 'getProductOrder']);


    Route::get('/getUserReservations/ByStatus', [RseevationController::class, 'getUserReservations']);
    Route::get('reservation/show/{id}', [RseevationController::class, 'getProductReservation']);





    Route::post('/favourites/add/{favourite_id}', [FavouriteUserController::class, 'addToFavourites']);
    Route::delete('/favourites/remove/{favourite_id}', [FavouriteUserController::class, 'removeFromFavourites']);
    Route::get('/favourites/categories/get_all', [FavouriteUserController::class, 'getFavouriteCategories']);
    Route::get('/favourites/products/get_all', [FavouriteUserController::class, 'getFavouriteProducts']);



    Route::post('/profile/store', [ProfileController::class, 'storeProfile']);
    Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
    Route::put('/my_info/update', [ProfileController::class, 'UpdateInfo']);
    Route::get('/my_info/get', [ProfileController::class, 'getUserInfo']);

});
