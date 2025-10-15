<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Dashboard</title>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <div x-data="testDashboard()">
        <h1>Test Dashboard</h1>
        <div x-show="loading">Loading...</div>
        <div x-show="!loading">
            <canvas id="testChart" width="400" height="200"></canvas>
        </div>
    </div>

    <script>
        function testDashboard() {
            return {
                loading: true,
                init() {
                    console.log('Test dashboard init');
                    setTimeout(() => {
                        this.loading = false;
                        this.createChart();
                    }, 1000);
                },
                createChart() {
                    console.log('Creating test chart');
                    const ctx = document.getElementById('testChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['A', 'B', 'C'],
                            datasets: [{
                                label: 'Test',
                                data: [1, 2, 3],
                                borderColor: 'blue'
                            }]
                        }
                    });
                }
            }
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-dashboard.blade.php ENDPATH**/ ?>