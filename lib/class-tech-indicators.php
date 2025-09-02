<?php

/**
 * Technical Indicators (RSI, StochRSI, SMA, EMA)
 * Input order: arrays must be oldest → newest.
 */
class TechIndicators
{
    /**
     * Simple moving average (SMA) of the last $period values in $values.
     *
     * @param float[] $values
     * @param int     $period
     * @return float|null
     */
    public static function sma(array $values, int $period): ?float
    {
        if ($period <= 0 || count($values) < $period) return null;
        $slice = array_slice($values, -$period);
        return array_sum($slice) / $period;
    }

    /**
     * Simple Moving Average (SMA) full series.
     * Returns array aligned with input prices (earliest values null until seed).
     *
     * @param float[] $values  Oldest → newest
     * @param int     $period
     * @return array<int,float|null>
     */
    public static function smaSeries(array $values, int $period): array
    {
        $n = count($values);
        $smaArr = array_fill(0, $n, null);
        if ($period <= 0 || $n < $period) return $smaArr;

        for ($i = $period - 1; $i < $n; $i++) {
            $slice = array_slice($values, $i - $period + 1, $period);
            $smaArr[$i] = array_sum($slice) / $period;
        }
        return $smaArr;
    }

    /**
 * Exponential Moving Average (EMA) full series.
 * Returns array aligned with input prices (earliest values null until seed).
 *
 * @param float[] $values  Oldest → newest
 * @param int     $period
 * @return array<int,float|null>
 */
public static function emaSeries(array $values, int $period): array
{
    $n = count($values);
    $emaArr = array_fill(0, $n, null);
    if ($period <= 0 || $n < $period) return $emaArr;

    $alpha = 2 / ($period + 1);

    // Seed with SMA of first $period values
    $seed = array_sum(array_slice($values, 0, $period)) / $period;
    $emaArr[$period - 1] = $seed;
    $ema = $seed;

    // Forward recursion
    for ($i = $period; $i < $n; $i++) {
        $ema = ($values[$i] - $ema) * $alpha + $ema;
        $emaArr[$i] = $ema;
    }
    return $emaArr;
}


    /**
     * Exponential moving average (EMA) over entire $values series.
     * Seeds with SMA of the first $period values.
     *
     * @param float[] $values
     * @param int     $period
     * @return float|null
     */
    public static function ema(array $values, int $period): ?float
    {
        $n = count($values);
        if ($period <= 0 || $n < $period) return null;

        $alpha = 2 / ($period + 1);
        $ema   = array_sum(array_slice($values, 0, $period)) / $period; // seed

        for ($i = $period; $i < $n; $i++) {
            $ema = ($values[$i] - $ema) * $alpha + $ema;
        }
        return $ema;
    }

    /**
     * RSI (Wilder's method).
     * Returns array aligned to $closes (first $period entries are null).
     *
     * @param float[] $closes  Oldest → newest close prices
     * @param int     $period  Default 14
     * @return array<int, float|null>  RSI values (0–100)
     */
    public static function rsi(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $rsi = array_fill(0, $n, null);
        if ($period <= 0 || $n <= $period) return $rsi;

        $gainSum = 0.0;
        $lossSum = 0.0;

        for ($i = 1; $i <= $period; $i++) {
            $chg = $closes[$i] - $closes[$i - 1];
            if ($chg >= 0) $gainSum += $chg; else $lossSum -= $chg;
        }
        $avgGain = $gainSum / $period;
        $avgLoss = $lossSum / $period;

        $rs = ($avgLoss == 0.0) ? INF : $avgGain / $avgLoss;
        $rsi[$period] = 100 - (100 / (1 + $rs));

        for ($i = $period + 1; $i < $n; $i++) {
            $chg  = $closes[$i] - $closes[$i - 1];
            $gain = $chg > 0 ? $chg : 0.0;
            $loss = $chg < 0 ? -$chg : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

            $rs = ($avgLoss == 0.0) ? INF : $avgGain / $avgLoss;
            $rsi[$i] = 100 - (100 / (1 + $rs));
        }
        return $rsi;
    }

