<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
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

        return $response;
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

    /* Public Endpoints */

    public function getFundingRate($crypto)
    {
        $crypto = strtoupper($crypto);
        $params = [
            'symbol' => $crypto . 'USD'
        ];

        $uri = '/open/public/fundingRate/v1';
        $response = $this->apiRequest('GET', $uri, $params);

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

        return $response;
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

        return $response;
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

        return $response;
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

        return $response;
    }

    public function getOpenOrdersSpot($symbol = null)
    {
        $params = [
            'symbol' => $symbol
        ];
        $uri = '/open/spot/trade/orders/v1';
        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
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

        return $response;
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

    public function getInstrumentsFuture()
    {
        $uri = '/open/futures/market/instruments/v1';
        $response =  $this->apiRequest('GET', $uri);

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

    public function getSpreadOP()
    {
        // Capta preço atual de BTC
        $data = $this->getOrderBookSpot('BTC');
        $price = $data['data']['a']['0']['0'];

        // Capta as options
        $options = $this->getInstrumentsOption('BTC');
        $filteredOptions = [];

        $endMonth = now()->endOfMonth()->timestamp * 1000;
        $minPrice = round($price - 2000);

        // Filtra as opções com base no strike e na data de expiração com array_filter por ser mais rápido que um foreach
        $filteredOptions = array_filter($options['data'], function($option) use ($minPrice, $price, $endMonth) {
            return $option['strike'] >= $minPrice && $option['strike'] <= $price && $option['expirationTimestamp'] <= $endMonth;
        });

        $symbolNames = array_map(fn($option) => $option['symbolName'], $filteredOptions);

        //Requisição dos orderBook filtrados
        $orderBookData = [];
        foreach ($symbolNames as $symbolName) {
            $orderBookData[] = $this->getOrderBookOption($symbolName);
        }

        $strikes = [];
        foreach ($orderBookData as $orderBook) {
            $strike = $orderBook['data']['strike'];
            $symbol = $orderBook['data']['symbol'];
            $optionName = substr($symbol, 0, -2); // '-C' ou '-P'
            $type = substr($symbol, -2); // C (Call) ou P (Put)

            if (!isset($strikes[$strike])) {
                $strikes[$strike] = [];
            }
            if (!isset($strikes[$strike][$optionName])) {
                $strikes[$strike][$optionName] = ['P' => null, 'C' => null];
            }

            if ($type == '-C') {
                $strikes[$strike][$optionName]['C'] = $orderBook['data']['bids'];
            } else if ($type == '-P') {
                $strikes[$strike][$optionName]['P'] = $orderBook['data']['asks'];
            }
        }

        $positiveOptionsData = [];
        foreach ($strikes as $strike => $options) {
            foreach ($options as $optionName => $data) {
                $buyOptionPrice = !empty($data['P']) ? (float)($data['P'][0]['price'] ?? 0) : null;
                $sellOptionPrice = !empty($data['C']) ? (float)($data['C'][0]['price'] ?? 0) : null;

                // Calcula a diferença para saber o lucro da operação.
                $diffOptions = $sellOptionPrice - $buyOptionPrice;
                $gainValue = round($diffOptions - ($price - $strike), 2);

                if ($gainValue > 0) {
                    // Calcula a porcentagem de ganho da OP
                    $percentageOP = round(($gainValue / $price) * 100);

                    // Desconto de taxas pagas para realizar a operação
                    $operationRate = round($price * 0.00325, 2);
                    $gainWithFee = round($gainValue - $operationRate, 2);

                    $itemData = [
                        'strike' => $strike,
                        'optionName' => $optionName,
                        'buyOptionPrice' => $buyOptionPrice,
                        'sellOptionPrice' => $sellOptionPrice,
                        'diffOptions' => $diffOptions,
                        'gainValue' => $gainValue, // Ganho da operação
                        'spreadPercent' => $percentageOP, // Porcentagem de ganho da OP
                        'gainWithFee' => $gainWithFee, // Ganho da operação descontando taxa
                    ];
                    $positiveOptionsData[] = $itemData;
                }
            }
        }

        return [
            'data' => [
                'price' => $price, // Preço atual do BTC
                'operations' => $positiveOptionsData, // Possíveis operações
            ],
            // 'books' => $orderBookData, //Retorna os orderbook
        ];
    }

    public function getRate()
    {
        $cryptos = [
            'BTC',
            'ETH',
            'ADA',
            'SOL',
            'DOT',
            'DNB',
            'TON',
        ];

        $fundingRate = $this->getInstrumentsFuture();
        $dataRates = [];

        foreach ($cryptos as $crypto) {
            $rate = $this->getFundingRate($crypto);

            if (isset($rate['data'][0])) {
                $symbol = $rate['data'][0]['symbol'];
                $dataRates[] = [
                    'symbol' => $symbol,
                    'rate' => $rate['data'][0]['rate'],
                    'funding_rate' => $fundingRate['data'][0]['funding_rate'],
                ];
            }
        }

        return $dataRates;
    }


    public function getFutureFundingRate()
    {
        $cacheKey = 'funding_data';
        $now = now()->timezone('America/Sao_Paulo');
        $currentHour = $now->hour;
        $startTime = $now->format('d-m-Y H:i:s');
        // Intervalos específicos para validação de funding rate
        $intervals = [
            [5, 12], // 05h até 12h59
            [13, 20], // 13h até 20h59
            [21, 4] // 21h até 04h59
        ];

        $cachedData = Cache::get($cacheKey, [
            'priceBtcSpot' => null,
            'priceBtcFuture' => null,
            'priceFutureWithFeeTaker' => null,
            'priceFutureWithFeeMaker' => null,
            'data' => [
                'fundingRatePay' => 0,
                'fundingRateReceive' => 0,
                'custFeeTaker' => 0,
                'custFeeMaker' => 0,
                'timeArray' => [],
                'startTime' => $startTime // Inicializa startTime
            ]
        ]);

        // Verifica o último horário de registro e o intervalo correspondente.
        $lastTime = end($cachedData['data']['timeArray']);
        $currentInterval = null;

        foreach ($intervals as $interval) {
            [$start, $end] = $interval;
            if (($start <= $currentHour && $currentHour <= $end) || ($start > $end && ($currentHour >= $start || $currentHour <= $end))) {
                $currentInterval = "$start-$end";
                break;
            }
        }

        // Conta quantas vezes registramos dados hoje
        $today = $now->format('d-m-Y');
        $today = '30-10-2024';
        $countToday = count(array_filter($cachedData['data']['timeArray'], fn($entry) => strpos($entry, $today) !== false));

        // Verifica se o horário atual está dentro do intervalo correto, se já não foi registrado e se não excede 3 execuções no dia.
        if ($currentInterval && (!$lastTime || strpos(end($cachedData['data']['timeArray']), $currentInterval) === false) && $countToday < 3) {
            $spotData = $this->getOrderBookSpot('BTC');
            $priceBtcSpot = $spotData['data']['b']['0']['0'];

            $futureData = $this->getInstrumentsFuture();
            $priceBtcFuture = $futureData['data'][0]['ask'];

            $feePercentageTaker = 0.0062;
            $feePercentageMaker = 0.0043;

            $priceFutureWithFeeTaker = round($priceBtcFuture * (1 + $feePercentageTaker), 2);
            $priceFutureWithFeeMaker = round($priceBtcFuture * (1 + $feePercentageMaker), 2);

            $fundingRate = $futureData['data'][0]['funding_rate'];
            $fundingRateValue = round($priceBtcFuture * $fundingRate, 2);

            // Atualiza os valores de funding rate e taxas pagas
            $cachedData['data']['fundingRateReceive'] += $fundingRate > 0 ? $fundingRateValue : -$fundingRateValue;

            $cachedData['data']['custFeeTaker'] += round($priceBtcFuture * $feePercentageTaker, 2);
            $cachedData['data']['custFeeMaker'] += round($priceBtcFuture * $feePercentageMaker, 2);

            // Adiciona o timestamp atual ao histórico com o intervalo de tempo
            $cachedData['data']['timeArray'][] = "$startTime ($currentInterval)";

            // Atualiza os preços e taxas calculadas no cache
            $cachedData['priceBtcSpot'] = round($priceBtcSpot, 2);
            $cachedData['priceBtcFuture'] = round($priceBtcFuture, 2);
            $cachedData['priceFutureWithFeeTaker'] = $priceFutureWithFeeTaker;
            $cachedData['priceFutureWithFeeMaker'] = $priceFutureWithFeeMaker;

            Cache::put($cacheKey, $cachedData);
        }

        return [
            'priceBtcSpot' => $cachedData['priceBtcSpot'],
            'priceBtcFuture' => $cachedData['priceBtcFuture'],
            'priceFutureWithFeeTaker' => $cachedData['priceFutureWithFeeTaker'],
            'priceFutureWithFeeMaker' => $cachedData['priceFutureWithFeeMaker'],
            'data' => $cachedData['data'],
        ];
    }

}

