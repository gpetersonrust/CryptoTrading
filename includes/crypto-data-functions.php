<?php
/**
 * Extracted functions from front-page.php for use in background updates
 */

/**
 * Fetch and process cryptocurrency data with technical indicators
 *
 * @param string $pair        Trading pair (e.g., 'HFTUSD', 'BTCUSD')
 * @param string $interval    Interval name (e.g., '5 minutes', '1 hour')
 * @param bool   $display     Whether to display results (default: true)
 * @param string $timezone    Timezone for time formatting (default: 'America/New_York')
 * @return array|null         Returns candles array with indicators, or null on error
 */
function fetch_crypto_data($pair = 'HFTUSD', $interval = '5 minutes', $display = true, $timezone = 'America/New_York') {
    $intervals = [
         '1 minute' => 1,
         '5 minutes' => 5,
         '15 minutes' => 15,
         '30 minutes' => 30,
         '1 hour' => 60,
         '4 hours' => 240,
         '1 day' => 1440,
     
    ];

    try {
        // Validate interval
        if (!isset($intervals[$interval])) {
            throw new RuntimeException("Invalid interval: $interval. Available: " . implode(', ', array_keys($intervals)));
        }

        $intervalValue = $intervals[$interval];
        $api_url = 'https://api.kraken.com/0/public/OHLC?pair=' . $pair . '&interval=' . $intervalValue;

        $candles = curl_request($api_url);

        $pairKey = array_key_first($candles);
        $ohlcData = $candles[$pairKey];

        // Map candles to array of associative arrays with typed values
        $mappedCandles = array_map(function($candle) use ($timezone) {
            // Convert timestamp to DateTime in specified timezone
            $dt = new DateTime('@' . $candle[0]);  // @ = unix timestamp
            $dt->setTimezone(new DateTimeZone($timezone));

            return [
                'time'   => $dt->format('D M j, Y g:ia'), // Example: Mon Sep 1, 2020 6:40pm
                'open'   => (float)$candle[1],
                'high'   => (float)$candle[2],
                'low'    => (float)$candle[3],
                'close'  => (float)$candle[4],
                'vwap'   => (float)$candle[5],
                'volume' => (float)$candle[6],
                'count'  => (int)$candle[7],
            ];
        }, $ohlcData);

        // Attach all indicators to each candle
        TechIndicators::attachAllIndicators($mappedCandles);

        return $mappedCandles;

    } catch (RuntimeException $e) {
        // Don't echo errors directly - they'll be handled in the UI
        return null;
    }
}

/**
 * Fetch cryptocurrency data across multiple timeframes
 *
 * @param string $pair        Trading pair (e.g., 'HFTUSD', 'BTCUSD')
 * @param string $timezone    Timezone for time formatting (default: 'America/New_York')
 * @param bool   $display     Whether to display results (default: true)
 * @return array|null         Master candles array with all timeframes, or null on error
 */
function fetch_multi_timeframe_data($pair = 'HFTUSD', $timezone = 'America/New_York', $display = true) {
    $timeframes = [
        '1 minute' => 1,
        '5 minutes' => 5,
        '15 minutes' => 15,
        '30 minutes' => 30,
        '1 hour' => 60,
        '4 hours' => 240,
        '1 day' => 1440
    ];

    $masterCandles = [];
    $lastCandles = [];

    foreach ($timeframes as $interval => $intervalValue) {
        try {            
            // Fetch data for this timeframe (without displaying individual results)
            $candleData = fetch_crypto_data($pair, $interval, false, $timezone);
            
            if ($candleData !== null) {
                $masterCandles[$interval] = $candleData;
                $lastCandles[$interval] = array_slice($candleData, -3); // Get last 3 candles
            } else {
                $masterCandles[$interval] = null;
                $lastCandles[$interval] = null;
            }
            
            // Small delay to be respectful to API
            // usleep(100000); // 0.1 second delay
            
        } catch (Exception $e) {
            $masterCandles[$interval] = null;
            $lastCandles[$interval] = null;
        }
    }

    return [
        'master_candles' => $masterCandles,
        'last_candles' => $lastCandles,
        'pair' => $pair,
        'timezone' => $timezone
    ];
}