    /**
     * Stochastic RSI (StoRSI) with SMA or EMA smoothing.
     * Returns arrays aligned to $closes (early values are null until warm-up satisfied).
     *
     * @param float[] $closes     Oldest → newest close prices
     * @param int     $rsiLen     RSI period (default 14)
     * @param int     $stochLen   Lookback over RSI for raw %K (default 14)
     * @param int     $kSmooth    %K smoothing length (default 3)
     * @param int     $dSmooth    %D smoothing length (default 3)
     * @param string  $maType     'SMA' or 'EMA' for smoothing (default 'SMA')
     * @return array{rsi: array<int, float|null>, k: array<int, float|null>, d: array<int, float|null>}
     */
    public static function stochRsi(
        array $closes,
        int $rsiLen = 14,
        int $stochLen = 14,
        int $kSmooth = 3,
        int $dSmooth = 3,
        string $maType = 'SMA'
    ): array {
        $rsi = self::rsi($closes, $rsiLen);
        $n   = count($rsi);

        $stoch = array_fill(0, $n, null); // raw StochRSI %K before smoothing
        $k     = array_fill(0, $n, null); // smoothed %K
        $d     = array_fill(0, $n, null); // smoothed %D

        // Choose MA function
        $ma = function(array $vals, int $len) use ($maType) {
            if ($len <= 0) return null;
            return ($maType === 'EMA') ? self::ema($vals, $len) : self::sma($vals, $len);
        };

        for ($i = 0; $i < $n; $i++) {
            // Need enough RSI history (conservative warm-up)
            if ($i < $rsiLen + $stochLen) continue;

            // Last $stochLen RSI values; skip if any nulls present
            $window = array_slice($rsi, $i - $stochLen + 1, $stochLen);
            if (in_array(null, $window, true)) continue;

            $minRsi = min($window);
            $maxRsi = max($window);
            $denom  = $maxRsi - $minRsi;

            // If RSI is flat over the window, define StochRSI as 0
            $stoch[$i] = ($denom == 0.0) ? 0.0 : (($rsi[$i] - $minRsi) / $denom) * 100.0;

            // %K smoothing
            $kVals = array_slice($stoch, max(0, $i - $kSmooth + 1), $kSmooth);
            $kVal  = $ma($kVals, $kSmooth);
            if ($kVal !== null) $k[$i] = $kVal;

            // %D smoothing on %K
            $dVals = array_slice($k, max(0, $i - $dSmooth + 1), $dSmooth);
            $dVal  = $ma($dVals, $dSmooth);
            if ($dVal !== null) $d[$i] = $dVal;
        }

        return ['rsi' => $rsi, 'k' => $k, 'd' => $d];
    }

    /**
     * Optional helper: attach StochRSI outputs back onto your candle rows by reference.
     *
     * @param array<int, array<string,mixed>> $candles Each candle should have a 'close' (float)
     * @param int $rsiLen
     * @param int $stochLen
     * @param int $kSmooth
     * @param int $dSmooth
     * @param string $maType
     * @param string $kKey      Key name for %K (default 'stoch_k')
     * @param string $dKey      Key name for %D (default 'stoch_d')
     * @param string $rsiKey    Key name for RSI (default 'rsi')
     * @return void
     */
    public static function attachStochRsiToCandles(
        array &$candles,
        int $rsiLen = 14,
        int $stochLen = 14,
        int $kSmooth = 3,
        int $dSmooth = 3,
        string $maType = 'SMA',
        string $kKey = 'stoch_k',
        string $dKey = 'stoch_d',
        string $rsiKey = 'rsi'
    ): void {
        $closes = array_map(fn($c) => (float)$c['close'], $candles);
        $sto    = self::stochRsi($closes, $rsiLen, $stochLen, $kSmooth, $dSmooth, $maType);

        foreach ($candles as $i => &$c) {
            $c[$rsiKey] = $sto['rsi'][$i];
            $c[$kKey]   = $sto['k'][$i];
            $c[$dKey]   = $sto['d'][$i];
        }
        unset($c);
    }


