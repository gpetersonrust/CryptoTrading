<?php
/**
 * Coin Cache Manager
 * Handles caching, updating, and retrieving cryptocurrency analysis data
 */

class CoinCacheManager {
    
    /**
     * Register the Coins custom post type
     */
    public static function register_post_type() {
        register_post_type('coin_analysis', [
            'label' => 'Coin Analysis',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-chart-line',
            'labels' => [
                'name' => 'Coin Analysis',
                'singular_name' => 'Coin Analysis',
                'add_new' => 'Add New Coin',
                'add_new_item' => 'Add New Coin Analysis',
                'edit_item' => 'Edit Coin Analysis',
                'new_item' => 'New Coin Analysis',
                'view_item' => 'View Coin Analysis',
                'search_items' => 'Search Coin Analysis',
                'not_found' => 'No coin analysis found',
                'not_found_in_trash' => 'No coin analysis found in trash'
            ]
        ]);
    }
    
    /**
     * Get cached analysis for a coin
     * 
     * @param string $coin_symbol The coin symbol (e.g., 'BTCUSD')
     * @return array|null The cached analysis data or null if not found/expired
     */
    public static function get_cached_analysis($coin_symbol) {
        $post = self::get_coin_post($coin_symbol);
        
        if (!$post) {
            return null;
        }
        
        $historical_data = get_post_meta($post->ID, 'historical_data', true);
        $last_update = get_post_meta($post->ID, 'last_search_datetime', true);
        
        // Check if data is too old (more than 2 hours = stale)
        if ($last_update && (time() - strtotime($last_update)) > 7200) {
            // Data is stale but return it anyway with a flag
            $analysis = maybe_unserialize($historical_data);
            if ($analysis) {
                $analysis['_cache_status'] = 'stale';
                $analysis['_last_update'] = $last_update;
            }
            return $analysis;
        }
        
        $analysis = maybe_unserialize($historical_data);
        if ($analysis) {
            $analysis['_cache_status'] = 'fresh';
            $analysis['_last_update'] = $last_update;
        }
        
        return $analysis;
    }
    
    /**
     * Save analysis data for a coin
     * 
     * @param string $coin_symbol The coin symbol
     * @param array $analysis_data The complete analysis data from TradeEngine
     * @return bool Success status
     */
    public static function save_analysis($coin_symbol, $analysis_data) {
        $post = self::get_coin_post($coin_symbol);
        
        if (!$post) {
            // Create new post
            $post_id = wp_insert_post([
                'post_title' => $coin_symbol,
                'post_type' => 'coin_analysis',
                'post_status' => 'publish',
                'post_content' => "Analysis data for $coin_symbol"
            ]);
        } else {
            $post_id = $post->ID;
        }
        
        if (!$post_id || is_wp_error($post_id)) {
            return false;
        }
        
        // Save the analysis data and timestamp
        $serialized_data = maybe_serialize($analysis_data);
        $current_time = current_time('mysql');
        
        update_post_meta($post_id, 'historical_data', $serialized_data);
        update_post_meta($post_id, 'last_search_datetime', $current_time);
        
        // Store overall score for easy sorting
        $overall_score = $analysis_data['overall']['score'] ?? 0;
        update_post_meta($post_id, 'overall_score', $overall_score);
        
        // Store historical overall trend scores (last 5)
        $trend_score = [
            'timestamp' => $current_time,
            'score' => $overall_score
        ];
        
        // Store timeframe-specific scores for trend analysis
        $timeframe_scores = [
            'timestamp' => $current_time,
            'overall' => $overall_score
        ];
        
        // Extract timeframe scores if available
        $timeframes = ['1m', '5m', '15m', '30m', '1h', '4h'];
        foreach ($timeframes as $tf) {
            if (isset($analysis_data['per_timeframe'][$tf]['score'])) {
                $timeframe_scores[$tf] = $analysis_data['per_timeframe'][$tf]['score'];
            }
        }
        
        // Get existing trend history
        $trend_history = get_post_meta($post_id, 'trend_history', true);
        if (!is_array($trend_history)) {
            $trend_history = [];
        }
        
        // Get existing timeframe trend history
        $tf_trend_history = get_post_meta($post_id, 'timeframe_trend_history', true);
        if (!is_array($tf_trend_history)) {
            $tf_trend_history = [];
        }
        
        // Add new score to beginning of array
        array_unshift($trend_history, $trend_score);
        array_unshift($tf_trend_history, $timeframe_scores);
        
        // Keep only last 5 entries
        $trend_history = array_slice($trend_history, 0, 5);
        $tf_trend_history = array_slice($tf_trend_history, 0, 5);
        
        update_post_meta($post_id, 'trend_history', $trend_history);
        update_post_meta($post_id, 'timeframe_trend_history', $tf_trend_history);
        
        return true;
    }
    
