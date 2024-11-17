<?php

namespace App\Http\Controllers;

class BinanceController extends Controller
{

    private function apiRequest($method, $uri, $params = [])
    {
        $apiKey = env('BINANCE_API_KEY');
        $secretKey = env('BINANCE_SECRET_KEY');
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        $params['timestamp'] = $timestamp;
        $params['recvWindow'] = $recvWindow;
        $params = array_filter($params, fn($value) => !is_null($value));
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $url = "https://api.binance.com{$uri}?signature={$signature}";

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

    public function newOrder( array $dados)
    {
        $uri = '/api/v3/order';
        if(empty($dados['symbol']) && empty($dados['side']) && empty($dados['type'])){
            return [
                'success' => false,
                'message' => 'Os campos symbol, side e type sao obrigatorios.'
            ];
        }

        if ($dados['type'] === 'LIMIT') {
            if (empty($dados['timeInForce']) || empty($dados['quantity']) || empty($dados['price'])) {
                return [
                    'success' => false,
                    'message' => 'Para ordens LIMIT, os campos timeInForce, quantity e price são obrigatórios.'
                ];
            }
        } elseif ($dados['type'] === 'MARKET') {
            if (empty($dados['quantity']) && empty($dados['quoteOrderQty'])) {
                return [
                    'success' => false,
                    'message' => 'MARKET, requer quantity ou quoteOrderQty.'
                ];
            }
        } elseif ($dados['type'] === 'STOP_LOSS' || $dados['type'] === 'TAKE_PROFIT') {
            if (empty($dados['quantity']) || (empty($dados['stopPrice']) && empty($dados['trailingDelta']))) {
                return [
                    'success' => false,
                    'message' => 'STOP_LOSS e TAKE_PROFIT, requer quantity e stopPrice ou trailingDelta.'
                ];
            }
        } elseif ($dados['type'] === 'STOP_LOSS_LIMIT' || $dados['type'] === 'TAKE_PROFIT_LIMIT') {
            if (empty($dados['timeInForce']) || empty($dados['quantity']) || empty($dados['price']) || (empty($dados['stopPrice']) && empty($dados['trailingDelta']))) {
                return [
                    'success' => false,
                    'message' => 'STOP_LOSS_LIMIT e TAKE_PROFIT_LIMIT requer timeInForce, quantity, price e stopPrice ou trailingDelta.'
                ];
            }
        } elseif ($dados['type'] === 'LIMIT_MAKER') {
            if (empty($dados['quantity']) || empty($dados['price'])) {
                return [
                    'success' => false,
                    'message' => 'LIMIT_MAKER, requer quantity e price.'
                ];
            }
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

        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Cancel Order (TRADE) */
    public function cancelOrderSpot(array $dados) {
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
    public function cancelAndReplace()
    {
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
                    'message' => "{$dados['type']} requer: quantity, price, timeInForce e stopPrice ou trailingDelta."
                ];
            }
        } elseif ($dados['type'] === 'LIMIT_MAKER') {
            if (empty($dados['quantity']) || empty($dados['price'])) {
                return [
                    'success' => false,
                    'message' => 'LIMIT_MAKER requer: quantity e price.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'type inválido. Deve ser: LIMIT, MARKET, STOP_LOSS, STOP_LOSS_LIMIT, TAKE_PROFIT, TAKE_PROFIT_LIMIT, LIMIT_MAKER.'
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
    public function getAllOrdersSpot($symbol ='LTCUSDT', $orderId=null, $startTime = null, $endTime = null, $limit = 500)
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
    public function newOrderOCO(/*array $dados */) {
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
        if (isset($dados['stopLimitTimeInForce']) && isset($dados['stopLimitPrice']) && !in_array($dados['stopLimitTimeInForce'], ['GTC', 'FOK', 'IOC'])) {
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

    public function newOrderListOCO(/* array $dados */)
    {
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

        if ($dados['belowType'] === 'STOP_LOSS_LIMIT' && empty($dados['belowTimeInForce'])) {
            return [
                'success' => false,
                'message' => ' belowTimeInForce é obrigatório quando belowType for STOP_LOSS_LIMIT.',
            ];
        }

        if (!in_array($dados['side'], ['BUY', 'SELL'])) {
            return [
                'success' => false,
                'message' => 'side deve ser: BUY ou SELL.'
            ];
        }

        if (isset($dados['stopLimitTimeInForce']) && isset($dados['stopLimitPrice']) && !in_array($dados['stopLimitTimeInForce'], ['GTC', 'FOK', 'IOC'])) {
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

        $params = [
            'symbol' => $dados['symbol'],
            'side' => $dados['side'],
            'quantity' => $dados['quantity'],
            'aboveType' => $dados['aboveType'],
            'abovePrice' => $dados['abovePrice'] ?? null,
            'aboveStopPrice' => $dados['aboveStopPrice'] ?? null,
            'aboveTrailingDelta' => $dados['aboveTrailingDelta'] ?? null,
            'aboveTimeInForce' => $dados['aboveTimeInForce'] ?? null,
            'aboveClientOrderId' => $dados['aboveClientOrderId'] ?? null,
            'belowType' => $dados['belowType'],
            'belowPrice' => $dados['belowPrice'] ?? null,
            'belowStopPrice' => $dados['belowStopPrice'] ?? null,
            'belowTrailingDelta' => $dados['belowTrailingDelta'] ?? null,
            'belowTimeInForce' => $dados['belowTimeInForce'] ?? 'GTC',
            'belowClientOrderId' => $dados['belowClientOrderId'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,
        ];

        $uri = '/api/v3/orderList/oco';

        $response = $this->apiRequest('POST', $uri, $params);
        return $response;
    }

    public function newOrderListOTO(/*$dados */)
    {
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

        if (empty($dados['symbol']) || empty($dados['workingSide']) || empty($dados['workingQuantity']) || empty($dados['workingPrice']) || empty($dados['workingType']) || empty($dados['workingTimeInForce'])) {
            return [
                'success' => false,
                'message' => 'Campos obrigatórios ausentes.'
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

        if (empty($dados['pendingQuantity'])) {
            return [
                'success' => false,
                'message' => 'pendingQuantity é obrigatório.'
            ];
        }

        $params = [
            'symbol' => $dados['symbol'],
            'listClientOrderId' => $dados['listClientOrderId'] ?? null,
            'newOrderRespType' => $dados['newOrderRespType'] ?? 'FULL',
            'selfTradePreventionMode' => $dados['selfTradePreventionMode'] ?? null,

            'workingSide' => $dados['workingSide'],                            // BUY ou SELL
            'workingType' => $dados['workingType'],                            // LIMIT ou LIMIT_MAKER
            'workingQuantity' => $dados['workingQuantity'],
            'workingPrice' => $dados['workingPrice'],
            'workingTimeInForce' => $dados['workingTimeInForce'],

            'pendingSide' => $dados['pendingSide'],                            // BUY ou SELL
            'pendingType' => $dados['pendingType'],                            // STOP_LOSS_LIMIT, STOP_LOSS, etc.
            'pendingQuantity' => $dados['pendingQuantity'],
            'pendingPrice' => $dados['pendingPrice'] ?? null,                 // Necessário se a ordem for LIMIT
            'pendingStopPrice' => $dados['pendingStopPrice'] ?? null,         // Necessário para STOP_LOSS_LIMIT
            'pendingTrailingDelta' => $dados['pendingTrailingDelta'] ?? null, // Necessário para Trailing Stop
            'pendingTimeInForce' => $dados['pendingTimeInForce'] ?? null,     // Necessário para LIMIT ou STOP_LOSS_LIMIT
        ];

        $uri = '/api/v3/orderList/oto';

        return $this->apiRequest('POST', $uri, $params); // Faz a requisição à API
    }

    public function newOrderListOTOCO(/*array $dados */)
    {
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

        if (empty($dados['symbol']) || empty($dados['workingSide']) || empty($dados['workingPrice']) || empty($dados['workingQuantity']) || empty($dados['workingType'])) {
            return [
                'success' => false,
                'message' => 'Campos obrigatórios ausentes: symbol, workingSide, workingPrice, workingQuantity, workingType.'
            ];
        }

        if ($dados['workingType'] == 'LIMIT' && empty($dados['workingTimeInForce'])) {
            return [
                'success' => false,
                'message' => 'Campo obrigatório ausente: workingTimeInForce (necessário para workingType = LIMIT).'
            ];
        }

        if (in_array($dados['pendingAboveType'], ['LIMIT_MAKER', 'STOP_LOSS_LIMIT']) && empty($dados['pendingAbovePrice'])) {
            return [
                'success' => false,
                'message' => 'Campo obrigatório ausente: pendingAbovePrice (necessário para pendingAboveType = LIMIT_MAKER ou STOP_LOSS_LIMIT).'
            ];
        }

        if (in_array($dados['pendingAboveType'], ['STOP_LOSS', 'STOP_LOSS_LIMIT']) && (empty($dados['pendingAboveStopPrice']) && empty($dados['pendingAboveTrailingDelta']))) {
            return [
                'success' => false,
                'message' => 'Campo obrigatório ausente: pendingAboveStopPrice ou pendingAboveTrailingDelta (necessário para pendingAboveType = STOP_LOSS ou STOP_LOSS_LIMIT).'
            ];
        }

        if (in_array($dados['pendingBelowType'], ['LIMIT_MAKER', 'STOP_LOSS_LIMIT']) && empty($dados['pendingBelowPrice'])) {
            return [
                'success' => false,
                'message' => 'Campo obrigatório ausente: pendingBelowPrice (necessário para pendingBelowType = LIMIT_MAKER ou STOP_LOSS_LIMIT).'
            ];
        }

        if (in_array($dados['pendingBelowType'], ['STOP_LOSS', 'STOP_LOSS_LIMIT']) && (empty($dados['pendingBelowStopPrice']) && empty($dados['pendingBelowTrailingDelta']))) {
            return [
                'success' => false,
                'message' => 'Campo obrigatório ausente: pendingBelowStopPrice ou pendingBelowTrailingDelta (necessário para pendingBelowType = STOP_LOSS ou STOP_LOSS_LIMIT).'
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
            'pendingAboveIcebergQty' => $dados['pendingAboveIcebergQty'] ?? null,
            'pendingAboveTimeInForce' => $dados['pendingAboveTimeInForce'] ?? null,
            'pendingAboveStrategyId' => $dados['pendingAboveStrategyId'] ?? null,
            'pendingAboveStrategyType' => $dados['pendingAboveStrategyType'] ?? null,
            'pendingBelowType' => $dados['pendingBelowType'],
            'pendingBelowPrice' => $dados['pendingBelowPrice'] ?? null,
            'pendingBelowStopPrice' => $dados['pendingBelowStopPrice'] ?? null,
            'pendingBelowTrailingDelta' => $dados['pendingBelowTrailingDelta'] ?? null,
            'pendingBelowIcebergQty' => $dados['pendingBelowIcebergQty'] ?? null,
            'pendingBelowTimeInForce' => $dados['pendingBelowTimeInForce'] ?? null,
            'pendingBelowStrategyId' => $dados['pendingBelowStrategyId'] ?? null,
            'pendingBelowStrategyType' => $dados['pendingBelowStrategyType'] ?? null,
        ];

        $uri = '/api/v3/orderList/otoco';

        $response = $this->apiRequest('POST', $uri, $params);

        return $response;
    }

    /* Cancel Order lists (TRADE) */
    public function cancelOrderList(/* array $dados */)
    {
        $dados = [
            'symbol' => 'LTCUSDT',
            'orderListId' => '4391',
            'newClientOrderId' => null,
            'newClientOrderId' => '111111'
        ];

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
}
