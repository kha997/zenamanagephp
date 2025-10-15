<!-- Revenue Goal Donut Chart -->
<div class="bg-white rounded-lg shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Goal</h3>
    <div id="revenue-goal-chart" class="h-64"></div>
</div>

<script>
// Revenue Goal Donut Chart Implementation
function initRevenueGoalChart() {
    const options = {
        series: [1735, 14987, 11548], // Total Profit, Discounts, Sales Trend
        chart: {
            type: 'donut',
            height: 250,
            width: 250,
            offsetX: 10,
            parentHeightOffset: 0
        },
        labels: ['Total Profit', 'Discounts', 'Sales Trend'],
        colors: ['#8b5cf6', '#f59e0b', '#10b981'], // Purple, Orange, Green
        stroke: {
            width: 4,
            colors: ['#f3f4f6']
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        grid: {
            show: false
        },
        states: {
            hover: {
                filter: {
                    type: 'none'
                }
            },
            active: {
                filter: {
                    type: 'none'
                }
            }
        },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '83%',
                    background: 'transparent',
                    labels: {
                        show: true,
                        value: {
                            fontSize: '1.5rem',
                            fontFamily: 'Inter, ui-sans-serif',
                            fontWeight: 700,
                            color: '#374151',
                            offsetY: -17,
                            formatter: function(val) {
                                return '$' + parseInt(val)
                            }
                        },
                        name: {
                            offsetY: 17,
                            fontFamily: 'Inter, ui-sans-serif'
                        },
                        total: {
                            show: true,
                            fontSize: '14px',
                            color: '#374151',
                            fontWeight: 500,
                            label: 'Total Profit',
                            formatter: function() {
                                return '$1,735'
                            }
                        }
                    }
                }
            }
        }
    };

    const chart = new ApexCharts(document.querySelector("#revenue-goal-chart"), options);
    chart.render();
    
    return chart;
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ApexCharts !== 'undefined') {
        initRevenueGoalChart();
    }
});
</script>
