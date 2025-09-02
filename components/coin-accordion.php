<?php
/**
 * Coin Accordion Component
 */

class CoinAccordion {
    
    public static function render($coinData, $index) {
        $coin = $coinData['coin'];
        $analysis = $coinData['analysis'];
        $error = $coinData['error'];
        $growth = $coinData['growth'] ?? 0;
        $timeframe_growth = $coinData['timeframe_growth'] ?? [];
        
        // Get trend history for this coin
        $trendHistory = CoinCacheManager::get_trend_history($coin);
        $timeframeTrendHistory = CoinCacheManager::get_timeframe_trend_history($coin);
        
        if ($analysis) {
            $score = $analysis['overall']['score'];
            $confidence = $analysis['overall']['confidence'];
            $velocity = $analysis['overall']['velocity'];
        } else {
            $score = 0;
            $confidence = 0;
            $velocity = 0;
        }
        
        // Calculate display values
        $scoreColor = $score >= 70 ? '#28a745' : ($score <= 30 ? '#dc3545' : '#ffc107');
        $scoreEmoji = $score >= 70 ? '🟢' : ($score <= 30 ? '🔴' : '🟡');
        $headerBg = 'linear-gradient(45deg, ' . $scoreColor . ', ' . self::adjustBrightness($scoreColor, 20) . ')';
        
        $trendIcon = $velocity > 5 ? '📈' : ($velocity < -5 ? '📉' : '➡️');
        
        $growthColor = $growth > 0 ? '#28a745' : ($growth < 0 ? '#dc3545' : '#6c757d');
        $growthDisplay = $growth > 0 ? "+{$growth}%" : "{$growth}%";
        
        // Build data attributes for sorting
        $dataAttributes = "data-score='$score' data-confidence='" . round($confidence * 100) . "' data-growth='$growth'";
        
        $timeframes = ['1m', '5m', '15m', '30m', '1h', '4h'];
        foreach ($timeframes as $tf) {
            if (isset($analysis['per_timeframe'][$tf]['score'])) {
                $tfScore = $analysis['per_timeframe'][$tf]['score'];
            } else {
                $tfScore = 0;
            }
            $dataAttributes .= " data-score-$tf='$tfScore'";
        }
        
        ?>
        <div class='coin-accordion' <?php echo $dataAttributes; ?>>
            <?php self::render_header($coin, $analysis, $score, $scoreEmoji, $scoreColor, $headerBg, $trendIcon, $growth, $growthColor, $growthDisplay, $confidence, $velocity, $trendHistory, $timeframeTrendHistory, $timeframe_growth, $index); ?>
            <?php self::render_content($coin, $analysis, $error, $index); ?>
        </div>
        <?php
    }
    
    private static function render_header($coin, $analysis, $score, $scoreEmoji, $scoreColor, $headerBg, $trendIcon, $growth, $growthColor, $growthDisplay, $confidence, $velocity, $trendHistory, $timeframeTrendHistory, $timeframe_growth, $index) {
        ?>
        <button class='accordion-header' onclick='toggleAccordion(<?php echo $index; ?>)' aria-expanded="false" aria-controls="accordion-content-<?php echo $index; ?>">
            <div style='display: flex; align-items: center; justify-content: space-between; width: 100%;'>
                <!-- Left side: Main coin info -->
                <div style='display: flex; flex-direction: column; align-items: flex-start; flex-grow: 1;'>
                    <!-- Primary row: Coin name and key metrics -->
                    <div style='display: flex; align-items: center; gap: 12px; margin-bottom: 8px;'>
                        <h2 style='margin: 0; color: var(--text); font-size: 22px; font-weight: 700; letter-spacing: -0.5px;'>💰 <?php echo $coin; ?></h2>
                        
                        <?php if ($analysis): ?>
                            <span class='score-badge' style='background: <?php echo $scoreColor; ?>;'><?php echo $scoreEmoji . ' ' . $score; ?>/100</span>
                            <span class='trend-indicator'><?php echo $trendIcon; ?></span>
                            
                            <?php if ($growth != 0): ?>
                                <span class='growth-badge' style='background: <?php echo $growthColor; ?>; color: white; padding: 6px 10px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; font-family: var(--font-mono);'><?php echo $growthDisplay; ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class='score-badge' style='background: var(--danger);'><?php echo $scoreEmoji; ?> Error</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($analysis): ?>
                    <!-- Secondary row: Detailed metrics -->
                    <div style='color: var(--text-secondary); font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;'>
                        <span style='display: flex; align-items: center; gap: 4px;'>
                            <span style='font-weight: 600; color: var(--text);'>Confidence:</span> 
                            <?php echo round($confidence * 100); ?>%
                        </span>
                        <span style='display: flex; align-items: center; gap: 4px;'>
                            <span style='font-weight: 600; color: var(--text);'>Velocity:</span> 
                            <?php echo $velocity; ?>
                        </span>
                        <span style='display: flex; align-items: center; gap: 4px;'>
                            <span style='font-weight: 600; color: var(--text);'>Synergy:</span> 
                            <?php echo $analysis['overall']['synergy'] ?? 'N/A'; ?>
                        </span>
                    </div>
                    
                    <!-- Trend history section -->
                    <div style='margin-top: 12px; width: 100%;'>
                        <?php self::render_trend_history($trendHistory, $growth); ?>
                        <?php self::render_timeframe_trends($timeframeTrendHistory, $timeframe_growth); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right side: Toggle button -->
                <div style='margin-left: 16px;'>
                    <span id='toggle-icon-<?php echo $index; ?>' class='toggle-icon'>▼</span>
                </div>
            </div>
        </button>
        <?php
    }
    
