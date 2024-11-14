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
}