    /**
 * Slow Stochastic Oscillator
 *
 * @param float[] $highs    High prices, oldest → newest
 * @param float[] $lows     Low prices, oldest → newest
 * @param float[] $closes   Close prices, oldest → newest
 * @param int     $period   Lookback period (default 14)
 * @param int     $kSmooth  %K smoothing (default 3)
 * @param int     $dSmooth  %D smoothing (default 3)
 * @param string  $maType   'SMA' or 'EMA'
 * @return array{percentK: array<int,float|null>, percentD: array<int,float|null>}
 */
public static function stochasticSlow(
    array $highs,
    array $lows,
    array $closes,
    int $period = 14,
    int $kSmooth = 3,
    int $dSmooth = 3,
    string $maType = 'SMA'
): array {
    $n = count($closes);
    $percentK = array_fill(0, $n, null);
    $slowK    = array_fill(0, $n, null);
    $slowD    = array_fill(0, $n, null);

    $ma = function(array $vals, int $len) use ($maType) {
        return ($maType === 'EMA')
            ? self::ema($vals, $len)
            : self::sma($vals, $len);
    };

    for ($i = $period - 1; $i < $n; $i++) {
        $hh = max(array_slice($highs, $i - $period + 1, $period));
        $ll = min(array_slice($lows, $i - $period + 1, $period));
        $denom = $hh - $ll;

        $percentK[$i] = ($denom == 0.0) ? 0.0 : (($closes[$i] - $ll) / $denom) * 100.0;

        // Slow %K smoothing
        $kVals = array_slice($percentK, max(0, $i - $kSmooth + 1), $kSmooth);
        $kVal  = $ma($kVals, $kSmooth);
        if ($kVal !== null) $slowK[$i] = $kVal;

        // Slow %D smoothing
        $dVals = array_slice($slowK, max(0, $i - $dSmooth + 1), $dSmooth);
        $dVal  = $ma($dVals, $dSmooth);
        if ($dVal !== null) $slowD[$i] = $dVal;
    }

    return ['percentK' => $slowK, 'percentD' => $slowD];
}


/**
 * Moving Average Convergence Divergence (MACD)
 *
 * @param float[] $closes   Close prices, oldest → newest
 * @param int     $fastLen  Fast EMA length (default 8)
 * @param int     $slowLen  Slow EMA length (default 21)
 * @param int     $signalLen Signal EMA length (default 5)
 * @return array{macd: array<int,float|null>, signal: array<int,float|null>, hist: array<int,float|null>}
 */
public static function macd(
    array $closes,
    int $fastLen = 8,
    int $slowLen = 21,
    int $signalLen = 5
): array {
    $n = count($closes);
    $fast = self::emaSeries($closes, $fastLen);
    $slow = self::emaSeries($closes, $slowLen);

    $macdLine = array_fill(0, $n, null);
    for ($i = 0; $i < $n; $i++) {
        if ($fast[$i] !== null && $slow[$i] !== null) {
            $macdLine[$i] = $fast[$i] - $slow[$i];
        }
    }

    // Signal line is EMA of MACD line
    $signalLine = self::emaSeries(
        array_map(fn($v) => $v ?? 0.0, $macdLine), // fill nulls with 0.0 for safe EMA
        $signalLen
    );

    $hist = array_fill(0, $n, null);
    for ($i = 0; $i < $n; $i++) {
        if ($macdLine[$i] !== null && $signalLine[$i] !== null) {
            $hist[$i] = $macdLine[$i] - $signalLine[$i];
        }
    }

    return ['macd' => $macdLine, 'signal' => $signalLine, 'hist' => $hist];
}




/**
 * Rolling standard deviation series (population by default).
 * Set $sample=true for sample stddev (N-1).
 *
 * @param float[] $values  Oldest → newest
 * @param int     $period
 * @param bool    $sample
 * @return array<int,float|null>
 */
public static function stddevSeries(array $values, int $period, bool $sample = false): array
{
    $n = count($values);
    $out = array_fill(0, $n, null);
    if ($period <= 0 || $n < $period) return $out;

    // Helper to compute stddev of a small window quickly
    $std = function(array $win) use ($sample) {
        $m = count($win);
        if ($m === 0) return null;
        $mean = array_sum($win) / $m;
        $varSum = 0.0;
        foreach ($win as $v) { $d = $v - $mean; $varSum += $d * $d; }
        $den = $sample ? max(1, $m - 1) : $m;
        return sqrt($varSum / $den);
    };

    // First window
    $window = array_slice($values, 0, $period);
    $out[$period - 1] = $std($window);

    // Slide window
    for ($i = $period; $i < $n; $i++) {
        array_shift($window);
        $window[] = $values[$i];
        $out[$i] = $std($window);
    }
    return $out;
}


