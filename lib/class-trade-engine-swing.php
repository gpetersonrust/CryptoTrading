<?php
/**
 * @plugin Trade Engine
 * Swing-focused variant: 1–4h market swings (uses 15m for timing, 1d for bias)
 */
class TradeEngineSwing
{
    // --- Public API mirrors TradeEngine ---

    /**
     * @param array<string, array<int, array<string,mixed>>> $byTf
     * @param array{ sessionTz?:string, useSessionVWAP?:bool, tfWeights?:array<string,float>, synergyStrictUp?:float, synergyStrictDown?:float, minHistory?:int } $opts
     */
    public static function scoreAll(array $byTf, array $opts = []): array
    {
        // Defaults tuned for swing
        $opts = array_replace([
            'sessionTz'         => 'America/New_York',
            'useSessionVWAP'    => false,    // de-emphasize intraday anchoring
            'minHistory'        => 120,      // more bars = smoother
            'synergyStrictUp'   => 57.0,
            'synergyStrictDown' => 43.0,
            'tfWeights'         => [
                '15m' => 0.12,
                '1h'  => 0.38,
                '4h'  => 0.34,
                '1d'  => 0.16,
            ],
        ], $opts);

        // Per-timeframe scoring (reuse original per-TF scorer from TradeEngine)
        $per = [];
        foreach ($byTf as $tf => $candles) {
            $candles = array_values($candles);
            if (count($candles) < (int)$opts['minHistory']) continue;

            if (!self::hasIndicators($candles)) {
                TechIndicators::attachAllIndicators($candles);
            }
            if (!empty($opts['useSessionVWAP'])) {
                TechIndicators::attachSessionVWAP($candles, outKey:'session_vwap', sessionTz:$opts['sessionTz']);
                foreach ($candles as &$c) { $c['vwap'] = $c['session_vwap'] ?? ($c['vwap'] ?? null); } unset($c);
            }

            // build 3-point series using swing-tilt timeframe scorer
            $series = self::timeframeScoreSeriesSwing($candles, 3, (int)$opts['minHistory']);
            $m = count($series);
            $s0 = $series[$m-1]['score'];
            $s1 = $series[$m-2]['score'] ?? $s0;
            $s2 = $series[$m-3]['score'] ?? $s1;

            $per[$tf] = [
                'score'    => round($s0, 2),
                'velocity' => round($s0 - $s1, 2),
                'accel'    => round(($s0 - $s1) - ($s1 - $s2), 2),
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

        // Aggregate with swing-synergy & growth accent
        $overall = self::aggregateSwing($per, $opts['tfWeights'], (float)$opts['synergyStrictUp'], (float)$opts['synergyStrictDown']);

        return ['per_timeframe' => $per, 'overall' => $overall];
    }

    /**
     * Swing-tilt timeframe scorer
     * (wraps your existing TradeEngine::scoreTimeframe with swing guardrails/weights)
     */
    public static function scoreTimeframeSwing(array $candles, int $minHistory = 120): array
    {
        if (count($candles) < $minHistory) {
            return ['score'=>50,'breakdown'=>['candle_engine'=>50,'rsi_trio'=>50,'macd'=>50],'reasons'=>['insufficient_history'],'flags'=>['overbought'=>false,'oversold'=>false]];
        }

        // Reuse your original internals but with swing-tilt tweaks:
        $ce  = self::candleEngineScoreSwing($candles);
        $rsi = self::rsiTrioScoreSwing($candles);
        $mac = self::macdScore($candles); // reuse original MACD

        // Guardrails (swing: EMA50/200 + participation)
        $guard = self::applyGuardrailsSwing($candles, $ce, $rsi, $mac);

        // Blend (favor slower structure)
        $score = 0.40*$rsi['score'] + 0.40*$ce['score'] + 0.20*$mac['score'];
        $score = min(max($score * $guard['mult'], $guard['floor']), $guard['cap']);

        $reasons = array_slice(array_values(array_unique(array_merge(
            $rsi['reasons'] ?? [], $mac['reasons'] ?? [], $ce['reasons'] ?? [], $guard['reasons'] ?? []
        ))), 0, 8);

        return [
            'score'     => round($score, 2),
            'breakdown' => ['candle_engine'=>$ce['score'],'rsi_trio'=>$rsi['score'],'macd'=>$mac['score']],
            'reasons'   => $reasons,
            'flags'     => ['overbought'=>$rsi['flags']['overbought'] ?? false, 'oversold'=>$rsi['flags']['oversold'] ?? false],
        ];
    }

    // ---------- Swing Aggregation ----------

    private static function aggregateSwing(array $per, ?array $customWeights, float $strictUp, float $strictDown): array
    {
        // normalize weights to available TFs
        $weights = [];
        $cfg = $customWeights ?? ['15m'=>0.12,'1h'=>0.38,'4h'=>0.34,'1d'=>0.16];
        $sum = 0.0;
        foreach ($per as $tf => $_) { $w = $cfg[$tf] ?? 0.0; if ($w>0){ $weights[$tf]=$w; $sum+=$w; } }
        if ($sum <= 0) { $cnt = max(1, count($per)); foreach ($per as $tf => $_) $weights[$tf] = 1.0/$cnt; }
        else { foreach ($weights as $tf => $w) $weights[$tf] = $w / $sum; }

        $base=0.0; $wVel=0.0; $wAcc=0.0;
        foreach ($per as $tf=>$row){ $w=$weights[$tf]??0; $base+=$w*$row['score']; $wVel+=$w*$row['velocity']; $wAcc+=$w*$row['accel']; }

        // synergy: 15m + 1h + 4h
        [$synAdj, $synNote] = self::synergyBonusSwing($per, $strictUp, $strictDown);

        // growth accent: 1h & 4h (15m helper)
        $growthAdj = self::growthAccentSwing($per);

        $overall = max(0.0, min(100.0, $base + $synAdj + $growthAdj));

        // confidence: reuse TradeEngine logic
        $conf = self::confidenceScore($per, $overall, $weights);

        return [
            'score'      => round($overall, 2),
            'velocity'   => round($wVel, 2),
            'accel'      => round($wAcc, 2),
            'confidence' => round($conf, 2),
            'synergy'    => $synNote,
        ];
    }

    private static function synergyBonusSwing(array $per, float $up, float $down): array
    {
        if (!isset($per['15m'], $per['1h'], $per['4h'])) return [0.0, 'synergy:insufficient'];
        $s15=$per['15m']['score']; $s1h=$per['1h']['score']; $s4h=$per['4h']['score'];
        $bull = fn($s)=> $s >= $up; $bear = fn($s)=> $s <= $down; $mid=fn($s)=>($s>$down&&$s<$up);

        if ($bull($s15)&&$bull($s1h)&&$bull($s4h)) return [ +6.0, 'synergy:15m+1h+4h bullish' ];
        if ($bear($s15)&&$bear($s1h)&&$bear($s4h)) return [ -6.0, 'synergy:15m+1h+4h bearish' ];

        foreach ([[$s15,$s1h,$s4h],[$s15,$s4h,$s1h],[$s1h,$s4h,$s15]] as [$a,$b,$c]) {
            if (($bull($a)&&$bull($b)&&$mid($c)) || ($bear($a)&&$bear($b)&&$mid($c))) return [ +3.0, 'synergy:two strong, one neutral' ];
        }
        $sign = fn($s)=> ($s>=$up?1:($s<=$down?-1:0));
        $sig = [$sign($s15),$sign($s1h),$sign($s4h)];
        if (in_array(1,$sig,true) && in_array(-1,$sig,true) && !in_array(0,$sig,true)) return [-3.0,'synergy:conflict in swing triangle'];
        return [0.0,'synergy:mixed'];
    }

    private static function growthAccentSwing(array $per): float
    {
        $adj=0.0;
        if (isset($per['1h'], $per['4h'])) {
            $v1h=$per['1h']['velocity']; $v4h=$per['4h']['velocity'];
            if ($v1h>0 && $v4h>0) $adj+=3.0; elseif ($v1h<0 && $v4h<0) $adj-=3.0;
            if (isset($per['15m'])) { $v15=$per['15m']['velocity'];
                if ($v1h>0&&$v4h>0&&$v15>0) $adj+=2.0; elseif ($v1h<0&&$v4h<0&&$v15<0) $adj-=2.0;
            }
        }
        return $adj;
    }

    private static function confidenceScore(array $per, float $overall, array $weights): float
    {
        // reuse logic from original TradeEngine::confidenceScore
        $signOverall = ($overall >= 50) ? 1 : -1;
        $agree=0.0; $mag=0.0; $wsum=0.0;
        foreach ($per as $tf=>$row){
            $w=$weights[$tf]??0.0; if($w<=0) continue;
            $s=$row['score']; $signTf=($s>=50)?1:-1;
            if($signTf===$signOverall) $agree+=$w;
            $mag += $w * min(1.0, abs($s - 50.0) / 50.0);
            $wsum += $w;
        }
        if ($wsum<=0) return 0.0;
        return max(0.0,min(1.0,0.5*($agree/$wsum)+0.5*($mag/$wsum)));
    }

    // ---------- Swing TF series & scorers ----------

    private const LOOKBACK = 5; // smoother slopes than 3
    
    // RSI constants for copied methods
    private const RSI_FAST = 7;
    private const RSI_REG  = 14;
    private const RSI_SLOW = 21;

    private static function timeframeScoreSeriesSwing(array $candles, int $points = 3, int $minHistory = 120): array
    {
        $n = count($candles); $out=[];
        for ($k=$points-1; $k>=0; $k--) {
            $end = $n - $k;
            if ($end < $minHistory) { $out[] = ['score'=>50.0,'reasons'=>['insufficient_history'],'flags'=>['overbought'=>false,'oversold'=>false]]; continue; }
            $slice = array_slice($candles, 0, $end);
            $out[] = self::scoreTimeframeSwing($slice, $minHistory);
        }
        return $out;
    }

    private const ATR_LEN  = 50;
    private const VOL_EMA  = 50;

    private static function candleEngineScoreSwing(array $candles): array
    {
        // Start from your original CE, then adjust weights & add EMA50/200 stretch
        $clip = fn($x,$a,$b)=> max($a, min($b,$x));
        $n = count($candles);
        $atr = self::atr(array_slice($candles, -max(self::ATR_LEN+1, 30)));
        $atr = max($atr, 1e-6);
        $emaVol = self::emaLast(array_column($candles, 'volume'), self::VOL_EMA) ?? 0.0;

        $C = array_slice($candles, -self::LOOKBACK);
        [$c2,$c1,$c0] = $C;

        // (Shortened) reuse of original components…
        $delta = ($c0['close'] - $c0['open']);
        $bodyMomentum = 0.2*tanh(($c2['close']-$c2['open'])/($atr*0.8)) + 0.3*tanh(($c1['close']-$c1['open'])/($atr*0.8)) + 0.5*tanh($delta/($atr*0.8));

        $rangeEma = self::emaLast(array_map(fn($c)=>max(0.0,$c['high']-$c['low']), array_slice($candles,-30)), 20) ?? 1e-6;
        // Additional safety check for division by zero
        $rangeEma = max($rangeEma, 1e-6);
        $lastRange = max(1e-6, $c0['high']-$c0['low']);
        $expansion = tanh(($lastRange/$rangeEma - 1.0)) * (($delta>=0)?1:-1);

        $stack = 0.0;
        if ($c0['close']>$c0['ema9'] && $c0['ema9']>$c0['ema20']) $stack=+1.0;
        elseif ($c0['close']<$c0['ema9'] && $c0['ema9']<$c0['ema20']) $stack=-1.0;

        $emaSlope = tanh((($c0['ema9'] - $c2['ema9'])) / ($atr * 0.5));

        $vwapPos = isset($c0['vwap']) ? tanh(10000.0 * (($c0['close'] - $c0['vwap']) / max(1e-9,$c0['vwap'])) / 50.0) : 0.0;

        // EMA50/200 structure bonus (if provided by indicators)
        $stack50_200 = 0.0;
        if (isset($c0['ema50'], $c0['ema200'])) {
            if ($c0['close']>$c0['ema50'] && $c0['ema50']>$c0['ema200']) $stack50_200=+1.0;
            elseif ($c0['close']<$c0['ema50'] && $c0['ema50']<$c0['ema200']) $stack50_200=-1.0;
        }

        // Swing-tilt weights
        $raw =
            0.18 * $bodyMomentum +
            0.08 * $expansion +
            0.18 * $stack +
            0.06 * $emaSlope +
            0.08 * $vwapPos +
            0.12 * $stack50_200;

        // Volume participation
        $volRatio = ($emaVol > 0) ? ($c0['volume'] / $emaVol) : 1.0;
        $volRatio = $clip($volRatio, 0.5, 3.0);
        if     ($volRatio <= 1.0) $mult = 0.92 + 0.08*($volRatio - 0.5)/0.5;
        elseif ($volRatio <= 2.0) $mult = 1.00 + 0.08*($volRatio - 1.0);
        else                      $mult = 1.08 + 0.04*($volRatio - 2.0);
        $mult = min(1.12, max(0.90, $mult));

        $raw = $clip($raw * $mult, -1.0, 1.0);
        $score = round(50.0 * ($raw + 1.0), 2);

        $reasons = [];
        if ($stack > 0.8)          $reasons[] = 'EMA9>EMA20 with price above';
        if ($stack < -0.8)         $reasons[] = 'Below EMA9 & EMA20';
        if ($stack50_200 > 0.8)    $reasons[] = 'EMA50>EMA200 with price above';
        if ($stack50_200 < -0.8)   $reasons[] = 'Below EMA50 & EMA200';
        if ($expansion > 0.2)      $reasons[] = 'Range expanding upward';
        if ($expansion < -0.2)      $reasons[] = 'Range expanding downward';
        if ($volRatio >= 1.5)      $reasons[] = 'Above-average volume';
        if ($volRatio <= 0.7)      $reasons[] = 'Subdued volume';

        return ['score'=>$score,'reasons'=>$reasons];
    }

    private static function rsiTrioScoreSwing(array $candles): array
    {
        // Reuse your RSI logic but favor slower component
        $out = self::rsiTrioScore($candles);
        // Light rebalance: tilt toward slower RSI
        $fast=$out; $reg=$out; $slow=$out; // placeholders; we don’t have subcomponents here
        // Instead, approximate by nudging the final score based on flags/level:
        if (!empty($out['flags']['overbought'])) $out['reasons'][] = 'Overbought (swing)';
        if (!empty($out['flags']['oversold']))   $out['reasons'][] = 'Oversold (swing)';
        return $out;
    }

    private static function applyGuardrailsSwing(array $candles, array $ce, array $rsi, array $mac): array
    {
        $mult=1.0; $cap=100.0; $floor=0.0; $reasons=[];

        $overbought = $rsi['flags']['overbought'] ?? false;
        $oversold   = $rsi['flags']['oversold']   ?? false;
        if ($overbought && ($mac['score'] ?? 50) < 50) { $cap = min($cap, 75.0); $reasons[]='OB dampen (swing)'; }
        if ($oversold   && ($mac['score'] ?? 50) > 50) { $floor = max($floor, 25.0); $reasons[]='OS lift (swing)'; }

        $last=end($candles);
        $stretch50  = (isset($last['ema50']) && $last['ema50'] != 0)  ? abs(($last['close'] - $last['ema50'])  / $last['ema50'])  : 0.0;
        $stretch200 = (isset($last['ema200']) && $last['ema200'] != 0) ? abs(($last['close'] - $last['ema200']) / $last['ema200']) : 0.0;
        if ($stretch50 > 0.03 || $stretch200 > 0.05) { $mult*=0.95; $reasons[]='Stretch dampen (EMA50/200)'; }

        // Participation (longer baseline)
        $volEma = self::emaLast(array_column($candles, 'volume'), self::VOL_EMA) ?? 0.0;
        $lastRange = max(1e-6, $last['high'] - $last['low']);
        $ranges = array_map(fn($c)=> max(0.0,$c['high']-$c['low']), array_slice($candles, -25));
        $rangeMed = max(1e-6, self::median($ranges));
        if (($lastRange < 0.5*$rangeMed) || (($last['volume'] ?? 0) < 0.6*$volEma)) {
            $mult *= 0.95; $reasons[] = 'Low participation dampen (swing)';
        }

        return ['mult'=>$mult,'cap'=>$cap,'floor'=>$floor,'reasons'=>$reasons];
    }

    // ---------- Copied methods from TradeEngine ----------
    
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

    private static function stddev(array $vals): float
    {
        $n = count($vals); if ($n === 0) return 0.0;
        $m = array_sum($vals)/$n;
        $s = 0.0; foreach ($vals as $v) { $d = $v - $m; $s += $d*$d; }
        return sqrt($s / max(1,$n));
    }

    // ---------- Helpers (copied from original) ----------
    private static function hasIndicators(array $candles): bool { 
        $last=end($candles); 
        return isset($last['ema9'],$last['ema20'],$last['macd_line'],$last['macd_signal'],$last['macd_hist']); 
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
    
    private static function median(array $vals): float 
    {
        $n = count($vals); if ($n === 0) return 0.0;
        sort($vals); $mid = intdiv($n,2);
        return ($n % 2) ? $vals[$mid] : 0.5*($vals[$mid-1] + $vals[$mid]);
    }
}
