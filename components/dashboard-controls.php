<?php
/**
 * Dashboard Controls Component
 */

class DashboardControls {
    
    public static function render() {
        ?>
        <div style="text-align: center; margin: var(--space-5) 0; max-width: 1400px; margin-left: auto; margin-right: auto; padding: 0 var(--space-3);">
            
            <!-- Timeframe Controls -->
            <div style="background: var(--surface); padding: var(--space-5); border-radius: var(--radius); margin-bottom: var(--space-4); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                <div style="display: flex; align-items: center; justify-content: center; gap: var(--space-2); margin-bottom: var(--space-4);">
                    <span style="font-size: 24px;">📊</span>
                    <h3 style="margin: 0; color: var(--text); font-size: 20px; font-weight: 600; letter-spacing: -0.3px;">Sort by Timeframe Score</h3>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); justify-content: center; align-items: center;">
                    <button onclick="sortByTimeframe('1m')" class="tf-sort-btn apple-btn apple-btn-primary" data-tf="1m">
                        <span>⚡</span> 1m
                    </button>
                    <button onclick="sortByTimeframe('5m')" class="tf-sort-btn apple-btn apple-btn-primary" data-tf="5m">
                        <span>🚀</span> 5m
                    </button>
                    <button onclick="sortByTimeframe('15m')" class="tf-sort-btn apple-btn apple-btn-secondary" data-tf="15m">
                        <span>📈</span> 15m
                    </button>
                    <button onclick="sortByTimeframe('30m')" class="tf-sort-btn apple-btn apple-btn-accent" data-tf="30m">
                        <span>📊</span> 30m
                    </button>
                    <button onclick="sortByTimeframe('1h')" class="tf-sort-btn apple-btn apple-btn-warning" data-tf="1h">
                        <span>⏰</span> 1h
                    </button>
                    <button onclick="sortByTimeframe('4h')" class="tf-sort-btn apple-btn apple-btn-info" data-tf="4h">
                        <span>⏳</span> 4h
                    </button>
                </div>
            </div>
            
            <!-- Refresh Status -->
            <div id="refresh-status" style="
                background: color-mix(in srgb, var(--accent) 8%, var(--surface));
                padding: var(--space-4);
                border-radius: var(--radius);
                border: 1px solid color-mix(in srgb, var(--accent) 20%, var(--border));
                box-shadow: var(--shadow-sm);
                backdrop-filter: blur(20px);
            ">
                <div style="display: flex; align-items: center; justify-content: center; gap: var(--space-4); flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <span style="font-size: 18px;">🔄</span>
                        <span id="refresh-countdown" style="font-weight: 600; color: var(--text); font-family: var(--font-mono);">Next refresh in 60 seconds</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <span style="color: var(--text-secondary); font-weight: 500;">Background updates:</span>
                        <span id="update-status" style="
                            background: var(--success);
                            color: white;
                            padding: var(--space-1) var(--space-2);
                            border-radius: var(--radius-sm);
                            font-size: 13px;
                            font-weight: 600;
                            font-family: var(--font-mono);
                        ">Active</span>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* Apple Button System */
        .apple-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-1);
            padding: var(--space-2) var(--space-4);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            font-family: var(--font-system);
            cursor: pointer;
            transition: var(--transition);
            min-width: 80px;
            height: 44px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .apple-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .apple-btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }
        
        .apple-btn:focus {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        
        /* Button Variants */
        .apple-btn-primary {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .apple-btn-primary:hover {
            background: color-mix(in srgb, var(--success) 90%, black);
        }
        
        .apple-btn-secondary {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .apple-btn-secondary:hover {
            background: var(--accent-hover);
        }
        
        .apple-btn-accent {
            background: #6f42c1;
            color: white;
            border-color: #6f42c1;
        }
        
        .apple-btn-accent:hover {
            background: color-mix(in srgb, #6f42c1 90%, black);
        }
        
        .apple-btn-warning {
            background: var(--warning);
            color: white;
            border-color: var(--warning);
        }
        
        .apple-btn-warning:hover {
            background: color-mix(in srgb, var(--warning) 90%, black);
        }
        
        .apple-btn-info {
            background: #17a2b8;
            color: white;
            border-color: #17a2b8;
        }
        
        .apple-btn-info:hover {
            background: color-mix(in srgb, #17a2b8 90%, black);
        }
        
        /* Active state for timeframe buttons */
        .tf-sort-btn.active {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
            z-index: 1;
        }
        
        @media (max-width: 768px) {
            .apple-btn {
                min-width: 70px;
                padding: var(--space-2) var(--space-3);
                font-size: 13px;
            }
        }
        </style>
        <?php
    }
}
