<?php

namespace App\Http\Controllers;

class CoinCallController extends Controller
{
    private function apiRequest($uri)
    {
        $apiKey = '/5AeyqmVeF7YKVetwCgLvnifokYmpnM5giu4VcqQLoA=';
        $secretKey = '7IAXOK9/ofbLSydaL52JR2EKouCSmD81bvWiFbtDOd0=';
        $timestamp = round(microtime(true) * 1000);
        $tsDiff = 5000;

        $url = "https://api.coincall.com{$uri}";
        $prehashString = "GET{$uri}?uuid={$apiKey}&ts={$timestamp}&x-req-ts-diff={$tsDiff}";

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
            CURLOPT_CUSTOMREQUEST => 'GET',
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

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    public function getAccountInfo()
    {
        $uri = '/open/user/info/v1';
        return $this->apiRequest($uri);
    }

    public function getSummaryInfo($coin = null)
    {
        $uri = '/open/account/summary/v1';
        $response = $this->apiRequest($uri);

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
        //BTCUSD-6JUN23-24000-C
        $uri = '/open/option/order/orderbook/v1/' . $symbol;
        return $this->apiRequest($uri);
    }

    public function getSpotMarketOrderBook($symbol = 'TRXUSD', $depth = 1)
    {

        $uri = "/open/spot/market/orderbook?symbol={$symbol}&depth={$depth}";
        $response = $this->apiRequest($uri);

        return $response;
    }

    /**
     * test
     */
    public function getSymbols()
    {
        $uri = '/open/futures/market/symbol/v1';
        $response = $this->apiRequest($uri);

        return $response;
    }
}


