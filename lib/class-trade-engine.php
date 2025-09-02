<?php

/**
 * TradeEngine
 * - Per-timeframe scoring (0–100): Candle Engine + RSI Trio + MACD + Guardrails
 * - Aggregation across timeframes with scalper-first weights
 * - Velocity/Acceleration (Δ over 3 windows), confidence & synergy notes
 *
 * Assumes: TechIndicators::attachAllIndicators(), TechIndicators::attachSessionVWAP()
 * Candle array order: oldest → newest
 */
class TradeEngine
{
    // ---------- PUBLIC ENTRY POINTS ----------

    /**
     * Score multiple timeframes and aggregate to an overall 0..100.
     *
     * @param array<string, array<int, array<string,mixed>>> $byTf  e.g. ['1m'=>[...], '5m'=>[...]]
     * @param array{
     *   sessionTz?:string,
     *   useSessionVWAP?:bool,
     *   tfWeights?:array<string,float>,
     *   synergyStrictUp?:float,
     *   synergyStrictDown?:float,
     *   minHistory?:int
     * } $opts
     * @return array{
     *   per_timeframe: array<string, array{score:float, velocity:float, accel:float, reasons:array, flags:array}>,
     *   overall: array{score:float, velocity:float, accel:float, confidence:float, synergy:string}
     * }
     */
    public static function scoreAll(array $byTf, array $opts = []): array
    {
        $sessionTz       = $opts['sessionTz']        ?? 'America/New_York';
        $useSessionVWAP  = $opts['useSessionVWAP']   ?? true;
        $tfWeightsCustom = $opts['tfWeights']        ?? null;
        $strictUp        = $opts['synergyStrictUp']  ?? 55.0;
        $strictDown      = $opts['synergyStrictDown']?? 45.0;
        $minHistory      = (int)($opts['minHistory'] ?? 30);

        // 1) Per-timeframe scoring with velocity/accel
        $per = [];
        foreach ($byTf as $tf => $candles) {
            $candles = array_values($candles);
            if (count($candles) < $minHistory) continue;

            // Ensure indicators
            if (!self::hasIndicators($candles)) {
                TechIndicators::attachAllIndicators($candles);
            }
            if ($useSessionVWAP) {
                TechIndicators::attachSessionVWAP($candles, outKey:'session_vwap', sessionTz:$sessionTz);
                foreach ($candles as &$c) { $c['vwap'] = $c['session_vwap'] ?? ($c['vwap'] ?? null); } unset($c);
            }

            // Build tiny score series for t-2, t-1, t
            $series = self::timeframeScoreSeries($candles, 3, $minHistory);
            $m = count($series);
            $s0 = $series[$m-1]['score'];
            $s1 = $series[$m-2]['score'] ?? $s0;
            $s2 = $series[$m-3]['score'] ?? $s1;

            $vel = $s0 - $s1;
            $acc = ($s0 - $s1) - ($s1 - $s2);

            $per[$tf] = [
                'score'    => round($s0, 2),
                'velocity' => round($vel, 2),
                'accel'    => round($acc, 2),
                'reasons'  => $series[$m-1]['reasons'] ?? [],
                'flags'    => $series[$m-1]['flags'] ?? ['overbought'=>false,'oversold'=>false],
            ];
        }

        if (empty($per)) {
            return [
                'per_timeframe' => [],
                'overall' => ['score'=>50, 'velocity'=>0, 'accel'=>0, 'confidence'=>0.0, 'synergy'=>'no_data']
            ];
        }

        // 2) Aggregate to overall
        $overall = self::aggregate($per, $tfWeightsCustom, $strictUp, $strictDown);

        return [
            'per_timeframe' => $per,
            'overall'       => $overall,
        ];
    }

