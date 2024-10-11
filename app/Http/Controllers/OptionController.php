<?php

namespace App\Http\Controllers;

class OptionController extends Controller
{
    protected $coinCallController;
    private $price;

    public function __construct(CoinCallController $coinCallController)
    {
        $this->coinCallController = $coinCallController;
    }

    public function getFilteredOptions()
    {
        // Capta preço atual de BTC
        $data = $this->coinCallController->getOrderBookSpot('BTC');
        $this->price = $data['data']['a']['0']['0'];
        // Capta as options
        $options = $this->coinCallController->getInstrumentsOption('BTC');
        $filteredOptions = [];
        $endMonth = now()->endOfMonth()->timestamp * 1000;

        $minPrice = round($this->price - 2000);

        foreach ($options['data'] as $option) {
            // strike deve estar entre o minPrice e o preço real (this->price), e a data de expiração deve ser até o final do mês
            if ($option['strike'] >= $minPrice && $option['strike'] <= $this->price && $option['expirationTimestamp'] <= $endMonth) {
                $filteredOptions[] = $option;
            }
        }

        return $filteredOptions;

    }

    public function getOrderBookOption()
    {
        $options = $this->getFilteredOptions();
        $data = [];

        $symbolNames = array_map(function($option){
            return $option['symbolName'];
        }, $options);

        // $symbolNames = array_slice($symbolNames, 0, 2); // limite de arrays
        foreach ($symbolNames as $symbolName) {
            $option = $this->coinCallController->getOrderBookOption($symbolName);
            $data[] = $option;
        }
        return $data;
    }

    public function getStrikes()
    {
        $data = $this->getOrderBookOption();
        $strikes = [];

        // armazena em pares, onde Call assume bids, e Put assume asks referente ao mesmo strike e option name
        foreach ($data as $orderBook) {
            $strike = $orderBook['data']['strike'];
            $symbol = $orderBook['data']['symbol'];
            $optionName = substr($symbol, 0, -2); // Retira os últimos caractere (-C ou -P)
            $type = substr($symbol, -2); // Último caractere (C ou P)

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

        return $strikes;
    }

    public function getPrice()
    {
        $strikes = $this->getStrikes();
        $optionsData = [];

        foreach ($strikes as $strike => $options) {
            foreach ($options as $optionName => $data) {
                $buyOptionPrice = null;
                $sellOptionPrice = null;

                // preço da opção Put
                if (!empty($data['P'])) {
                    $buyOptionPrice = (float)($data['P'][0]['price'] ?? 0);
                }

                // preço da opção Call
                if (!empty($data['C'])) {
                    $sellOptionPrice = (float)($data['C'][0]['price'] ?? 0);
                }

                // diferença entre Call e Put
                $diffOptions = $sellOptionPrice - $buyOptionPrice;

                $optionsData[] = [
                    'strike' => $strike,
                    'optionName' => $optionName,
                    'buyOptionPrice' => $buyOptionPrice,
                    'sellOptionPrice' => $sellOptionPrice,
                    'diffOptions' => $diffOptions,
                    'value' => round($diffOptions - ($this->price - $strike), 2),
                ];
            }
        }

        // Filtrar os strikes com valores positivos e armazenar o value num array
        $positiveStrikes = [];
        foreach ($optionsData as $item) {
            if ($item['value'] >= 1) {
                $positiveStrikes[$item['strike']] = [];

                $positiveStrikes[$item['strike']][] = [
                    'optionName' => $item['optionName'],
                    'value' => $item['value']
                ];
            }
        }

        return [
            'strikes' => $positiveStrikes,
            // 'priceBtcNow' => $this->price, // (preço BTC->SPOT)

            // 'data' => $optionsData // array com informações para o cálculo
        ];
    }

    public function index()
    {
        $response = $this->getPrice();

        return $response;
    }

}
