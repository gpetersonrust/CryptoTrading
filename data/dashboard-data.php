<?php
/**
 * Dashboard Data Handler
 * Handles all data collection and processing for the dashboard
 */

class DashboardData {
    
    public static function get_coin_analysis_data() {
        // Get coins from WordPress post types instead of hardcoded array
        $coin_posts = get_posts([
            'post_type' => 'coin_analysis',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        
        $coinAnalysisData = [];
        
        foreach ($coin_posts as $post) {
            $coin = $post->post_title;
            $analysis = CoinCacheManager::get_cached_analysis($coin);
            $error = null;
            
            if (!$analysis) {
                $error = "No cached analysis available for $coin";
            }
            
            // Get growth percentages using the correct method names
            $growth = CoinCacheManager::calculate_growth_trend($coin);
            $timeframe_growth = [];
            
            // Calculate growth for each timeframe
            $timeframes = ['1m', '5m', '15m', '30m', '1h', '4h'];
            foreach ($timeframes as $tf) {
                $timeframe_growth[$tf] = CoinCacheManager::calculate_timeframe_growth($coin, $tf);
            }
            
            $coinAnalysisData[] = [
                'coin' => $coin,
                'analysis' => $analysis,
                'error' => $error,
                'growth' => $growth,
                'timeframe_growth' => $timeframe_growth
            ];
        }
        
        return $coinAnalysisData;
    }
    
    public static function get_sorted_coins($coinAnalysisData, $sortBy = 'score') {
        // Sort by overall score (highest first)
        usort($coinAnalysisData, function($a, $b) use ($sortBy) {
            if ($sortBy === 'score') {
                $scoreA = $a['analysis']['overall']['score'] ?? 0;
                $scoreB = $b['analysis']['overall']['score'] ?? 0;
                return $scoreB <=> $scoreA;
            }
            // Add other sorting options here
            return 0;
        });
        
        return $coinAnalysisData;
    }
    
    public static function get_engine_info() {
        // Check both possible option names to determine engine mode
        $engine_mode = get_option('crypto_trading_engine_mode', get_option('trade_engine_mode', 'scalp'));
        
        // Determine active engine based on the option value
        $active_engine = ($engine_mode === 'swing') ? 'swing' : 'scalp';
        
        return [
            'mode' => $engine_mode,
            'active_engine' => $active_engine,
            'is_swing' => ($active_engine === 'swing')
        ];
    }
    
    public static function get_trend_analysis() {
        return CoinCacheManager::get_24h_trend_analysis();
    }
}
