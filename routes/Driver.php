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
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderDriverController;
use App\Http\Controllers\OrderProductController;

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

Route::middleware(['auth:sanctum' ,'driver'])->group(function () {


    Route::prefix('order')->group(function () {
        Route::get('get_all', [OrderProductController::class, 'index']);
        Route::post('accept_order', [OrderDriverController::class, 'acceptOrderProducts']);
        Route::post('update_status', [OrderDriverController::class, 'updateOrderProductStatus']);
        Route::get('show/{id}', [OrderDriverController::class, 'showDriverOrder']);

        Route::get('all_my_order', [OrderDriverController::class, 'getDriverOrders']);

    });


    Route::get('dashboard', [OrderDriverController::class, 'driverStatistics']);


});
