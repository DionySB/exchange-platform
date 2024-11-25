<?php

namespace App\Http\Controllers;

class BinanceController extends Controller
{
    public $transferTypes = [
        1 => 'MAIN_UMFUTURE',
        2 => 'MAIN_CMFUTURE',
        3 => 'MAIN_MARGIN',
        4 => 'UMFUTURE_MAIN',
        5 => 'UMFUTURE_MARGIN',
        6 => 'CMFUTURE_MAIN',
        7 => 'CMFUTURE_MARGIN',
        8 => 'MARGIN_MAIN',
        9 => 'MARGIN_UMFUTURE',
        10 => 'MARGIN_CMFUTURE',
        11 => 'ISOLATEDMARGIN_MARGIN',
        12 => 'MARGIN_ISOLATEDMARGIN',
        13 => 'ISOLATEDMARGIN_ISOLATEDMARGIN',
        14 => 'MAIN_FUNDING',
        15 => 'FUNDING_MAIN',
        16 => 'FUNDING_UMFUTURE',
        17 => 'UMFUTURE_FUNDING',
        18 => 'MARGIN_FUNDING',
        19 => 'FUNDING_MARGIN',
        20 => 'FUNDING_CMFUTURE',
        21 => 'CMFUTURE_FUNDING',
        22 => 'MAIN_OPTION',
        23 => 'OPTION_MAIN',
        24 => 'UMFUTURE_OPTION',
        25 => 'OPTION_UMFUTURE',
        26 => 'MARGIN_OPTION',
        27 => 'OPTION_MARGIN',
        28 => 'FUNDING_OPTION',
        29 => 'OPTION_FUNDING',
        30 => 'MAIN_PORTFOLIO_MARGIN',
        31 => 'PORTFOLIO_MARGIN_MAIN',
        32 => 'MAIN_ISOLATED_MARGIN',
        33 => 'ISOLATED_MARGIN_MAIN',
    ];

