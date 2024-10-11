<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;


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

    /* User Account Related Functions */

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

    /* Options Functions */

    public function getOrderBookOption($optionName)
    {
        $optionName = strtoupper($optionName);

        $uri = '/open/option/order/orderbook/v1/' . $optionName;

        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    public function getChainOption($index, $endTime)
    {
        $params = [
            'endTime' => $endTime,
        ];

        $uri = "/open/option/get/v1/{$index}";
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }

    public function getPositionsOption()
    {
        $uri = '/open/option/position/get/v1';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    public function getOpenOrdersOption($currency = null, $page = 1, $pageSize = 20)
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

    public function getOrderInfoOption($paramType, $id)
    {
        $params = [
            $paramType => $id
        ];

        $uri = '/open/option/order/singleQuery/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function getOrderDetailsOption($pageSize = 20, $fromId = null, $startTime = null, $endTime = null)
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

    public function cancelOrderOption(array $dados) {
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
                'message' => 'Ordem cancelada com sucesso.',
                'data' => $response
            ];
        }

        return [
            'sucess' => false,
            'message' => 'Erro ao enviar solicitacao de cancelamento',
            'data' => $response
        ];
    }

    return [
        'success' => false,
        'message' => 'Você deve fornecer ou clientOrderId ou orderId.'
    ];
    }


    public function getInstrumentsOption($baseCurrency)
    {
        $uri = '/open/option/getInstruments/' . $baseCurrency;
        $response = $this->apiRequest('GET', $uri);

        return response()->json($response);
    }

    public function createOrderOption(array $dados) {

        /*
            $dados = [
                'symbol' => 'BTCUSD-26OCT22-15000-C',           String obrigatória (Option name)
                'tradeSide' => 1,                               Inteiro obrigatório (1 para buy, 2 para sell)
                'tradeType' => 1,                               Inteiro obrigatório (1 para LIMIT, 3 para POST_ONLY)
                'clientOrderId' => '12345',                     String opcional
                'qty' => 10,                                    Float obrigatório
                'price' => 50000,                               Float obrigatório para LIMIT
                'stp' => 1                                      Inteiro opcional (1 para CM, 2 para CT, 3 para CB)
            ];
        */

        $symbol = $dados['symbol'] ?? null;
        $tradeSide = $dados['tradeSide'] ?? null;
        $tradeType = $dados['tradeType'] ?? null;
        $clientOrderId = $dados['clientOrderId'] ?? null;
        $qty = $dados['qty'] ?? null;
        $price = $dados['price'] ?? null;
        $stp = $dados['stp'] ?? null;

        if (empty($symbol) || empty($tradeSide) || empty($tradeType) || empty($qty)) {
            return [
                'success' => false,
                'message' => 'Os campos symbol, tradeSide, tradeType e qty são obrigatórios.'
            ];
        } elseif (!in_array($tradeSide, [1, 2])) {
            return [
                'success' => false,
                'message' => 'tradeSide inválido. Deve ser 1 (buy) ou 2 (sell).'
            ];
        } elseif (!in_array($tradeType, [1,3])) {
            return [
                'success' => false,
                'message' => 'tradeType inválido 1 LIMIT 3 POST_ONLY'
            ];
        } elseif ($tradeType == 1 && empty($price)) {
            return [
                'success' => false,
                'message' => 'price é obrigatório para LIMIT order.'
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
                'message' => 'Ordem criada com sucesso.',
                'data' => $response
            ];
        }

        return [
            'sucess' => false,
            'message' => 'Erro ao enviar solicitacao',
            'data' => $response
        ];
    }

    /* Spots Functions */

    public function getOrderBookSpot($baseCurrency)
    {
        $baseCurrency = strtoupper($baseCurrency);

        $uri = '/open/spot/market/orderbook';

        $params = [
            'depth' => 1,
            'symbol' => $baseCurrency . 'USDT',
        ];
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function getQueryOrderSpot($paramType, $id)
    {
        $params = [
            $paramType => $id
        ];
        $uri = '/open/spot/trade/order/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }

    public function getOpenOrdersSpot($symbol = null)
    {
        $params = [
            'symbol' => $symbol
        ];
        $uri = '/open/spot/trade/orders/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return response()->json($response);
    }

    public function getAllOrdersSpot($symbol = null, $startTime = null, $endTime = null, $limit = 500)
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

    public function createOrderSpot(array $dados) {
        /*
            $dados = [
                'symbol' => 'BTCUSDT',           // String obrigatória
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
                'message' => 'Ordem criada com sucesso.',
                'data' => $response
            ];
        }

        return [
            'sucess' => false,
            'message' => 'Erro ao enviar solicitacao',
            'data' => $response
        ];
    }

    public function cancelOrderSpot(array $dados) {
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

        $uri = '/open/spot/trade/cancel/v1';
        $response = $this->apiRequest('POST', $uri, $params);


        if (isset($response['code']) && $response['code'] === 0) {
            return [
                'success' => true,
                'message' => 'Ordem cancelada com sucesso.',
                'data' => $response
            ];
        }

        return [
            'sucess' => false,
            'message' => 'Erro ao enviar solicitacao',
            'data' => $response
        ];
    }

    return [
        'success' => false,
        'message' => 'Você deve fornecer ou clientOrderId ou orderId.'
    ];
    }

    /* Futures Functions */

    public function getOrderBookFuture($baseCurrency)
    {
        $baseCurrency = strtoupper($baseCurrency);

        $uri = '/open/futures/market/orderbook';

        $params = [
            'depth' => 1,
            'symbol' => $baseCurrency . '-USD'
        ];
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function getLeverageFuture($symbol)
    {
        $params = [
            'symbol' => $symbol
        ];

        $uri = '/open/futures/leverage/current/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function setLeverageFuture(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'BTCUSD',           // String obrigatória
                'leverage' => 10,               // Inteiro obrigatório
            ];
        */

        $symbol = $dados['symbol'] ?? null;
        $leverage = $dados['leverage'] ?? null;

        if(empty($symbol) || empty($leverage)){
            return [
                'success' => true,
                'message' => 'symbol e leverage são obrigatórios.'
            ];
        }

        $params = [
            'symbol' => $symbol,
            'leverage' => $leverage,
        ];
        $uri = '/open/futures/leverage/set/v1';
        $response = $this->apiRequest('POST', $uri, $params);

        if($response['code'] === 0) {
            return [
                'success' => true,
                'message' => 'Solicitacao enviada.',
                'data' => $response
            ];
        };

        return [
            'sucess' => false,
            'message' => 'Erro ao enviar solicitacao',
            'data' => $response
        ];

    }

    public function getPositionsFuture()
    {
        $uri = '/open/futures/position/get/v1';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    public function createOrderFuture(array $dados)
    {

        /*
            $dados = [
                'symbol' => 'BTCUSD', // String obrigatória
                'qty' => 0.5, // Float obrigatório, quantidade
                'tradeSide' => 1, // Inteiro obrigatório, (1: BUY, 2: SELL)
                'tradeType' => 1, // Inteiro obrigatório, tipo de ordem (1: LIMIT, 2: MARKET, 3: POST_ONLY)
                'price' => 19000.01, // Float obrigatório para tipo LIMIT
                'clientOrderId' => '123123123', // String opcional
                'timeInForce' => 'IOC', // String opcional (GTC, IOC, FOK) default GTC
                'reduceOnly' => 1, // INTEIRO (1: true, 0: false)
            ];
        */
        $clientOrderId = $dados['clientOrderId'] ?? null;
        $symbol = $dados['symbol'] ?? null;
        $price = $dados['price'] ?? null;
        $qty = $dados['qty'] ?? null;
        $tradeSide = $dados['tradeSide'] ?? null;
        $tradeType = $dados['tradeType'] ?? null;
        $timeInForce = $dados['timeInForce'] ?? 'GTC';
        $reduceOnly = $dados['reduceOnly'] ?? 0;

        if (empty($symbol) || empty($tradeSide) || empty($tradeType) || empty($qty)) {
            return [
                'success' => false,
                'message' => 'Os campos symbol, qty, tradeSide e tradeType são obrigatórios.'
            ];
        } elseif (!in_array($tradeSide, [1, 2])) {
            return [
                'success' => false,
                'message' => 'tradeSide inválido. Deve ser 1 (buy) ou 2 (sell).'
            ];
        } elseif (!in_array($tradeType, [1, 2, 3])) {
            return [
                'success' => false,
                'message' => 'tradeType pode ser do tipo 1 (LIMIT), 2 (MARKET) e  3 (POST_ONLY).'
            ];
        } elseif ($tradeType == 1 && empty($price)) {
            return [
                'success' => false,
                'message' => 'price é obrigatório para o tipo  1 (LIMIT).'
            ];
        } elseif (!in_array($timeInForce, ['GTC', 'IOC', 'FOK'])) {
            return [
                'sucess' => false,
                'message' => 'Time in Force pode ser IOC, FOK. default: GTC'
            ];
        }
        $params = array_filter([
            'clientOrderId' => $clientOrderId ?? null,
            'symbol' => $symbol,
            'tradeSide' => $tradeSide,
            'tradeType' => $tradeType,
            'qty' => $qty,
            'price' => $price ?? null,
            'timeInForce' => $timeInForce ?? null,
            'reduceOnly' => $reduceOnly ?? null,
        ]);

        $uri = '/open/futures/order/create/v1';
        $response = $this->apiRequest('POST', $uri, $params);

        if($response['code'] === 0 && !empty($response['orderId'])){
            return [
                'sucess' => true,
                'message' => 'Solicitacao enviada',
                'data' => $response
            ];
        };

        return [
            'sucess' => false,
            'message' => 'Erro ao enviar solicitacao',
            'data' => $response
        ];
    }

    public function getOpenOrdersFuture($symbol, $page = 1, $pageSize = 20)
    {
        $uri = ('/open/futures/order/pending/v1');
        $params = [
            'symbol' => $symbol,
            'page' => $page,
            'pageSize' => $pageSize
        ];
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function cancelOrderFuture($version, $symbol)
    {
        $uri = "/open/futures/order/cancelOpenOrders/{$version}/{$symbol}";
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* WebSocket  Options */
    public function getOrderBook()
    {
        $market = 'spots'; // spots / options / futures

        $response = Http::post('http://localhost:8080/getOrderBook', [
            'market' => $market,
        ]);

        $data = $response->json();

        return $data;
    }
}

