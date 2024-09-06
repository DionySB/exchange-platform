<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\CreateOrderRequest;

class CoinCallController extends Controller
{
    //method GET
    private function apiRequest($uri, $params = [])
    {
        $apiKey = '/5AeyqmVeF7YKVetwCgLvnifokYmpnM5giu4VcqQLoA=';
        $secretKey = '7IAXOK9/ofbLSydaL52JR2EKouCSmD81bvWiFbtDOd0=';
        $timestamp = round(microtime(true) * 1000);
        $tsDiff = 5000;
        ksort($params);
        $queryString = http_build_query($params);

        if (!blank($queryString)) {
            $uri .= '?' . $queryString;
            $prehashString = "GET{$uri}&uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";
        }else{
            $prehashString = "GET{$uri}?uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";
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
            CURLOPT_CUSTOMREQUEST => 'GET',
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
        $response = curl_exec($curl);
        dd($response, $prehashString, $url);
        curl_close($curl);

        return json_decode($response, true);
    }

    private function apiRequestPOST($uri, $params = [])
    {
        $apiKey = '3KOy8PA2yMf4CwgzC7kHlyopuxkYRJRnCYzceS3HQAY=';
        $secretKey = 'DXwvncH2w6cyJP3rJwymQkiG4pPs2WrTb20rNkaVHo4=';
        $timestamp = round(microtime(true) * 1000);
        $tsDiff = 5000;
        ksort($params);
        $queryString = http_build_query($params);
        $prehashString = "POST{$uri}?{$queryString}&uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";

        $signature = hash_hmac('sha256', $prehashString, $secretKey);
        $signature = strtoupper($signature);

        $url = "https://api.coincall.com{$uri}?{$queryString}&uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "X-CC-APIKEY: {$apiKey}",
                "sign: {$signature}",
                "ts: {$timestamp}",
                "X-REQ-TS-DIFF: {$tsDiff}",
            ],
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($curl);
        dd($response, $prehashString, $url);
        curl_close($curl);

        return json_decode($response, true);
    }

    public function getAccountInfo()
    {
        $uri = '/open/user/info/v1';

        return $this->apiRequest($uri);
    }

    public function getSummaryInfo($coin = null)
    {
        $uri = '/open/account/summary/v1';
        $response = $this->apiRequest($uri);

        if ($coin) {
            foreach ($response['data']['accounts'] as $symbol) {
                if ($symbol['coin'] === strtoupper($coin)) {
                    return [
                        'data' => [
                            'userId' => $response['data']['userId'],
                            'totalBtcValue' => $response['data']['totalBtcValue'],
                            'totalDollarValue' => $response['data']['totalDollarValue'],
                            'totalUsdtValue' => $response['data']['totalUsdtValue'],
                            'accounts' => [$symbol],
                        ],
                    ];
                }
            }

            return ['error' => 'Moeda não encontrada'];
        }

        return $response;
    }

    public function getOptionOrderBook($symbol)
    {
        $uri = '/open/option/order/orderbook/v1/' . $symbol;

        return $this->apiRequest($uri);
    }

    public function getSpotMarketOrderBook ($symbol, $depth = 1)
    {
        $params = [
            'depth' => $depth,
            'symbol' => $symbol,

        ];
        $uri = '/open/spot/market/orderbook';

        return $this->apiRequest($uri, $params);
    }

    public function createOrder(CreateOrderRequest $request)
    {
        $validatedData = $request->validated();
        $tradeType = $validatedData['tradeType'];

        if (in_array($tradeType, [1, 3])) {
            if (empty($validatedData['qty']) || empty($validatedData['price'])) {
                return response()->json([
                    'error' => 'Quantity and price are required for LIMIT and POST_ONLY trade types.'
                ], 400);
            }
        } elseif ($tradeType == 2) {
            if (empty($validatedData['qty'])) {
                return response()->json([
                    'error' => 'Quantity is required for MARKET trade type.'
                ], 400);
            }
        }

        $params = array_filter([
            'symbol' => $validatedData['symbol'],
            'clientOrderId' => $validatedData['clientOrderId'] ?? null,
            'tradeSide' => $validatedData['tradeSide'],
            'tradeType' => $validatedData['tradeType'],
            'qty' => $validatedData['qty'] ?? null,
            'price' => $validatedData['price'] ?? null,
        ]);

        $uri = '/open/spot/trade/order/v1';
        $response = $this->apiRequestPOST($uri, $params);

        return response()->json($response);
    }

    public function cancelOrder(CancelOrderRequest $request)
    {
        $validatedData = $request->validated();
        $params = array_filter([
            'clientOrderId' => $validatedData['clientOrderId'] ?? null,
            'orderId' => $validatedData['orderId'] ?? null,
        ]);

        $uri = '/open/option/order/cancel/v1';
        $response = $this->apiRequestPOST($uri, $params);

        return response()->json($response);
    }

    /***
     * Esse método requer um clientOrderId OU  orderId
     *
     */
    public function getQueryOrder ($clientOrderId = null, $orderId = null)
    {
        $params = [
            'clientOrderId' => $clientOrderId,
            'orderId' => $orderId,
        ];
        $uri = '/open/spot/trade/order/v1';

        return $this->apiRequest($uri, $params);
    }

    public function getOpenOrders($symbol = null)
    {
        $uri = '/open/spot/trade/orders/v1/' . $symbol;

        return $this->apiRequest($uri);
    }

    public function getAllOrders($symbol = null, $startTime = null, $endTime = null,  $limit = 500)
    {
        $params = [
            'symbol' => $symbol,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit
        ];
        $uri = '/open/spot/trade/allorders/v1';

        return $this->apiRequest($uri, $params);
    }
}