    /**
     * Score a single timeframe array (0..100) — exposed if you want it directly.
     *
     * @param array<int,array<string,mixed>> $candles
     * @param int $minHistory
     * @return array{score:float, breakdown:array, reasons:array, flags:array}
     */
    public static function scoreTimeframe(array $candles, int $minHistory = 30): array
    {
        if (count($candles) < $minHistory) {
            return ['score'=>50,'breakdown'=>['candle_engine'=>50,'rsi_trio'=>50,'macd'=>50],'reasons'=>['insufficient_history'],'flags'=>['overbought'=>false,'oversold'=>false]];
        }
        $ce  = self::candleEngineScore($candles);
        $rsi = self::rsiTrioScore($candles);
        $mac = self::macdScore($candles);

        // Guardrails
        $guard = self::applyGuardrails($candles, $ce, $rsi, $mac);

        // Blend (RSI > Candle > MACD; scalper-friendly)
        $score = 0.45*$rsi['score'] + 0.35*$ce['score'] + 0.20*$mac['score'];
        $score = min(max($score * $guard['mult'], $guard['floor']), $guard['cap']);

        $reasons = array_merge($rsi['reasons'],$mac['reasons'],$ce['reasons'],$guard['reasons']);
        $reasons = array_slice(array_values(array_unique($reasons)), 0, 8);

        return [
            'score'     => round($score, 2),
            'breakdown' => ['candle_engine'=>$ce['score'],'rsi_trio'=>$rsi['score'],'macd'=>$mac['score']],
            'reasons'   => $reasons,
            'flags'     => ['overbought'=>$rsi['flags']['overbought'] ?? false, 'oversold'=>$rsi['flags']['oversold'] ?? false],
        ];
    }

    // ---------- CONFIG / CONSTANTS ----------

    // Candle Engine weights
    private static array $CE_W = [
        'body'       => 0.28,
        'rangeExp'   => 0.12,
        'wicks'      => 0.05,
        'ema_stack'  => 0.14,
        'ema_dist20' => 0.06,
        'ema_slope9' => 0.10,
        'vwap_pos'   => 0.17,
        'cross'      => 0.08,
        'pattern'    => 0.05,
    ];

    // Pattern light points
    private static array $PATTERN_POINTS = [
        'bullish marubozu' => +0.60,
        'bearish marubozu' => -0.60,
        'hammer'           => +0.50,
        'hanging man'      => -0.50,
        'inverted hammer'  => +0.40,
        'shooting star'    => -0.40,
        'spinning top'     => -0.20,
    ];

    // Default timeframe weights (scalper-first)
    private static array $TF_WEIGHTS_DEFAULT = [
        '1m'  => 0.30,
        '5m'  => 0.27,
        '15m' => 0.20,
        '1h'  => 0.13,
        '4h'  => 0.06,
        '1d'  => 0.04,
    ];

    // RSI periods
    const RSI_FAST = 7;
    const RSI_REG  = 14;
    const RSI_SLOW = 21;

    const ATR_LEN  = 20;  // for candle engine normalization
    const VOL_EMA  = 20;  // volume thrust baseline
    const LOOKBACK = 3;   // use last 3 bars for slopes/deltas

    // ---------- AGGREGATOR CORE ----------

    /** @param array<string, array{score:float, velocity:float, accel:float, reasons:array, flags:array}> $per */
    private static function aggregate(array $per, ?array $customWeights, float $strictUp, float $strictDown): array
    {
        // Normalize weights to the frames we have
        $weights = [];
        $cfg = $customWeights ?? self::$TF_WEIGHTS_DEFAULT;
        $sum = 0.0;
        foreach ($per as $tf => $_) {
            $w = $cfg[$tf] ?? 0.0;
            if ($w > 0) { $weights[$tf] = $w; $sum += $w; }
        }
        if ($sum <= 0) {
            $cnt = max(1, count($per));
            foreach ($per as $tf => $_) $weights[$tf] = 1.0/$cnt;
        } else {
            foreach ($weights as $tf => $w) $weights[$tf] = $w / $sum;
        }

        // Weighted base + dynamics
        $base = 0.0; $wVel = 0.0; $wAcc = 0.0;
        foreach ($per as $tf => $row) {
            $w = $weights[$tf] ?? 0.0;
            $base += $w * $row['score'];
            $wVel += $w * $row['velocity'];
            $wAcc += $w * $row['accel'];
        }

        // Synergy (5m + 15m + 1h)
        [$synAdj, $synNote] = self::synergyBonus($per, $strictUp, $strictDown);

        // Growth accent (5m & 15m; 1h micro-bonus)
        $growthAdj = self::growthAccent($per);

        $overall = max(0.0, min(100.0, $base + $synAdj + $growthAdj));

        // Confidence 0..1 (agreement + magnitude)
        $conf = self::confidenceScore($per, $overall, $weights);

        return [
            'score'      => round($overall, 2),
            'velocity'   => round($wVel, 2),
            'accel'      => round($wAcc, 2),
            'confidence' => round($conf, 2),
            'synergy'    => $synNote,
        ];
    }