 /**
 * Bollinger Bands
 *
 * mid = SMA(period)
 * upper = mid + mult * stddev
 * lower = mid - mult * stddev
 * Optionally returns %B and Bandwidth.
 *
 * @param float[] $closes     Oldest → newest
 * @param int     $period     Default 20
 * @param float   $mult       Std dev multiplier (default 2.0)
 * @param bool    $sample     Use sample stddev (N-1) if true; population if false
 * @param bool    $withExtras Include %B and Bandwidth if true
 * @return array{
 *   mid: array<int,float|null>,
 *   upper: array<int,float|null>,
 *   lower: array<int,float|null>,
 *   pctB?: array<int,float|null>,
 *   bandwidth?: array<int,float|null>
 * }
 */
public static function bollingerBands(
    array $closes,
    int $period = 20,
    float $mult = 2.0,
    bool $sample = false,
    bool $withExtras = false
): array {
    $n = count($closes);
    $mid  = self::smaSeries($closes, $period);
    $sd   = self::stddevSeries($closes, $period, $sample);

    $upper = array_fill(0, $n, null);
    $lower = array_fill(0, $n, null);
    $pctB  = $withExtras ? array_fill(0, $n, null) : null;
    $bw    = $withExtras ? array_fill(0, $n, null) : null;

    for ($i = 0; $i < $n; $i++) {
        if ($mid[$i] === null || $sd[$i] === null) continue;
        $upper[$i] = $mid[$i] + $mult * $sd[$i];
        $lower[$i] = $mid[$i] - $mult * $sd[$i];

        if ($withExtras) {
            $range = $upper[$i] - $lower[$i];
            $pctB[$i] = ($range == 0.0) ? null : ($closes[$i] - $lower[$i]) / $range;
            $bw[$i]   = ($mid[$i] == 0.0) ? null : ($upper[$i] - $lower[$i]) / $mid[$i]; // normalized width
        }
    }

    $out = ['mid' => $mid, 'upper' => $upper, 'lower' => $lower];
    if ($withExtras) { $out['pctB'] = $pctB; $out['bandwidth'] = $bw; }
    return $out;
}

/**
 * Attach Bollinger outputs onto candle rows.
 *
 * @param array<int, array<string,mixed>> $candles  Each needs 'close'
 * @param int   $period
 * @param float $mult
 * @param bool  $sample
 * @param bool  $withExtras
 * @param string $midKey
 * @param string $upKey
 * @param string $loKey
 * @param string $pctBKey
 * @param string $bwKey
 * @return void
 */
public static function attachBollingerToCandles(
    array &$candles,
    int $period = 20,
    float $mult = 2.0,
    bool $sample = false,
    bool $withExtras = false,
    string $midKey = 'bb_mid',
    string $upKey = 'bb_upper',
    string $loKey = 'bb_lower',
    string $pctBKey = 'bb_pctB',
    string $bwKey = 'bb_bandwidth'
): void {
    $closes = array_map(fn($c) => (float)$c['close'], $candles);
    $bb = self::bollingerBands($closes, $period, $mult, $sample, $withExtras);

    foreach ($candles as $i => &$c) {
        $c[$midKey] = $bb['mid'][$i];
        $c[$upKey]  = $bb['upper'][$i];
        $c[$loKey]  = $bb['lower'][$i];
        if ($withExtras) {
            $c[$pctBKey] = $bb['pctB'][$i];
            $c[$bwKey]   = $bb['bandwidth'][$i];
        }
    }
    unset($c);
}

