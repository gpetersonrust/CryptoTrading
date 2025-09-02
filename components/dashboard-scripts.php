<?php
/**
 * Dashboard JavaScript Component
 */

class DashboardScripts {
    
    public static function render() {
        ?>
        <script>
        function toggleAccordion(index) {
            const content = document.getElementById('accordion-content-' + index);
            const icon = document.getElementById('toggle-icon-' + index);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.classList.remove('rotated');
            } else {
                content.classList.add('active');
                icon.classList.add('rotated');
            }
        }

        function sortByTimeframe(timeframe) {
            const accordions = Array.from(document.querySelectorAll('.coin-accordion'));
            
            accordions.sort((a, b) => {
                const scoreA = parseInt(a.getAttribute('data-score-' + timeframe)) || 0;
                const scoreB = parseInt(b.getAttribute('data-score-' + timeframe)) || 0;
                return scoreB - scoreA;
            });
            
            const container = document.querySelector('.coins-column');
            
            // Add stagger animation to accordions
            accordions.forEach((accordion, index) => {
                accordion.style.opacity = '0';
                accordion.style.transform = 'translateY(20px)';
                accordion.style.transition = 'all 200ms cubic-bezier(0.4, 0, 0.2, 1)';
                
                setTimeout(() => {
                    container.appendChild(accordion);
                    accordion.style.opacity = '1';
                    accordion.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            // Update button styles with Apple-style feedback
            document.querySelectorAll('.tf-sort-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.style.transform = 'scale(1)';
                btn.style.boxShadow = 'var(--shadow-sm)';
            });
            
            const activeBtn = document.querySelector(`[data-tf="${timeframe}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.style.transform = 'scale(1.05)';
                activeBtn.style.boxShadow = 'var(--shadow-lg)';
                
                // Add a subtle success haptic feedback (visual)
                activeBtn.style.animation = 'buttonPress 150ms cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    activeBtn.style.animation = '';
                }, 150);
            }
        }

        function updateAllCoins() {
            const updateBtn = document.getElementById('updateAllBtn');
            const originalContent = updateBtn.innerHTML;
            
            // Show loading state
            updateBtn.innerHTML = '<span style="font-size: 18px;">⏳</span><span>Updating All Coins...</span>';
            updateBtn.style.background = 'var(--muted)';
            updateBtn.disabled = true;
            updateBtn.style.cursor = 'not-allowed';
            
            // Make request to the REST API endpoint
            fetch(window.location.origin + '/wp-json/coin-analysis/v1/update-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            })
            .then(data => {
                if (data.success) {
                    // Success state
                    updateBtn.innerHTML = '<span style="font-size: 18px;">✅</span><span>Update Complete!</span>';
                    updateBtn.style.background = 'linear-gradient(135deg, var(--success), color-mix(in srgb, var(--success) 90%, #2E7D32))';
                    
                    console.log('Update results:', data.data);
                    
                    // Show success message with details
                    if (data.data && data.data.success_count) {
                        updateBtn.innerHTML = `<span style="font-size: 18px;">✅</span><span>Updated ${data.data.success_count} coins!</span>`;
                    }
                    
                    // Refresh the page after 2 seconds to show new data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Partial success or failure
                    updateBtn.innerHTML = '<span style="font-size: 18px;">⚠️</span><span>Partial Update</span>';
                    updateBtn.style.background = 'linear-gradient(135deg, var(--warning), color-mix(in srgb, var(--warning) 90%, #F57C00))';
                    
                    console.warn('Update completed with errors:', data);
                    
                    // Still refresh to show any updates that succeeded
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error updating coins:', error);
                
                // Error state
                updateBtn.innerHTML = '<span style="font-size: 18px;">❌</span><span>Update Failed</span>';
                updateBtn.style.background = 'linear-gradient(135deg, var(--danger), color-mix(in srgb, var(--danger) 90%, #C62828))';
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    updateBtn.innerHTML = originalContent;
                    updateBtn.style.background = 'linear-gradient(135deg, var(--warning), color-mix(in srgb, var(--warning) 90%, #F57C00))';
                    updateBtn.disabled = false;
                    updateBtn.style.cursor = 'pointer';
                }, 3000);
            });
        }

        function refreshDashboard() {
            console.log('Refreshing dashboard...');
            
            // Simple full page reload approach for reliability
            window.location.reload();
        }

        function showAddCoinModal() {
            // Create modal HTML
            const modalHtml = `
                <div id="addCoinModal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(10px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    opacity: 0;
                    transition: opacity 200ms ease;
                ">
                    <div style="
                        background: var(--surface);
                        border: 1px solid var(--border);
                        border-radius: var(--radius-lg);
                        padding: var(--space-6);
                        max-width: 500px;
                        width: 90%;
                        box-shadow: var(--shadow-lg);
                        transform: scale(0.95);
                        transition: transform 200ms ease;
                    ">
                        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
                            <span style="font-size: 24px;">🪙</span>
                            <h3 style="margin: 0; color: var(--text); font-size: 20px; font-weight: 600;">Add New Coin</h3>
                        </div>
                        
                        <form id="addCoinForm">
                            <div style="margin-bottom: var(--space-4);">
                                <label style="display: block; margin-bottom: var(--space-2); color: var(--text); font-weight: 500;">
                                    Coin Symbol (e.g., BTCUSDT)
                                </label>
                                <input type="text" id="coinSymbol" required style="
                                    width: 100%;
                                    padding: var(--space-3);
                                    border: 1px solid var(--border);
                                    border-radius: var(--radius-sm);
                                    background: var(--surface);
                                    color: var(--text);
                                    font-size: 16px;
                                    font-family: var(--font-system);
                                    box-sizing: border-box;
                                " placeholder="Enter coin symbol (e.g., ETHUSDT, ADAUSDT)">
                            </div>
                            
                            <div style="display: flex; gap: var(--space-3); justify-content: flex-end;">
                                <button type="button" onclick="closeAddCoinModal()" style="
                                    background: var(--muted);
                                    color: var(--text-secondary);
                                    border: none;
                                    padding: var(--space-2) var(--space-4);
                                    border-radius: var(--radius-sm);
                                    font-size: 14px;
                                    font-weight: 500;
                                    cursor: pointer;
                                    transition: var(--transition);
                                ">Cancel</button>
                                
                                <button type="submit" id="addCoinSubmit" style="
                                    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 90%, #1976D2));
                                    color: white;
                                    border: none;
                                    padding: var(--space-2) var(--space-4);
                                    border-radius: var(--radius-sm);
                                    font-size: 14px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: var(--transition);
                                ">Add Coin</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = document.getElementById('addCoinModal');
            
            // Animate in
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.querySelector('div').style.transform = 'scale(1)';
            }, 10);
            
            // Handle form submission
            document.getElementById('addCoinForm').addEventListener('submit', handleAddCoin);
            
            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeAddCoinModal();
                }
            });
        }

        function closeAddCoinModal() {
            const modal = document.getElementById('addCoinModal');
            if (modal) {
                modal.style.opacity = '0';
                modal.querySelector('div').style.transform = 'scale(0.95)';
                setTimeout(() => {
                    modal.remove();
                }, 200);
            }
        }

        function handleAddCoin(e) {
            e.preventDefault();
            
            const coinSymbol = document.getElementById('coinSymbol').value.trim().toUpperCase();
            const submitBtn = document.getElementById('addCoinSubmit');
            const originalText = submitBtn.textContent;
            
            if (!coinSymbol) {
                alert('Please enter a coin symbol');
                return;
            }
            
            // Show loading state
            submitBtn.textContent = 'Adding...';
            submitBtn.disabled = true;
            submitBtn.style.background = 'var(--muted)';
            
            // Create new coin post
            fetch(window.location.origin + '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'add_new_coin',
                    coin_symbol: coinSymbol,
                    nonce: '<?php echo wp_create_nonce("add_coin_nonce"); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success
                    submitBtn.textContent = '✅ Added!';
                    submitBtn.style.background = 'linear-gradient(135deg, var(--success), color-mix(in srgb, var(--success) 90%, #2E7D32))';
                    
                    setTimeout(() => {
                        closeAddCoinModal();
                        // Refresh page to show new coin
                        window.location.reload();
                    }, 1500);
                } else {
                    // Error
                    alert(data.data || 'Failed to add coin');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    submitBtn.style.background = 'linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 90%, #1976D2))';
                }
            })
            .catch(error => {
                console.error('Error adding coin:', error);
                alert('Failed to add coin. Please try again.');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                submitBtn.style.background = 'linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 90%, #1976D2))';
            });
        }

        function updateRefreshStatus() {
            let countdown = 60;
            const countdownElement = document.getElementById('refresh-countdown');
            const statusElement = document.getElementById('update-status');
            
            if (!countdownElement || !statusElement) {
                console.log('Refresh status elements not found');
                return;
            }
            
            statusElement.textContent = 'Active';
            
            const timer = setInterval(() => {
                countdown--;
                countdownElement.textContent = `Next refresh in ${countdown} seconds`;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    refreshDashboard();
                    // Restart the countdown after refresh
                    setTimeout(() => updateRefreshStatus(), 2000);
                }
            }, 1000);
        }

        // Auto-collapse all accordions on load for cleaner view
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.coin-accordion').forEach(accordion => {
                const content = accordion.querySelector('.accordion-content');
                const icon = accordion.querySelector('.toggle-icon');
                if (content && icon) {
                    content.classList.remove('active');
                    icon.classList.remove('rotated');
                }
            });
            
            // Start the refresh countdown immediately
            console.log('Starting auto-refresh...');
            updateRefreshStatus();
        });
        </script>
        <?php
    }
}