    private static function synergyBonus(array $per, float $up, float $down): array
    {
        $have = isset($per['5m'], $per['15m'], $per['1h']);
        if (!$have) return [0.0, 'synergy:insufficient'];

        $s5=$per['5m']['score']; $s15=$per['15m']['score']; $s1h=$per['1h']['score'];
        $bull = fn($s)=> $s >= $up;
        $bear = fn($s)=> $s <= $down;

        if ($bull($s5) && $bull($s15) && $bull($s1h)) return [ +6.0, 'synergy:5m+15m+1h bullish' ];
        if ($bear($s5) && $bear($s15) && $bear($s1h)) return [ -6.0, 'synergy:5m+15m+1h bearish' ];

        $mid = fn($s)=> ($s > $down && $s < $up);
        $triples = [[$s5,$s15,$s1h],[$s5,$s1h,$s15],[$s15,$s1h,$s5]];
        foreach ($triples as [$a,$b,$c]) {
            if (($bull($a)&&$bull($b)&&$mid($c)) || ($bear($a)&&$bear($b)&&$mid($c))) {
                return [ +3.0, 'synergy:two strong, one neutral' ];
            }
        }

        $sign = fn($s)=> ($s>=$up?1:($s<=$down?-1:0));
        $sig = [$sign($s5), $sign($s15), $sign($s1h)];
        if (in_array(1,$sig,true) && in_array(-1,$sig,true) && !in_array(0,$sig,true)) {
            return [ -3.0, 'synergy:conflict in golden triangle' ];
        }

        return [0.0, 'synergy:mixed'];
    }

    private static function growthAccent(array $per): float
    {
        $adj = 0.0;
        if (isset($per['5m'], $per['15m'])) {
            $v5=$per['5m']['velocity']; $v15=$per['15m']['velocity'];
            if ($v5 > 0 && $v15 > 0) $adj += 3.0;
            elseif ($v5 < 0 && $v15 < 0) $adj -= 3.0;

            if (isset($per['1h'])) {
                $v1h = $per['1h']['velocity'];
                if ($v1h > 0 && $v5 > 0 && $v15 > 0) $adj += 2.0;
                elseif ($v1h < 0 && $v5 < 0 && $v15 < 0) $adj -= 2.0;
            }
        }
        return $adj;
    }

    private static function confidenceScore(array $per, float $overall, array $weights): float
    {
        $signOverall = ($overall >= 50) ? 1 : -1;
        $agree = 0.0; $mag = 0.0; $wsum = 0.0;

        foreach ($per as $tf => $row) {
            $w = $weights[$tf] ?? 0.0; if ($w <= 0) continue;
            $s = $row['score'];
            $signTf = ($s >= 50) ? 1 : -1;
            if ($signTf === $signOverall) $agree += $w;
            $mag += $w * min(1.0, abs($s - 50.0) / 50.0);
            $wsum += $w;
        }
        if ($wsum <= 0) return 0.0;
        return max(0.0, min(1.0, 0.5*($agree/$wsum) + 0.5*($mag/$wsum)));
    }

    // ---------- TIMEFRAME SCORE SERIES (for velocity/accel) ----------

    /**
     * Build a short series (e.g., 3 points) of timeframe scores by truncation.
     *
     * @param array<int,array<string,mixed>> $candles
     * @param int $points
     * @param int $minHistory
     * @return array<int, array{score:float, reasons:array, flags:array}>
     */
    private static function timeframeScoreSeries(array $candles, int $points = 3, int $minHistory = 30): array
    {
        $n = count($candles);
        $out = [];
        for ($k = $points-1; $k >= 0; $k--) {
            $end = $n - $k;
            if ($end < $minHistory) {
                $out[] = ['score'=>50.0, 'reasons'=>['insufficient_history'], 'flags'=>['overbought'=>false,'oversold'=>false]];
                continue;
            }
            $slice = array_slice($candles, 0, $end);
            $sc    = self::scoreTimeframe($slice, $minHistory);
            $out[] = ['score'=>$sc['score'], 'reasons'=>$sc['reasons'], 'flags'=>$sc['flags']];
        }
        return $out;
    }

