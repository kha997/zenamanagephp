<!-- Cohort Analysis Horizontal Bar Chart -->
<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Cohort Analysis</h3>
        <div class="flex items-center space-x-4">
            <button class="flex items-center space-x-2 px-3 py-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-chart-bar text-blue-600"></i>
                <span class="text-sm font-medium text-blue-900">Open Statistics</span>
            </button>
            <label class="flex items-center space-x-2 cursor-pointer">
                <input type="checkbox" checked class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-700">Percentage Change</span>
            </label>
        </div>
    </div>
    
    <p class="text-sm text-gray-600 mb-6">
        Analyzes the behaviour of a group of users who joined a product/service at the same time, over a certain period.
    </p>
    
    <div class="h-32">
        <canvas id="cohort-analysis-chart" width="400" height="120"></canvas>
    </div>
</div>

<script>
// Cohort Analysis Horizontal Bar Chart Implementation
function initCohortAnalysisChart() {
    const canvas = document.getElementById('cohort-analysis-chart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    
    // Clear canvas
    ctx.clearRect(0, 0, width, height);
    
    // Sample cohort data (monthly retention rates)
    const cohortData = [
        { month: 'Month 1', retention: 100 },
        { month: 'Month 2', retention: 85 },
        { month: 'Month 3', retention: 72 },
        { month: 'Month 4', retention: 68 },
        { month: 'Month 5', retention: 61 },
        { month: 'Month 6', retention: 55 },
        { month: 'Month 7', retention: 48 },
        { month: 'Month 8', retention: 42 }
    ];
    
    // Chart dimensions
    const padding = { top: 20, right: 40, bottom: 30, left: 60 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    
    // Calculate bar dimensions
    const barHeight = chartHeight / cohortData.length;
    const barSpacing = barHeight * 0.1; // 10% spacing
    const actualBarHeight = barHeight - barSpacing;
    
    // Color gradient function
    function getBarColor(percentage) {
        const intensity = percentage / 100;
        const r = Math.floor(139 + (116 - 139) * intensity); // Purple gradient
        const g = Math.floor(92 + (185 - 92) * intensity);
        const b = Math.floor(246 + (211 - 246) * intensity);
        return `rgb(${r}, ${g}, ${b})`;
    }
    
    // Draw bars
    cohortData.forEach((item, index) => {
        const barWidth = (item.retention / 100) * chartWidth;
        const barY = padding.top + index * barHeight + barSpacing / 2;
        
        // Draw bar
        ctx.fillStyle = getBarColor(item.retention);
        ctx.fillRect(padding.left, barY, barWidth, actualBarHeight);
        
        // Draw bar border
        ctx.strokeStyle = '#8b5cf6';
        ctx.lineWidth = 1;
        ctx.strokeRect(padding.left, barY, barWidth, actualBarHeight);
        
        // Draw percentage text
        ctx.fillStyle = '#374151';
        ctx.font = '12px Inter, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(`${item.retention}%`, padding.left - 10, barY + actualBarHeight / 2 + 4);
        
        // Draw month label
        ctx.fillStyle = '#6b7280';
        ctx.font = '11px Inter, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(item.month, padding.left + barWidth + 10, barY + actualBarHeight / 2 + 4);
    });
    
    // Draw axes
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    
    // Y-axis
    ctx.beginPath();
    ctx.moveTo(padding.left, padding.top);
    ctx.lineTo(padding.left, height - padding.bottom);
    ctx.stroke();
    
    // X-axis
    ctx.beginPath();
    ctx.moveTo(padding.left, height - padding.bottom);
    ctx.lineTo(width - padding.right, height - padding.bottom);
    ctx.stroke();
    
    // Draw grid lines
    ctx.strokeStyle = '#f3f4f6';
    ctx.lineWidth = 0.5;
    
    for (let i = 0; i <= 10; i++) {
        const x = padding.left + (i / 10) * chartWidth;
        ctx.beginPath();
        ctx.moveTo(x, padding.top);
        ctx.lineTo(x, height - padding.bottom);
        ctx.stroke();
    }
    
    // Draw percentage labels on x-axis
    ctx.fillStyle = '#6b7280';
    ctx.font = '10px Inter, sans-serif';
    ctx.textAlign = 'center';
    
    for (let i = 0; i <= 10; i++) {
        const x = padding.left + (i / 10) * chartWidth;
        const percentage = i * 10;
        ctx.fillText(`${percentage}%`, x, height - padding.bottom + 15);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initCohortAnalysisChart();
});

// Re-draw chart when checkbox changes
document.addEventListener('change', function(e) {
    if (e.target.type === 'checkbox' && e.target.closest('#cohort-analysis-chart')) {
        // Re-draw chart with different data based on checkbox state
        setTimeout(() => {
            initCohortAnalysisChart();
        }, 100);
    }
});
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/dashboard/charts/cohort-analysis-chart.blade.php ENDPATH**/ ?>