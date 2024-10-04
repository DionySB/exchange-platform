<?php

use App\Http\Controllers\CoinCallController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ExportCsvController;
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
Route::get('account-info', [CoinCallController::class, 'getAccountInfo']);
Route::get('summary-info/{symbol?}', [CoinCallController::class, 'getSummaryInfo']);

/* futures */
Route::prefix('futures')->group(function () {
    Route::get('/orderbook/{currency}', [CoinCallController::class, 'getOrderBookFuture']);
    Route::get('/leverage/{symbol}', [CoinCallController::class, 'getLeverageFuture']);
    Route::get('/positions', [CoinCallController::class, 'getPositionsFuture']);
    Route::get('/open-orders/{symbol}/{page?}/{pageSize?}', [CoinCallController::class, 'getOpenOrdersFuture']);
    Route::get('cancel-order/{symbol}/{version?}', [CoinCallController::class, 'cancelOrderFuture']);
});

/* options prefix */
Route::prefix('options')->group(function () {
    Route::get('/orderbook/{currency}/{optionName}', [CoinCallController::class, 'getOrderBookOption']);
    Route::get('/option-chain/{index}/{endTime}', [CoinCallController::class, 'getChainOption']);
    Route::get('/positions', [CoinCallController::class, 'getPositionsOption']);
    Route::get('/open-orders/{currency?}/{page?}/{pageSize?}', [CoinCallController::class, 'getOpenOrdersOption']);
    Route::get('/order-info/{type}/{id}', [CoinCallController::class, 'getOrderInfoOption']);
    Route::get('/order-details/{pageSize?}/{fromId?}/{startTime?}/{endTime?}', [CoinCallController::class, 'getOrderDetailsOption']);
    Route::get('/instruments/{baseCurrency?}', [CoinCallController::class, 'getInstrumentsOption']);
    Route::post('/cancel-order', [CoinCallController::class, 'cancelOrderOption']); //($dados)
    Route::post('/create-order', [CoinCallController::class, 'createOrderOption']); //($dados)
});

/* spots prefix */
Route::prefix('spots')->group(function () {
    Route::get('/orderbook/{baseCurrency}', [CoinCallController::class, 'getOrderBookSpot']);
    Route::get('/query-order/{type}/{id}', [CoinCallController::class, 'getQueryOrderSpot']);
    Route::get('/open-orders/{symbol?}', [CoinCallController::class, 'getOpenOrdersSpot']);
    Route::get('/orders/{symbol?}/{endTime?}/{startTime?}/{limit?}', [CoinCallController::class, 'getAllOrdersSpot']);
    Route::post('/create-order', [CoinCallController::class, 'createOrderSpot']); //($dados)
    Route::post('/cancel-order', [CoinCallController::class, 'cancelOrderSpot']); //($dados)
});

/* WebSocket */
Route::prefix('socket')->group(function () {
    Route::get('/orderbook', [CoinCallController::class, 'getOrderBook']);
});

Route::get('/csv', [ExportCsvController::class, 'export']);