    // ---------- CANDLE ENGINE ----------

    private static function candleEngineScore(array $candles): array
    {
        $clip = fn($x,$a,$b)=> max($a, min($b,$x));
        $sgn  = fn($x)=> ($x>0?1:($x<0?-1:0));

        $n = count($candles);
        $L = self::LOOKBACK;

        // Baselines
        $atr = self::atr(array_slice($candles, -max(self::ATR_LEN+1, $L+5)));
        $atr = max($atr, 1e-6);
        $emaVol = self::emaLast(array_column($candles, 'volume'), self::VOL_EMA) ?? 0.0;

        // Last 3 candles
        $C = array_slice($candles, -$L);
        [$c2,$c1,$c0] = $C;

        // 1) Body momentum (ATR-normalized, weighted)
        $bodyNorm = function($c) use ($atr) {
            $delta = ($c['close'] - $c['open']);
            return tanh($delta / ($atr * 0.8));
        };
        $bm2 = $bodyNorm($c2); $bm1 = $bodyNorm($c1); $bm0 = $bodyNorm($c0);
        $bodyMomentum = 0.2*$bm2 + 0.3*$bm1 + 0.5*$bm0;

        // Growth accent inside CE
        $growthAccent = 0.0;
        if ($bm2 < $bm1 && $bm1 < $bm0 && $bm0 > 0) $growthAccent += 0.12;
        if ($bm2 > $bm1 && $bm1 > $bm0 && $bm0 < 0) $growthAccent -= 0.12;

        // 2) Range expansion vs EMA(range)
        $ranges = array_map(fn($c)=> max(0.0, $c['high']-$c['low']), array_slice($candles, -max(25,$L+10)));
        $rangeEma = self::emaLast($ranges, 20) ?? (array_sum($ranges)/max(1,count($ranges)));
        $lastRange = max(1e-6, $c0['high']-$c0['low']);
        $expansionRatio = $lastRange / max(1e-6, $rangeEma);
        $expansion = tanh(($expansionRatio - 1.0)) * $sgn($c0['close'] - $c0['open']);

        // 3) Wick quality
        $r0 = max(1e-9, $c0['high'] - $c0['low']);
        $upper0 = $c0['high'] - max($c0['open'],$c0['close']);
        $lower0 = min($c0['open'],$c0['close']) - $c0['low'];
        $wickQuality = $clip(($lower0 - $upper0) / $r0, -1, 1);

        // 4) EMA structure
        $stack = 0.0;
        if ($c0['close'] > $c0['ema9'] && $c0['ema9'] > $c0['ema20'])      $stack = +1.0;
        elseif ($c0['close'] < $c0['ema9'] && $c0['ema9'] < $c0['ema20'])  $stack = -1.0;
        elseif ($c0['close'] > $c0['ema9'] && $c0['close'] > $c0['ema20']) $stack = +0.4;
        elseif ($c0['close'] < $c0['ema9'] && $c0['close'] < $c0['ema20']) $stack = -0.4;

        $emaSlope = tanh( (($c0['ema9'] - $c2['ema9'])) / ($atr * 0.5) );

        $dist20bp = 10000.0 * (($c0['close'] - $c0['ema20']) / max(1e-9,$c0['ema20']));
        $emaDist20= tanh($dist20bp / 60.0);

        // 5) Cross events ema9/ema20 (within last 3)
        $d2 = $c2['ema9'] - $c2['ema20'];
        $d0 = $c0['ema9'] - $c0['ema20'];
        $cross = 0.0;
        if ($d2 <= 0 && $d0 > 0) $cross = +0.30;
        if ($d2 >= 0 && $d0 < 0) $cross = -0.30;

        // 6) VWAP position + reclaim (using session_vwap if provided)
        $vwapPos = tanh( 10000.0 * (($c0['close'] - $c0['vwap']) / max(1e-9,$c0['vwap'])) / 50.0 );
        $pv2 = $c2['close'] - $c2['vwap'];
        $pv0 = $c0['close'] - $c0['vwap'];
        $vwapReclaim = 0.0;
        if ($pv2 <= 0 && $pv0 > 0) $vwapReclaim = +0.30;
        if ($pv2 >= 0 && $pv0 < 0) $vwapReclaim = -0.30;

        // 7) Pattern (light)
        $pattern = 0.0;
        if (!empty($c0['candle_type'])) {
            $key = strtolower(trim($c0['candle_type']));
            if (isset(self::$PATTERN_POINTS[$key])) $pattern = self::$PATTERN_POINTS[$key];
        }
        $pattern = max(-1.0, min(1.0, $pattern));

        // Weighted sum
        $raw =
            self::$CE_W['body']       * $bodyMomentum +
            self::$CE_W['rangeExp']   * $expansion +
            self::$CE_W['wicks']      * $wickQuality +
            self::$CE_W['ema_stack']  * $stack +
            self::$CE_W['ema_dist20'] * $emaDist20 +
            self::$CE_W['ema_slope9'] * $emaSlope +
            self::$CE_W['vwap_pos']   * $vwapPos +
            self::$CE_W['cross']      * $cross +
            self::$CE_W['pattern']    * $pattern +
            $growthAccent;

        // Volume thrust multiplier
        $volRatio = ($emaVol > 0) ? ($c0['volume'] / $emaVol) : 1.0;
        $volRatio = $clip($volRatio, 0.5, 3.0);
        if     ($volRatio <= 1.0) $mult = 0.92 + 0.08*($volRatio - 0.5)/0.5;
        elseif ($volRatio <= 2.0) $mult = 1.00 + 0.08*($volRatio - 1.0);
        else                      $mult = 1.08 + 0.04*($volRatio - 2.0);
        $mult = min(1.12, max(0.90, $mult));

        $raw = $clip($raw * $mult, -1.0, 1.0);
        $score = round(50.0 * ($raw + 1.0), 2);

        $reasons = [];
        if ($stack > 0.8) $reasons[] = 'EMA9>EMA20 with price above';
        if ($stack < -0.8) $reasons[] = 'Below EMA9 & EMA20';
        if ($cross > 0.25) $reasons[] = 'EMA9 up-crossed EMA20';
        if ($cross < -0.25) $reasons[] = 'EMA9 down-crossed EMA20';
        if ($vwapReclaim > 0.25) $reasons[] = 'VWAP reclaim';
        if ($vwapReclaim < -0.25) $reasons[] = 'VWAP loss';
        if ($expansion > 0.2) $reasons[] = 'Range expanding upward';
        if ($expansion < -0.2) $reasons[] = 'Range expanding downward';
        if ($pattern > 0.4) $reasons[] = 'Bullish pattern';
        if ($pattern < -0.4) $reasons[] = 'Bearish pattern';
        if ($volRatio >= 1.5) $reasons[] = 'Above-average volume';
        if ($volRatio <= 0.7) $reasons[] = 'Subdued volume';

        return ['score'=>$score, 'reasons'=>$reasons];
    }

