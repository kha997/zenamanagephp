
<div id="charts-section" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <!-- Signups Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">New Signups</h3>
            <div class="flex items-center space-x-2">
                <select 
                    class="text-sm border border-gray-300 rounded-md px-3 py-1"
                    onchange="updateSignupsChart(this.value)">
                    <option value="30d">Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                    <option value="1y">Last year</option>
                </select>
                <button 
                    onclick="exportSignupsData()"
                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
        </div>
        
        <div class="chart-container" style="height: 280px; position: relative;">
            <canvas 
                id="signupsChart" 
                width="400" 
                height="280"
                aria-label="New signups chart showing user registrations over time">
            </canvas>
        </div>
        
        <div class="mt-2 text-xs text-gray-500">
            Last updated: <span id="signupsLastUpdate">Loading...</span>
        </div>
    </div>

    <!-- Error Rate Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Error Rate</h3>
            <div class="flex items-center space-x-2">
                <select 
                    class="text-sm border border-gray-300 rounded-md px-3 py-1"
                    onchange="updateErrorsChart(this.value)">
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                </select>
                <button 
                    onclick="exportErrorsData()"
                    class="px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition-colors">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
        </div>
        
        <div class="chart-container" style="height: 280px; position: relative;">
            <canvas 
                id="errorsChart" 
                width="400" 
                height="280"
                aria-label="Error rate chart showing system errors over time">
            </canvas>
        </div>
        
        <div class="mt-2 text-xs text-gray-500">
            Last updated: <span id="errorsLastUpdate">Loading...</span>
        </div>
    </div>
    
</div>

<script>
// Global chart instances
let signupsChartInstance = null;
let errorsChartInstance = null;

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Charts] Initializing dashboard charts...');
    
    // Wait for Chart.js to be available
    if (typeof Chart === 'undefined') {
        console.error('[Charts] Chart.js not loaded');
        return;
    }
    
    initializeSignupsChart();
    initializeErrorsChart();
    
    console.log('[Charts] Dashboard charts initialized');
});

// Signups Chart
function initializeSignupsChart() {
    const ctx = document.getElementById('signupsChart');
    if (!ctx) {
        console.error('[Charts] Signups chart canvas not found');
        return;
    }
    
    // Destroy existing chart if any
    if (signupsChartInstance) {
        signupsChartInstance.destroy();
    }
    
    // Sample data for signups
    const signupsData = {
        labels: generateDateLabels(30),
        datasets: [{
            label: 'New Signups',
            data: Array.from({length: 30}, () => Math.floor(Math.random() * 50) + 10),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    };
    
    signupsChartInstance = new Chart(ctx, {
        type: 'line',
        data: signupsData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: 'white',
                    bodyColor: 'white'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
    
    document.getElementById('signupsLastUpdate').textContent = new Date().toLocaleTimeString();
}

// Errors Chart  
function initializeErrorsChart() {
    const ctx = document.getElementById('errorsChart');
    if (!ctx) {
        console.error('[Charts] Errors chart canvas not found');
        return;
    }
    
    // Destroy existing chart if any
    if (errorsChartInstance) {
        errorsChartInstance.destroy();
    }
    
    // Sample data for errors
    const errorsData = {
        labels: generateDateLabels(7),
        datasets: [{
            label: 'Error Rate (%)',
            data: Array.from({length: 7}, () => (Math.random() * 5).toFixed(1)),
            backgroundColor: 'rgba(239, 68, 68, 0.8)',
            borderColor: 'rgb(239, 68, 68)',
            borderWidth: 2
        }]
    };
    
    errorsChartInstance = new Chart(ctx, {
        type: 'bar',
        data: errorsData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: 'white',
                    bodyColor: 'white'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    document.getElementById('errorsLastUpdate').textContent = new Date().toLocaleTimeString();
}

// Generate date labels
function generateDateLabels(days) {
    const labels = [];
    for (let i = days - 1; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    }
    return labels;
}

// Update functions
function updateSignupsChart(range) {
    console.log('[Charts] Updating signups chart for range:', range);
    // Reload signups chart data
    initializeSignupsChart();
}

function updateErrorsChart(range) {
    console.log('[Charts] Updating errors chart for range:', range);
    // Reload errors chart data  
    initializeErrorsChart();
}

// Export functions
function exportSignupsData() {
    console.log('[Charts] Exporting signups data...');
    // Placeholder for CSV export
    alert('Signups data export functionality coming soon!');
}

function exportErrorsData() {
    console.log('[Charts] Exporting errors data...');
    // Placeholder for CSV export
    alert('Errors data export functionality coming soon!');
}

// Cleanup function
function destroyCharts() {
    if (signupsChartInstance) {
        signupsChartInstance.destroy();
        signupsChartInstance = null;
    }
    if (errorsChartInstance) {
        errorsChartInstance.destroy();
        errorsChartInstance = null;
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', destroyCharts);
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_charts.blade.php ENDPATH**/ ?>