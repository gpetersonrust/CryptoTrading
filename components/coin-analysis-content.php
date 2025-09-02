<?php
/**
 * Coin Analysis Content Component
 */

class CoinAnalysisContent {
    
    public static function render($analysis) {
        $score = $analysis['overall']['score'] ?? 0;
        $confidence = $analysis['overall']['confidence'] ?? 0;
        $scoreColor = $score >= 70 ? '#28a745' : ($score <= 30 ? '#dc3545' : '#ffc107');
        $scoreEmoji = $score >= 70 ? '🟢' : ($score <= 30 ? '🔴' : '🟡');
        
        ?>
        <!-- Overall Score Section -->
        <div style='background: linear-gradient(45deg, <?php echo $scoreColor; ?>, <?php echo self::adjustBrightness($scoreColor, 20); ?>); color: white; padding: 20px; margin: 10px 0; border-radius: 8px;'>
            <h3><?php echo $scoreEmoji; ?> Overall Trading Score: <?php echo $score; ?>/100</h3>
            <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;'>
                <div><strong>Velocity:</strong> <?php echo $analysis['overall']['velocity'] ?? 0; ?></div>
                <div><strong>Acceleration:</strong> <?php echo $analysis['overall']['accel'] ?? 0; ?></div>
                <div><strong>Confidence:</strong> <?php echo round($confidence * 100); ?>%</div>
                <div><strong>Synergy:</strong> <?php echo $analysis['overall']['synergy'] ?? 'N/A'; ?></div>
            </div>
        </div>
        
        <!-- Quick Insight -->
        <div style='background: #e8f4f8; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007cba;'>
            <h4 style='margin-top: 0;'>💡 Quick Insight</h4>
            <?php self::render_insight($score, $confidence); ?>
            <p style='margin-bottom: 0;'><em>Timeframes analyzed: <?php echo implode(', ', array_keys($analysis['per_timeframe'])); ?></em></p>
        </div>
        
        <!-- Timeframe Breakdown -->
        <div style='margin: 15px 0;'>
            <h4>⏱️ Timeframe Breakdown</h4>
            <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;'>
                <?php self::render_timeframe_breakdown($analysis['per_timeframe']); ?>
            </div>
        </div>
        
        <!-- Detailed Technical Analysis -->
        <?php self::render_detailed_analysis($analysis); ?>
        
        <!-- Trading Strategy Recommendations -->
        <?php self::render_trading_strategy($analysis); ?>
        <?php
    }
    
    private static function render_insight($score, $confidence) {
        if ($score >= 70 && $confidence >= 0.6) {
            echo "<p style='color: #28a745; font-weight: bold;'>🟢 BULLISH SIGNAL - Strong upward momentum detected</p>";
        } elseif ($score <= 30 && $confidence >= 0.6) {
            echo "<p style='color: #dc3545; font-weight: bold;'>🔴 BEARISH SIGNAL - Strong downward momentum detected</p>";
        } elseif ($confidence < 0.4) {
            echo "<p style='color: #ffc107; font-weight: bold;'>⚠️ LOW CONFIDENCE - Mixed signals across timeframes</p>";
        } else {
            echo "<p style='color: #6c757d; font-weight: bold;'>⚪ NEUTRAL - No clear directional bias</p>";
        }
    }
    
    private static function render_timeframe_breakdown($per_timeframe) {
        foreach ($per_timeframe as $tf => $data) {
            $tfScore = $data['score'] ?? 0;
            $tfColor = $tfScore >= 60 ? '#28a745' : ($tfScore <= 40 ? '#dc3545' : '#ffc107');
            $velocity = $data['velocity'] ?? 0;
            $velIcon = $velocity > 0 ? '📈' : ($velocity < 0 ? '📉' : '➡️');
            
            echo "<div style='background: rgba(255,255,255,0.9); padding: 10px; border-radius: 5px; border-left: 3px solid $tfColor;'>";
            echo "<div style='font-weight: bold; color: $tfColor;'>$velIcon $tf</div>";
            echo "<div style='font-size: 12px; color: #666;'>";
            echo "Score: $tfScore/100<br>";
            echo "Velocity: " . $velocity . "<br>";
            echo "Accel: " . ($data['accel'] ?? 0);
            echo "</div>";
            echo "</div>";
        }
    }
    