    // ---------- RSI TRIO ----------

    private static function rsiTrioScore(array $candles): array
    {
        $closes = array_column($candles, 'close');
        $rsiF = self::lastRsiSeries($closes, self::RSI_FAST);
        $rsiR = self::lastRsiSeries($closes, self::RSI_REG);
        $rsiS = self::lastRsiSeries($closes, self::RSI_SLOW);

        $L = self::LOOKBACK;
        $t  = count($closes) - 1;
        $t3 = max(0, $t - $L);

        $lvl = fn($x)=> tanh(($x - 50.0) / 12.0);
        $slp = fn($a,$b)=> tanh( ($a - $b) / 6.0 );

        $F_lvl = $lvl($rsiF[$t] ?? 50.0); $F_slp = $slp($rsiF[$t] ?? 50.0, $rsiF[$t3] ?? 50.0);
        $R_lvl = $lvl($rsiR[$t] ?? 50.0); $R_slp = $slp($rsiR[$t] ?? 50.0, $rsiR[$t3] ?? 50.0);
        $S_lvl = $lvl($rsiS[$t] ?? 50.0); $S_slp = $slp($rsiS[$t] ?? 50.0, $rsiS[$t3] ?? 50.0);

        $fastPts = 60*$F_slp + 20*$F_lvl;
        $regPts  = 40*$R_lvl + 30*$R_slp;
        $slowPts = 45*$S_slp + 15*$S_lvl;

        $norm = fn($p)=> tanh($p / 90.0);
        $fast = $norm($fastPts);
        $reg  = $norm($regPts);
        $slow = $norm($slowPts);

        $agree = ( ($F_slp > 0 && $S_slp > 0) || ($F_slp < 0 && $S_slp < 0) ) ? 1 : ( ($F_slp == 0 || $S_slp == 0) ? 0 : -1 );
        $alignBonus = 0.06 * $agree;

        $growth = 0.0;
        if ($F_slp > 0.25 && $R_slp > 0.15) $growth += 0.06;
        if ($F_slp < -0.25 && $R_slp < -0.15) $growth -= 0.06;

        $raw = 0.45*$reg + 0.35*$fast + 0.20*$slow + $alignBonus + $growth;
        $raw = max(-1.0, min(1.0, $raw));
        $score = round(50.0 * ($raw + 1.0), 2);

        $reasons = [];
        if ($R_lvl > 0.3) $reasons[] = 'RSI(14) above 60';
        if ($R_lvl < -0.3) $reasons[] = 'RSI(14) below 40';
        if ($F_slp > 0.4) $reasons[] = 'RSI fast rising';
        if ($F_slp < -0.4) $reasons[] = 'RSI fast falling';
        if ($agree > 0) $reasons[] = 'Fast RSI trend validated by slow';
        if ($agree < 0) $reasons[] = 'Fast RSI disagrees with slow';

        $flags = ['overbought' => (($rsiR[$t] ?? 50) >= 80), 'oversold' => (($rsiR[$t] ?? 50) <= 20)];

        return ['score'=>$score, 'reasons'=>$reasons, 'flags'=>$flags];
    }

