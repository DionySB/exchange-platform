#exhange-platform

Route: /create-order

Method: createOrder

Route: /cancel-order

Method: cancelOrder

Route: /create-option-order
Method: createOptionOrder

Route: / 
Method: index

Route: /account-info
Method: getAccountInfo

Route: /summary-info/{symbol?}
Method: getSummaryInfo

Route: /option-orderbook/{symbol}
Method: getOptionOrderBook

Route: /orderbook/{symbol}/{depth?}
Method: getSpotMarketOrderBook

Route: /query-order/{id?}
Method: getQueryOrder

Route: /open-orders/{symbol?}
Method: getOpenOrders

Route: /orders/{symbol?}/{endTime?}/{startTime?}/{limit?}
Method: getAllOrders

Route: /option-chain/{index}/{endTime}
Method: getOptionChain

Route: /positions
Method: getPositions

Route: /open-option-orders/{currency?}/{page?}/{pageSize?}
Method: getOpenOptionOrders

Route: /order-info/{type}/{id}
Method: getOrderInfo

Route: /order-details/{pageSize?}/{fromId?}/{startTime?}/{endTime?}
Method: getOrderDetails

Route: /option-instruments/{baseCurrency?}
Method: getOptionInstruments
