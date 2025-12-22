/**
 * Charts Clean Architecture Implementation
 * ZenaManage Project Management System
 */

class ChartBuilder {
    constructor() {
        this.defaultColors = {
            primary: '#3B82F6',    // Blue
            success: '#10B981',     // Green
            warning: '#F59E0B',     // Amber
            danger: '#EF4444',      // Red
            purple: '#8B5CF6',     // Purple
            gray: '#6B7280'         // Gray
        };
        
        this.defaultConfig = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'start'
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    cornerRadius: 6
                }
            },
            animation: {
                duration: 800,
                easing: 'easeInOut'
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        };
    }
    
    /**
     * Create Project Activity Timeline Chart
     * @param {HTMLCanvasElement} canvas - Target canvas element
     * @param {Array} timeSeriesData - Data array with {date, created, completed}
     * @returns {Chart} Chart.js instance
     */
    createActivityTimeline(canvas, timeSeriesData = []) {
        if (!canvas || !Array.isArray(timeSeriesData) || timeSeriesData.length === 0) {
            return null;
        }
        
        // 🎨 Data processing
        const processedData = this.processTimeSeriesData(timeSeriesData);
        const timeLabels = processedData.map(d => d.date);
        const createdData = processedData.map(d => d.created);
        const completedData = processedData.map(d => d.completed);
        
        return new Chart(canvas, {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Projects Created',
                    data: createdData,
                    borderColor: this.defaultColors.success,
                    backgroundColor: `${this.defaultColors.success}20`,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: this.defaultColors.success,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }, {
                    label: 'Projects Completed',
                    data: completedData,
                    borderColor: this.defaultColors.primary,
                    backgroundColor: `${this.defaultColors.primary}20`,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: this.defaultColors.primary,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                ...this.defaultConfig,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM dd'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    ...this.defaultConfig.plugins,
                    title: {
                        display: false  // ✅ REMOVED: HTML titles already present
                    }
                }
            }
        });
    }
    
    /**
     * Create Project Progress Distribution Chart
     * @param {HTMLCanvasElement} canvas - Target canvas element
     * @param {Array} progressData - Data array with progress buckets
     * @returns {Chart} Chart.js instance
     */
    createProgressDistribution(canvas, progressData = []) {
        if (!canvas || !Array.isArray(progressData) || progressData.length === 0) {
            return null;
        }
        
        // 🎨 Data processing
        const buckets = this.processProgressBuckets(progressData);
        
        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: buckets.map(b => b.label),
                datasets: [{
                    label: 'Number of Projects',
                    data: buckets.map(b => b.count),
                    backgroundColor: [
                        `${this.defaultColors.danger}80`,
                        `${this.defaultColors.warning}80`,
                        `${this.defaultColors.success}80`,
                        `${this.defaultColors.primary}80`
                    ],
                    borderColor: [
                        this.defaultColors.danger,
                        this.defaultColors.warning,
                        this.defaultColors.success,
                        this.defaultColors.primary
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                ...this.defaultConfig,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    ...this.defaultConfig.plugins,
                    title: {
                        display: false  // ✅ REMOVED: HTML titles already present
                    }
                }
            }
        });
    }
    
    /**
     * Process time series data for chart consumption
     * @param {Array} rawData - Raw time series data
     * @returns {Array} Processed data
     */
    processTimeSeriesData(rawData) {
        return rawData.map(item => ({
            date: new Date(item.t || item.date || item.created_at),
            created: Number(item.created || item.projects_created || 0),
            completed: Number(item.completed || item.projects_completed || 0)
        })).sort((a, b) => a.date - b.date);
    }
    
    /**
     * Process progress data into buckets
     * @param {Array} rawData - Raw progress data
     * @returns {Array} Processed buckets
     */
    processProgressBuckets(rawData) {
        const buckets = {
            '0-25%': { count: 0, projects: [] },
            '25-50%': { count: 0, projects: [] },
            '50-75%': { count: 0, projects: [] },
            '75-100%': { count: 0, projects: [] }
        };
        
        rawData.forEach(project => {
            const progress = Number(project.progress || project.avg_progress || 0);
            
            if (progress <= 25) {
                buckets['0-25%'].count++;
                buckets['0-25%'].projects.push(project);
            } else if (progress <= 50) {
                buckets['25-50%'].count++;
                buckets['25-50%'].projects.push(project);
            } else if (progress <= 75) {
                buckets['50-75%'].count++;
                buckets['50-75%'].projects.push(project);
            } else {
                buckets['75-100%'].count++;
                buckets['75-100%'].projects.push(project);
            }
        });
        
        return Object.entries(buckets).map(([label, data]) => ({
            label,
            count: data.count,
            projects: data.projects
        }));
    }
    
    /**
     * Destroy chart instance safely
     * @param {Chart} chartInstance - Chart to destroy
     */
    static destroyChart(chartInstance) {
        if (chartInstance && typeof chartInstance.destroy === 'function') {
            chartInstance.destroy();
        }
    }
}

// Export for use in ProjectsPage
window.ChartBuilder = ChartBuilder;

