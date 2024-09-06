<?php

use App\Http\Controllers\CoinCallController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', [RouteController::class, 'index']);

//------------------------------------ Get User Info(SIGNED) -----------------------------------//
Route::get('account-info', [CoinCallController::class, 'getAccountInfo']);

//------------------------------------ Get Account Summary(SIGNED) -----------------------------------//
Route::get('summary-info/{symbol?}', [CoinCallController::class, 'getSummaryInfo']);

//------------------------------------ Get OrderBook(SIGNED) -----------------------------------//
Route::get('option-orderbook/{symbol}', [CoinCallController::class, 'getOptionOrderBook']);

//------------------------------------ Get Orderbook(SIGNED) 'depth data' -----------------------------------//
Route::get('/orderbook/{symbol}/{depth?}', [CoinCallController::class, 'getSpotMarketOrderBook']);

//------------------------------------ Place Order(SIGNED) -----------------------------------//
Route::post('create-order', [CoinCallController::class, 'createOrder']);

//------------------------------------ Cancel Order(SIGNED) -----------------------------------//
Route::post('cancel-order', [CoinCallController::class, 'cancelOrder']);

//------------------------------------ Query Order (SIGNED) -----------------------------------//
Route::get('/query-order/{clientOrderId?}/{orderId?}', [CoinCallController::class, 'getQueryOrder']);

//------------------------------------ Query Open Orders(SIGNED) -----------------------------------//
Route::get('open-orders/{symbol?}', [CoinCallController::class, 'getOpenOrders']);

//------------------------------------ Query Open Orders(SIGNED) -----------------------------------//
Route::get('/orders/{symbol?}/{endTime?}/{startTime?}/{limit?}', [CoinCallController::class, 'getAllOrders']);



