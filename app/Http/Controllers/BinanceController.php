<?php

namespace App\Http\Controllers;

class BinanceController extends Controller
{

    private function apiRequest($method, $uri, $params = [])
    {
        $apiKey = env('BINANCE_API_KEY_TEST');
        $secretKey = env('BINANCE_SECRET_KEY_TEST');
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        $params['timestamp'] = $timestamp;
        $params['recvWindow'] = $recvWindow;
        $params = array_filter($params, fn($value) => !is_null($value));
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $url = "https://testnet.binance.vision{$uri}?signature={$signature}";

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

        dd([
            'response' => $response,
            'url' => $url,
            'signature' => $signature,
            'queryString' => $queryString,
            'timestamp' => $timestamp,
        ]);

        return json_decode($response, true);
    }

    public function getAccountInfo()
    {
        $uri = '/api/v3/account';

        $response = $this->apiRequest('GET', $uri);

        return $response;
    }
}
