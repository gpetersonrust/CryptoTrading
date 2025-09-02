<?php
/**
 * Dashboard Styles Component
 */

class DashboardStyles {
    
    public static function render() {
        ?>
        <style>
        /* =============================================
           APPLE-INSPIRED DESIGN SYSTEM
           Based on coin-analysis-design-guide.md
           ============================================= */
        
        :root {
            /* Apple Color Palette */
            --accent: #0A84FF;                 /* iOS Blue */
            --accent-hover: #0056CC;           /* Darker blue for hover */
            --success: #30D158;                /* Apple Green (gains) */
            --danger: #FF453A;                 /* Apple Red (losses) */
            --warning: #FF9F0A;                /* Apple Orange */
            
            /* Neutral Colors */
            --bg: #FBFBFD;                     /* Near-white background */
            --surface: #FFFFFF;                /* Pure white surfaces */
            --surface-secondary: #F2F2F7;      /* Light gray */
            --text: #1D1D1F;                   /* Near-black text */
            --text-secondary: #86868B;         /* Gray text */
            --muted: #8E8E93;                  /* Muted text */
            --border: #D1D1D6;                 /* Light borders */
            --separator: #C6C6C8;              /* Separators */
            
            /* Radius & Spacing */
            --radius: 16px;
            --radius-lg: 24px;
            --radius-sm: 8px;
            --space-1: 8px;
            --space-2: 12px; 
            --space-3: 16px;
            --space-4: 24px;
            --space-5: 32px;
            --space-6: 48px;
            
            /* Typography */
            --font-system: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            --font-mono: 'SF Mono', Monaco, Inconsolata, 'Roboto Mono', Consolas, 'Courier New', monospace;
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.08), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            
            /* Transitions */
            --transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* =============================================
           GLOBAL STYLES
           ============================================= */
        
        body {
            font-family: var(--font-system);
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* =============================================
           MAIN LAYOUT
           ============================================= */
        
        .main-layout {
            display: flex !important;
            gap: var(--space-4);
            margin: var(--space-4) 0;
            align-items: flex-start;
            width: 100%;
            box-sizing: border-box;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 var(--space-3);
        }
        
        .coins-column {
            flex: 70% !important;
            min-width: 0;
            max-width: 70%;
            box-sizing: border-box;
        }
        
        .trends-column {
            flex: 30% !important;
            min-width: 300px;
            max-width: 30%;
            position: sticky;
            top: var(--space-4);
            box-sizing: border-box;
        }
        
        /* =============================================
           RESPONSIVE DESIGN
           ============================================= */
        
        @media (max-width: 1200px) {
            .coins-column {
                flex: 65% !important;
                max-width: 65%;
            }
            .trends-column {
                flex: 35% !important;
                max-width: 35%;
                min-width: 280px;
            }
        }
        
        @media (max-width: 1024px) {
            .main-layout {
                flex-direction: column;
                gap: var(--space-5);
            }
            .coins-column, .trends-column {
                flex: none !important;
                min-width: auto;
                max-width: none;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .main-layout {
                padding: 0 var(--space-2);
                margin: var(--space-3) 0;
            }
        }

        /* =============================================
           ACCORDION CARDS (MAIN FEATURE)
           ============================================= */
        
        .coin-accordion {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: var(--space-3);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .coin-accordion:hover {
            box-shadow: var(--shadow);
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .coin-accordion:focus-within {
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }

        /* =============================================
           ACCORDION HEADER
           ============================================= */
        
        .accordion-header {
            padding: var(--space-4);
            cursor: pointer;
            transition: var(--transition);
            background: var(--surface);
            border: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            text-align: left;
            position: relative;
        }

        .accordion-header:hover {
            background: color-mix(in srgb, var(--accent) 3%, var(--surface));
        }

        .accordion-header:focus {
            outline: 2px solid var(--accent);
            outline-offset: -2px;
            background: color-mix(in srgb, var(--accent) 5%, var(--surface));
        }

        /* =============================================
           ACCORDION CONTENT
           ============================================= */
        
        .accordion-content {
            display: none;
            overflow: hidden;
            transition: var(--transition-slow);
            background: var(--surface);
            border-top: 1px solid var(--border);
        }

        .accordion-content.active {
            display: block;
            animation: slideDown 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* =============================================
           TOGGLE ICON
           ============================================= */
        
        .toggle-icon {
            font-size: 20px;
            transition: var(--transition);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: var(--radius-sm);
            background: var(--surface-secondary);
        }

        .toggle-icon.rotated {
            transform: rotate(180deg);
            background: var(--accent);
            color: white;
        }

        /* =============================================
           BADGES & INDICATORS
           ============================================= */
        
        .score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            margin-left: var(--space-2);
            min-width: 60px;
            box-shadow: var(--shadow-sm);
            font-family: var(--font-mono);
        }

        .trend-indicator {
            font-size: 18px;
            margin-left: var(--space-1);
            display: inline-flex;
            align-items: center;
        }

        /* =============================================
           TREND COMPONENTS
           ============================================= */
        
        .trend-history {
            margin-top: var(--space-2);
            font-size: 13px;
            color: var(--text-secondary);
            background: var(--surface-secondary);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            border-left: 3px solid var(--accent);
            box-shadow: var(--shadow-sm);
        }

        .trend-history strong {
            color: var(--text);
            margin-right: var(--space-2);
            font-weight: 600;
        }

        .timeframe-trends {
            background: var(--surface-secondary);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--muted);
            font-family: var(--font-mono);
            font-size: 13px;
            box-shadow: var(--shadow-sm);
        }

        .timeframe-trends div {
            white-space: nowrap;
            line-height: 1.4;
        }

        .growth-badge {
            font-weight: 600;
            font-family: var(--font-mono);
        }

        /* =============================================
           REDUCED MOTION SUPPORT & ANIMATIONS
           ============================================= */
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes buttonPress {
            0% { transform: scale(1.05); }
            50% { transform: scale(0.98); }
            100% { transform: scale(1.05); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Loading state animation for cards */
        .coin-accordion.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .coin-accordion.loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Accessibility: Honor user's motion preferences */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            
            .toggle-icon.rotated {
                transform: none !important;
            }
        }
        
        /* Dark mode support (future-ready) */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #000000;
                --surface: #1C1C1E;
                --surface-secondary: #2C2C2E;
                --text: #FFFFFF;
                --text-secondary: #8E8E93;
                --border: #38383A;
                --separator: #48484A;
            }
        }
        </style>
        <?php
    }
}
