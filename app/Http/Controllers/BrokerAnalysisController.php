<?php

namespace App\Http\Controllers;

class BrokerAnalysisController extends Controller
{
    public function getOrderBooks()
    {
        $endpoints = [
            'A' => 'https://brasilbitcoin.com.br/API/v2/orderbook/BTCBRL?limit=20',
            'B' => 'https://bitnuvem.com/api/BTC/orderbook',
            'C' => 'https://api.bitpreco.com/btc-brl/orderbook'
        ];

        $orderBooks = [];

        foreach ($endpoints as $key => $endpoint) {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($curl);
            curl_close($curl);

            $orderBooks[$key] = $this->normalizeOrderBook($key, json_decode($response, true));
        }

        return $orderBooks;
    }

    public function normalizeOrderBook($exchange, $data)
    {
        $normalized = [
            'bids' => [],
            'asks' => []
        ];

        // Normaliza os bids
        if (isset($data['orders']['bids'])) { // Corretora A
            foreach ($data['orders']['bids'] as $bid) {
                $normalized['bids'][] = [
                    'price' => (float)$bid[0],
                    'quantity' => (float)$bid[1]
                ];
            }
        } elseif (isset($data['bids'])) { // Corretora B ou C
            foreach ($data['bids'] as $bid) {
                if (isset($bid['price'])) { // Corretora C
                    $normalized['bids'][] = [
                        'price' => (float)$bid['price'],
                        'quantity' => (float)$bid['amount']
                    ];
                } else { // Corretora B
                    $normalized['bids'][] = [
                        'price' => (float)$bid[0],
                        'quantity' => (float)$bid[1]
                    ];
                }
            }
        }

        // Normaliza os asks
        if (isset($data['orders']['asks'])) { // Corretora A
            foreach ($data['orders']['asks'] as $ask) {
                $normalized['asks'][] = [
                    'price' => (float)$ask[0],
                    'quantity' => (float)$ask[1]
                ];
            }
        } elseif (isset($data['asks'])) { // Corretora B ou C
            foreach ($data['asks'] as $ask) {
                if (isset($ask['price'])) { // Corretora C
                    $normalized['asks'][] = [
                        'price' => (float)$ask['price'],
                        'quantity' => (float)$ask['amount']
                    ];
                } else { // Corretora B
                    $normalized['asks'][] = [
                        'price' => (float)$ask[0],
                        'quantity' => (float)$ask[1]
                    ];
                }
            }
        }

        return $normalized;
    }

    public function analyzeOrderBooks()
    {
        $orderBooks = $this->getOrderBooks();

        $exchangeNames = [
            'A' => 'Brasil Bitcoin',
            'B' => 'Bit Nuvem',
            'C' => 'Bit Preco'
        ];

        $opportunities = [];

        // Compara cada corretora com todas as outras
        foreach ($orderBooks as $exchangeX => $dataX) {
            foreach ($orderBooks as $exchangeY => $dataY) {
                if ($exchangeX !== $exchangeY) {
                    // Valida se existem ordens válidas em ambas as corretoras
                    if (!empty($dataX['bids']) && !empty($dataY['asks'])) {
                        foreach ($dataX['bids'] as $bidX) {
                            foreach ($dataY['asks'] as $askY) {
                                // Se tiver lucro na operação cria o index
                                if ($bidX['price'] > $askY['price']) {
                                    $quantity = min($bidX['quantity'], $askY['quantity']);
                                    $profit = ($bidX['price'] - $askY['price']) * $quantity;
                                    $cost = $askY['price'] * $quantity;
                                    $profit = round($profit, 4);
                                    if ($cost > 0) { // Caso tenha divisão por zero para não bugar
                                        $roi = ($profit / $cost) * 100;
                                        $roi = round($roi, 4);

                                        $opportunities[] = [
                                            'buy_exchange' => $exchangeNames[$exchangeY],
                                            'sell_exchange' => $exchangeNames[$exchangeX],
                                            'buy_price' => $askY['price'],
                                            'sell_price' => $bidX['price'],
                                            'quantity' => $quantity,
                                            'profit' => $profit,
                                            'roi' => $roi
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'opportunities' => $opportunities,
            // 'data' => $orderBooks, //Todos os pares
        ]);
    }
}
