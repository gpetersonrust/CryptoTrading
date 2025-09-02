<?php
/**
 * Coin Analysis Cron Manager
 * Handles scheduled background updates
 */

class CoinAnalysisCron {
    
    /**
     * Initialize cron functionality
     */
    public static function init() {
        // Register custom cron intervals
        add_filter('cron_schedules', [self::class, 'add_cron_intervals']);
        
        // Hook our update function to the cron event
        add_action('coin_analysis_update_next', [self::class, 'cron_update_next_coin']);
        add_action('coin_analysis_continuous_update', [self::class, 'cron_continuous_update']);
        add_action('coin_24h_trend_analysis', [self::class, 'cron_24h_trend_analysis']);
        
        // Schedule the continuous update if not already scheduled (every 10 seconds for batch updates)
        if (!wp_next_scheduled('coin_analysis_continuous_update')) {
            wp_schedule_event(time(), 'every_10_seconds', 'coin_analysis_continuous_update');
        }
        
        // Schedule 24h trend analysis every 10 minutes
        if (!wp_next_scheduled('coin_24h_trend_analysis')) {
            wp_schedule_event(time(), 'every_10_minutes', 'coin_24h_trend_analysis');
        }
        
        // Clean up on deactivation
        register_deactivation_hook(__FILE__, [self::class, 'deactivate_cron']);
    }
    
    /**
     * Add custom cron intervals
     */
    public static function add_cron_intervals($schedules) {
        $schedules['every_5_seconds'] = [
            'interval' => 5,
            'display' => __('Every 5 Seconds')
        ];
        
        $schedules['every_10_seconds'] = [
            'interval' => 10,
            'display' => __('Every 10 Seconds')
        ];
        
        $schedules['every_10_minutes'] = [
            'interval' => 600, // 10 minutes * 60 seconds
            'display' => __('Every 10 Minutes')
        ];
        
        return $schedules;
    }
    
    /**
     * Cron job to update the next coin (legacy - single coin update)
     */
    public static function cron_update_next_coin() {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
        
        if ($next_coin) {
            $result = CoinCacheManager::update_coin_analysis($next_coin);
            
            // Log the result
            self::log_update_result($result);
            
            // Schedule the next update
            self::schedule_next_update();
        }
    }
    
    /**
     * Continuous cron job that runs every 10 seconds - updates 4 coins at a time
     * Selects 4 oldest coins (by last update time) and breaks ties with trend score
     */
    public static function cron_continuous_update() {
        // Check if another update is already in progress
        $update_lock = get_transient('coin_analysis_update_lock');
        if ($update_lock) {
            return; // Another update is in progress
        }
        
        // Set a lock to prevent multiple simultaneous updates (increased to 30 seconds for batch)
        set_transient('coin_analysis_update_lock', time(), 30);
        
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
        
        if (!empty($next_coins)) {
            $batch_result = CoinCacheManager::update_coins_batch($next_coins);
            
            // Log each coin update result
            foreach ($batch_result['results'] as $coin => $result) {
                self::log_update_result($result);
            }
            
            // Log batch summary
            self::log_batch_result($batch_result);
        }
        
        // Release the lock
        delete_transient('coin_analysis_update_lock');
    }
    
    /**
     * Schedule the next single update
     */
    private static function schedule_next_update() {
        if (!wp_next_scheduled('coin_analysis_update_next')) {
            wp_schedule_single_event(time() + 5, 'coin_analysis_update_next');
        }
    }
    
    /**
     * Log update results
     */
    private static function log_update_result($result) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'coin' => $result['coin'] ?? 'unknown',
            'success' => $result['success'] ?? false,
            'execution_time' => $result['execution_time'] ?? 0,
            'error' => $result['error'] ?? null
        ];
        
        // Store last 10 log entries
        $log = get_option('coin_analysis_update_log', []);
        $log[] = $log_entry;
        
        // Keep only last 20 entries
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }
        
        update_option('coin_analysis_update_log', $log);
    }
    
    /**
     * Log batch update results
     */
    private static function log_batch_result($batch_result) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'coin' => 'BATCH_UPDATE',
            'success' => $batch_result['batch_success'] ?? false,
            'execution_time' => $batch_result['total_execution_time'] ?? 0,
            'error' => null,
            'batch_info' => sprintf(
                'Updated %d/%d coins successfully', 
                $batch_result['success_count'] ?? 0,
                $batch_result['total_coins'] ?? 0
            )
        ];
        
        // Store batch log entry
        $log = get_option('coin_analysis_update_log', []);
        $log[] = $log_entry;
        
        // Keep only last 20 entries
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }
        
        update_option('coin_analysis_update_log', $log);
    }
    
    /**
     * Deactivate cron jobs
     */
    public static function deactivate_cron() {
        wp_clear_scheduled_hook('coin_analysis_update_next');
        wp_clear_scheduled_hook('coin_analysis_continuous_update');
    }
    
    /**
     * Get update log for admin display
     */
    public static function get_update_log() {
        return get_option('coin_analysis_update_log', []);
    }
    
    /**
     * Manual trigger for testing
     */
    public static function trigger_manual_update() {
        return self::cron_continuous_update();
    }
    
    /**
     * Cron job to analyze 24-hour trends every 10 minutes
     */
    public static function cron_24h_trend_analysis() {
        $start_time = microtime(true);
        
        try {
            $result = CoinCacheManager::analyze_24h_trends();
            
            $end_time = microtime(true);
            $execution_time = round($end_time - $start_time, 2);
            
            // Log the analysis
            $log_entry = [
                'timestamp' => current_time('mysql'),
                'action' => '24h_trend_analysis',
                'result' => $result ? 'success' : 'no_data',
                'execution_time' => $execution_time . 's'
            ];
            
            // Store in update log
            $update_log = get_option('coin_analysis_update_log', []);
            array_unshift($update_log, $log_entry);
            $update_log = array_slice($update_log, 0, 100); // Keep last 100 entries
            update_option('coin_analysis_update_log', $update_log);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('24h Trend Analysis Error: ' . $e->getMessage());
            
            $log_entry = [
                'timestamp' => current_time('mysql'),
                'action' => '24h_trend_analysis',
                'result' => 'error',
                'error' => $e->getMessage()
            ];
            
            $update_log = get_option('coin_analysis_update_log', []);
            array_unshift($update_log, $log_entry);
            $update_log = array_slice($update_log, 0, 100);
            update_option('coin_analysis_update_log', $update_log);
            
            return false;
        }
    }
    
    /**
     * Manual trigger for batch updates (testing/admin use)
     */
    public static function trigger_manual_batch_update() {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
        
        if (!empty($next_coins)) {
            $batch_result = CoinCacheManager::update_coins_batch($next_coins);
            
            // Log each coin update result
            foreach ($batch_result['results'] as $coin => $result) {
                self::log_update_result($result);
            }
            
            // Log batch summary
            self::log_batch_result($batch_result);
            
            return $batch_result;
        }
        
        return false;
    }
}

// Initialize cron functionality
CoinAnalysisCron::init();
