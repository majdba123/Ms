<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Subscribe\SubscribeController;
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

Route::middleware(['auth:sanctum' , 'services_provider'])->group(function () {

    Route::get('/categories/get_all', [CategoryController::class, 'category_provider']);



    Route::get('/web_subscribe/get_all', [SubscribeController::class, 'getAllWebSubs']);
    Route::get('/subscribe/get_all_my', [SubscribeController::class, 'my_subscribe']);
    Route::post('/subscribe/store', [SubscribeController::class, 'store']);



    Route::post('/product/store', [ProductController::class, 'store']);


    Route::post('/answer_rating/store/{rate_id}', [AnswerRatingController::class, 'store']);
    Route::get('/answer_rating/get_all/{rate_id}', [AnswerRatingController::class, 'getAnswersByRating']);
    Route::put('/answer_rating/update/{answer_rate_id}', [AnswerRatingController::class, 'update']);
    Route::delete('/answer_rating/delete/{answer_rate_id}', [AnswerRatingController::class, 'destroy']);


});
