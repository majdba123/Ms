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
        Route::get('get_all', [OrderProductController::class, 'getAllVendorsOrders']);
        Route::post('accept_order', [OrderDriverController::class, 'acceptOrderProducts']);
        Route::post('update_to_on_way', [OrderDriverController::class, 'updateOrderToOnWay']);

        Route::get('all_my_order', [OrderDriverController::class, 'getDriverOrders']);

    });


});
