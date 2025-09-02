<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'astra-theme-css' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

// Coin Analysis System
require_once get_stylesheet_directory() . '/includes/class-coin-cache-manager.php';
require_once get_stylesheet_directory() . '/includes/class-coin-analysis-api.php';
require_once get_stylesheet_directory() . '/includes/class-coin-analysis-cron.php';

// Trade Engine System
require_once get_stylesheet_directory() . '/lib/class-tech-indicators.php';
require_once get_stylesheet_directory() . '/lib/class-trade-engine.php';
require_once get_stylesheet_directory() . '/lib/class-trade-engine-swing.php';
require_once get_stylesheet_directory() . '/lib/class-trade-engine-router.php';
require_once get_stylesheet_directory() . '/includes/class-trade-engine-settings.php';

// Initialize Trade Engine Settings in admin
if (class_exists('Trade_Engine_Settings')) {
    Trade_Engine_Settings::define_hooks();
}

// Register custom post type on init
add_action('init', ['CoinCacheManager', 'register_post_type']);

// Add admin menu for monitoring
add_action('admin_menu', 'coin_analysis_admin_menu');

function coin_analysis_admin_menu() {
    add_menu_page(
        'Coin Analysis',
        'Coin Analysis',
        'manage_options',
        'coin-analysis',
        'coin_analysis_admin_page',
        'dashicons-chart-line',
        30
    );
}