    private static function render_detailed_analysis($analysis) {
        echo "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #6f42c1;'>";
        echo "<h4 style='margin-top: 0; color: #6f42c1;'>📋 Detailed Analysis & Key Factors</h4>";
        
        foreach ($analysis['per_timeframe'] as $tf => $data) {
            $tfScore = $data['score'] ?? 0;
            $velocity = $data['velocity'] ?? 0;
            $accel = $data['accel'] ?? 0;
            $reasons = $data['reasons'] ?? [];
            $flags = $data['flags'] ?? [];
            
            $tfColor = $tfScore >= 60 ? '#28a745' : ($tfScore <= 40 ? '#dc3545' : '#ffc107');
            $velIcon = $velocity > 0 ? '📈' : ($velocity < 0 ? '📉' : '➡️');
            
            echo "<div style='background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 3px solid $tfColor;'>";
            echo "<h5 style='color: $tfColor; margin-top: 0;'>$tf Timeframe $velIcon - Score: $tfScore/100</h5>";
            
            // Momentum indicators
            echo "<div style='background: #e8f4f8; padding: 8px; border-radius: 3px; margin: 8px 0;'>";
            echo "📊 <strong>Momentum Indicators:</strong> Velocity: $velocity | Acceleration: $accel";
            echo "</div>";
            
            // Overbought/Oversold warnings
            self::render_condition_warnings($flags, $tfScore, $reasons);
            
            // Key factors
            if (!empty($reasons)) {
                echo "<div style='margin: 8px 0;'>";
                echo "🔑 <strong>Key Factors:</strong>";
                echo "<ul style='margin: 5px 0; padding-left: 20px;'>";
                foreach ($reasons as $reason) {
                    $icon = self::get_reason_icon($reason);
                    echo "<li>$icon $reason</li>";
                }
                echo "</ul>";
                echo "</div>";
            }
            
            echo "</div>";
        }
        
        echo "</div>";
    }
    
    private static function render_condition_warnings($flags, $score, $reasons = []) {
        $warnings = [];
        
        // RSI Conditions (mutually exclusive)
        if (in_array('overbought', $flags) || $score > 80) {
            $warnings[] = "⚠️ OVERBOUGHT CONDITION (RSI > 80)";
        } elseif (in_array('oversold', $flags) || $score < 20) {
            $warnings[] = "⚠️ OVERSOLD CONDITION (RSI < 20)";
        }
        
        // Volume Conditions (mutually exclusive) - check reasons array
        $hasHighVolume = false;
        $hasLowVolume = false;
        foreach ($reasons as $reason) {
            if (stripos($reason, 'above-average volume') !== false) {
                $hasHighVolume = true;
                break;
            } elseif (stripos($reason, 'subdued volume') !== false) {
                $hasLowVolume = true;
                break;
            }
        }
        
        if ($hasHighVolume) {
            $warnings[] = "📊 HIGH VOLUME";
        } elseif ($hasLowVolume) {
            $warnings[] = "📊 SUBDUED VOLUME";
        }
        
        foreach ($warnings as $warning) {
            echo "<div style='background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 3px; margin: 4px 0; font-size: 12px; font-weight: bold;'>";
            echo $warning;
            echo "</div>";
        }
    }
    
    private static function get_reason_icon($reason) {
        $reason_lower = strtolower($reason);
        
        if (strpos($reason_lower, 'rsi') !== false) {
            if (strpos($reason_lower, 'above') !== false || strpos($reason_lower, 'rising') !== false) {
                return '🟢';
            } elseif (strpos($reason_lower, 'falling') !== false) {
                return '⚡';
            }
            return '📊';
        }
        
        if (strpos($reason_lower, 'macd') !== false) {
            if (strpos($reason_lower, 'expanding') !== false) {
                return '🌊';
            } elseif (strpos($reason_lower, 'contracting') !== false) {
                return '🌊';
            } elseif (strpos($reason_lower, 'histogram') !== false) {
                return '🌊';
            }
            return '🌊';
        }
        
        if (strpos($reason_lower, 'ema') !== false || strpos($reason_lower, 'price above') !== false) {
            if (strpos($reason_lower, 'above') !== false) {
                return '🟢';
            } elseif (strpos($reason_lower, 'below') !== false) {
                return '🔴';
            }
            return '📈';
        }
        
        if (strpos($reason_lower, 'range') !== false) {
            if (strpos($reason_lower, 'expanding upward') !== false) {
                return '🟢';
            } elseif (strpos($reason_lower, 'expanding downward') !== false) {
                return '🔴';
            }
            return '🔄';
        }
        
        if (strpos($reason_lower, 'volume') !== false) {
            return '📊';
        }
        
        if (strpos($reason_lower, 'overbought') !== false || strpos($reason_lower, 'oversold') !== false) {
            return '📋';
        }
        
        if (strpos($reason_lower, 'bear cross') !== false) {
            return '🔄';
        }
        
        // Default icon
        return '📋';
    }
    
