<?php
/**
 * Trade Engine Settings
 * Admin settings: toggle short-term vs long-term engine.
 */
class Trade_Engine_Settings {

    public static function define_hooks() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('update_option_trade_engine_mode', [__CLASS__, 'clear_cache_on_engine_change'], 10, 2);
    }
    
    public static function clear_cache_on_engine_change($old_value, $new_value) {
        if ($old_value !== $new_value) {
            // Clear all cached analysis when engine mode changes
            if (class_exists('CoinCacheManager')) {
                CoinCacheManager::clear_all_cache();
                
                // Add admin notice
                add_action('admin_notices', function() use ($new_value) {
                    $engine_name = $new_value === 'swing' ? 'Long-Term (Swing)' : 'Short-Term (Scalp)';
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>Trade Engine changed to ' . $engine_name . '</strong> - All cached analysis cleared. New analysis will use the selected engine.</p>';
                    echo '</div>';
                });
            }
        }
    }

    public static function register_settings() {
        register_setting('trade_engine_settings', 'trade_engine_mode', [
            'type' => 'string',
            'sanitize_callback' => function($v){ return in_array($v,['scalp','swing'],true)?$v:'scalp'; },
            'default' => 'scalp',
        ]);

        add_settings_section('trade_engine_main', 'Trade Engine Mode', function(){
            echo '<p>Select which engine to use globally.</p>';
        }, 'trade-engine');

        add_settings_field('trade_engine_mode', 'Engine Mode', [__CLASS__, 'field_mode'], 'trade-engine', 'trade_engine_main');
    }

    public static function add_menu() {
        add_options_page('Trade Engine', 'Trade Engine', 'manage_options', 'trade-engine', [__CLASS__, 'render_page']);
    }

    public static function field_mode() {
        $mode = get_option('trade_engine_mode', 'scalp');
        ?>
        <label><input type="radio" name="trade_engine_mode" value="scalp" <?php checked($mode,'scalp'); ?>> Short-Term (Scalp)</label><br>
        <label><input type="radio" name="trade_engine_mode" value="swing" <?php checked($mode,'swing'); ?>> Long-Term (Swing)</label>
        <?php
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Trade Engine</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('trade_engine_settings');
                do_settings_sections('trade-engine');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
