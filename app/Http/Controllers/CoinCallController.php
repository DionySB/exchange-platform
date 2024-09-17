<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CoinCallController extends Controller
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
        $uri = '/open/option/order/orderbook/v1/' .  $symbol;
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
        $params = [
            'symbol' => $symbol
        ];
        $uri = '/open/spot/trade/orders/v1';
        $response = $this->apiRequest('GET', $uri, $params);

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

    public function getOptionChain($index, $endTime)
    {
        $params = [
            'endTime' => $endTime,
        ];

        $uri = "/open/option/get/v1/{$index}";
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }

    public function getPositions()
    {
        $uri = '/open/option/position/get/v1';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    public function getOpenOptionOrders($currency = null, $page = 1, $pageSize = 20)
    {
        $params = [
            'page' => $page,
            'pageSize' => $pageSize,
            'currency' => $currency
        ];

        $uri = '/open/option/order/pending/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function getOrderInfo($paramType, $id)
    {
        $params = [
            $paramType => $id
        ];

        $uri = '/open/option/order/singleQuery/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function getOrderDetails($pageSize = 20, $fromId = null, $startTime = null, $endTime = null)
    {
        $params = [
            'pageSize' => $pageSize,
            'fromId' => $fromId,
            'startTime' => $startTime,
            'endTime' => $endTime
        ];
        $uri = '/open/option/order/history/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }

    public function getOptionInstruments($baseCurrency)
    {
        $uri = '/open/option/getInstruments/' . $baseCurrency;
        $response = $this->apiRequest('GET', $uri);

        return response()->json($response);
    }

    public function createOptionOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'tradeSide' => 'required|integer|in:1,2',
            'tradeType' => 'required|integer|in:1,3',
            'clientOrderId' => 'nullable|numeric',
            'qty' => 'required|numeric',
            'price' => 'nullable|numeric',
            'stp' => 'nullable|in:1,2,3',
        ], $this->getMessagesCreateOptionOrder());

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $tradeType = $validatedData['tradeType'];

        if (in_array($tradeType, [1])) {
            if (empty($validatedData['price'])) {
                return response()->json([
                    'error' => 'price é obrigatório para o tipo de negociação LIMIT'
                ], 400);
            }
        };

        $params = array_filter([
            'clientOrderId' => $validatedData['clientOrderId'] ?? null,
            'tradeSide' => $validatedData['tradeSide'],
            'tradeType' => $validatedData['tradeType'],
            'symbol' => $validatedData['symbol'],
            'qty' => $validatedData['qty'],
            'price' => $validatedData['price'] ?? null,
            'stp' => $validatedData['stp'] ?? null

        ]);

        $uri = '/open/option/order/create/v1';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'tradeSide' => 'required|integer|in:1,2',
            'tradeType' => 'required|integer|in:1,2,3',
            'clientOrderId' => 'nullable|numeric',
            'qty' => 'nullable|numeric',
            'price' => 'nullable|numeric',
        ], $this->getMessagesCreateOrder());

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $tradeType = $validatedData['tradeType'];

        if (in_array($tradeType, [1, 3])) {
            if (empty($validatedData['qty']) || empty($validatedData['price'])) {
                return response()->json([
                    'error' => 'qty e price são obrigatórios para os tipos de negociação LIMIT e POST_ONLY.'
                ], 400);
            }
        } elseif ($tradeType == 2) {
            if (empty($validatedData['qty'])) {
                return response()->json([
                    'error' => 'qty é obrigatório para o tipo de negociação MARKET.'
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

    public function cancelOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clientOrderId' => 'nullable|string|required_without:orderId',
            'orderId' => 'nullable|string|required_without:clientOrderId',
        ], $this->getMessagesCancelOrder());

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        $params = array_filter([
            'clientOrderId' => $validatedData['clientOrderId'] ?? null,
            'orderId' => $validatedData['orderId'] ?? null,
        ]);

        $uri = '/open/option/order/cancel/v1';
        $response = $this->apiRequest('POST', $uri, $params);

        return response()->json($response);
    }

    protected function getMessagesCancelOrder()
    {
        return [
            'clientOrderId.required_without' => 'O campo clientOrderId é obrigatório quando orderId não está presente.',
            'orderId.required_without' => 'O campo orderId é obrigatório quando clientOrderId não está presente.',
        ];
    }

    protected function getMessagesCreateOptionOrder()
    {
        return [
            'tradeSide.in' => 'O campo tradeSide deve ter um dos seguintes valores: 1 ou 2.',
            'tradeType.in' => 'O campo tradeType deve ter um dos seguintes valores: 1 ou 3.',
            'stp.in' => 'O campo stp deve ter um dos seguintes valores: 1 (CM - Cancel Maker), 2 (CT - Cancel Taker), ou 3 (CB - Cancel Both).',
        ];
    }

    protected function getMessagesCreateOrder()
    {
        return [
            'tradeSide.in' => 'O campo tradeSide deve ter um dos seguintes valores: 1 ou 2.',
            'tradeType.in' => 'O campo tradeType deve ter um dos seguintes valores: 1 ou 3.',
        ];
    }
}

