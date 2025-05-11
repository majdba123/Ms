<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Admin\DashboardController;


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


});