    private function apiRequest($method, $uri, $params = [], $signed = true)
    {
        $apiKey = env('BINANCE_API_KEY');
        $secretKey = env('BINANCE_SECRET_KEY');
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 20000;

        if($signed !== false){
            $params['timestamp'] = $timestamp;
            $params['recvWindow'] = $recvWindow;

            $params = array_filter($params, fn($value) => !is_null($value));
            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $secretKey);
            $url = "https://api.binance.com{$uri}?signature={$signature}";
        } else{
            $params = array_filter($params, fn($value) => !is_null($value));
            $queryString = http_build_query($params);
            $url = "https://api.binance.com{$uri}?";
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                "X-MBX-APIKEY: {$apiKey}",
                "Content-Type: application/x-www-form-urlencoded"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($method === 'GET') {
            curl_setopt($curl, CURLOPT_URL, $url . "&" . $queryString);
        } else {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    public function getAccountInfo()
    {
        $uri = '/api/v3/account';
        $response = $this->apiRequest('GET', $uri);
        return $response;
    }

    public function newOrder(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'BTCUSDT',
                'side' => 'BUY', // 'BUY' ou 'SELL'
                'type' => 'LIMIT', // 'LIMIT', 'MARKET', 'STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_LIMIT', 'LIMIT_MAKER'
                'timeInForce' => 'GTC',
                'quantity' => '1.00000',
                // 'quoteOrderQty' => '100.00',
                'price' => '91979.98000000',
                // 'newClientOrderId' => 'teste',
                // 'strategyId' => 0,
                // 'strategyType' => 1000000,
                // 'stopPrice' => '74.0000',
                // 'trailingDelta' => 10,
                // 'icebergQty' => '0.50000',
                // 'newOrderRespType' => 'FULL', // 'ACK', 'RESULT', 'FULL'
                // 'selfTradePreventionMode' => null, // 'EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'

            ];
        */
        if (empty($dados['symbol']) || empty($dados['side']) || empty($dados['type'])) {
            return [
                'success' => false,
                'message' => 'symbol, side e type são obrigatórios.'
            ];
        }

        if ($dados['type'] === 'LIMIT') {
            if (empty($dados['timeInForce']) || empty($dados['quantity']) || empty($dados['price'])) {
                return [
                    'success' => false,
                    'message' => 'LIMIT: timeInForce, quantity e price são obrigatórios.'
                ];
            }
        } elseif ($dados['type'] === 'MARKET') {
            if (empty($dados['quantity']) && empty($dados['quoteOrderQty'])) {
                return [
                    'success' => false,
                    'message' => 'MARKET:  quantity ou quoteOrderQty.'
                ];
            }
            if (isset($dados['quantity']) && isset($dados['quoteOrderQty'])) {
                return [
                    'success' => false,
                    'message' => 'MARKET: apenas quantity ou quoteOrderQty.'
                ];
            }
        } elseif (in_array($dados['type'], ['STOP_LOSS', 'TAKE_PROFIT'])) {
            if (empty($dados['quantity']) || (empty($dados['stopPrice']) && empty($dados['trailingDelta']))) {
                return [
                    'success' => false,
                    'message' => 'STOP_LOSS e TAKE_PROFIT requerem quantity e stopPrice ou trailingDelta.'
                ];
            }
        } elseif (in_array($dados['type'], ['STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT'])) {
            if (empty($dados['timeInForce']) || empty($dados['quantity']) || empty($dados['price']) || (empty($dados['stopPrice']) && empty($dados['trailingDelta']))) {
                return [
                    'success' => false,
                    'message' => 'STOP_LOSS_LIMIT e TAKE_PROFIT_LIMIT requerem timeInForce, quantity, price e stopPrice ou trailingDelta.'
                ];
            }
        } elseif ($dados['type'] === 'LIMIT_MAKER') {
            if (empty($dados['quantity']) || empty($dados['price'])) {
                return [
                    'success' => false,
                    'message' => 'LIMIT_MAKER requer quantity e price.'
                ];
            }
        }

        if (isset($dados['icebergQty'])) {
            if (!in_array($dados['type'], ['LIMIT', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT'])) {
                return [
                    'success' => false,
                    'message' => 'icebergQty pode ser usado apenas com LIMIT, STOP_LOSS_LIMIT, TAKE_PROFIT_LIMIT.'
                ];
            }
            if ($dados['timeInForce'] !== 'GTC') {
                return [
                    'success' => false,
                    'message' => 'icebergQty exige timeInForce "GTC".'
                ];
            }
        }

        if (isset($dados['strategyType']) && $dados['strategyType'] < 1000000) {
            return [
                'success' => false,
                'message' => 'strategyType deve ser maior ou igual a 1000000.'
            ];
        }

        if (isset($dados['timeInForce']) && !in_array($dados['timeInForce'], ['GTC', 'FOK', 'IOC'])) {
            return [
                'success' => false,
                'message' => 'timeInForce deve ser: GTC, FOK, IOC.'
            ];
        }

        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }

        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'type' => $dados['type'],
            'timeInForce' => $dados['timeInForce'] ?? null,
            'quantity' => $dados['quantity'] ?? null,
            'quoteOrderQty' => $dados['quoteOrderQty'] ?? null,
            'price' => $dados['price'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
            'strategyId' => $dados['strategyId'] ?? null,
            'strategyType' => $dados['strategyType'] ?? null,
            'stopPrice' => $dados['stopPrice'] ?? null,
            'trailingDelta' => $dados['trailingDelta'] ?? null,
            'icebergQty' => $dados['icebergQty'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
        ];

        $uri = '/api/v3/order';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Cancel Order (TRADE) */
    public function cancelOrderSpot(array $dados) {
        /*
            $dados = [
                'symbol',
                'orderId',
                'origClientOrderId',
                'newClientOrderId',
                'cancelRestrictions' => '' //ONLY_NEW, ONLY_PARTIALLY_FILLED OR PARTIALLY_FILLED

            ];
        */
        if (empty($dados['orderId']) && empty($dados['origClientOrderId']) && empty($dados['cancelRestrictions'])) {
            return [
                'success' => false,
                'message' => 'Mandatorio cancelRestrictions e origClientOrderId ou orderId.'
            ];
        }
         if(!in_array($dados['cancelRestrictions'], ['ONLY_NEW', 'ONLY_PARTIALLY_FILLED', 'PARTIALLY_FILLED'])){
            return [
                'success' => false,
                'message' => 'cancelRestrictions deve ser ONLY_NEW, ONLY_PARTIALLY_FILLED OR PARTIALLY_FILLED.'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'orderId' => $dados['orderId'] ?? null,
            'origClientOrderId' => $dados['origClientOrderId'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
            'cancelRestrictions' => $dados['cancelRestrictions']
        ];


        $params = array_filter($params);
        $uri = '/api/v3/order';
        return $this->apiRequest('DELETE', $uri, $params, true);
    }

    /* Cancel all Open Orders on a Symbol (TRADE) */
    public function cancelOpenOrdersWithSymbol($symbol)
    {
        $uri = '/api/v3/openOrders';
        $params = [
            'symbol' => $symbol
        ];
        $response = $this->apiRequest('DELETE', $uri, $params);

        return $response;
    }

    /* Query Order (USER_DATA) */
    public function getQueryOrder($symbol, $paramType = '', $id = '')
    {
        $params = [
            'symbol' => $symbol,
            $paramType => $id         //Requer $paramType (order, origClientOrder) + id.
        ];
        $uri = '/api/v3/order';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Cancel an Existing Order and Send a New Order (TRADE) */
    public function cancelAndReplace(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'side' => 'SELL', // 'BUY' ou 'SELL'
                'type' => 'LIMIT', // 'LIMIT', 'MARKET', 'STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_LIMIT', 'LIMIT_MAKER'
                'cancelReplaceMode' => 'STOP_ON_FAILURE', // 'STOP_ON_FAILURE' ou 'ALLOW_FAILURE'
                'quantity' => 1.00000,
                'timeInForce' => 'GTC', // 'GTC', 'FOK', 'IOC'
                'price' => 90.0000000,
                'quoteOrderQty' => null,
                'stopPrice' => null,
                'trailingDelta' => null,
                'cancelNewClientOrderId' => null,
                'cancelOrigClientOrderId' => null,
                'cancelOrderId' => null,
                'newClientOrderId' => null,
                'strategyId' => null,
                'strategyType' => null,
                'icebergQty' => null,
                'newOrderRespType' => 'FULL', // 'ACK', 'RESULT', 'FULL'
                'selfTradePreventionMode' => null, // 'EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'
                'cancelRestrictions' => null, // 'ONLY_NEW', 'ONLY_PARTIALLY_FILLED'
                'orderRateLimitExceededMode' => null, // 'DO_NOTHING', 'CANCEL_ONLY'
            ];
        */
        if (empty($dados['symbol']) || empty($dados['side']) || empty($dados['type']) || empty($dados['cancelReplaceMode'])) {
            return [
                'success' => false,
                'message' => 'Os campos symbol, side, type e cancelReplaceMode são mandatórios.'
            ];
        }
        if (!in_array($dados['cancelReplaceMode'], ['STOP_ON_FAILURE', 'ALLOW_FAILURE'])) {
            return [
                'success' => false,
                'message' => 'cancelReplaceMode deve ser: STOP_ON_FAILURE, ALLOW_FAILURE.'
            ];
        }
        if ($dados['type'] === 'LIMIT') {
            if (empty($dados['quantity']) || empty($dados['price']) || empty($dados['timeInForce'])) {
                return [
                    'success' => false,
                    'message' => 'LIMIT requer: quantity, price e timeInForce.'
                ];
            }
        } elseif ($dados['type'] === 'MARKET') {
            if (empty($dados['quantity']) && empty($dados['quoteOrderQty'])) {
                return [
                    'success' => false,
                    'message' => 'MARKET requer: quantity ou quoteOrderQty.'
                ];
            }
        } elseif (in_array($dados['type'], ['STOP_LOSS', 'TAKE_PROFIT'])) {
            if (empty($dados['quantity']) || (empty($dados['stopPrice']) && empty($dados['trailingDelta']))) {
                return [
                    'success' => false,
                    'message' => "{$dados['type']} requer: quantity e stopPrice ou trailingDelta."
                ];
            }
        } elseif (in_array($dados['type'], ['STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT'])) {
            if (empty($dados['quantity']) || empty($dados['price']) || empty($dados['timeInForce']) || (empty($dados['stopPrice']) && empty($dados['trailingDelta']))) {
                return [
                    'success' => false,
                    'message' => "quantity, price, timeInForce e stopPrice ou trailingDelta são requeridos para este type."
                ];
            }
        } elseif ($dados['type'] === 'LIMIT_MAKER') {
            if (empty($dados['quantity']) || empty($dados['price'])) {
                return [
                    'success' => false,
                    'message' => 'LIMIT_MAKER requer: quantity e price.'
                ];
            }
        }

        if (isset($dados['strategyType']) && $dados['strategyType'] < 1000000) {
            return [
                'success' => false,
                'message' => 'strategyType deve ser maior ou igual a 1000000.'
            ];
        }

        if (isset($dados['timeInForce']) && !in_array($dados['timeInForce'], ['GTC', 'FOK', 'IOC'])) {
            return [
                'success' => false,
                'message' => 'timeInForce deve ser: GTC, FOK, IOC.'
            ];
        }

        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }

        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        if (isset($dados['cancelRestrictions']) && !in_array($dados['cancelRestrictions'], ['ONLY_NEW', 'ONLY_PARTIALLY_FILLED'])) {
            return [
                'success' => false,
                'message' => 'cancelRestrictions deve ser: ONLY_NEW, ONLY_PARTIALLY_FILLED.'
            ];
        }

        if (isset($dados['orderRateLimitExceededMode']) && !in_array($dados['orderRateLimitExceededMode'], ['DO_NOTHING', 'CANCEL_ONLY'])) {
            return [
                'success' => false,
                'message' => 'orderRateLimitExceededMode deve ser: DO_NOTHING, CANCEL_ONLY.'
            ];
        }

        $uri = '/api/v3/order/cancelReplace';
        $params = array_filter([
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'type' => $dados['type'],
            'cancelReplaceMode' => $dados['cancelReplaceMode'],
            'timeInForce' => $dados['timeInForce'] ?? null,
            'quantity' => $dados['quantity'] ?? null,
            'quoteOrderQty' => $dados['quoteOrderQty'] ?? null,
            'price' => $dados['price'] ?? null,
            'cancelNewClientOrderId' => $dados['cancelNewClientOrderId'] ?? null,
            'cancelOrigClientOrderId' => $dados['cancelOrigClientOrderId'] ?? null,
            'cancelOrderId' => $dados['cancelOrderId'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
            'strategyId' => $dados['strategyId'] ?? null,
            'strategyType' => $dados['strategyType'] ?? null,
            'stopPrice' => $dados['stopPrice'] ?? null,
            'trailingDelta' => $dados['trailingDelta'] ?? null,
            'icebergQty' => $dados['icebergQty'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
            'cancelRestrictions' => $dados['cancelRestrictions'] ?? null,
            'orderRateLimitExceededMode' => $dados['orderRateLimitExceededMode'] ?? null,
        ]);

        $response = $this->apiRequest('POST', $uri, $params);
        return $response;
    }

    /* Current Open Orders (USER_DATA) */
    public function getOpenOrders($symbol)
    {
        $params = [
            'symbol' => $symbol,
        ];
        $uri = '/api/v3/openOrders';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* All Orders (USER_DATA) */
    public function getAllOrdersSpot($symbol = '', $orderId=null, $startTime = null, $endTime = null, $limit = 500)
    {
        $uri = '/api/v3/allOrders';
        $params = [
            'symbol' => $symbol,
            'orderId' => $orderId,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit
        ];
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /*New OCO - Deprecated (TRADE) */
    public function newOrderOCO(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'side' => 'SELL', // 'BUY' ou 'SELL'
                'quantity' => 1.00000,
                'price' => 88.00,
                'stopPrice' => 85.00,
                'listClientOrderId' => null,
                'limitClientOrderId' => null,
                'limitStrategyId' => null,
                'limitStrategyType' => null,
                'limitIcebergQty' => null,
                'trailingDelta' => null,
                'stopClientOrderId' => null,
                'stopStrategyId' => null,
                'stopStrategyType' => null,
                'stopLimitPrice' => null,
                'stopIcebergQty' => null,
                'stopLimitTimeInForce' => null, // 'GTC', 'FOK', 'IOC'
                'newOrderRespType' => 'FULL', // 'ACK', 'RESULT', 'FULL'
                'selfTradePreventionMode' => null, // 'EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'
            ];
        */
        if (empty($dados['symbol']) || empty($dados['side']) || empty($dados['quantity']) || empty($dados['price']) || empty($dados['stopPrice'])) {
            return [
                'success' => false,
                'message' => 'symbol, side, quantity, price, stopPrice são mandatórios.'
            ];
        }
        if (!in_array($dados['side'], ['BUY', 'SELL'])) {
            return [
                'success' => false,
                'message' => 'side deve ser: BUY ou SELL.'
            ];
        }
        if (isset($dados['stopLimitPrice']) && !in_array($dados['stopLimitTimeInForce'], ['GTC', 'FOK', 'IOC'])) {
            return [
                'success' => false,
                'message' => 'stopLimitTimeInForce deve ser: GTC, FOK, IOC quando stopLimitPrice existir.'
            ];
        }
        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }
        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        if (isset($dados['limitStrategyType']) && $dados['limitStrategyType'] < 1000000) {
            return [
                'success' => false,
                'message' => 'limitStrategyType deve ser maior ou igual a 1000000.'
            ];
        }

        if (isset($dados['stopStrategyType']) && $dados['stopStrategyType'] < 1000000) {
            return [
                'success' => false,
                'message' => 'stopStrategyType deve ser maior ou igual a 1000000.'
            ];
        }
        $params = [
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'quantity' => $dados['quantity'],
            'price' => $dados['price'],
            'stopPrice' => $dados['stopPrice'],
            'listClientOrderId' => $dados['listClientOrderId'] ?? null,
            'limitClientOrderId' => $dados['limitClientOrderId'] ?? null,
            'limitStrategyId' => $dados['limitStrategyId'] ?? null,
            'limitStrategyType' => $dados['limitStrategyType'] ?? null,
            'limitIcebergQty' => $dados['limitIcebergQty'] ?? null,
            'trailingDelta' => $dados['trailingDelta'] ?? null,
            'stopClientOrderId' => $dados['stopClientOrderId'] ?? null,
            'stopStrategyId' => $dados['stopStrategyId'] ?? null,
            'stopStrategyType' => $dados['stopStrategyType'] ?? null,
            'stopLimitPrice' => $dados['stopLimitPrice'] ?? null,
            'stopIcebergQty' => $dados['stopIcebergQty'] ?? null,
            'stopLimitTimeInForce' => $dados['stopLimitTimeInForce'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null
        ];

        $uri = '/api/v3/order/oco';
        return $this->apiRequest('POST', $uri, $params);
    }

    /* New Order List - OCO (TRADE) */
    public function newOrderListOCO(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'side' => 'SELL', // 'BUY' ou 'SELL'
                'quantity' => 1,
                'aboveType' => 'LIMIT_MAKER', // 'STOP_LOSS_LIMIT', 'STOP_LOSS', 'LIMIT_MAKER'
                'aboveClientOrderId' => 'above123',
                'aboveIcebergQty' => null,
                'abovePrice' => 95.00000000,
                'aboveStopPrice' => null,
                'aboveTrailingDelta' => null,
                'aboveTimeInForce' => null,
                'aboveStrategyId' => null,
                'aboveStrategyType' => null,
                'belowType' => 'STOP_LOSS_LIMIT', // 'STOP_LOSS_LIMIT', 'STOP_LOSS', 'LIMIT_MAKER'
                'belowClientOrderId' => 'below123',
                'belowIcebergQty' => null,
                'belowPrice' => 74.00000000,
                'belowStopPrice' => 80.00000000,
                'belowTrailingDelta' => null,
                'belowTimeInForce' => 'GTC', // 'GTC', 'FOK', 'IOC'
                'belowStrategyId' => null,
                'belowStrategyType' => null,
                'newOrderRespType' => 'FULL', // 'ACK', 'RESULT', 'FULL'
                'selfTradePreventionMode' => null, // 'EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'
            ];
        */
        if (empty($dados['symbol']) || empty($dados['side']) || empty($dados['quantity']) ||
            empty($dados['aboveType']) || empty($dados['belowType'])) {
            return [
                'success' => false,
                'message' => 'symbol, side, quantity, aboveType e belowType são obrigatórios.',
            ];
        }

        if ($dados['aboveType'] !== 'LIMIT_MAKER' && $dados['belowType'] !== 'LIMIT_MAKER') {
            return [
                'success' => false,
                'message' => 'Pelo menos uma das pernas (aboveType ou belowType) deve ser LIMIT_MAKER.',
            ];
        }

        if (empty($dados['abovePrice']) || empty($dados['belowStopPrice']) || empty($dados['belowPrice'])) {
            return [
                'success' => false,
                'message' => 'abovePrice, belowStopPrice e belowPrice são obrigatórios.',
            ];
        }

        if (empty($dados['aboveStopPrice']) && empty($dados['aboveTrailingDelta'])) {
            return [
                'success' => false,
                'message' => 'aboveStopPrice ou aboveTrailingDelta deve ser especificado.'
            ];
        }

        if (isset($dados['aboveStopPrice']) && !in_array($dados['aboveType'], ['STOP_LOSS', 'STOP_LOSS_LIMIT'])) {
            return [
                'success' => false,
                'message' => 'aboveStopPrice é usado quando for STOP_LOSS ou STOP_LOSS_LIMIT.'
            ];
        }

        if (empty($dados['belowStopPrice']) && empty($dados['belowTrailingDelta'])) {
            return [
                'success' => false,
                'message' => 'belowStopPrice ou belowTrailingDelta deve ser especificado.'
            ];
        }

        if (isset($dados['belowStopPrice']) && !in_array($dados['belowType'], ['STOP_LOSS', 'STOP_LOSS_LIMIT'])) {
            return [
                'success' => false,
                'message' => 'belowStopPrice é usado quando for STOP_LOSS ou STOP_LOSS_LIMIT.'
            ];
        }

        if ($dados['belowType'] === 'STOP_LOSS_LIMIT' && empty($dados['belowTimeInForce'])) {
            return [
                'success' => false,
                'message' => ' belowTimeInForce é obrigatório quando belowType for STOP_LOSS_LIMIT.',
            ];
        }

        if($dados['aboveType'] === 'STOP_LOSS_LIMIT' && empty($dados['aboveTimeInForce'])) {
            return [
                'success' => false,
                'message' => ' aboveTimeInForce é obrigatório quando aboveType for STOP_LOSS_LIMIT.',
            ];
        }

        if (!in_array($dados['side'], ['BUY', 'SELL'])) {
            return [
                'success' => false,
                'message' => 'side deve ser: BUY ou SELL.'
            ];
        }

        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }
        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'quantity' => $dados['quantity'],
            'aboveClientOrderId' => $dados['aboveClientOrderId'],
            'aboveIcebergQty' => $dados['aboveIcebergQty'],
            'aboveType' => $dados['aboveType'],
            'abovePrice' => $dados['abovePrice'] ?? null,
            'aboveStopPrice' => $dados['aboveStopPrice'] ?? null,
            'aboveTrailingDelta' => $dados['aboveTrailingDelta'] ?? null,
            'aboveStrategyId' => $dados['aboveStrategyId'] ?? null,
            'aboveStrategyType' =>  $dados['aboveStrategyType'] ?? null,
            'aboveTimeInForce' => $dados['aboveTimeInForce'] ?? null,
            'belowType' => $dados['belowType'],
            'belowPrice' => $dados['belowPrice'] ?? null,
            'belowStopPrice' => $dados['belowStopPrice'] ?? null,
            'belowIcebergQty' => $dados['belowIcebergQty'] ?? null,
            'belowTrailingDelta' => $dados['belowTrailingDelta'] ?? null,
            'belowTimeInForce' => $dados['belowTimeInForce'] ?? null,
            'belowClientOrderId' => $dados['belowClientOrderId'] ?? null,
            'belowStrategyId' => $dados['belowStrategyId'] ?? null,
            'belowStrategyType' => $dados['belowStrategyType'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
        ];

        $uri = '/api/v3/orderList/oco';

        $response = $this->apiRequest('POST', $uri, $params);
        return $response;
    }

    /* New Order List - OTO (TRADE) */
    public function newOrderListOTO(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'listClientOrderId' => null,
                'newOrderRespType' => null,  // 'ACK', 'FULL', 'RESULT'
                'selfTradePreventionMode' => null,
                'workingType' => 'LIMIT',  // 'LIMIT', 'LIMIT_MAKER'
                'workingSide' => 'SELL',  // 'BUY', 'SELL'
                'workingClientOrderId' => null,
                'workingPrice' => 85.00000000,
                'workingQuantity' => 1.0,
                'workingIcebergQty' => null,
                'workingTimeInForce' => 'GTC',  // 'FOK', 'IOC', 'GTC'
                'workingStrategyId' => null,
                'workingStrategyType' => null,
                'pendingType' => 'STOP_LOSS_LIMIT',  // 'LIMIT', 'STOP_LOSS', 'TAKE_PROFIT', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT'
                'pendingSide' => 'SELL',  // 'BUY', 'SELL'
                'pendingClientOrderId' => null,
                'pendingPrice' => 74.00000000,
                'pendingStopPrice' => 75.00000000,
                'pendingTrailingDelta' => null,
                'pendingQuantity' => 1.0,
                'pendingIcebergQty' => null,
                'pendingTimeInForce' => 'GTC',  // 'GTC', 'FOK', 'IOC'
                'pendingStrategyId' => null,
                'pendingStrategyType' => null,
            ];
        */
        if (empty($dados['symbol']) || empty($dados['workingSide']) || empty($dados['workingQuantity']) || empty($dados['workingPrice']) || empty($dados['workingType']) || empty($dados['workingTimeInForce']) || empty($dados['pendingQuantity'])) {
            return [
                'success' => false,
                'message' => 'Campos symbol, workingSide, workingQuantity, workingPrice, workingType, workingTimeInForce e pendingQuantity mandatórios.'
            ];
        }

        if (!in_array($dados['workingType'], ['LIMIT', 'LIMIT_MAKER'])) {
            return [
                'success' => false,
                'message' => 'workingType deve ser: LIMIT ou LIMIT_MAKER.'
            ];
        }

        if (!in_array($dados['workingSide'], ['BUY', 'SELL'])) {
            return [
                'success' => false,
                'message' => 'workingSide deve ser: BUY ou SELL.'
            ];
        }

        if (in_array($dados['pendingType'], ['LIMIT', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT'])) {
            if (empty($dados['pendingPrice']) || empty($dados['pendingTimeInForce'])) {
                return [
                    'success' => false,
                    'message' => 'pendingPrice e pendingTimeInForce são obrigatórios para tipos LIMIT, STOP_LOSS_LIMIT ou TAKE_PROFIT_LIMIT.'
                ];
            }
        }

        if (in_array($dados['pendingType'], ['STOP_LOSS', 'TAKE_PROFIT'])) {
            if (empty($dados['pendingStopPrice']) && empty($dados['pendingTrailingDelta'])) {
                return [
                    'success' => false,
                    'message' => 'pendingStopPrice ou pendingTrailingDelta são obrigatórios para tipos STOP_LOSS ou TAKE_PROFIT.'
                ];
            }
        }

        if (isset($dados['workingIcebergQty']) && !($dados['workingTimeInForce'] === 'GTC' || $dados['workingType'] === 'LIMIT_MAKER')) {
            return [
                'success' => false,
                'message' => 'workingIcebergQty pode ser usado apenas quando workingTimeInForce for GTC ou workingType for LIMIT_MAKER.'
            ];
        }

        if (isset($dados['pendingIcebergQty']) && !($dados['pendingTimeInForce'] === 'GTC' || $dados['pendingType'] === 'LIMIT_MAKER')) {
            return [
                'success' => false,
                'message' => 'pendingIcebergQty pode ser usado apenas quando pendingTimeInForce for GTC ou pendingType for LIMIT_MAKER.'
            ];
        }

        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }

        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'listClientOrderId' => $dados['listClientOrderId'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
            'workingSide' => $dados['workingSide'],
            'workingType' => $dados['workingType'],
            'workingClientOrderId' => $dados['workingClientOrderId'] ?? null,
            'workingPrice' => $dados['workingPrice'],
            'workingQuantity' => $dados['workingQuantity'],
            'workingIcebergQty' => $dados['workingIcebergQty'] ?? null,
            'workingTimeInForce' => $dados['workingTimeInForce'] ?? null,
            'workingStrategyId' => $dados['workingStrategyId'] ?? null,
            'workingStrategyType' => $dados['workingStrategyType'] ?? null,
            'pendingSide' => $dados['pendingSide'],
            'pendingType' => $dados['pendingType'],
            'pendingClientOrderId' => $dados['pendingClientOrderId'] ?? null,
            'pendingPrice' => $dados['pendingPrice'] ?? null,
            'pendingStopPrice' => $dados['pendingStopPrice'] ?? null,
            'pendingTrailingDelta' => $dados['pendingTrailingDelta'] ?? null,
            'pendingQuantity' => $dados['pendingQuantity'],
            'pendingIcebergQty' => $dados['pendingIcebergQty'] ?? null,
            'pendingTimeInForce' => $dados['pendingTimeInForce'] ?? null,
            'pendingStrategyId' => $dados['pendingStrategyId'] ?? null,
            'pendingStrategyType' => $dados['pendingStrategyType'] ?? null,
        ];

        $uri = '/api/v3/orderList/oto';

        return $this->apiRequest('POST', $uri, $params); // Faz a requisição à API
    }

    public function newOrderListOTOCO(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'listClientOrderId' => null,
                'newOrderRespType' => null,  // 'ACK', 'FULL', 'RESPONSE'
                'selfTradePreventionMode' => null,
                'workingType' => 'LIMIT',  // 'LIMIT', 'LIMIT_MAKER'
                'workingSide' => 'SELL',  // 'BUY', 'SELL'
                'workingClientOrderId' => null,
                'workingPrice' => 95.00000000,
                'workingQuantity' => 1.0,
                'workingIcebergQty' => null,
                'workingTimeInForce' => 'GTC',  // 'GTC', 'IOC', 'FOK'
                'workingStrategyId' => null,
                'workingStrategyType' => null,
                'pendingSide' => 'SELL',  // 'BUY', 'SELL'
                'pendingQuantity' => 1.0,
                'pendingAboveType' => 'LIMIT_MAKER',  // 'LIMIT_MAKER', 'STOP_LOSS', 'STOP_LOSS_LIMIT'
                'pendingAboveClientOrderId' => null,
                'pendingAbovePrice' => 74.00000000,
                'pendingAboveStopPrice' => null,
                'pendingAboveTrailingDelta' => null,
                'pendingAboveIcebergQty' => null,
                'pendingAboveTimeInForce' => null,  // 'GTC', 'FOK', 'IOC'
                'pendingAboveStrategyId' => null,
                'pendingAboveStrategyType' => null,
                'pendingBelowType' => 'STOP_LOSS_LIMIT',  // 'LIMIT_MAKER', 'STOP_LOSS', 'STOP_LOSS_LIMIT'
                'pendingBelowClientOrderId' => null,
                'pendingBelowPrice' => 72.00000000,
                'pendingBelowStopPrice' => 70.00000000,
                'pendingBelowTrailingDelta' => null,
                'pendingBelowIcebergQty' => null,
                'pendingBelowTimeInForce' => 'GTC',  // 'GTC', 'FOK', 'IOC'
                'pendingBelowStrategyId' => null,
                'pendingBelowStrategyType' => null,
            ];
        */
        if (empty($dados['symbol']) || empty($dados['workingSide']) || empty($dados['workingPrice']) || empty($dados['workingQuantity']) || empty($dados['workingType'])) {
            return [
                'success' => false,
                'message' => 'symbol, workingSide, workingPrice, workingQuantity, workingType são mandatórios.'
            ];
        }

        if ($dados['workingType'] == 'LIMIT' && empty($dados['workingTimeInForce'])) {
            return [
                'success' => false,
                'message' => 'workingTimeInForce (necessário para workingType = LIMIT).'
            ];
        }

        if (in_array($dados['pendingAboveType'], ['LIMIT_MAKER', 'STOP_LOSS_LIMIT']) && empty($dados['pendingAbovePrice'])) {
            return [
                'success' => false,
                'message' => 'pendingAbovePrice é obrigatório quando pendingAboveType for LIMIT_MAKER ou STOP_LOSS_LIMIT.'
            ];
        }

        if (in_array($dados['pendingAboveType'], ['STOP_LOSS', 'STOP_LOSS_LIMIT']) && (empty($dados['pendingAboveStopPrice']) && empty($dados['pendingAboveTrailingDelta']))) {
            return [
                'success' => false,
                'message' => 'pendingAboveStopPrice ou pendingAboveTrailingDelta são obrigatórios quando pendingAboveType for STOP_LOSS ou STOP_LOSS_LIMIT.'
            ];
        }

        if (in_array($dados['pendingBelowType'], ['LIMIT_MAKER', 'STOP_LOSS_LIMIT']) && empty($dados['pendingBelowPrice'])) {
            return [
                'success' => false,
                'message' => 'pendingBelowPrice é obrigatório quando pendingBelowType for LIMIT_MAKER ou STOP_LOSS_LIMIT.'
            ];
        }

        if (in_array($dados['pendingBelowType'], ['STOP_LOSS', 'STOP_LOSS_LIMIT']) && (empty($dados['pendingBelowStopPrice']) && empty($dados['pendingBelowTrailingDelta']))) {
            return [
                'success' => false,
                'message' => 'pendingBelowStopPrice ou pendingBelowTrailingDelta são obrigatórios quando pendingBelowType for STOP_LOSS ou STOP_LOSS_LIMIT.'
            ];
        }

        if (isset($dados['workingIcebergQty']) && !($dados['workingTimeInForce'] === 'GTC' || $dados['workingType'] === 'LIMIT_MAKER')) {
            return [
                'success' => false,
                'message' => 'workingIcebergQty pode ser usado apenas quando workingTimeInForce for GTC ou workingType for LIMIT_MAKER.'
            ];
        }

        if (isset($dados['pendingAboveIcebergQty']) && !($dados['pendingAboveTimeInForce'] === 'GTC' || $dados['pendingAboveType'] === 'LIMIT_MAKER')) {
            return [
                'success' => false,
                'message' => 'pendingAboveTimeInForce: GTC ou pendingAboveType: LIMIT_MAKER para pendingAboveIcebergQty'
            ];
        }

        if (isset($dados['pendingBelowIcebergQty']) && !($dados['pendingBelowTimeInForce'] === 'GTC' || $dados['pendingBelowType'] === 'LIMIT_MAKER')) {
            return [
                'success' => false,
                'message' => 'pendingBelowTimeInForce: GTC ou pendingBelowType: LIMIT_MAKER para pendingBelowIcebergQty'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'listClientOrderId' => $dados['listClientOrderId'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
            'workingSide' => $dados['workingSide'],
            'workingType' => $dados['workingType'],
            'workingQuantity' => $dados['workingQuantity'],
            'workingPrice' => $dados['workingPrice'],
            'workingTimeInForce' => $dados['workingTimeInForce'] ?? null,
            'workingStrategyId' => $dados['workingStrategyId'] ?? null,
            'workingStrategyType' => $dados['workingStrategyType'] ?? null,
            'workingClientOrderId' => $dados['workingClientOrderId'] ?? null,
            'pendingSide' => $dados['pendingSide'],
            'pendingQuantity' => $dados['pendingQuantity'],
            'pendingAboveType' => $dados['pendingAboveType'],
            'pendingAbovePrice' => $dados['pendingAbovePrice'] ?? null,
            'pendingAboveStopPrice' => $dados['pendingAboveStopPrice'] ?? null,
            'pendingAboveTrailingDelta' => $dados['pendingAboveTrailingDelta'] ?? null,
            'pendingAboveIcebergQty' => isset($dados['pendingAboveIcebergQty']) ? $dados['pendingAboveIcebergQty'] : null,
            'pendingAboveTimeInForce' => $dados['pendingAboveTimeInForce'] ?? null,
            'pendingAboveStrategyId' => $dados['pendingAboveStrategyId'] ?? null,
            'pendingAboveStrategyType' => $dados['pendingAboveStrategyType'] ?? null,
            'pendingBelowType' => $dados['pendingBelowType'],
            'pendingBelowPrice' => $dados['pendingBelowPrice'] ?? null,
            'pendingBelowStopPrice' => $dados['pendingBelowStopPrice'] ?? null,
            'pendingBelowTrailingDelta' => $dados['pendingBelowTrailingDelta'] ?? null,
            'pendingBelowIcebergQty' => isset($dados['pendingBelowIcebergQty']) ? $dados['pendingBelowIcebergQty'] : null,
            'pendingBelowTimeInForce' => $dados['pendingBelowTimeInForce'] ?? null,
            'pendingBelowStrategyId' => $dados['pendingBelowStrategyId'] ?? null,
            'pendingBelowStrategyType' => $dados['pendingBelowStrategyType'] ?? null,
        ];

        $uri = '/api/v3/orderList/otoco';

        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Cancel Order lists (TRADE) */
    public function cancelOrderList(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'orderListId' => '4391',
                'newClientOrderId' => null,
                'newClientOrderId' => '111111'
            ];
        */

        if (empty($dados['symbol']) || (empty($dados['orderListId']) && empty($dados['listClientOrderId']))) {
            return [
                'success' => false,
                'message' => 'Symbol é obrigatório, e deve ao menos ter orderListId ou listClientOrderId'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'orderListId' => $dados['orderListId'] ?? null,
            'listClientOrderId' => $dados['listClientOrderId'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
        ];

        $uri = '/api/v3/orderList';

        return $this->apiRequest('DELETE', $uri, $params);
    }

    /* Query Order lists (USER_DATA) */
    public function getQueryOrderList($paramType = 'orderListId', $id = '')
    {
        $params = [
            $paramType => $id         //Requer $paramType (order, origClientOrder) + id.
        ];
        $uri = '/api/v3/orderList';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Query all Order lists (USER_DATA) */
    public function getQueryAllOrderLists($fromId = null, $startTime = null, $endTime = null, $limit = 500)
    {
        $params = [
            'fromId' => $fromId,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit
        ];
        $uri = '/api/v3/allOrderList';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Query Open Order lists (USER_DATA) */
    public function getQueryOpenOrderLists()
    {
        $uri = '/api/v3/openOrderList';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* New order using SOR (TRADE) */
    public function newOrderSOR(array $dados)
    {
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'side' => 'SELL',
                'type' => 'LIMIT',
                'quantity' => 1,
                'price' => 85.00,
                'timeInForce' => 'GTC',
                'newClientOrderId' => 'uniqueOrder123',
                'strategyId' => 1000001,
                'strategyType' => 1000002,
                'icebergQty' => 0.5,
                'newOrderRespType' => 'FULL', // ACK, RESULT, or FULL. Default to FULL
                'selfTradePreventionMode' => 'EXPIRE_MAKER', //EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE
                'recvWindow' => 50000,
            ];
        */
        if (empty($dados['symbol']) || empty($dados['side']) || empty($dados['type']) || empty($dados['quantity'])) {
            return [
                'success' => false,
                'message' => 'Parâmetros obrigatórios ausentes: symbol, side, type, quantity.'
            ];
        }

        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }

        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        if(isset($dados['icebergQty']) && $dados['type'] !== 'LIMIT') {
            return [
                'success' => false,
                'message' => 'icebergQty apenas para LIMIT'
            ];
        }

        if($dados['type'] === 'LIMIT' && !in_array($dados['timeInForce'], ['GTC', 'FOK', 'IOC'])) {
            return [
                'success' => false,
                'message' => 'timeInForce deve ser: GTC, FOK ou IOC'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'type' => $dados['type'],
            'quantity' => $dados['quantity'],
            'price' => $dados['price'] ?? null,
            'timeInForce' => $dados['timeInForce'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
            'strategyId' => $dados['strategyId'] ?? null,
            'strategyType' => $dados['strategyType'] ?? null,
            'icebergQty' => $dados['icebergQty'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'],
        ];

        $uri = '/api/v3/order';
        return $this->apiRequest('POST', $uri, $params);
    }

    public function testNewOrder(array $dados)
    {
        $uri = '/api/v3/order/test';
        $computeCommissionRates = 'true'; //Default Null. Caso não seja enviado, a Binance interpreta como false.
        /*
            $dados = [
                'symbol' => 'BTCUSDT',
                'side' => 'SELL', // 'BUY' ou 'SELL'
                'type' => 'LIMIT', // 'LIMIT', 'MARKET', 'STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_LIMIT', 'LIMIT_MAKER'
                'timeInForce' => 'GTC',
                'quantity' => '1.00000',
                // 'quoteOrderQty' => '100.00',
                'price' => '91979.98000000',
                // 'newClientOrderId' => 'teste',
                // 'strategyId' => 0,
                // 'strategyType' => 1000000,
                // 'stopPrice' => '74.0000',
                // 'trailingDelta' => 10,
                // 'icebergQty' => '0.50000',
                // 'newOrderRespType' => 'FULL', // 'ACK', 'RESULT', 'FULL'
                // 'selfTradePreventionMode' => null, // 'EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'

            ];
        */

        $params = [
            'computeCommissionRates' => $computeCommissionRates,
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'type' => $dados['type'],
            'timeInForce' => $dados['timeInForce'] ?? null,
            'quantity' => $dados['quantity'] ?? null,
            'quoteOrderQty' => $dados['quoteOrderQty'] ?? null,
            'price' => $dados['price'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
            'strategyId' => $dados['strategyId'] ?? null,
            'strategyType' => $dados['strategyType'] ?? null,
            'stopPrice' => $dados['stopPrice'] ?? null,
            'trailingDelta' => $dados['trailingDelta'] ?? null,
            'icebergQty' => $dados['icebergQty'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
        ];

        $response = $this->apiRequest('POST', $uri, $params);
        return $response;
    }

    public function testNewOrderSOR(array $dados)
    {
        $computeCommissionRates = 'true';
        /*
            $dados = [
                'symbol' => 'LTCUSDT',
                'side' => 'SELL',
                'type' => 'LIMIT',
                'quantity' => 1,
                'price' => 86.00,
                'timeInForce' => 'FOK',
                // 'newClientOrderId' => 'uniqueOrder123',
                // 'strategyId' => 1000001,
                // 'strategyType' => 1000002,
                // 'icebergQty' => 0.5,
                // 'newOrderRespType' => 'FULL', // ACK, RESULT, or FULL. Default to FULL
                // 'selfTradePreventionMode' => 'EXPIRE_MAKER', //EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE
                // 'recvWindow' => 50000,
            ];
        */
        if (empty($dados['symbol']) || empty($dados['side']) || empty($dados['type']) || empty($dados['quantity'])) {
            return [
                'success' => false,
                'message' => 'Parâmetros obrigatórios ausentes: symbol, side, type, quantity.'
            ];
        }

        if (isset($dados['newOrderRespType']) && !in_array($dados['newOrderRespType'], ['ACK', 'RESULT', 'FULL'])) {
            return [
                'success' => false,
                'message' => 'newOrderRespType deve ser: ACK, RESULT, FULL.'
            ];
        }

        if (isset($dados['selfTradePreventionMode']) && !in_array($dados['selfTradePreventionMode'], ['EXPIRE_TAKER', 'EXPIRE_MAKER', 'EXPIRE_BOTH', 'NONE'])) {
            return [
                'success' => false,
                'message' => 'selfTradePreventionMode deve ser: EXPIRE_TAKER, EXPIRE_MAKER, EXPIRE_BOTH, NONE.'
            ];
        }

        if(isset($dados['icebergQty']) && $dados['type'] !== 'LIMIT') {
            return [
                'success' => false,
                'message' => 'icebergQty apenas para LIMIT.'
            ];
        }

        if($dados['type'] === 'LIMIT' && !isset($dados['timeInForce'])) {
            return [
                'success' => false,
                'message' => 'timeInForce deve ser: GTC, FOK ou IOC.'
            ];
        }

        $params = [
            'computeCommissionRates' =>  $computeCommissionRates,
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'type' => $dados['type'],
            'quantity' => $dados['quantity'],
            'price' => $dados['price'] ?? null,
            'timeInForce' => $dados['timeInForce'] ?? null,
            'newClientOrderId' => $dados['newClientOrderId'] ?? null,
            'strategyId' => $dados['strategyId'] ?? null,
            'strategyType' => $dados['strategyType'] ?? null,
            'icebergQty' => $dados['icebergQty'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
        ];

        $uri = '/api/v3/sor/order/test';
        return $this->apiRequest('POST', $uri, $params);
    }

    /* Market Data Endpoints */
    public function testConectivity()
    {
        $uri = '/api/v3/ping';
        $params = [];
        $response = $this->apiRequest('GET', $uri, $params, false);
        return $response;
    }

    public function getOrderBook($symbol, $limit = null)
    {
        if(!$symbol){
            return [
                'success' => false,
                'message' => 'symbol deve ser mandatório.'
            ];
        }
        $params = [
            'symbol' => $symbol,
            'limit' => $limit ?? 100
        ];


        $uri = '/api/v3/depth';
        $response = $this->apiRequest('GET', $uri, $params, false);

        return $response;
    }
    /* Wallet Endpoints */

    /* System Status (System) */
    public function getSystemStatus()
    {
        $uri = '/sapi/v1/system/status';

        $response = $this->apiRequest('GET', $uri, false);

        return $response;
    }

    /* All Coins' Information (USER_DATA) */
    public function infoCoins()
    {
        $uri = '/sapi/v1/capital/config/getall';

        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* Daily Account Snapshot (USER_DATA) */
    public function getDailyAccountSnapshat($type, $startTime = null, $endTime = null, $limit = 7)
    {
        $uri = '/sapi/v1/accountSnapshot';

        $params = [
            'type' => $type, //'SPOT' 'MARGIN' ou 'FUTURE'
            'startTime' => $startTime, //Default 7 dias.
            'endTime' => $endTime, //Default 7 dias.
            'limit' => $limit
        ];
        if(!in_array($params['type'], ['SPOT', 'MARGIN', 'FUTURE'])){
            return [
                'success' => false,
                'message' => 'limit deve ser maior que 7 ou menor que 30 dias.'
            ];
        }
        if($params['limit'] < 7 || $params['limit'] > 30){
            return [
                'success' => false,
                'message' => 'limit deve ser maior que 7 ou menor que 30 dias.'
            ];
        }
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Disable Fast Withdraw Switch (USER_DATA) */
    public function disableFastWithdraw()
    {
        $uri = '/sapi/v1/account/disableFastWithdrawSwitch';

        $response = $this->apiRequest('POST', $uri);

        return $response;
    }

    /* Enable Fast Withdraw Switch (USER_DATA) */
    public function enableFastWithdraw()
    {
        $uri = '/sapi/v1/account/enableFastWithdrawSwitch';

        $response = $this->apiRequest('POST', $uri);

        return $response;
    }

    /* Withdraw(USER_DATA) */
    public function withdraw(array $dados)
    {
        /*
            $dados = [
                'coin' => 'BTC',
                'withdrawOrderId => null,
                'address' => '',
                'addressTag' => '',
                'amount' => '',
                'transactionFeeFlag' => //'true' ou 'false',
                'name' => '',
                'walletType' => '0' // 0-1
            ];
        */

        if (empty($dados['coin']) || empty($dados['address']) || empty($dados['amount'])) {
            return [
                'success' => false,
                'message' => 'coin, address e amount são obrigatórios.'
            ];
        }

        $params = [
            'coin' => $dados['coin'],
            'withdrawOrderId' => $dados['withdrawOrderId'] ?? null,
            'address' => $dados['address'],
            'addressTag' => $dados['addressTag'] ?? null,
            'amount' => $dados['amount'],
            'transactionFeeFlag' => $dados['transactionFeeFlag'] ?? null,
            'name' => $dados['name'] ?? null,
            'walletType' => $dados['walletType'] ?? null,
        ];
        $uri = '/sapi/v1/capital/withdraw/apply';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Deposit History (supporting network) (USER_DATA) */
    public function getDepositHistory(array $dados)
    {
        /*
            $data = [
                'includeSource' => 'BTC',
                'coin' => '',
                'status' => '' // 0:Email Sent / 1:  Cancelled / 2: Awaiting Approval / 3: Rejected // 4: Processing / 5: Failure / 6: Completed
                'offset' => null, //Default 0
                'limit' => '1000' //Default 1000
                'startTime' => '', //Default 90 dias
                'endTime' => '' Default Current Time
            ];
        */
        $params = [
            'coin' => $dados['coin'] ?? null,
            'withdrawOrderId' => $dados['withdrawOrderId'] ?? null,
            'status' => $dados['status'] ?? null,
            'offset' => $dados['offset'] ?? null,
            'limit' => $dados['limit'] ?? null,
            'startTime' => $dados['startTime'] ?? null,
            'endTime' => $dados['endTime'] ?? null
        ];

        $uri = '/sapi/v1/capital/deposit/hisrec';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Withdraw History (supporting network) (USER_DATA) */
    public function getWithdrawHistory(array $dados)
    {
        /*
            $dados = [
                'coin' => $dados['coin'],
                'withdrawOrderId' => '',
                'status' => '' // 0:Email Sent / 1:  Cancelled / 2: Awaiting Approval / 3: Rejected // 4: Processing / 5: Failure / 6: Completed
                'offset' => null,
                'limit' => '1000' //Default 1000
                'startTime' => '', //Default 90 dias
                'endTime' => '' Default Current Time
            ];
        */

        $params = [
            'coin' => $dados['coin'] ?? null,
            'withdrawOrderId' => $dados['withdrawOrderId'] ?? null,
            'status' => $dados['status'] ?? null,
            'offset' => $dados['offset'] ?? null,
            'limit' => $dados['limit'] ?? null,
            'startTime' => $dados['startTime'] ?? null,
            'endTime' => $dados['endTime'] ?? null
        ];
        $uri = '/sapi/v1/capital/withdraw/history';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Deposit Address (supporting network) (USER_DATA) */
    public function getDepositAddress($coin = '', $network = null, $amount = null)
    {
        $params = [
            'coin' => $coin,
            'network' => $network,
            'amount' => $amount
        ];

        if(!$coin){
            return [
                'success' => false,
                'message' => 'coin mandatório.'
            ];
        }
        $uri = '/sapi/v1/capital/deposit/address';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Account Status (USER_DATA) */
    public function getAccountStatus()
    {
        $uri = '/sapi/v1/account/status';

        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* Account API Trading Status (USER_DATA) */
    public function getAccountApiTradingStatus()
    {
        $uri = '/sapi/v1/account/apiTradingStatus';

        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* DustLog(USER_DATA) */
    public function getDustLog($accountType = null, $startTime = null, $endTime = null)
    {

        $params = [
            'accountType' => $accountType,
            'startTime' => $startTime,
            'endTime' => $endTime
        ];

        if(!in_array($accountType, ['SPOT', 'MARGIN', null])){
            return [
                'success' => false,
                'message' => 'accountType deve ser MARGIN ou SPOT.'
            ];
        }
        $uri = '/sapi/v1/asset/dribblet';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Get Assets That Can Be Converted Into BNB (USER_DATA) */
    public function getAssetCanConvertedBNB($accountType = null)
    {

        $params = [
            'accountType' => $accountType,
        ];

        if(!in_array($accountType, ['SPOT', 'MARGIN', null])){
            return [
                'success' => false,
                'message' => 'accountType deve ser MARGIN ou SPOT.'
            ];
        }
        $uri = '/sapi/v1/asset/dust-btc';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Dust Transfer (USER_DATA) */
    public function convertDustAssetsBNB(array $assets, $accountType = null)
    {
        if (empty($assets)) {
            return [
                'success' => false,
                'message' => 'asset deve conter pelo menos um ativo.'
            ];
        }

        $params = [
            'asset' => implode(',', $assets),
            'accountType' => $accountType,
        ];
        $uri = '/sapi/v1/asset/dust';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Asset Dividend Record (USER_DATA) */
    public function getDividendRecord($asset = null, $startTime = null, $endTime = null, $limit = 20)
    {
        $params = [
        'asset' => $asset,
        'startTime' => $startTime,
        'endTime' => $endTime,
        'limit' => $limit ?? 20 // Default 20
        ];

        if($limit > 500){
            return [
                'success' => false,
                'message' => 'limit máximo 500.'
            ];
        }
        $uri = '/sapi/v1/asset/assetDividend';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Asset Detail (USER_DATA) */
    public function getAssetDetail($asset = null)
    {
        $params = [
            'asset' => $asset
        ];
        $uri = '/sapi/v1/asset/assetDetail';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Trade Fee (USER_DATA) */
    public function getTradeFee($symbol = null)
    {
        $params = [
         'symbol' => $symbol
        ];

        $uri = '/sapi/v1/asset/tradeFee';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* User Universal Transfer (USER_DATA) */
    public function createUserUniversalTransfer(array $dados)
    {
        /*
            $dados = [
                'type' => '11', // Índice do tipo de transferência
                'asset' => 'BTC',
                'amount' => 0.01,
                'fromSymbol' => '',
                'toSymbol' => null,
            ];
        */

        if (empty($dados['type']) || empty($dados['asset']) || empty($dados['amount'])) {
            return [
                'success' => false,
                'message' => 'type, asset e amount são mandatórios.',
            ];
        }

        if (!isset($this->transferTypes[$dados['type']])) {
            return [
                'success' => false,
                'message' => 'type não é válido.',
            ];
        }

        $type = $this->transferTypes[$dados['type']];

        if (in_array($type, ['ISOLATEDMARGIN_MARGIN', 'ISOLATEDMARGIN_ISOLATEDMARGIN']) && !isset($dados['fromSymbol'])) {
            return [
                'success' => false,
                'message' => 'fromSymbol é exigido quando type é ISOLATEDMARGIN_MARGIN ou ISOLATEDMARGIN_ISOLATEDMARGIN',
            ];
        }

        if (in_array($type, ['MARGIN_ISOLATEDMARGIN', 'ISOLATEDMARGIN_ISOLATEDMARGIN']) && !isset($dados['toSymbol'])) {
            return [
                'success' => false,
                'message' => 'toSymbol é exigido quando type é MARGIN_ISOLATEDMARGIN ou ISOLATEDMARGIN_ISOLATEDMARGIN',
            ];
        }

        $params = [
            'type' => $type,
            'asset' => $dados['asset'],
            'amount' => $dados['amount'],
            'fromSymbol' => $dados['fromSymbol'] ?? null,
            'toSymbol' => $dados['toSymbol'] ?? null,
        ];

        $uri = '/sapi/v1/asset/transfer';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Query User Universal Transfer History (USER_DATA) */
    public function getQueryUserUniversalTransferHistory(array $dados)
    {
        /*
            $dados = [
                'type' => '',
                'startTime' => null,
                'endTime' => null,
                'current' => null,
                'size' => null,
                'fromSymbol' => null,
                'toSymbol' => null,
            ];
        */

        if (empty($dados['type'])){
            return [
                'success' => false,
                'message' => 'type é mandatório.'
            ];
        }

        if($dados['size'] > 100){
            return [
                'success' => false,
                'message' => 'size deve ser igual ou menor que 100.',
            ];
        }
        if (!isset($this->transferTypes[$dados['type']])) {
            return [
                'success' => false,
                'message' => 'O tipo de transferência especificado não é válido.',
            ];
        }

        $type = $this->transferTypes[$dados['type']];
        if (in_array($type, ['ISOLATEDMARGIN_MARGIN', 'ISOLATEDMARGIN_ISOLATEDMARGIN']) && !isset($dados['fromSymbol'])) {
            return [
                'success' => false,
                'message' => 'fromSymbol é exigido quando type é ISOLATEDMARGIN_MARGIN ou ISOLATEDMARGIN_ISOLATEDMARGIN',
            ];
        }

        if (in_array($type, ['MARGIN_ISOLATEDMARGIN', 'ISOLATEDMARGIN_ISOLATEDMARGIN']) && !isset($dados['toSymbol'])) {
            return [
                'success' => false,
                'message' => 'toSymbol é exigido quando type é MARGIN_ISOLATEDMARGIN ou ISOLATEDMARGIN_ISOLATEDMARGIN',
            ];
        }

        $params = [
            'type' => $type,
            'startTime' => $dados['startTime'] ?? null,
            'endTime' => $dados['endTime'] ?? null,
            'current' => $dados['current'] ?? null,
            'size' => $dados['size'] ?? null,
            'fromSymbol' => $dados['fromSymbol'] ?? null,
            'toSymbol' => $dados['toSymbol'] ?? null,
        ];
        $uri = '/sapi/v1/asset/transfer';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Funding Wallet (USER_DATA) */
    public function newFundingWallet($asset = null, $needBtcValuation = null)
    {
        $params = [
            'asset' => $asset,
            'needBtcValuation' => $needBtcValuation //true ou false - default false
        ];

        $uri = '/sapi/v1/asset/get-funding-asset';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* User Asset (USER_DATA) */
    public function getUserAsset($asset = null, $needBtcValuation = null)
    {
        $params = [
            'asset' => $asset,
            'needBtcValuation' => $needBtcValuation //true ou false
        ];

        $uri = '/sapi/v3/asset/getUserAsset';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Get Cloud-Mining payment and refund history (USER_DATA) */
    public function getCloudMiningAndRefundHistory(array $dados)
    {
        /*
            $dados = [
                'tranId' => null,
                'clientTranId' => null,
                'asset' => null,
                'startTime' => '',
                'endTime' => '',
                'current => '1' // Default > 1
                'size' => '10' // Default 10  < 100
            ];
        */

        if(empty($dados['startTime']) || empty($dados['endTime'])){
            return [
                'success' => false,
                'message' => 'startTime & endTime requeridos em formato UNIT.'
            ];
        }

        if(isset($dados['size']) > 100){
            return [
                'success' => false,
                'message' => 'Máximo de 100 para size.'
            ];
        }

        $params = [
            'tranId' => $dados['tranId'] ?? null,
            'clientTranId' => $dados['clientTranId'] ?? null,
            'asset' => $dados['asset'] ?? null,
            'startTime' => $dados['startTime'],
            'endTime' => $dados['endTime'],
            'current' => $dados['current'] ?? null,
            'size' => $dados['size'] ?? null
        ];
        $uri = '/sapi/v1/asset/ledger-transfer/cloud-mining/queryByPage';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Get API Key Permission (USER_DATA) */
    public function getKeyPermission()
    {
        $uri = '/sapi/v1/account/apiRestrictions';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* Query auto-converting stable coins (USER_DATA) */
    public function getAutoConvertingStableCoin()
    {
        $uri = '/sapi/v1/capital/contract/convertible-coins';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* One click arrival deposit apply (for expired address deposit) (USER_DATA) */
    public function oneClickDepositApply(array $dados = [])
    {
        /*
            $dados = [
                'depositId' => null,
                'txId' => null,
                'subAccountId' => null,
                'subUserId' => null,
            ];
        */
        $params = [
            'depositId' => $dados['depositId'] ?? null,
            'txId' => $dados['txId'] ?? null,
            'subAccountId' => $dados['subAccountId'] ?? null,
            'subUserId' => $dados['subUserId'] ?? null
        ];

        $uri = '/sapi/v1/capital/deposit/credit-apply';
        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Fetch deposit address list with network(USER_DATA) */
    public function fetchDepositAddressList($coin = '', $network = null)
    {
        $params = [
            'coin' => $coin,
            'network' => $network
        ];

        if (empty($coin)) {
            return [
                'success' => false,
                'message' => 'O campo coin é obrigatório.'
            ];
        }

        $uri = '/sapi/v1/capital/deposit/address/list';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Query User Wallet Balance (USER_DATA) */
    public function queryUserWalletBalance()
    {
        $uri = '/sapi/v1/asset/wallet/balance';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* Query User Delegation History(For Master Account)(USER_DATA) */
    public function queryDelegationHistory(array $dados)
    {
        /*
            $dados = [
                'email' => '',
                'startTime => '',
                'endTime' => '',
                'type' => null,
                'asset' => null,
                'current' => null,
                'size' = null
            ];
        */
        if (empty($dados['email']) || empty($dados['startTime']) || empty($dados['endTime'])) {
            return ['success' => false, 'message' => 'email, startTime e endTime são obrigatórios.'];
        }

        if (isset($dados['size']) > 100) {
            return ['success' => false, 'message' => 'O tamanho máximo permitido para size é 100.'];
        }

        $params = [
            'email' => $dados['email'],
            'startTime' => $dados['startTime'],
            'endTime' => $dados['endTime'],
            'type' => $dados['type'] ?? null,
            'asset' => $dados['asset'] ?? null,
            'current' => $dados['current'] ?? null,
            'size' => $dados['size'] ?? null
        ];
        $uri = '/sapi/v1/asset/custody/transfer-history';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    /* Get symbols delist schedule for spot (MARKET_DATA) */
    public function getSymbolsDelistScheduleSpot()
    {
        $uri = '/sapi/v1/spot/delist-schedule';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /*Fetch withdraw address list (USER_DATA) */
    public function getWithdrawAddressList()
    {
        $uri = '/sapi/v1/capital/withdraw/address/list';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }

    /* Account info (USER_DATA) */
    public function getWalletAccountInfo()
    {
        $uri = '/sapi/v1/account/info';
        $response = $this->apiRequest('GET', $uri);

        return $response;
    }
}