    /**
     * Identify candle type by body/wick proportions.
     *
     * @param float $open
     * @param float $high
     * @param float $low
     * @param float $close
     * @return string
     */
    public static function candleType(float $open, float $high, float $low, float $close): string
    {
        // Tunable thresholds
        $STAR_UPPER_TO_BODY  = 1.5;  // was 2.0
        $STAR_MAX_LOWER_PCT  = 0.15; // lower wick <= 15% of range
        $STAR_MAX_BODY_PCT   = 0.40; // body <= 40% of range

        // Precomputed pieces
        $body = abs($close - $open);
        $range = max(1e-12, $high - $low); // avoid div-by-zero
        $upper = $high - max($open, $close);
        $lower = min($open, $close) - $low;

        $bodyRatio  = $body / $range;
        $lowerRatio = $lower / $range;
        $upperRatio = $upper / $range;

        // Doji: tiny body
        if ($bodyRatio <= 0.1) return 'doji';

        // Marubozu: full body
        if ($bodyRatio >= 0.9) return ($close > $open) ? 'bullish marubozu' : 'bearish marubozu';

        // Hammer / Hanging man
        if ($lowerRatio >= 0.5 && $bodyRatio <= 0.3) {
            return ($close > $open) ? 'hammer' : 'hanging man';
        }

        // Shooting Star (bearish) / Inverted Hammer (bullish) — permissive
        if ($upper >= $STAR_UPPER_TO_BODY * $body
            && $lowerRatio <= $STAR_MAX_LOWER_PCT
            && $bodyRatio  <= $STAR_MAX_BODY_PCT) {
            return ($close > $open) ? 'inverted hammer' : 'shooting star';
        }

        // Spinning top
        if ($bodyRatio <= 0.3 && $upperRatio >= 0.2 && $lowerRatio >= 0.2) {
            return 'spinning top';
        }

        // Default: bullish/bearish candle
        return ($close > $open) ? 'bullish' : 'bearish';
    }

    /**
     * Attach per-session VWAP to candles.
     *
     * - Resets cumulative VWAP when the "session day" changes in $sessionTz.
     * - If 'time' is a UNIX timestamp or a parseable string, we use it to detect new days.
     * - If 'time' is missing, all candles are treated as one continuous session (no resets).
     * - Price basis:
     *      'typical' = (H+L+C)/3   (default)
     *      'ohlc4'   = (O+H+L+C)/4
     *      'close'   = C
     *
     * @param array<int,array<string,mixed>> $candles  Each needs: open, high, low, close, volume; optional 'time'
     * @param string $outKey      Field name to write (default 'session_vwap')
     * @param string $priceMode   'typical'|'ohlc4'|'close'
     * @param string $sessionTz   Session day boundary timezone (default 'UTC')
     * @param string $volumeKey   Field name for volume (default 'volume')
     * @return void
     */
    public static function attachSessionVWAP(
        array &$candles,
        string $outKey    = 'session_vwap',
        string $priceMode = 'typical',
        string $sessionTz = 'UTC',
        string $volumeKey = 'volume'
    ): void {
        if (empty($candles)) return;

        $tz = new \DateTimeZone($sessionTz);
        $cumPV = 0.0;   // cumulative price*volume for the session
        $cumV  = 0.0;   // cumulative volume for the session
        $prevSessionKey = null;

        $priceOf = function(array $c) use ($priceMode): ?float {
            $o = isset($c['open'])  ? (float)$c['open']  : null;
            $h = isset($c['high'])  ? (float)$c['high']  : null;
            $l = isset($c['low'])   ? (float)$c['low']   : null;
            $cl= isset($c['close']) ? (float)$c['close'] : null;

            if ($priceMode === 'close')   return ($cl !== null) ? $cl : null;
            if ($priceMode === 'ohlc4')   return ($o!==null && $h!==null && $l!==null && $cl!==null) ? ($o+$h+$l+$cl)/4.0 : null;
            /* typical (default) */       return ($h!==null && $l!==null && $cl!==null) ? ($h+$l+$cl)/3.0 : null;
        };

        $sessionKeyOf = function($time) use ($tz): ?string {
            if ($time === null) return null;
            // Numeric unix seconds
            if (is_int($time) || is_float($time)) {
                $dt = (new \DateTimeImmutable('@'.(int)$time))->setTimezone($tz);
                return $dt->format('Y-m-d');
            }
            // String parse
            try {
                $dt = new \DateTimeImmutable((string)$time, $tz);
                // If the string had a TZ, PHP respects it; we care only about the day *in $tz*
                $dt = $dt->setTimezone($tz);
                return $dt->format('Y-m-d');
            } catch (\Exception $e) {
                return null; // unparseable → treat as same session
            }
        };

        foreach ($candles as &$c) {
            $sessionKey = array_key_exists('time', $c) ? $sessionKeyOf($c['time']) : null;

            // Reset when day changes (if we can compute a key)
            if ($sessionKey !== null && $prevSessionKey !== null && $sessionKey !== $prevSessionKey) {
                $cumPV = 0.0;
                $cumV  = 0.0;
            }
            $prevSessionKey = $sessionKey ?? $prevSessionKey;

            $tp  = $priceOf($c);
            $vol = isset($c[$volumeKey]) ? max(0.0, (float)$c[$volumeKey]) : 0.0;

            if ($tp !== null && $vol > 0.0) {
                $cumPV += $tp * $vol;
                $cumV  += $vol;
            }
            $c[$outKey] = ($cumV > 0.0) ? ($cumPV / $cumV) : null;
        }
        unset($c);
    }

