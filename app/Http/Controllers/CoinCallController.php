<?php

namespace App\Http\Controllers;

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

    public function createOptionOrder(array $dados) {

        /*
            $dados = [
                'symbol' => 'BTCUSD',           String obrigatória
                'tradeSide' => 1,               Inteiro obrigatório (1 para buy, 2 para sell)
                'tradeType' => 1,               Inteiro obrigatório (1 para LIMIT, 2 para MARKET)
                'clientOrderId' => '12345',     String opcional
                'qty' => 10,                    Float obrigatório para LIMIT e MARKET
                'price' => 50000,               Float obrigatório para LIMIT
                'stp' => 1                      Inteiro opcional (1 para CM, 2 para CT, 3 para CB)
            ];
        */

        $symbol = $dados['symbol'];
        $tradeSide = $dados['tradeSide'];
        $tradeType = $dados['tradeType'];
        $clientOrderId = $dados['clientOrderId'] ?? null;
        $qty = $dados['qty'] ?? null;
        $price = $dados['price'] ?? null;
        $stp = $dados['stp'] ?? null;

        if (empty($symbol) || empty($tradeSide) || empty($tradeType)) {
            return [
                'success' => false,
                'message' => 'Os campos symbol, tradeSide e tradeType são obrigatórios.'
            ];
        } elseif (!in_array($tradeSide, [1, 2])) {
            return [
                'success' => false,
                'message' => 'tradeSide inválido. Deve ser 1 (buy) ou 2 (sell).'
            ];
        } elseif ($tradeType == 1 && (empty($qty) || empty($price))) {
            return [
                'success' => false,
                'message' => 'qty e price são obrigatórios para o tipo LIMIT.'
            ];
        } elseif ($tradeType == 2 && empty($qty)) {
            return [
                'success' => false,
                'message' => 'qty é obrigatório para o tipo MARKET.'
            ];
        } elseif ($stp !== null && !in_array($stp, [1, 2, 3])) {
            return [
                'success' => false,
                'message' => 'stp deve ser 1 (CM), 2 (CT), ou 3 (CB).'
            ];
        }

        $params = array_filter([
            'symbol' => $symbol,
            'clientOrderId' => $clientOrderId ?? null,
            'tradeSide' => $tradeSide,
            'tradeType' => $tradeType,
            'qty' => $qty ?? null,
            'price' => $price ?? null,
            'stp' => $stp ?? null
        ]);

        $uri = '/open/option/order/create/v1';
        $response = $this->apiRequest('POST', $uri, $params);

        if (!empty($response['success']) && !empty($response['orderId'])) {
            return [
                'success' => true,
                'message' => 'Ordem criada com sucesso.'
            ];
        }

        return [
            'success' => false,
            'code' => $response['code'],
            'message' => $response['msg'],
        ];
    }

    public function createOrder(array $dados) {
        /*
            $dados = [
                'symbol' => 'BTCUSD',           // String obrigatória
                'tradeSide' => 1,               // Inteiro obrigatório (1 para BUY, 2 para SELL)
                'tradeType' => 1,               // Inteiro obrigatório (1 para LIMIT, 2 para MARKET, 3 para POST_ONLY)
                'clientOrderId' => '12345',     // String opcional
                'qty' => 10.0,                  // Float obrigatório para LIMIT, MARKET e POST_ONLY
                'price' => 50000.0,             // Float obrigatório para LIMIT e POST_ONLY (opcional para MARKET)
            ];
        */

        $symbol = $dados['symbol'] ?? null;
        $tradeSide = $dados['tradeSide'] ?? null;
        $tradeType = $dados['tradeType'] ?? null;
        $clientOrderId = $dados['clientOrderId'] ?? null;
        $qty = $dados['qty'] ?? null;
        $price = $dados['price'] ?? null;

        if (empty($symbol) || empty($tradeSide) || empty($tradeType)) {
            return [
                'success' => false,
                'message' => 'Os campos symbol, tradeSide e tradeType são obrigatórios.'
            ];
        } elseif (!in_array($tradeSide, [1, 2])) {
            return [
                'success' => false,
                'message' => 'tradeSide inválido. Deve ser 1 (buy) ou 2 (sell).'
            ];
        } elseif (in_array($tradeType, [1, 3]) && (empty($qty) || empty($price))) {
            return [
                'success' => false,
                'message' => 'qty e price são obrigatórios para o tipo LIMIT e POST_ONLY.'
            ];
        } elseif ($tradeType == 2 && empty($qty)) {
            return [
                'success' => false,
                'message' => 'qty é obrigatório para o tipo  MARKET.'
            ];
        }

        $params = array_filter([
            'symbol' => $symbol,
            'clientOrderId' => $clientOrderId ?? null,
            'tradeSide' => $tradeSide,
            'tradeType' => $tradeType,
            'qty' => $qty ?? null,
            'price' => $price ?? null,
        ]);

        $uri = '/open/spot/trade/order/v1';
        $response = $this->apiRequest('POST', $uri, $params);

        if (!empty($response['success']) && !empty($response['orderId'])) {
            return [
                'success' => true,
                'message' => 'Ordem criada com sucesso.'
            ];
        }

        return [
            'success' => false,
            'code' => $response['code'],
            'message' => $response['msg'],
        ];
    }

    public function cancelOrder(array $dados) {
        /*
            $dados = [
                'orderId' => 1663820914095300608,  // opcional, mas um dos dois (orderId ou clientOrderId) deve ser passado
                'clientOrderId' => 123123123,      // opcional
            ];
        */

        $clientOrderId = $dados['clientOrderId'] ?? null;
        $orderId = $dados['orderId'] ?? null;

        if (!empty($clientOrderId) || !empty($orderId)) {
            $params = [
                'clientOrderId' => $clientOrderId ?? null,
                'orderId' => $orderId ?? null,
            ];

        $uri = '/open/option/order/cancel/v1';
        $response = $this->apiRequest('POST', $uri, $params);


        if (isset($response['code']) && $response['code'] === 0) {
            return [
                'success' => true,
                'message' => 'Ordem cancelada com sucesso.'
            ];
        }

        return [
            'success' => false,
            'code' => $response['code'],
            'message' => $response['msg'],
        ];
    }

    return [
        'success' => false,
        'message' => 'Você deve fornecer ou clientOrderId ou orderId.'
    ];
    }
}