    /**
     * Get trend history for a coin (last 5 scores)
     * 
     * @param string $coin_symbol The coin symbol
     * @return array Array of trend data with confidence, velocity, synergy
     */
    public static function get_trend_history($coin_symbol) {
        $post = self::get_coin_post($coin_symbol);
        
        if (!$post) {
            return [];
        }
        
        $trend_history = get_post_meta($post->ID, 'trend_history', true);
        
        if (!is_array($trend_history)) {
            return [];
        }
        
        return $trend_history;
    }
    
    /**
     * Get timeframe trend history for a coin (last 5 scores for each timeframe)
     * 
     * @param string $coin_symbol The coin symbol
     * @return array Array of timeframe trend data
     */
    public static function get_timeframe_trend_history($coin_symbol) {
        $post = self::get_coin_post($coin_symbol);
        
        if (!$post) {
            return [];
        }
        
        $tf_trend_history = get_post_meta($post->ID, 'timeframe_trend_history', true);
        
        if (!is_array($tf_trend_history)) {
            return [];
        }
        
        return $tf_trend_history;
    }
    
    /**
     * Calculate growth trend for a specific timeframe
     * 
     * @param string $coin_symbol The coin symbol
     * @param string $timeframe The timeframe (e.g., '1m', '5m', '15m', '30m', '1h', '4h', 'overall')
     * @return float Growth percentage
     */
    public static function calculate_timeframe_growth($coin_symbol, $timeframe = 'overall') {
        $tf_trend_history = self::get_timeframe_trend_history($coin_symbol);
        
        if (count($tf_trend_history) < 2) {
            return 0; // Not enough data
        }
        
        // Get newest and oldest scores for the specified timeframe
        $newest_score = $tf_trend_history[0][$timeframe] ?? 0;
        $oldest_score = end($tf_trend_history)[$timeframe] ?? 0;
        
        if ($oldest_score == 0) {
            return 0;
        }
        
        // Calculate percentage change
        $growth = (($newest_score - $oldest_score) / $oldest_score) * 100;
        
        return round($growth, 2);
    }
    
    /**
     * Calculate growth trend for a coin based on trend history
     * 
     * @param string $coin_symbol The coin symbol
     * @return float Growth percentage (positive = growth, negative = decline)
     */
    public static function calculate_growth_trend($coin_symbol) {
        $trend_history = self::get_trend_history($coin_symbol);
        
        if (count($trend_history) < 2) {
            return 0; // Not enough data
        }
        
        // Get oldest and newest scores
        $newest_score = $trend_history[0]['score'] ?? 0;
        $oldest_score = end($trend_history)['score'] ?? 0;
        
        if ($oldest_score == 0) {
            return 0;
        }
        
        // Calculate percentage change
        $growth = (($newest_score - $oldest_score) / $oldest_score) * 100;
        
        return round($growth, 2);
    }
    
    /**
     * Get all coin symbols from the database dynamically
     * 
     * @return array List of coin symbols from post titles
     */
    public static function get_all_coin_symbols() {
        $posts = get_posts([
            'post_type' => 'coin_analysis',
            'post_status' => 'publish',
            'numberposts' => -1, // Get all
            'fields' => 'post_title'
        ]);
        
        $coin_symbols = [];
        foreach ($posts as $post) {
            $coin_symbols[] = $post->post_title;
        }
        
        // If no coins exist yet, return default list for initial setup
        if (empty($coin_symbols)) {
            return ['BTCUSD', 'SOLUSD', 'HFTUSD', 'MUSD', 'ETHUSD', 'TRACUSD', 'NTRNUSD', 'BEAMUSD'];
        }
        
        return $coin_symbols;
    }
    
