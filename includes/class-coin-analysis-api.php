<?php
/**
 * Coin Analysis REST API
 * Handles background updates and status monitoring
 */

class CoinAnalysisAPI {
    
    /**
     * Initialize the REST API endpoints
     */
    public static function init() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Update next coin endpoint
        register_rest_route('coin-analysis/v1', '/update-next', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_next_coin'],
            'permission_callback' => '__return_true', // Allow public access for now
        ]);
        
        // Update specific coin endpoint
        register_rest_route('coin-analysis/v1', '/update-coin/(?P<coin>[a-zA-Z0-9]+)', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_specific_coin'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get status endpoint
        register_rest_route('coin-analysis/v1', '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_status'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get next coin to update
        register_rest_route('coin-analysis/v1', '/next-coin', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_next_coin'],
            'permission_callback' => '__return_true',
        ]);
        
        // Update all coins endpoint
        register_rest_route('coin-analysis/v1', '/update-all', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_all_coins'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get next 4 coins to update
        register_rest_route('coin-analysis/v1', '/next-4-coins', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_next_4_coins'],
            'permission_callback' => '__return_true',
        ]);
        
        // Update next 4 coins (batch)
        register_rest_route('coin-analysis/v1', '/update-batch', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_batch_coins'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Update the next coin that needs updating
     */
    public static function update_next_coin($request) {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
        
        if (!$next_coin) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No coins available for update'
            ], 400);
        }
        
        $result = CoinCacheManager::update_coin_analysis($next_coin);
        
        if ($result['success']) {
            // Schedule the next update
            self::schedule_next_update();
            
            return new WP_REST_Response([
                'success' => true,
                'message' => "Successfully updated $next_coin",
                'data' => $result,
                'next_update_scheduled' => true
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => "Failed to update $next_coin",
                'error' => $result['error'] ?? 'Unknown error'
            ], 500);
        }
    }
    
    /**
     * Update a specific coin
     */
    public static function update_specific_coin($request) {
        $coin = strtoupper($request['coin']);
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        
        if (!in_array($coin, $coin_list)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "Coin $coin is not in the monitored list"
            ], 400);
        }
        
        $result = CoinCacheManager::update_coin_analysis($coin);
        
        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => "Successfully updated $coin",
                'data' => $result
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => "Failed to update $coin",
                'error' => $result['error'] ?? 'Unknown error'
            ], 500);
        }
    }
    
    /**
     * Get status of all coins
     */
    public static function get_status($request) {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $status = CoinCacheManager::get_update_status($coin_list);
        $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
        $next_4_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
        
        return new WP_REST_Response([
            'success' => true,
            'coin_status' => $status,
            'next_coin_to_update' => $next_coin,
            'next_4_coins_to_update' => $next_4_coins,
            'total_coins' => count($coin_list),
            'timestamp' => current_time('mysql')
        ], 200);
    }
    
    /**
     * Get the next coin that needs updating
     */
    public static function get_next_coin($request) {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
        
        return new WP_REST_Response([
            'success' => true,
            'next_coin' => $next_coin,
            'timestamp' => current_time('mysql')
        ], 200);
    }
    
    /**
     * Update all coins at once
     */
    public static function update_all_coins($request) {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        
        if (empty($coin_list)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No coins available for update'
            ], 400);
        }
        
        $results = [];
        $success_count = 0;
        $error_count = 0;
        
        // Update each coin
        foreach ($coin_list as $coin) {
            $result = CoinCacheManager::update_coin_analysis($coin);
            $results[$coin] = $result;
            
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
            
            // Add a small delay to prevent overwhelming the API
            usleep(500000); // 0.5 seconds delay between requests
        }
        
        // Trigger trend analysis after all coins are updated
        if ($success_count > 0) {
            CoinAnalysisCron::cron_24h_trend_analysis();
        }
        
        return new WP_REST_Response([
            'success' => $error_count === 0,
            'message' => "Updated {$success_count} coins successfully, {$error_count} failed",
            'data' => [
                'total_coins' => count($coin_list),
                'success_count' => $success_count,
                'error_count' => $error_count,
                'results' => $results
            ]
        ], $error_count === 0 ? 200 : 206); // 206 = Partial Content if some failed
    }
    
    /**
     * Schedule the next update using WordPress cron
     */
    private static function schedule_next_update() {
        // Schedule next update in 5 seconds (allowing for some processing time)
        if (!wp_next_scheduled('coin_analysis_update_next')) {
            wp_schedule_single_event(time() + 5, 'coin_analysis_update_next');
        }
    }
    
    /**
     * Get the next 4 coins that need updating
     */
    public static function get_next_4_coins($request) {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_4_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
        
        return new WP_REST_Response([
            'success' => true,
            'next_4_coins' => $next_4_coins,
            'total_coins' => count($coin_list),
            'timestamp' => current_time('mysql')
        ], 200);
    }
    
    /**
     * Update the next 4 coins in batch
     */
    public static function update_batch_coins($request) {
        $coin_list = CoinCacheManager::get_all_coin_symbols();
        $next_4_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
        
        if (empty($next_4_coins)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No coins available for batch update'
            ], 400);
        }
        
        $batch_result = CoinCacheManager::update_coins_batch($next_4_coins);
        
        // Trigger trend analysis after batch update
        if ($batch_result['success_count'] > 0) {
            CoinAnalysisCron::cron_24h_trend_analysis();
        }
        
        if ($batch_result['batch_success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => "Batch update completed: {$batch_result['success_count']}/{$batch_result['total_coins']} coins updated successfully",
                'data' => $batch_result
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => "Batch update failed: {$batch_result['error_count']}/{$batch_result['total_coins']} coins failed",
                'data' => $batch_result
            ], 500);
        }
    }
}

// Initialize the API
CoinAnalysisAPI::init();
