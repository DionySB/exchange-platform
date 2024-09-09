<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\CreateOrderRequest;

class CoinCallController extends Controller
{
    private function apiRequest($method, $uri, $params = [])
    {
        $apiKey = '/5AeyqmVeF7YKVetwCgLvnifokYmpnM5giu4VcqQLoA=';
        $secretKey = '7IAXOK9/ofbLSydaL52JR2EKouCSmD81bvWiFbtDOd0=';
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

    public function getAccountInfo()
    {
        $uri = '/open/user/info/v1';
        $response = $this->apiRequest('GET', $uri);

        return response()->json($response);
    }

    public function getSummaryInfo($coin = null)
    {
        $uri = '/open/account/summary/v1';
        $response = $this->apiRequest('GET', $uri);

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
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    public function getSpotMarketOrderBook($symbol, $depth = 1)
    {
        $params = [
            'depth' => $depth,
            'symbol' => $symbol,
        ];
        $uri = '/open/spot/market/orderbook';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
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
        $response = $this->apiRequest('POST', $uri, $params);

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
        $response = $this->apiRequest('POST', $uri, $params);

        return response()->json($response);
    }

    public function getQueryOrder($id = null)
    {
        if (is_numeric($id)) {
            $params['orderId'] = $id;
        } else {
            $params['clientOrderId'] = $id;
        }
        $uri = '/open/spot/trade/order/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }

    public function getOpenOrders($symbol = null)
    {
        $uri = '/open/spot/trade/orders/v1/' . $symbol;
        $response = $this->apiRequest('GET', $uri);

        return response()->json($response);
    }

    public function getAllOrders($symbol = null, $startTime = null, $endTime = null, $limit = 500)
    {
        $params = [
            'symbol' => $symbol,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit
        ];
        $uri = '/open/spot/trade/allorders/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }
}