    /**
     * Get the coin post by symbol
     * 
     * @param string $coin_symbol The coin symbol
     * @return WP_Post|null The post object or null
     */
    private static function get_coin_post($coin_symbol) {
        $posts = get_posts([
            'post_type' => 'coin_analysis',
            'title' => $coin_symbol,
            'numberposts' => 1,
            'post_status' => 'publish'
        ]);
        
        return $posts ? $posts[0] : null;
    }
    
    /**
     * Get the next coin that needs updating
     * Priority: Oldest update time, then highest score for tie-breaking
     * 
     * @param array $coin_list List of coin symbols to check
     * @return string|null The coin symbol that needs updating
     */
    public static function get_next_coin_to_update($coin_list) {
        global $wpdb;
        
        $coin_placeholders = implode(',', array_fill(0, count($coin_list), '%s'));
        
        $query = "
            SELECT p.post_title as coin_symbol, 
                   COALESCE(m1.meta_value, '1970-01-01 00:00:00') as last_update,
                   COALESCE(m2.meta_value, 0) as overall_score
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'last_search_datetime'
            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'overall_score'
            WHERE p.post_type = 'coin_analysis' 
            AND p.post_status = 'publish'
            AND p.post_title IN ($coin_placeholders)
            ORDER BY last_update ASC, overall_score DESC
            LIMIT 1
        ";
        
        $result = $wpdb->get_row($wpdb->prepare($query, ...$coin_list));
        
        if ($result) {
            return $result->coin_symbol;
        }
        
        // If no coins exist in database, return the first one from the list
        return $coin_list[0] ?? null;
    }
    