function coin_analysis_admin_page() {
    $coin_list = CoinCacheManager::get_all_coin_symbols();
    $status = CoinCacheManager::get_update_status($coin_list);
    $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
    $next_4_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
    $update_log = CoinAnalysisCron::get_update_log();
    
    // Handle manual update trigger
    if (isset($_POST['trigger_update']) && wp_verify_nonce($_POST['_wpnonce'], 'trigger_update')) {
        CoinAnalysisCron::trigger_manual_update();
        echo '<div class="notice notice-success"><p>Manual update triggered!</p></div>';
        // Refresh data
        $status = CoinCacheManager::get_update_status($coin_list);
        $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
        $next_4_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
    }
    
    // Handle manual batch update trigger
    if (isset($_POST['trigger_batch_update']) && wp_verify_nonce($_POST['_wpnonce'], 'trigger_batch_update')) {
        $batch_result = CoinAnalysisCron::trigger_manual_batch_update();
        if ($batch_result && $batch_result['batch_success']) {
            echo '<div class="notice notice-success"><p>Batch update completed! Updated ' . 
                 $batch_result['success_count'] . '/' . $batch_result['total_coins'] . 
                 ' coins successfully in ' . $batch_result['total_execution_time'] . 's</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>Batch update completed with some issues.</p></div>';
        }
        // Refresh data
        $status = CoinCacheManager::get_update_status($coin_list);
        $next_coin = CoinCacheManager::get_next_coin_to_update($coin_list);
        $next_4_coins = CoinCacheManager::get_next_4_coins_to_update($coin_list);
    }
    
    // Handle manual trend analysis trigger
    if (isset($_POST['trigger_trend_analysis']) && wp_verify_nonce($_POST['_wpnonce'], 'trigger_trend_analysis')) {
        $result = CoinAnalysisCron::cron_24h_trend_analysis();
        if ($result) {
            echo '<div class="notice notice-success"><p>24h Trend Analysis completed successfully!</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>24h Trend Analysis completed but no data available yet. More data needed.</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Coin Analysis Dashboard</h1>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
            
            <!-- Status Overview -->
            <div class="card" style="padding: 20px;">
                <h2>Cache Status</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Coin</th>
                            <th>Status</th>
                            <th>Last Update</th>
                            <th>Age (min)</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status as $coin_status): ?>
                        <tr>
                            <td><strong><?php echo esc_html($coin_status['coin']); ?></strong></td>
                            <td>
                                <?php
                                $status_color = [
                                    'fresh' => '#28a745',
                                    'aging' => '#ffc107', 
                                    'stale' => '#dc3545',
                                    'missing' => '#6c757d'
                                ];
                                $color = $status_color[$coin_status['status']] ?? '#6c757d';
                                ?>
                                <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                    <?php echo ucfirst($coin_status['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $coin_status['last_update'] ?: 'Never'; ?></td>
                            <td><?php echo $coin_status['age_minutes'] ?: 'N/A'; ?></td>
                            <td><?php echo $coin_status['overall_score'] ?: 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px;">
                    <p><strong>Next 4 Coins to Update:</strong> 
                    <?php 
                    if (!empty($next_4_coins)) {
                        echo '<span style="font-family: monospace; background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">' . 
                             esc_html(implode(', ', $next_4_coins)) . '</span>';
                    } else {
                        echo 'None';
                    }
                    ?>
                    </p>
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        <em>Batch updates run every 10 seconds. Priority: Oldest update time, then highest trend score.</em>
                    </p>
                    
                    <form method="post" style="display: inline; margin-right: 10px;">
                        <?php wp_nonce_field('trigger_update'); ?>
                        <input type="submit" name="trigger_update" class="button button-primary" value="Trigger Manual Update (1 coin)">
                    </form>
                    
                    <form method="post" style="display: inline; margin-right: 10px;">
                        <?php wp_nonce_field('trigger_batch_update'); ?>
                        <input type="submit" name="trigger_batch_update" class="button button-primary" value="Trigger Batch Update (4 coins)">
                    </form>
                    
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('trigger_trend_analysis'); ?>
                        <input type="submit" name="trigger_trend_analysis" class="button button-secondary" value="Analyze 24h Trends">
                    </form>
                </div>
            </div>
            
            <!-- Update Log -->
            <div class="card" style="padding: 20px;">
                <h2>Recent Updates</h2>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($update_log)): ?>
                        <p>No updates recorded yet.</p>
                    <?php else: ?>
                        <?php foreach (array_reverse($update_log) as $log_entry): ?>
                        <div style="border-bottom: 1px solid #ddd; padding: 10px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong><?php echo esc_html($log_entry['coin']); ?></strong>
                                <span style="color: <?php echo $log_entry['success'] ? '#28a745' : '#dc3545'; ?>;">
                                    <?php echo $log_entry['success'] ? '✓' : '✗'; ?>
                                </span>
                            </div>
                            <div style="font-size: 12px; color: #666;">
                                <?php echo esc_html($log_entry['timestamp']); ?>
                                <?php if ($log_entry['execution_time']): ?>
                                    | <?php echo $log_entry['execution_time']; ?>s
                                <?php endif; ?>
                            </div>
                            <?php if (!$log_entry['success'] && $log_entry['error']): ?>
                                <div style="color: #dc3545; font-size: 12px;">
                                    Error: <?php echo esc_html($log_entry['error']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- API Endpoints -->
        <div class="card" style="padding: 20px; margin-top: 20px;">
            <h2>API Endpoints</h2>
            <ul>
                <li><strong>Update Next Coin:</strong> <code><?php echo home_url('/wp-json/coin-analysis/v1/update-next'); ?></code></li>
                <li><strong>Get Status:</strong> <code><?php echo home_url('/wp-json/coin-analysis/v1/status'); ?></code></li>
                <li><strong>Update Specific Coin:</strong> <code><?php echo home_url('/wp-json/coin-analysis/v1/update-coin/{COIN}'); ?></code></li>
            </ul>
        </div>
    </div>
    <?php
}

// AJAX handler for adding new coins
add_action('wp_ajax_add_new_coin', 'handle_add_new_coin');
add_action('wp_ajax_nopriv_add_new_coin', 'handle_add_new_coin');

function handle_add_new_coin() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'add_coin_nonce')) {
        wp_die(json_encode(['success' => false, 'data' => 'Security check failed']));
    }
    
    // Check if user has permissions (optional - remove if you want any user to add coins)
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(['success' => false, 'data' => 'Insufficient permissions']));
    }
    
    $coin_symbol = sanitize_text_field($_POST['coin_symbol']);
    
    if (empty($coin_symbol)) {
        wp_die(json_encode(['success' => false, 'data' => 'Coin symbol is required']));
    }
    
    // Validate coin symbol format (basic validation)
    if (!preg_match('/^[A-Z0-9]{3,20}$/', $coin_symbol)) {
        wp_die(json_encode(['success' => false, 'data' => 'Invalid coin symbol format. Use uppercase letters and numbers only (3-20 characters)']));
    }
    
    // Check if coin already exists
    $existing_posts = get_posts([
        'post_type' => 'coin_analysis',
        'title' => $coin_symbol,
        'post_status' => 'any',
        'numberposts' => 1
    ]);
    
    if (!empty($existing_posts)) {
        wp_die(json_encode(['success' => false, 'data' => 'Coin already exists in the system']));
    }
    
    // Create new coin post
    $post_data = [
        'post_title' => $coin_symbol,
        'post_content' => 'Auto-generated coin analysis post for ' . $coin_symbol,
        'post_status' => 'publish',
        'post_type' => 'coin_analysis',
        'meta_input' => [
            'coin_symbol' => $coin_symbol,
            'created_via_frontend' => true,
            'creation_date' => current_time('mysql')
        ]
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        wp_die(json_encode(['success' => false, 'data' => 'Failed to create coin post: ' . $post_id->get_error_message()]));
    }
    
    if ($post_id) {
        wp_die(json_encode([
            'success' => true, 
            'data' => [
                'message' => 'Coin added successfully',
                'coin_symbol' => $coin_symbol,
                'post_id' => $post_id
            ]
        ]));
    } else {
        wp_die(json_encode(['success' => false, 'data' => 'Failed to create coin post']));
    }
}