    /**
     * Attach all main indicators to candles array
     *
     * @param array<int, array<string,mixed>> $candles Each candle needs 'high', 'low', 'close'
     * @return void
     */
    public static function attachAllIndicators(array &$candles): void
    {
        if (empty($candles)) return;

        $highs  = array_map(fn($c) => (float)$c['high'], $candles);
        $lows   = array_map(fn($c) => (float)$c['low'], $candles);
        $closes = array_map(fn($c) => (float)$c['close'], $candles);

        // Calculate all indicators
        $rsiValues = self::rsi($closes, 14);
        $stochRsi = self::stochRsi($closes, 14, 14, 3, 3, 'SMA');
        $ema9Series = self::emaSeries($closes, 9);
        $ema20Series = self::emaSeries($closes, 20);
        $stochSlow = self::stochasticSlow($highs, $lows, $closes, 14, 3, 3, 'SMA');
        $macd = self::macd($closes, 8, 21, 5);
        $bb = self::bollingerBands($closes, 20, 2.0, false, true); // Include %B and Bandwidth

        // Attach Session VWAP (this modifies candles directly)
        self::attachSessionVWAP($candles, 'session_vwap', 'typical', 'America/New_York');

        // Attach to each candle
        foreach ($candles as $i => &$candle) {
            $candle['rsi'] = $rsiValues[$i];
            $candle['stoch_rsi_k'] = $stochRsi['k'][$i];
            $candle['stoch_rsi_d'] = $stochRsi['d'][$i];
            $candle['ema9'] = $ema9Series[$i];
            $candle['ema20'] = $ema20Series[$i];
            $candle['stoch_slow_k'] = $stochSlow['percentK'][$i];
            $candle['stoch_slow_d'] = $stochSlow['percentD'][$i];
            $candle['macd_line'] = $macd['macd'][$i];
            $candle['macd_signal'] = $macd['signal'][$i];
            $candle['macd_hist'] = $macd['hist'][$i];
            // Bollinger Bands
            $candle['bb_mid'] = $bb['mid'][$i];
            $candle['bb_upper'] = $bb['upper'][$i];
            $candle['bb_lower'] = $bb['lower'][$i];
            $candle['bb_pctB'] = $bb['pctB'][$i];
            $candle['bb_bandwidth'] = $bb['bandwidth'][$i];
            // Candle type analysis
            $candle['candle_type'] = self::candleType(
                (float)$candle['open'],
                (float)$candle['high'],
                (float)$candle['low'],
                (float)$candle['close']
            );
        }
        unset($candle);
    }
}