    /**
     * Get the next 4 coins that need updating based on last update time
     * Ties broken by overall score (higher score = higher priority)
     * 
     * @param array $coin_list List of coin symbols to check
     * @return array Array of coin symbols that need updating (up to 4)
     */
    public static function get_next_4_coins_to_update($coin_list) {
        global $wpdb;
        
        if (empty($coin_list)) {
            return [];
        }
        
        $coin_placeholders = implode(',', array_fill(0, count($coin_list), '%s'));
        
        $query = "
            SELECT p.post_title as coin_symbol, 
                   COALESCE(m1.meta_value, '1970-01-01 00:00:00') as last_update,
                   COALESCE(m2.meta_value, 0) as overall_score
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'last_search_datetime'
            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'overall_score'
            WHERE p.post_type = 'coin_analysis' 
            AND p.post_status = 'publish'
            AND p.post_title IN ($coin_placeholders)
            ORDER BY last_update ASC, overall_score DESC
            LIMIT 4
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, ...$coin_list));
        
        if ($results) {
            return array_map(function($row) {
                return $row->coin_symbol;
            }, $results);
        }
        
        // If no coins exist in database, return the first 4 from the list
        return array_slice($coin_list, 0, 4);
    }
    
    /**
     * Update a single coin's analysis data
     * 
     * @param string $coin_symbol The coin to update
     * @return array Result with success status and data
     */
    public static function update_coin_analysis($coin_symbol) {
        try {
            // Include required files
            require_once get_stylesheet_directory() . '/lib/class-tech-indicators.php';
            require_once get_stylesheet_directory() . '/lib/class-trade-engine.php';
            require_once get_stylesheet_directory() . '/includes/utilities.php';
            require_once get_stylesheet_directory() . '/includes/crypto-data-functions.php';
            
            $start_time = microtime(true);
            
            // Fetch multi-timeframe data
            $multiData = fetch_multi_timeframe_data($coin_symbol);
            
            // Prepare data for TradeEngine
            $byTf = [];
            $intervalMapping = [
                '1 minute' => '1m',
                '5 minutes' => '5m', 
                '15 minutes' => '15m',
                '30 minutes' => '30m',
                '1 hour' => '1h',
                '4 hours' => '4h',
                '1 day' => '1d'
            ];
            
            foreach ($multiData['master_candles'] as $interval => $candles) {
                if ($candles !== null && isset($intervalMapping[$interval])) {
                    $shortInterval = $intervalMapping[$interval];
                    $byTf[$shortInterval] = $candles;
                }
            }
            
            if (empty($byTf)) {
                return [
                    'success' => false,
                    'error' => 'No data available for analysis',
                    'coin' => $coin_symbol
                ];
            }
            
            // Run TradeEngine analysis
            $analysis = TradeEngineRouter::scoreAll($byTf, [
                'sessionTz' => 'America/New_York',
                'useSessionVWAP' => true,
                'minHistory' => 30
            ]);
            
            // Save to cache
            $saved = self::save_analysis($coin_symbol, $analysis);
            
            $end_time = microtime(true);
            $execution_time = round($end_time - $start_time, 2);
            
            return [
                'success' => $saved,
                'coin' => $coin_symbol,
                'analysis' => $analysis,
                'execution_time' => $execution_time,
                'timestamp' => current_time('mysql')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'coin' => $coin_symbol
            ];
        }
    }
    
    /**
     * Update multiple coins' analysis data in batch
     * 
     * @param array $coin_symbols Array of coin symbols to update
     * @return array Results with success status and data for each coin
     */
    public static function update_coins_batch($coin_symbols) {
        $results = [];
        $start_time = microtime(true);
        
        foreach ($coin_symbols as $coin_symbol) {
            $results[$coin_symbol] = self::update_coin_analysis($coin_symbol);
        }
        
        $end_time = microtime(true);
        $total_execution_time = round($end_time - $start_time, 2);
        
        // Calculate summary stats
        $success_count = array_sum(array_map(function($result) {
            return $result['success'] ? 1 : 0;
        }, $results));
        
        $error_count = count($coin_symbols) - $success_count;
        
        return [
            'batch_success' => $success_count > 0,
            'total_coins' => count($coin_symbols),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'total_execution_time' => $total_execution_time,
            'results' => $results,
            'timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Get all cached coin data for display
     * 
     * @param array $coin_list List of coins to retrieve
     * @return array Array of coin data with cache status
     */
    public static function get_all_cached_coins($coin_list) {
        $coin_data = [];
        
        foreach ($coin_list as $coin) {
            $analysis = self::get_cached_analysis($coin);
            
            if ($analysis) {
                $coin_data[] = [
                    'coin' => $coin,
                    'analysis' => $analysis,
                    'error' => null,
                    'cache_status' => $analysis['_cache_status'] ?? 'unknown',
                    'last_update' => $analysis['_last_update'] ?? null
                ];
            } else {
                // No cache - will need to load fresh
                $coin_data[] = [
                    'coin' => $coin,
                    'analysis' => null,
                    'error' => 'No cached data available',
                    'cache_status' => 'missing',
                    'last_update' => null
                ];
            }
        }
        
        return $coin_data;
    }
    
    /**
     * Get update status for admin dashboard
     * 
     * @param array $coin_list List of coins to check
     * @return array Status information
     */
    public static function get_update_status($coin_list) {
        $status = [];
        
        foreach ($coin_list as $coin) {
            $post = self::get_coin_post($coin);
            
            if ($post) {
                $last_update = get_post_meta($post->ID, 'last_search_datetime', true);
                $overall_score = get_post_meta($post->ID, 'overall_score', true);
                
                $age_seconds = $last_update ? (time() - strtotime($last_update)) : null;
                $age_minutes = $age_seconds ? round($age_seconds / 60) : null;
                
                $status[] = [
                    'coin' => $coin,
                    'last_update' => $last_update,
                    'age_minutes' => $age_minutes,
                    'overall_score' => $overall_score,
                    'status' => $age_seconds > 7200 ? 'stale' : ($age_seconds > 3600 ? 'aging' : 'fresh')
                ];
            } else {
                $status[] = [
                    'coin' => $coin,
                    'last_update' => null,
                    'age_minutes' => null,
                    'overall_score' => null,
                    'status' => 'missing'
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Clear all cached analysis data for all coins
     * Called when trade engine mode changes to force fresh analysis
     */
    public static function clear_all_cache() {
        $posts = get_posts([
            'post_type' => 'coin_analysis',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);
        
        $cleared_count = 0;
        foreach ($posts as $post) {
            // Clear the analysis cache
            delete_post_meta($post->ID, 'cached_analysis');
            delete_post_meta($post->ID, 'cache_timestamp');
            delete_post_meta($post->ID, 'trend_history');
            delete_post_meta($post->ID, 'timeframe_trend_history');
            $cleared_count++;
        }
        
        return $cleared_count;
    }
    
    /**
     * Analyze 24-hour trends and identify top/bottom performers
     * Called by cron job every 10 minutes
     */
    public static function analyze_24h_trends() {
        $coins = self::get_all_coin_symbols();
        $trend_analysis = [];
        
        foreach ($coins as $coin) {
            $post = self::get_coin_post($coin);
            if (!$post) continue;
            
            // Get trend history for last 24 hours
            $trend_history = get_post_meta($post->ID, 'trend_history', true) ?: [];
            $timeframe_history = get_post_meta($post->ID, 'timeframe_trend_history', true) ?: [];
            
            if (empty($trend_history)) continue;
            
            // Filter to last 24 hours (288 entries = 24h * 60min / 5min intervals)
            $cutoff_time = time() - (24 * 60 * 60);
            $recent_trends = array_filter($trend_history, function($trend) use ($cutoff_time) {
                $trend_time = isset($trend['timestamp']) ? strtotime($trend['timestamp']) : 0;
                return $trend_time >= $cutoff_time;
            });
            
            if (count($recent_trends) < 10) continue; // Need at least 10 data points
            
            // Calculate statistics
            $scores = array_column($recent_trends, 'score');
            $highest_score = max($scores);
            $lowest_score = min($scores);
            $average_score = array_sum($scores) / count($scores);
            
            // Calculate trend direction (first vs last score)
            $first_score = reset($recent_trends)['score'] ?? 0;
            $last_score = end($recent_trends)['score'] ?? 0;
            $trend_change = $last_score - $first_score;
            
            // Calculate volatility (standard deviation)
            $mean = $average_score;
            $variance = array_sum(array_map(function($score) use ($mean) {
                return pow($score - $mean, 2);
            }, $scores)) / count($scores);
            $volatility = sqrt($variance);
            
            $trend_analysis[$coin] = [
                'coin' => $coin,
                'highest_score' => $highest_score,
                'lowest_score' => $lowest_score,
                'average_score' => round($average_score, 2),
                'trend_change' => round($trend_change, 2),
                'volatility' => round($volatility, 2),
                'data_points' => count($recent_trends),
                'last_update' => current_time('mysql')
            ];
        }
        
        if (empty($trend_analysis)) {
            return false;
        }
        
        // Sort by different criteria
        $highest_trend = $trend_analysis;
        $lowest_trend = $trend_analysis;
        $highest_average = $trend_analysis;
        
        uasort($highest_trend, function($a, $b) { return $b['trend_change'] <=> $a['trend_change']; });
        uasort($lowest_trend, function($a, $b) { return $a['trend_change'] <=> $b['trend_change']; });
        uasort($highest_average, function($a, $b) { return $b['average_score'] <=> $a['average_score']; });
        
        // Store results in WordPress options
        update_option('coin_24h_trend_analysis', [
            'last_update' => current_time('mysql'),
            'highest_trend' => array_slice($highest_trend, 0, 5, true), // Top 5
            'lowest_trend' => array_slice($lowest_trend, 0, 5, true), // Bottom 5
            'highest_average' => array_slice($highest_average, 0, 5, true), // Top 5 averages
            'total_coins_analyzed' => count($trend_analysis)
        ]);
        
        return true;
    }
    
    /**
     * Get the latest 24-hour trend analysis
     */
    public static function get_24h_trend_analysis() {
        return get_option('coin_24h_trend_analysis', [
            'last_update' => null,
            'highest_trend' => [],
            'lowest_trend' => [],
            'highest_average' => [],
            'total_coins_analyzed' => 0
        ]);
    }
}
