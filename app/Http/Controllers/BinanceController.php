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
}
