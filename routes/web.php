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
Route::get('account-info', [CoinCallController::class, 'getAccountInfo']);
Route::get('summary-info/{symbol?}', [CoinCallController::class, 'getSummaryInfo']);
Route::get('option-orderbook/{symbol}', [CoinCallController::class, 'getOptionOrderBook']);
//Route::get('orderbook/{symbol}/{depth?}', [CoinCallController::class, 'getSpotMarketOrderBook']);


