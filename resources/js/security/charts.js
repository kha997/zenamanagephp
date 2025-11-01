/**
 * Security Charts - Chart.js visualizations for KPI panels
 */

// Constants
const DATE_FORMAT_DAY = 'MMM dd';
const DATE_FORMAT_HOUR = DATE_FORMAT_HOUR;

export function lineChart(el, {labels, datasets}) {
    if (el._chart) { 
        el._chart.destroy(); 
    }
    
    el._chart = new Chart(el.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { 
                mode: 'index', 
                intersect: false 
            },
            plugins: { 
                legend: { display: true }, 
                tooltip: { enabled: true } 
            },
            scales: { 
                x: { 
                    ticks: { maxRotation: 0 },
                    type: 'time',
                    time: {
                        displayFormats: {
                            hour: DATE_FORMAT_HOUR,
                            day: DATE_FORMAT_DAY,
                            week: DATE_FORMAT_DAY,
                            month: 'MMM yyyy'
                        }
                    }
                }, 
                y: { beginAtZero: true } 
            }
        }
    });
}

export function areaChart(el, {labels, datasets}) {
    if (el._chart) { 
        el._chart.destroy(); 
    }
    
    el._chart = new Chart(el.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { 
                mode: 'index', 
                intersect: false 
            },
            plugins: { 
                legend: { display: true }, 
                tooltip: { enabled: true } 
            },
            scales: { 
                x: { 
                    ticks: { maxRotation: 0 },
                    type: 'time',
                    time: {
                        displayFormats: {
                            hour: DATE_FORMAT_HOUR,
                            day: DATE_FORMAT_DAY,
                            week: DATE_FORMAT_DAY,
                            month: 'MMM yyyy'
                        }
                    }
                }, 
                y: { beginAtZero: true } 
            },
            elements: {
                line: {
                    fill: true
                }
            }
        }
    });
}

export function stackedBarChart(el, {labels, datasets}) {
    if (el._chart) { 
        el._chart.destroy(); 
    }
    
    el._chart = new Chart(el.getContext('2d'), {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { 
                mode: 'index', 
                intersect: false 
            },
            plugins: { 
                legend: { display: true }, 
                tooltip: { enabled: true } 
            },
            scales: { 
                x: { 
                    ticks: { maxRotation: 0 },
                    type: 'time',
                    time: {
                        displayFormats: {
                            hour: DATE_FORMAT_HOUR,
                            day: DATE_FORMAT_DAY,
                            week: DATE_FORMAT_DAY,
                            month: 'MMM yyyy'
                        }
                    }
                }, 
                y: { 
                    beginAtZero: true,
                    stacked: true
                } 
            }
        }
    });
}

/**
 * Downsample data points if more than 365 points
 */
export function downsampleData(points, maxPoints = 365) {
    if (points.length <= maxPoints) {
        return points;
    }
    
    const step = Math.ceil(points.length / maxPoints);
    const downsampled = [];
    
    for (let i = 0; i < points.length; i += step) {
        const chunk = points.slice(i, i + step);
        const avgValue = chunk.reduce((sum, p) => sum + p.value, 0) / chunk.length;
        const timestamp = chunk[0].ts; // Use first timestamp in chunk
        
        downsampled.push({
            ts: timestamp,
            value: Math.round(avgValue * 100) / 100
        });
    }
    
    return downsampled;
}

/**
 * Build chart data from API response
 */
export function buildChartData(points, label, color = null) {
    const downsampled = downsampleData(points);
    
    return {
        labels: downsampled.map(p => p.ts),
        datasets: [{
            label: label,
            data: downsampled.map(p => p.value),
            borderColor: color || Chart.defaults.color,
            backgroundColor: color ? color + '20' : Chart.defaults.color + '20',
            tension: 0.1,
            fill: false
        }]
    };
}

/**
 * Build stacked chart data for login attempts
 */
export function buildStackedChartData(successPoints, failedPoints) {
    const maxLength = Math.max(successPoints.length, failedPoints.length);
    const labels = [];
    const successData = [];
    const failedData = [];
    
    for (let i = 0; i < maxLength; i++) {
        const successPoint = successPoints[i];
        const failedPoint = failedPoints[i];
        
        if (successPoint) {
            labels.push(successPoint.ts);
            successData.push(successPoint.value);
            failedData.push(failedPoint?.value || 0);
        } else if (failedPoint) {
            labels.push(failedPoint.ts);
            successData.push(0);
            failedData.push(failedPoint.value);
        }
    }
    
    return {
        labels,
        datasets: [
            {
                label: 'Successful Logins',
                data: successData,
                backgroundColor: '#10B981',
                borderColor: '#10B981'
            },
            {
                label: 'Failed Logins',
                data: failedData,
                backgroundColor: '#EF4444',
                borderColor: '#EF4444'
            }
        ]
    };
}

/**
 * Chart error handling
 */
export function showChartError(canvas, message) {
    if (canvas._chart) {
        canvas._chart.destroy();
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#6B7280';
    ctx.font = '14px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2);
}

/**
 * Chart loading state
 */
export function showChartLoading(canvas) {
    if (canvas._chart) {
        canvas._chart.destroy();
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#9CA3AF';
    ctx.font = '14px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Loading...', canvas.width / 2, canvas.height / 2);
}
