<?php

use App\Http\Controllers\CoinCallController;
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

/* User Related Infos */
Route::get('account-info', [CoinCallController::class, 'getAccountInfo']);
Route::get('summary-info/{symbol?}', [CoinCallController::class, 'getSummaryInfo']);

/* public endpoints */
Route::prefix('public')->group(function () {
    Route::get('/funding-rate/{symbols?}', [CoinCallController::class, 'getFundingRate']);
});

/* futures */
Route::prefix('futures')->group(function () {
    Route::get('/orderbook/{currency}', [CoinCallController::class, 'getOrderBookFuture']);
    Route::get('/instruments', [CoinCallController::class, 'getInstrumentsFuture']);
    Route::get('/leverage/{symbol}', [CoinCallController::class, 'getLeverageFuture']);
    Route::get('/positions', [CoinCallController::class, 'getPositionsFuture']);
    Route::get('/open-orders/{symbol}/{page?}/{pageSize?}', [CoinCallController::class, 'getOpenOrdersFuture']);
    Route::get('cancel-order/{symbol}/{version?}', [CoinCallController::class, 'cancelOrderFuture']);
});

/* options prefix */
Route::prefix('options')->group(function () {
    Route::get('/orderbook/{symbolName}', [CoinCallController::class, 'getOrderBookOption']);
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

Route::get('/spread', [CoinCallController::class, 'getSpreadOP']);

Route::get('/future/funding-rate', [CoinCallController::class, 'getFutureFundingRate']);

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
});

Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);