    private static function render_content($coin, $analysis, $error, $index) {
        ?>
        <div id='accordion-content-<?php echo $index; ?>' class='accordion-content' role="region" aria-labelledby="accordion-header-<?php echo $index; ?>">
            <?php if ($analysis): ?>
                <div style='padding: var(--space-5); background: var(--surface);'>
                    <?php CoinAnalysisContent::render($analysis); ?>
                </div>
            <?php else: ?>
                <div style='padding: var(--space-5); text-align: center; background: color-mix(in srgb, var(--danger) 5%, var(--surface));'>
                    <div style='max-width: 400px; margin: 0 auto;'>
                        <div style='font-size: 48px; margin-bottom: var(--space-3);'>❌</div>
                        <h3 style='color: var(--danger); margin: 0 0 var(--space-2) 0; font-size: 20px; font-weight: 600;'>Analysis Error</h3>
                        <p style='color: var(--text-secondary); margin: 0; font-size: 15px; line-height: 1.5;'><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private static function render_trend_history($trendHistory, $growth) {
        if (!empty($trendHistory)) {
            echo "<div class='trend-history'>";
            echo "<strong>Overall:</strong> ";
            $trendScores = [];
            foreach (array_slice($trendHistory, 0, 5) as $trend) {
                $trendScore = round($trend['score'] ?? 0);
                $scoreColor = $trendScore >= 70 ? '#28a745' : ($trendScore <= 30 ? '#dc3545' : '#ffc107');
                $trendScores[] = "<span style='color: $scoreColor; font-weight: bold;'>$trendScore</span>";
            }
            echo implode(' → ', $trendScores);
            
            $overallGrowthDisplay = $growth > 0 ? " (+{$growth}%)" : ($growth < 0 ? " ({$growth}%)" : "");
            $overallGrowthColor = $growth > 0 ? '#28a745' : ($growth < 0 ? '#dc3545' : '#6c757d');
            if ($growth != 0) {
                echo " <span style='color: $overallGrowthColor; font-weight: bold;'>$overallGrowthDisplay</span>";
            }
            echo "</div>";
        }
    }
    
    private static function render_timeframe_trends($timeframeTrendHistory, $timeframe_growth) {
        if (!empty($timeframeTrendHistory)) {
            echo "<div class='timeframe-trends' style='margin-top: 4px; font-size: 11px;'>";
            $timeframes = [
                '1m' => '⚡',
                '5m' => '🚀', 
                '15m' => '📈',
                '30m' => '📊',
                '1h' => '⏰',
                '4h' => '⏳'
            ];
            
            foreach ($timeframes as $tf => $emoji) {
                if (count($timeframeTrendHistory) >= 2) {
                    $tfTrends = [];
                    foreach (array_slice($timeframeTrendHistory, 0, 3) as $history) {
                        if (isset($history[$tf])) {
                            $tfScore = round($history[$tf]);
                            $tfColor = $tfScore >= 70 ? '#28a745' : ($tfScore <= 30 ? '#dc3545' : '#ffc107');
                            $tfTrends[] = "<span style='color: $tfColor;'>$tfScore</span>";
                        }
                    }
                    if (!empty($tfTrends)) {
                        $tfGrowth = $timeframe_growth[$tf] ?? 0;
                        $tfGrowthDisplay = $tfGrowth > 0 ? "(+{$tfGrowth}%)" : ($tfGrowth < 0 ? "({$tfGrowth}%)" : "");
                        $tfGrowthColor = $tfGrowth > 0 ? '#28a745' : ($tfGrowth < 0 ? '#dc3545' : '#6c757d');
                        echo "<div style='display: inline-block; margin-right: 8px;'>";
                        echo "$emoji " . implode('→', $tfTrends);
                        if ($tfGrowth != 0) {
                            echo " <span style='color: $tfGrowthColor; font-weight: bold;'>$tfGrowthDisplay</span>";
                        }
                        echo "</div>";
                    }
                }
            }
            echo "</div>";
        }
    }
    
    private static function adjustBrightness($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + ($percent * 255 / 100)));
        $g = max(0, min(255, $g + ($percent * 255 / 100)));
        $b = max(0, min(255, $b + ($percent * 255 / 100)));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}
