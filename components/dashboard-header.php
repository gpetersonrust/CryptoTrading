<?php
/**
 * Dashboard Header Component
 */

class DashboardHeader {
    
    public static function render($engine_info) {
        ?>
        <!-- Hero Header -->
        <div style="
            background: linear-gradient(135deg, var(--accent) 0%, color-mix(in srgb, var(--accent) 80%, #764ba2) 100%);
            color: white;
            padding: var(--space-6) var(--space-4);
            margin: var(--space-5) 0;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        ">
            <!-- Background decoration -->
            <div style="
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                animation: pulse 4s ease-in-out infinite alternate;
            "></div>
            
            <div style="position: relative; z-index: 1;">
                <div style="font-size: 48px; margin-bottom: var(--space-2);">🚀</div>
                <h1 style="
                    margin: 0 0 var(--space-3) 0;
                    font-size: clamp(28px, 5vw, 42px);
                    font-weight: 800;
                    letter-spacing: -1px;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                    line-height: 1.1;
                ">
                    Multi-Coin Trading Analysis
                </h1>
                <p style="
                    margin: 0;
                    font-size: 18px;
                    font-weight: 500;
                    opacity: 0.95;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                    max-width: 600px;
                    margin-left: auto;
                    margin-right: auto;
                ">
                    Real-time analysis across 15 cryptocurrency pairs with advanced technical indicators
                </p>
            </div>
        </div>
        
        <!-- Engine Status Cards -->
        <div style="display: flex; gap: var(--space-3); margin: var(--space-4) 0; flex-wrap: wrap; justify-content: center; max-width: 1400px; margin-left: auto; margin-right: auto; padding: 0 var(--space-3);">
            
            <!-- Engine Selector Button -->
            <?php if ($engine_info['is_swing']): ?>
            <a href="?engine=scalp" style="
                background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 90%, #4CAF50));
                color: white;
                padding: var(--space-4);
                border-radius: var(--radius);
                flex: 1;
                min-width: 300px;
                box-shadow: var(--shadow);
                position: relative;
                overflow: hidden;
                text-decoration: none;
                transition: var(--transition);
                cursor: pointer;
                border: none;
                display: block;
            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)';">
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                        <span style="font-size: 24px;">⏳</span>
                        <h3 style="margin: 0; font-size: 20px; font-weight: 700;">Swing Trading Engine</h3>
                        <span style="margin-left: auto; background: rgba(255,255,255,0.2); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: 12px; font-weight: 600;">ACTIVE</span>
                    </div>
                    <p style="margin: 0 0 var(--space-2) 0; opacity: 0.95; font-weight: 500;">
                        📈 Medium-term trend analysis with swing patterns
                    </p>
                    <div style="font-size: 14px; opacity: 0.9; font-family: var(--font-mono); display: flex; justify-content: space-between; align-items: center;">
                        <span>Focus: 15m → 1h → 4h timeframes</span>
                        <span style="background: rgba(255,255,255,0.15); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: 11px;">Click to Switch to Scalping</span>
                    </div>
                </div>
            </a>
            <?php else: ?>
            <a href="?engine=swing" style="
                background: linear-gradient(135deg, var(--success), color-mix(in srgb, var(--success) 90%, #2E7D32));
                color: white;
                padding: var(--space-4);
                border-radius: var(--radius);
                flex: 1;
                min-width: 300px;
                box-shadow: var(--shadow);
                position: relative;
                overflow: hidden;
                text-decoration: none;
                transition: var(--transition);
                cursor: pointer;
                border: none;
                display: block;
            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)';">
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                        <span style="font-size: 24px;">⚡</span>
                        <h3 style="margin: 0; font-size: 20px; font-weight: 700;">Scalping Engine</h3>
                        <span style="margin-left: auto; background: rgba(255,255,255,0.2); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: 12px; font-weight: 600;">ACTIVE</span>
                    </div>
                    <p style="margin: 0 0 var(--space-2) 0; opacity: 0.95; font-weight: 500;">
                        ⚡ High-frequency scalping signals and micro-trends
                    </p>
                    <div style="font-size: 14px; opacity: 0.9; font-family: var(--font-mono); display: flex; justify-content: space-between; align-items: center;">
                        <span>Focus: 1m → 5m → 15m timeframes</span>
                        <span style="background: rgba(255,255,255,0.15); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm); font-size: 11px;">Click to Switch to Swing</span>
                    </div>
                </div>
            </a>
            <?php endif; ?>
            
            <!-- Update All Coins Button -->
            <div style="
                background: var(--surface);
                border: 1px solid var(--border);
                padding: var(--space-4);
                border-radius: var(--radius);
                flex: 1;
                min-width: 300px;
                box-shadow: var(--shadow-sm);
                display: flex;
                flex-direction: column;
                gap: var(--space-3);
            ">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <div style="
                        background: var(--warning);
                        border-radius: 50%;
                        width: 12px;
                        height: 12px;
                        animation: pulse 2s infinite;
                    "></div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text);">Quick Actions</h3>
                </div>
                
                <!-- Update All Button -->
                <button onclick="updateAllCoins()" id="updateAllBtn" style="
                    background: linear-gradient(135deg, var(--warning), color-mix(in srgb, var(--warning) 90%, #F57C00));
                    color: white;
                    border: none;
                    padding: var(--space-3) var(--space-4);
                    border-radius: var(--radius-sm);
                    font-size: 16px;
                    font-weight: 600;
                    font-family: var(--font-system);
                    cursor: pointer;
                    transition: var(--transition);
                    box-shadow: var(--shadow-sm);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: var(--space-2);
                    min-height: 48px;
                " onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='var(--shadow)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-sm)';">
                    <span style="font-size: 18px;">🔄</span>
                    <span>Update All Coins Now</span>
                </button>
                
                <!-- Add New Coin Button -->
                <button onclick="showAddCoinModal()" id="addCoinBtn" style="
                    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 90%, #1976D2));
                    color: white;
                    border: none;
                    padding: var(--space-3) var(--space-4);
                    border-radius: var(--radius-sm);
                    font-size: 16px;
                    font-weight: 600;
                    font-family: var(--font-system);
                    cursor: pointer;
                    transition: var(--transition);
                    box-shadow: var(--shadow-sm);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: var(--space-2);
                    min-height: 48px;
                " onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='var(--shadow)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-sm)';">
                    <span style="font-size: 18px;">➕</span>
                    <span>Add New Coin</span>
                </button>
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--text-secondary); font-weight: 500; font-size: 14px;">Engine Status:</span>
                    <span style="
                        background: var(--success);
                        color: white;
                        padding: var(--space-1) var(--space-2);
                        border-radius: var(--radius-sm);
                        font-size: 12px;
                        font-weight: 600;
                        font-family: var(--font-mono);
                    ">
                        ✅ <?php echo strtoupper($engine_info['active_engine']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <style>
        @keyframes pulse {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .engine-cards {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
}