    // ---------- MACD ----------

    private static function macdScore(array $candles): array
    {
        $L = self::LOOKBACK;
        $t  = count($candles) - 1;
        $t3 = max(0, $t - $L);

        $H  = array_column($candles, 'macd_hist');
        $ML = array_column($candles, 'macd_line');
        $SG = array_column($candles, 'macd_signal');

        if (!isset($H[$t], $H[$t3])) return ['score'=>50,'reasons'=>['macd_neutral']];

        $sigma = self::stddev(array_slice($H, max(0, $t-50), 50)) ?: 0.5;

        $sgn = ($H[$t] > 0) ? 1 : (($H[$t] < 0) ? -1 : 0);
        $exp = tanh( (($H[$t] - $H[$t3])) / (0.9*$sigma) );

        $prox = 1.0 - min(1.0, abs(($ML[$t] ?? 0) - ($SG[$t] ?? 0)) / max(1e-6, 0.9*$sigma));

        $raw = 0.50*$exp + 0.30*$sgn + 0.20*($prox * (($exp>=0)? 1 : -1));
        $raw = max(-1.0, min(1.0, $raw));
        $score = round(50.0 * ($raw + 1.0), 2);

        $reasons = [];
        if ($sgn > 0) $reasons[] = 'MACD histogram > 0';
        if ($sgn < 0) $reasons[] = 'MACD histogram < 0';
        if ($exp > 0.3) $reasons[] = 'MACD momentum expanding';
        if ($exp < -0.3) $reasons[] = 'MACD momentum contracting';
        if ($prox > 0.6 && $exp > 0) $reasons[] = 'Bull cross proximity';
        if ($prox > 0.6 && $exp < 0) $reasons[] = 'Bear cross proximity';

        return ['score'=>$score, 'reasons'=>$reasons];
    }

    // ---------- GUARDRAILS ----------

