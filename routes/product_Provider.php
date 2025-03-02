<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Product_Provider\CategoryVendorController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Vendor\AnswerRatingController;



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

Route::middleware(['auth:sanctum' , 'product_provider'])->group(function () {

    Route::get('/categories/get_all', [CategoryController::class, 'category_provider']);



    Route::get('/product/get_all_By', [ProductController::class, 'getProviderProducts']);
    Route::post('/product/store', [ProductController::class, 'store']);
    Route::post('/product/update/{product_id}', [ProductController::class, 'update']);
    Route::delete('/product/delete/{product_id}', [ProductController::class, 'destroy']);



    Route::post('/answer_rating/store/{rate_id}', [AnswerRatingController::class, 'store']);
    Route::get('/answer_rating/get_all/{rate_id}', [AnswerRatingController::class, 'getAnswersByRating']);
    Route::put('/answer_rating/update/{answer_rate_id}', [AnswerRatingController::class, 'update']);
    Route::delete('/answer_rating/delete/{answer_rate_id}', [AnswerRatingController::class, 'destroy']);


});
