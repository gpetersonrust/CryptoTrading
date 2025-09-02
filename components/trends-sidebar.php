<?php
/**
 * Trends Sidebar Component
 */

class TrendsSidebar {
    
    public static function render($trend_analysis) {
        ?>
        <div style='background: var(--surface); border-radius: var(--radius); padding: var(--space-5); margin-bottom: var(--space-4); border: 1px solid var(--border); box-shadow: var(--shadow); backdrop-filter: blur(20px);'>
            <!-- Header -->
            <div style='display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4); padding-bottom: var(--space-3); border-bottom: 1px solid var(--border);'>
                <div style='
                    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 80%, var(--success)));
                    border-radius: var(--radius-sm);
                    padding: var(--space-2);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: var(--shadow-sm);
                '>
                    <span style='font-size: 24px;'>📊</span>
                </div>
                <div>
                    <h3 style='color: var(--text); margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px;'>
                        Live Trend Analysis
                    </h3>
                    <p style='color: var(--text-secondary); margin: 0; font-size: 14px; font-weight: 500;'>
                        Real-time market insights
                    </p>
                </div>
            </div>
            
            <?php if (!empty($trend_analysis['last_update'])): ?>
                <div style='background: color-mix(in srgb, var(--success) 8%, var(--surface)); padding: var(--space-3); border-radius: var(--radius-sm); margin-bottom: var(--space-4); border-left: 3px solid var(--success);'>
                    <p style='color: var(--text-secondary); font-size: 13px; margin: 0; font-weight: 500; font-family: var(--font-mono);'>
                        📅 Last updated: <?php echo date('M j, H:i', strtotime($trend_analysis['last_update'])); ?> 
                        <span style='color: var(--text); font-weight: 600;'>(<?php echo $trend_analysis['total_coins_analyzed']; ?> coins)</span>
                    </p>
                </div>
                
                <?php self::render_highest_trend($trend_analysis); ?>
                <?php self::render_lowest_trend($trend_analysis); ?>
                <?php self::render_market_summary($trend_analysis); ?>
                
            <?php else: ?>
                <!-- Generate trend analysis from current available data -->
                <?php $live_analysis = self::generate_live_trend_analysis(); ?>
                <?php if (!empty($live_analysis)): ?>
                    <div style='background: color-mix(in srgb, var(--accent) 8%, var(--surface)); padding: var(--space-3); border-radius: var(--radius-sm); margin-bottom: var(--space-4); border-left: 3px solid var(--accent);'>
                        <p style='color: var(--text-secondary); font-size: 13px; margin: 0; font-weight: 500; font-family: var(--font-mono);'>
                            ⚡ Live analysis from available data 
                            <span style='color: var(--text); font-weight: 600;'>(<?php echo $live_analysis['total_coins']; ?> coins)</span>
                        </p>
                    </div>
                    
                    <?php self::render_live_trends($live_analysis); ?>
                <?php else: ?>
                    <div style='background: color-mix(in srgb, var(--warning) 8%, var(--surface)); padding: var(--space-5); border-radius: var(--radius-sm); text-align: center; border: 1px solid color-mix(in srgb, var(--warning) 20%, var(--border));'>
                        <div style='font-size: 48px; margin-bottom: var(--space-3);'>⏳</div>
                        <h4 style='color: var(--text); margin: 0 0 var(--space-2) 0; font-weight: 600;'>Initializing Analysis</h4>
                        <p style='color: var(--text-secondary); margin: 0; font-size: 14px;'>Waiting for coin data to be collected...</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private static function render_highest_trend($trend_analysis) {
        if (!empty($trend_analysis['highest_trend'])) {
            echo "<div style='margin-bottom: var(--space-5);'>";
            echo "<div style='display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3);'>";
            echo "<div style='background: var(--success); border-radius: var(--radius-sm); padding: var(--space-1); display: flex; align-items: center; justify-content: center;'>";
            echo "<span style='font-size: 16px; color: white;'>🚀</span>";
            echo "</div>";
            echo "<h4 style='color: var(--success); margin: 0; font-size: 18px; font-weight: 600; letter-spacing: -0.2px;'>Biggest Gainers</h4>";
            echo "</div>";
            
            foreach (array_slice($trend_analysis['highest_trend'], 0, 3) as $coin => $data) {
                $trend_color = $data['trend_change'] > 0 ? 'var(--success)' : 'var(--danger)';
                $trend_icon = $data['trend_change'] > 0 ? '📈' : '📉';
                
                echo "<div style='background: var(--surface); padding: var(--space-3); margin: var(--space-2) 0; border-radius: var(--radius-sm); border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: var(--transition);' onmouseover='this.style.boxShadow=\"var(--shadow)\"; this.style.transform=\"translateY(-1px)\";' onmouseout='this.style.boxShadow=\"var(--shadow-sm)\"; this.style.transform=\"translateY(0)\";'>";
                echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-1);'>";
                echo "<span style='font-weight: 600; color: var(--text); font-size: 15px;'>{$coin}</span>";
                echo "<div style='display: flex; align-items: center; gap: var(--space-1);'>";
                echo "<span style='font-size: 14px;'>{$trend_icon}</span>";
                echo "<span style='color: {$trend_color}; font-weight: 700; font-family: var(--font-mono); font-size: 14px;'>+" . round($data['trend_change'], 1) . "</span>";
                echo "</div>";
                echo "</div>";
                echo "<div style='display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); font-family: var(--font-mono);'>";
                echo "<span><strong style='color: var(--text);'>Avg:</strong> " . round($data['avg_score'], 1) . "</span>";
                echo "<span><strong style='color: var(--text);'>High:</strong> " . round($data['max_score'], 1) . "</span>";
                echo "<span><strong style='color: var(--text);'>Low:</strong> " . round($data['min_score'], 1) . "</span>";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        }
    }
    
    private static function render_lowest_trend($trend_analysis) {
        if (!empty($trend_analysis['lowest_trend'])) {
            echo "<div style='margin-bottom: var(--space-5);'>";
            echo "<div style='display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3);'>";
            echo "<div style='background: var(--danger); border-radius: var(--radius-sm); padding: var(--space-1); display: flex; align-items: center; justify-content: center;'>";
            echo "<span style='font-size: 16px; color: white;'>📉</span>";
            echo "</div>";
            echo "<h4 style='color: var(--danger); margin: 0; font-size: 18px; font-weight: 600; letter-spacing: -0.2px;'>Biggest Losers</h4>";
            echo "</div>";
            
            foreach (array_slice($trend_analysis['lowest_trend'], 0, 3) as $coin => $data) {
                $trend_color = $data['trend_change'] > 0 ? 'var(--success)' : 'var(--danger)';
                $trend_icon = $data['trend_change'] > 0 ? '📈' : '📉';
                
                echo "<div style='background: var(--surface); padding: var(--space-3); margin: var(--space-2) 0; border-radius: var(--radius-sm); border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: var(--transition);' onmouseover='this.style.boxShadow=\"var(--shadow)\"; this.style.transform=\"translateY(-1px)\";' onmouseout='this.style.boxShadow=\"var(--shadow-sm)\"; this.style.transform=\"translateY(0)\";'>";
                echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-1);'>";
                echo "<span style='font-weight: 600; color: var(--text); font-size: 15px;'>{$coin}</span>";
                echo "<div style='display: flex; align-items: center; gap: var(--space-1);'>";
                echo "<span style='font-size: 14px;'>{$trend_icon}</span>";
                echo "<span style='color: {$trend_color}; font-weight: 700; font-family: var(--font-mono); font-size: 14px;'>" . round($data['trend_change'], 1) . "</span>";
                echo "</div>";
                echo "</div>";
                echo "<div style='display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); font-family: var(--font-mono);'>";
                echo "<span><strong style='color: var(--text);'>Avg:</strong> " . round($data['avg_score'], 1) . "</span>";
                echo "<span><strong style='color: var(--text);'>High:</strong> " . round($data['max_score'], 1) . "</span>";
                echo "<span><strong style='color: var(--text);'>Low:</strong> " . round($data['min_score'], 1) . "</span>";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        }
    }
    
    private static function render_market_summary($trend_analysis) {
        if (!empty($trend_analysis['market_summary'])) {
            $summary = $trend_analysis['market_summary'];
            echo "<div style='margin-bottom: var(--space-4);'>";
            
            // Header
            echo "<div style='display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3);'>";
            echo "<div style='background: linear-gradient(135deg, #6f42c1, #8e44c1); border-radius: var(--radius-sm); padding: var(--space-1); display: flex; align-items: center; justify-content: center;'>";
            echo "<span style='font-size: 16px; color: white;'>📈</span>";
            echo "</div>";
            echo "<h4 style='color: #6f42c1; margin: 0; font-size: 18px; font-weight: 600; letter-spacing: -0.2px;'>Market Summary</h4>";
            echo "</div>";
            
            // Main card
            echo "<div style='background: var(--surface); padding: var(--space-4); border-radius: var(--radius-sm); border: 1px solid var(--border); box-shadow: var(--shadow-sm);'>";
            
            // Grid of metrics
            echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);'>";
            
            // Average Score
            echo "<div style='text-align: center; padding: var(--space-2); background: color-mix(in srgb, var(--accent) 8%, var(--surface)); border-radius: var(--radius-sm);'>";
            echo "<div style='font-size: 20px; font-weight: 700; color: var(--accent); font-family: var(--font-mono);'>" . round($summary['avg_score'], 1) . "</div>";
            echo "<div style='font-size: 12px; color: var(--text-secondary); font-weight: 500;'>Avg Score</div>";
            echo "</div>";
            
            // Volatility
            $volatility_color = $summary['volatility'] > 15 ? 'var(--danger)' : ($summary['volatility'] > 10 ? 'var(--warning)' : 'var(--success)');
            echo "<div style='text-align: center; padding: var(--space-2); background: color-mix(in srgb, {$volatility_color} 8%, var(--surface)); border-radius: var(--radius-sm);'>";
            echo "<div style='font-size: 20px; font-weight: 700; color: {$volatility_color}; font-family: var(--font-mono);'>" . round($summary['volatility'], 1) . "</div>";
            echo "<div style='font-size: 12px; color: var(--text-secondary); font-weight: 500;'>Volatility</div>";
            echo "</div>";
            
            // Bullish Count
            echo "<div style='text-align: center; padding: var(--space-2); background: color-mix(in srgb, var(--success) 8%, var(--surface)); border-radius: var(--radius-sm);'>";
            echo "<div style='font-size: 20px; font-weight: 700; color: var(--success); font-family: var(--font-mono);'>" . $summary['bullish_count'] . "</div>";
            echo "<div style='font-size: 12px; color: var(--text-secondary); font-weight: 500;'>Bullish</div>";
            echo "</div>";
            
            // Bearish Count
            echo "<div style='text-align: center; padding: var(--space-2); background: color-mix(in srgb, var(--danger) 8%, var(--surface)); border-radius: var(--radius-sm);'>";
            echo "<div style='font-size: 20px; font-weight: 700; color: var(--danger); font-family: var(--font-mono);'>" . $summary['bearish_count'] . "</div>";
            echo "<div style='font-size: 12px; color: var(--text-secondary); font-weight: 500;'>Bearish</div>";
            echo "</div>";
            
            echo "</div>";
            
            // Market Direction Badge
            if (!empty($summary['trend_direction'])) {
                $direction_color = $summary['trend_direction'] === 'Bullish' ? 'var(--success)' : 
                                 ($summary['trend_direction'] === 'Bearish' ? 'var(--danger)' : 'var(--warning)');
                $direction_icon = $summary['trend_direction'] === 'Bullish' ? '🐂' : 
                                ($summary['trend_direction'] === 'Bearish' ? '🐻' : '🦎');
                
                echo "<div style='text-align: center; margin-top: var(--space-3); padding-top: var(--space-3); border-top: 1px solid var(--border);'>";
                echo "<div style='display: inline-flex; align-items: center; gap: var(--space-2); background: {$direction_color}; color: white; padding: var(--space-2) var(--space-4); border-radius: var(--radius-sm); font-weight: 600; box-shadow: var(--shadow-sm);'>";
                echo "<span style='font-size: 16px;'>{$direction_icon}</span>";
                echo "<span>{$summary['trend_direction']} Market</span>";
                echo "</div>";
                echo "</div>";
            }
            
            echo "</div>"; // Close main card
            echo "</div>"; // Close section
        }
    }
    
    private static function generate_live_trend_analysis() {
        // Get all coins from the current data
        $coinAnalysisData = DashboardData::get_coin_analysis_data();
        
        if (empty($coinAnalysisData)) {
            return null;
        }
        
        $trends = [];
        $scores = [];
        $total_coins = 0;
        
        foreach ($coinAnalysisData as $coinData) {
            if (!$coinData['analysis']) continue;
            
            $coin = $coinData['coin'];
            $analysis = $coinData['analysis'];
            $score = $analysis['overall']['score'] ?? 0;
            $velocity = $analysis['overall']['velocity'] ?? 0;
            
            $trends[$coin] = [
                'current_score' => $score,
                'velocity' => $velocity,
                'trend_direction' => $velocity > 0 ? 'rising' : ($velocity < 0 ? 'falling' : 'stable')
            ];
            
            $scores[] = $score;
            $total_coins++;
        }
        
        if (empty($trends)) {
            return null;
        }
        
        // Sort by current score
        uasort($trends, function($a, $b) {
            return $b['current_score'] <=> $a['current_score'];
        });
        
        $avg_score = array_sum($scores) / count($scores);
        $bullish_count = count(array_filter($trends, function($trend) { return $trend['velocity'] > 0; }));
        $bearish_count = count(array_filter($trends, function($trend) { return $trend['velocity'] < 0; }));
        
        return [
            'total_coins' => $total_coins,
            'trends' => $trends,
            'avg_score' => $avg_score,
            'bullish_count' => $bullish_count,
            'bearish_count' => $bearish_count,
            'market_sentiment' => $bullish_count > $bearish_count ? 'Bullish' : ($bearish_count > $bullish_count ? 'Bearish' : 'Neutral')
        ];
    }
    
    private static function render_live_trends($live_analysis) {
        $trends = $live_analysis['trends'];
        
        // Top performers (highest scores)
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h4 style='color: #28a745; margin-bottom: 10px; display: flex; align-items: center;'>";
        echo "<span style='font-size: 18px; margin-right: 6px;'>🚀</span> Top Performers (Current Scores)";
        echo "</h4>";
        
        $top_performers = array_slice($trends, 0, 3, true);
        foreach ($top_performers as $coin => $data) {
            $trend_color = $data['velocity'] > 0 ? '#28a745' : ($data['velocity'] < 0 ? '#dc3545' : '#6c757d');
            $trend_icon = $data['velocity'] > 0 ? '📈' : ($data['velocity'] < 0 ? '📉' : '➡️');
            
            echo "<div style='background: white; padding: 8px; margin: 5px 0; border-radius: 5px; border-left: 3px solid {$trend_color};'>";
            echo "<div style='display: flex; justify-content: space-between; align-items: center;'>";
            echo "<strong>{$coin}</strong>";
            echo "<span style='color: {$trend_color}; font-weight: bold;'>{$trend_icon} " . round($data['current_score'], 1) . "/100</span>";
            echo "</div>";
            echo "<div style='font-size: 11px; color: #6c757d;'>";
            echo "Velocity: " . round($data['velocity'], 2) . " | Status: " . ucfirst($data['trend_direction']);
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        
        // Bottom performers (lowest scores)
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h4 style='color: #dc3545; margin-bottom: 10px; display: flex; align-items: center;'>";
        echo "<span style='font-size: 18px; margin-right: 6px;'>📉</span> Needs Attention (Low Scores)";
        echo "</h4>";
        
        $bottom_performers = array_slice(array_reverse($trends, true), 0, 3, true);
        foreach ($bottom_performers as $coin => $data) {
            $trend_color = $data['velocity'] > 0 ? '#28a745' : ($data['velocity'] < 0 ? '#dc3545' : '#6c757d');
            $trend_icon = $data['velocity'] > 0 ? '📈' : ($data['velocity'] < 0 ? '📉' : '➡️');
            
            echo "<div style='background: white; padding: 8px; margin: 5px 0; border-radius: 5px; border-left: 3px solid {$trend_color};'>";
            echo "<div style='display: flex; justify-content: space-between; align-items: center;'>";
            echo "<strong>{$coin}</strong>";
            echo "<span style='color: {$trend_color}; font-weight: bold;'>{$trend_icon} " . round($data['current_score'], 1) . "/100</span>";
            echo "</div>";
            echo "<div style='font-size: 11px; color: #6c757d;'>";
            echo "Velocity: " . round($data['velocity'], 2) . " | Status: " . ucfirst($data['trend_direction']);
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        
        // Top average performers (best overall balance)
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h4 style='color: #17a2b8; margin-bottom: 10px; display: flex; align-items: center;'>";
        echo "<span style='font-size: 18px; margin-right: 6px;'>⭐</span> Top Averagers (Best Balance)";
        echo "</h4>";
        
        // Sort by a balance of score and positive velocity
        $balanced_trends = $trends;
        uasort($balanced_trends, function($a, $b) {
            // Create a weighted score: 80% current score + 20% normalized velocity bonus
            $velocity_bonus_a = min(20, max(0, $a['velocity'] * 2)); // Cap velocity bonus at 20 points
            $velocity_bonus_b = min(20, max(0, $b['velocity'] * 2));
            $balance_a = ($a['current_score'] * 0.8) + $velocity_bonus_a;
            $balance_b = ($b['current_score'] * 0.8) + $velocity_bonus_b;
            return $balance_b <=> $balance_a;
        });
        
        $top_balanced = array_slice($balanced_trends, 0, 3, true);
        foreach ($top_balanced as $coin => $data) {
            $trend_color = $data['velocity'] > 0 ? '#28a745' : ($data['velocity'] < 0 ? '#dc3545' : '#6c757d');
            $trend_icon = $data['velocity'] > 0 ? '📈' : ($data['velocity'] < 0 ? '📉' : '➡️');
            $velocity_bonus = min(20, max(0, $data['velocity'] * 2));
            $balanced_score = ($data['current_score'] * 0.8) + $velocity_bonus;
            
            echo "<div style='background: white; padding: 8px; margin: 5px 0; border-radius: 5px; border-left: 3px solid #17a2b8;'>";
            echo "<div style='display: flex; justify-content: space-between; align-items: center;'>";
            echo "<strong>{$coin}</strong>";
            echo "<span style='color: {$trend_color}; font-weight: bold;'>{$trend_icon} " . round($balanced_score, 1) . "/100</span>";
            echo "</div>";
            echo "<div style='font-size: 11px; color: #6c757d;'>";
            echo "Base: " . round($data['current_score'], 1) . " + Momentum: +" . round($velocity_bonus, 1) . " | Velocity: " . round($data['velocity'], 2);
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        
        // Low average performers (needs improvement)
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h4 style='color: #ffc107; margin-bottom: 10px; display: flex; align-items: center;'>";
        echo "<span style='font-size: 18px; margin-right: 6px;'>⚠️</span> Low Averagers (Needs Improvement)";
        echo "</h4>";
        
        // Sort by worst balance (low score AND negative velocity)
        $poor_balanced = $trends;
        uasort($poor_balanced, function($a, $b) {
            $penalty_a = ($a['current_score'] * 0.7) + (min(0, $a['velocity']) * 30); // Penalty for negative velocity
            $penalty_b = ($b['current_score'] * 0.7) + (min(0, $b['velocity']) * 30);
            return $penalty_a <=> $penalty_b; // Ascending - worst first
        });
        
        $worst_balanced = array_slice($poor_balanced, 0, 3, true);
        foreach ($worst_balanced as $coin => $data) {
            $trend_color = $data['velocity'] > 0 ? '#28a745' : ($data['velocity'] < 0 ? '#dc3545' : '#6c757d');
            $trend_icon = $data['velocity'] > 0 ? '📈' : ($data['velocity'] < 0 ? '📉' : '➡️');
            $penalty_score = ($data['current_score'] * 0.7) + (min(0, $data['velocity']) * 30);
            
            echo "<div style='background: white; padding: 8px; margin: 5px 0; border-radius: 5px; border-left: 3px solid #ffc107;'>";
            echo "<div style='display: flex; justify-content: space-between; align-items: center;'>";
            echo "<strong>{$coin}</strong>";
            echo "<span style='color: {$trend_color}; font-weight: bold;'>{$trend_icon} " . round($penalty_score, 1) . " avg</span>";
            echo "</div>";
            echo "<div style='font-size: 11px; color: #6c757d;'>";
            echo "Score: " . round($data['current_score'], 1) . "/100 | Velocity: " . round($data['velocity'], 2) . " | " . ucfirst($data['trend_direction']);
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        
        // Live market summary
        echo "<div style='margin-bottom: 15px;'>";
        echo "<h4 style='color: #6f42c1; margin-bottom: 10px; display: flex; align-items: center;'>";
        echo "<span style='font-size: 18px; margin-right: 6px;'>📈</span> Live Market Summary";
        echo "</h4>";
        
        echo "<div style='background: white; padding: 10px; border-radius: 5px; border-left: 3px solid #6f42c1;'>";
        echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;'>";
        echo "<div><strong>Avg Score:</strong> " . round($live_analysis['avg_score'], 1) . "</div>";
        echo "<div><strong>Total Coins:</strong> " . $live_analysis['total_coins'] . "</div>";
        echo "<div><strong>Rising:</strong> " . $live_analysis['bullish_count'] . " coins</div>";
        echo "<div><strong>Falling:</strong> " . $live_analysis['bearish_count'] . " coins</div>";
        echo "</div>";
        
        $sentiment_color = $live_analysis['market_sentiment'] === 'Bullish' ? '#28a745' : 
                          ($live_analysis['market_sentiment'] === 'Bearish' ? '#dc3545' : '#ffc107');
        echo "<div style='margin-top: 8px; text-align: center;'>";
        echo "<span style='background: {$sentiment_color}; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;'>";
        echo $live_analysis['market_sentiment'] . " Market";
        echo "</span>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
}
