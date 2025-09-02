<?php
/**
 * Clean Front Page Template
 * Using component-based architecture
 */

// Include all required files
require_once get_stylesheet_directory() . '/includes/class-coin-cache-manager.php';
require_once get_stylesheet_directory() . '/lib/class-trade-engine-router.php';

// Include all components
require_once get_stylesheet_directory() . '/data/dashboard-data.php';
require_once get_stylesheet_directory() . '/components/dashboard-header.php';
require_once get_stylesheet_directory() . '/components/dashboard-controls.php';
require_once get_stylesheet_directory() . '/components/dashboard-styles.php';
require_once get_stylesheet_directory() . '/components/dashboard-scripts.php';
require_once get_stylesheet_directory() . '/components/coin-accordion.php';
require_once get_stylesheet_directory() . '/components/coin-analysis-content.php';
require_once get_stylesheet_directory() . '/components/trends-sidebar.php';

 

try {
    // Get all data
    $coinAnalysisData = DashboardData::get_coin_analysis_data();
    $sortedCoins = DashboardData::get_sorted_coins($coinAnalysisData);
    $engineInfo = DashboardData::get_engine_info();
    $trendAnalysis = DashboardData::get_trend_analysis();
    
    // Render styles
    DashboardStyles::render();
    
    // Render header
    DashboardHeader::render($engineInfo);
    
    // Render controls
    DashboardControls::render();
    
    // Start main layout
    echo "<div class='main-layout'>";
    
    // Left column - Coins
    echo "<div class='coins-column'>";
    foreach ($sortedCoins as $index => $coinData) {
        CoinAccordion::render($coinData, $index);
    }
    echo "</div>";
    
    // Right column - Trends
    echo "<div class='trends-column'>";
    TrendsSidebar::render($trendAnalysis);
    echo "</div>";
    
    // Close main layout
    echo "</div>";
    
    // Render JavaScript
    DashboardScripts::render();
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
    echo "<h3>Application Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

 
?>
