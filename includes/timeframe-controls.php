<?php
/**
 * Timeframe Controls Display
 * Outputs the timeframe sorting buttons and JavaScript functions
 */

// Output timeframe sorting buttons
function display_timeframe_controls() {
    echo "<div style='margin-bottom: 10px; margin-top: 10px;'>";
    echo "<strong style='margin-right: 10px; color: #495057;'>Sort by Timeframe Growth:</strong><br>";
    echo "<button onclick='sortByTimeframe(\"overall\")' class='tf-sort-btn' data-tf='overall' style='background: #28a745; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>📊 Overall</button>";
    echo "<button onclick='sortByTimeframe(\"1m\")' class='tf-sort-btn' data-tf='1m' style='background: #17a2b8; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>⚡ 1m</button>";
    echo "<button onclick='sortByTimeframe(\"5m\")' class='tf-sort-btn' data-tf='5m' style='background: #17a2b8; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>🚀 5m</button>";
    echo "<button onclick='sortByTimeframe(\"15m\")' class='tf-sort-btn' data-tf='15m' style='background: #17a2b8; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>📈 15m</button>";
    echo "<button onclick='sortByTimeframe(\"30m\")' class='tf-sort-btn' data-tf='30m' style='background: #17a2b8; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>📊 30m</button>";
    echo "<button onclick='sortByTimeframe(\"1h\")' class='tf-sort-btn' data-tf='1h' style='background: #17a2b8; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>⏰ 1h</button>";
    echo "<button onclick='sortByTimeframe(\"4h\")' class='tf-sort-btn' data-tf='4h' style='background: #17a2b8; color: white; border: none; padding: 6px 12px; margin: 2px; border-radius: 3px; cursor: pointer; font-size: 12px;'>⏳ 4h</button>";
    echo "</div>";
}

// Output timeframe JavaScript functions
function display_timeframe_javascript() {
    echo "<script>
        // Sort by specific timeframe growth
        function sortByTimeframe(timeframe) {
            const accordions = Array.from(document.querySelectorAll('.coin-accordion'));
            
            // Store original order if not already stored
            if (typeof originalOrder === 'undefined' || originalOrder.length === 0) {
                window.originalOrder = accordions.slice();
            }
            
            // Sort by timeframe growth data attribute
            accordions.sort((a, b) => {
                const growthA = parseFloat(a.getAttribute('data-tf-' + timeframe) || 0);
                const growthB = parseFloat(b.getAttribute('data-tf-' + timeframe) || 0);
                return growthB - growthA; // Descending order (best growth first)
            });
            
            // Get the container where accordions should be placed
            const refreshStatus = document.getElementById('refresh-status');
            const container = refreshStatus.parentNode;
            
            // Remove all accordions first
            accordions.forEach(accordion => accordion.remove());
            
            // Add them back in sorted order
            accordions.forEach(accordion => {
                container.appendChild(accordion);
            });
            
            // Update button states
            document.querySelectorAll('.tf-sort-btn').forEach(btn => {
                btn.style.background = btn.getAttribute('data-tf') === 'overall' ? '#28a745' : '#17a2b8';
                btn.innerHTML = btn.innerHTML.replace('✓ ', '');
            });
            
            const activeButton = document.querySelector(\"[data-tf='\" + timeframe + \"']\");
            if (activeButton) {
                activeButton.style.background = '#dc3545';
                activeButton.innerHTML = '✓ ' + activeButton.innerHTML;
            }
        }
        
        // Enhanced reset function to handle timeframe buttons
        function resetSortWithTimeframes() {
            if (typeof originalOrder !== 'undefined' && originalOrder.length > 0) {
                // Get the container where accordions should be placed
                const refreshStatus = document.getElementById('refresh-status');
                const container = refreshStatus.parentNode;
                
                // Remove all current accordions
                const currentAccordions = Array.from(document.querySelectorAll('.coin-accordion'));
                currentAccordions.forEach(accordion => accordion.remove());
                
                // Add them back in original order
                originalOrder.forEach(accordion => {
                    container.appendChild(accordion);
                });
                
                // Reset all timeframe buttons
                document.querySelectorAll('.tf-sort-btn').forEach(btn => {
                    btn.style.background = btn.getAttribute('data-tf') === 'overall' ? '#28a745' : '#17a2b8';
                    btn.innerHTML = btn.innerHTML.replace('✓ ', '');
                });
            }
        }
    </script>";
}
?>
