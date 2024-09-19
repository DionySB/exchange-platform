<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CacheOrderBookController extends Controller
{
    private function apiRequest($method, $uri, $params = [])
    {
        $apiKey = env('COINCALL_API_KEY');
        $secretKey = env('COINCALL_SECRET_KEY');
        $timestamp = round(microtime(true) * 1000);
        $tsDiff = 5000;
        ksort($params);
        $queryString = http_build_query($params);
        if (!blank($queryString)) {
            $uri .= '?' . $queryString;
            $prehashString = "{$method}{$uri}&uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";
        }else{
            $prehashString = "{$method}{$uri}?uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";
        }

        $url = "https://api.coincall.com{$uri}";
        $signature = hash_hmac('sha256', $prehashString, $secretKey);
        $signature = strtoupper($signature);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "X-CC-APIKEY: {$apiKey}",
                "sign: {$signature}",
                "ts: {$timestamp}",
                "X-REQ-TS-DIFF: {$tsDiff}",
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    public function getOrderbookBTC()
    {
        $params = [
            'symbol' => 'BTCUSDT',
            'limit' => 200
        ];
        $uri = '/open/spot/market/orderbook';
        $response = $this->apiRequest('GET', $uri, $params);
        $timestamp = Carbon::now('America/Sao_Paulo')->toDateTimeString();
        $data = [
            'data' => $response,
            'last_updated_at' => $timestamp,
        ];
        Cache::put('orderbook-BTCUSDT', $data, 600);

        return response()->json([
            'message' => 'Orderbook data cached successfully.',
            'updated_at' => $timestamp
        ]);
    }

    public function showCacheBTC()
    {
        $data = Cache::get('orderbook-BTCUSDT');

        return response()->json([
            'BTCUSDT' => $data
        ]);
    }
}