    private static function render_trading_strategy($analysis) {
        $overallScore = $analysis['overall']['score'] ?? 0;
        $overallConfidence = $analysis['overall']['confidence'] ?? 0;
        $overallVelocity = $analysis['overall']['velocity'] ?? 0;
        $overallSynergy = $analysis['overall']['synergy'] ?? 'N/A';
        
        echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745;'>";
        echo "<h4 style='margin-top: 0; color: #28a745;'>💡 Trading Strategy Recommendations</h4>";
        
        // Market Outlook
        echo "<div style='margin-bottom: 15px;'>";
        echo "<strong>📈 Market Outlook:</strong> ";
        if ($overallScore >= 70 && $overallConfidence >= 0.6) {
            echo "Strong bullish momentum across multiple timeframes suggests potential upward movement. Consider long positions with appropriate risk management.";
        } elseif ($overallScore <= 30 && $overallConfidence >= 0.6) {
            echo "Strong bearish momentum detected. Consider short positions or wait for better entry points.";
        } else {
            echo "Mixed signals across timeframes. Exercise caution and wait for clearer directional bias.";
        }
        echo "</div>";
        
        // Entry/Exit Considerations
        echo "<div>";
        echo "<strong>🎯 Entry/Exit Considerations:</strong>";
        echo "<ul style='margin: 5px 0; padding-left: 20px;'>";
        
        // Velocity analysis
        if ($overallVelocity > 5) {
            echo "<li>📈 Positive velocity across most timeframes - momentum building</li>";
        } elseif ($overallVelocity < -5) {
            echo "<li>📉 Negative velocity trend - caution advised</li>";
        } else {
            echo "<li>⚖️ Mixed velocity signals - range-bound conditions</li>";
        }
        
        // Synergy analysis
        if (stripos($overallSynergy, 'bullish') !== false) {
            echo "<li>🟢 Multi-timeframe bullish alignment detected</li>";
        } elseif (stripos($overallSynergy, 'bearish') !== false) {
            echo "<li>🔴 Multi-timeframe bearish alignment detected</li>";
        } elseif (stripos($overallSynergy, 'conflict') !== false) {
            echo "<li>⚠️ Timeframe conflict - exercise caution</li>";
        }
        
        // Confidence assessment
        $confidenceText = $overallConfidence >= 0.7 ? "High confidence trade setup" : 
                         ($overallConfidence >= 0.5 ? "Moderate confidence" : "Low confidence - high risk");
        echo "<li>🎯 Confidence Level: " . round($overallConfidence * 100) . "% - $confidenceText</li>";
        
        echo "</ul>";
        echo "</div>";
        echo "</div>";
    }
    
    private static function render_trading_signals($analysis) {
        $overallScore = $analysis['overall']['score'] ?? 0;
        $overallConfidence = $analysis['overall']['confidence'] ?? 0;
        $overallVelocity = $analysis['overall']['velocity'] ?? 0;
        $overallSynergy = $analysis['overall']['synergy'] ?? 'N/A';
        
        echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ffc107;'>";
        echo "<h4 style='margin-top: 0; color: #856404;'>🎯 Trading Signals</h4>";
        echo "<ul style='margin: 0; padding-left: 20px;'>";
        
        // Score-based signals
        if ($overallScore >= 75) {
            echo "<li>🟢 Strong bullish momentum - consider long positions</li>";
        } elseif ($overallScore <= 25) {
            echo "<li>🔴 Strong bearish momentum - consider short positions</li>";
        } else {
            echo "<li>⚪ Neutral momentum - wait for clearer signals</li>";
        }
        
        // Velocity-based signals
        if ($overallVelocity > 10) {
            echo "<li>🚀 High positive velocity - strong upward trend</li>";
        } elseif ($overallVelocity < -10) {
            echo "<li>📉 High negative velocity - strong downward trend</li>";
        } else {
            echo "<li>⚖️ Mixed velocity signals - range-bound conditions</li>";
        }
        
        // Synergy analysis
        if (stripos($overallSynergy, 'bullish') !== false) {
            echo "<li>🟢 Multi-timeframe bullish alignment detected</li>";
        } elseif (stripos($overallSynergy, 'bearish') !== false) {
            echo "<li>🔴 Multi-timeframe bearish alignment detected</li>";
        } elseif (stripos($overallSynergy, 'conflict') !== false) {
            echo "<li>⚠️ Timeframe conflict - exercise caution</li>";
        }
        
        echo "<li>🎯 Confidence Level: " . round($overallConfidence * 100) . "% - " . 
             ($overallConfidence >= 0.7 ? "High confidence trade setup" : 
              ($overallConfidence >= 0.5 ? "Moderate confidence" : "Low confidence - high risk")) . "</li>";
        echo "</ul>";
        echo "</div>";
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