    private static function applyGuardrails(array $candles, array $ce, array $rsi, array $mac): array
    {
        $mult = 1.0; $cap = 100.0; $floor = 0.0; $reasons = [];

        $overbought = $rsi['flags']['overbought'] ?? false;
        $oversold   = $rsi['flags']['oversold']   ?? false;

        if ($overbought && ($mac['score'] ?? 50) < 50) { $cap = min($cap, 70.0); $reasons[] = 'OB dampen (RSI>80 & MACD not confirming)'; }
        if ($oversold   && ($mac['score'] ?? 50) > 50) { $floor = max($floor, 30.0); $reasons[] = 'OS lift (RSI<20 & MACD improving)'; }

        $last = $candles[count($candles)-1];
        $stretchVwap = ($last['vwap'] ?? 0) ? abs(($last['close'] - $last['vwap'])/ $last['vwap']) : 0.0;
        $stretchE20  = ($last['ema20'] ?? 0) ? abs(($last['close'] - $last['ema20'])/$last['ema20']) : 0.0;
        if ($stretchVwap > 0.015 || $stretchE20 > 0.02) { $mult *= 0.95; $reasons[] = 'Stretch dampen (far from VWAP/EMA20)'; }

        $atr = self::atr(array_slice($candles, -max(self::ATR_LEN+1, 25)));
        $lastRange = max(1e-6, $last['high'] - $last['low']);
        $ranges = array_map(fn($c)=> max(0.0,$c['high']-$c['low']), array_slice($candles, -25));
        $rangeMed = max(1e-6, self::median($ranges)); // Prevent division by zero
        $volEma = self::emaLast(array_column($candles, 'volume'), self::VOL_EMA) ?? 0.0;
        if (($lastRange < 0.5*$rangeMed) || (($last['volume'] ?? 0) < 0.6*$volEma)) {
            $mult *= 0.95; $reasons[] = 'Low participation dampen';
        }

        return ['mult'=>$mult,'cap'=>$cap,'floor'=>$floor,'reasons'=>$reasons];
    }

    // ---------- HELPERS ----------

    private static function hasIndicators(array $candles): bool
    {
        $last = $candles[count($candles)-1];
        return isset($last['ema9'], $last['ema20'], $last['macd_line'], $last['macd_signal'], $last['macd_hist']);
    }

    private static function atr(array $candles, int $len = self::ATR_LEN): float
    {
        $n = count($candles);
        if ($n < $len + 1) return 0.0;
        $trs = [];
        for ($i=1; $i<$n; $i++) {
            $h = $candles[$i]['high']; $l = $candles[$i]['low']; $pc = $candles[$i-1]['close'];
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }
        $alpha = 1.0 / $len;
        $ema = array_sum(array_slice($trs, 0, $len)) / $len;
        for ($i=$len; $i<count($trs); $i++) $ema = ($trs[$i] * $alpha) + ($ema * (1 - $alpha));
        return $ema;
    }

    private static function emaLast(array $values, int $period): ?float
    {
        $n = count($values);
        if ($n < $period) return null;
        $alpha = 2 / ($period + 1);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        for ($i = $period; $i < $n; $i++) $ema = ($values[$i] - $ema)*$alpha + $ema;
        return $ema;
    }

    private static function stddev(array $vals): float
    {
        $n = count($vals); if ($n === 0) return 0.0;
        $m = array_sum($vals)/$n;
        $s = 0.0; foreach ($vals as $v) { $d = $v - $m; $s += $d*$d; }
        return sqrt($s / max(1,$n));
    }

    private static function median(array $vals): float
    {
        $n = count($vals); if ($n === 0) return 0.0;
        sort($vals); $mid = intdiv($n,2);
        return ($n % 2) ? $vals[$mid] : 0.5*($vals[$mid-1] + $vals[$mid]);
    }

    private static function lastRsiSeries(array $closes, int $period): array
    {
        $n = count($closes);
        $rsi = array_fill(0, $n, null);
        if ($n <= $period) return $rsi;

        $gain = 0.0; $loss = 0.0;
        for ($i=1; $i <= $period; $i++) {
            $d = $closes[$i] - $closes[$i-1];
            if ($d >= 0) $gain += $d; else $loss -= $d;
        }
        $avgG = $gain / $period; $avgL = $loss / $period;
        $rsi[$period] = ($avgL == 0.0) ? 100.0 : (100 - 100/(1 + $avgG/$avgL));

        for ($i=$period+1; $i<$n; $i++) {
            $d = $closes[$i] - $closes[$i-1];
            $g = ($d > 0) ? $d : 0.0; $l = ($d < 0) ? -$d : 0.0;
            $avgG = (($avgG*($period-1)) + $g)/$period;
            $avgL = (($avgL*($period-1)) + $l)/$period;
            $rsi[$i] = ($avgL == 0.0) ? 100.0 : (100 - 100/(1 + $avgG/$avgL));
        }
        return $rsi;
    }
}
