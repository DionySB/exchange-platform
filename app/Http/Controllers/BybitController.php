<?php

namespace App\Http\Controllers;

class BybitController extends Controller
{
    private function apiRequest($method, $uri, $params = [])
    {
        $apiKey = env('BYBIT_API_KEY');
        $secretKey = env('BYBIT_SECRET_KEY');
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        $params = array_filter($params, fn($value) => !is_null($value) && $value !== '');
        $queryString = http_build_query($params);

        $prehashString = "{$timestamp}{$apiKey}{$recvWindow}{$queryString}";
        $signature = hash_hmac('sha256', $prehashString, $secretKey);
        $url = 'https://api.bybit.com' . $uri;
        if ($method === 'GET' && !empty($queryString)) {
            $url .= '?' . $queryString;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                "X-BAPI-API-KEY: {$apiKey}",
                "X-BAPI-SIGN: {$signature}",
                "X-BAPI-TIMESTAMP: {$timestamp}",
                "X-BAPI-RECV-WINDOW: {$recvWindow}",
                "Content-Type: application/json",
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    public function getFundingRateHistory($category = '', $symbol = '', $startTime = '', $endTime = '', $limit = '20')
    {
        $uri = '/v5/market/funding/history';

        if (empty($category) || empty($symbol)) {
            return [
                'success' => false,
                'message' => 'category e symbol são requeridos.',
            ];
        }

        $params = [
            'category' => $category,
            'symbol' => $symbol,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit,
        ];

        $response = $this->apiRequest('GET', $uri, $params);

        return $response;
    }

    public function compareFundingRates($longBTC = '', $shortBTC = '')
    {
        if(empty($longBTC) || empty($shortBTC)){
            return [
                'success' => 'false',
                'message' => 'Uma posição de long e short deve ser inserida.'
            ];
        }
        $dataLong = $this->getFundingRateHistory('linear', $longBTC);
        $dataShort = $this->getFundingRateHistory('linear', $shortBTC);

        $longFundingRate = $dataLong['result']['list'][0]['fundingRate'] ?? null;
        $shortFundingRate = $dataShort['result']['list'][0]['fundingRate'] ?? null;
        return [
            'success' => 'true',
            'fundingRateLong' => $longFundingRate,
            'fundingRateShort' => $shortFundingRate
        ];
    }

    public function teste()
    {
        // $teste = $this->getAccountBallance();
        // $teste = $this->getFundingRateHistory('linear', 'BTCPERP');
        $compare = $this->compareFundingRates('BTCUSDT', 'BTCPERP');

        return $compare;
    }
}
