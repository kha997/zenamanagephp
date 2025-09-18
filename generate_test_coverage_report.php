<?php
/**
 * Test Coverage Report Generator for ZenaManage
 * 
 * This script generates comprehensive test coverage reports
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class TestCoverageReportGenerator
{
    private $coverageData = [];
    private $reportPath = 'storage/app/coverage';

    public function __construct()
    {
        echo "📊 ZENA MANAGE - TEST COVERAGE REPORT\n";
        echo "=====================================\n\n";
    }

    public function generateCoverageReport()
    {
        try {
            $this->runCoverageTests();
            $this->analyzeCoverageData();
            $this->generateHtmlReport();
            $this->generateTextReport();
            $this->displayCoverageSummary();
            
        } catch (Exception $e) {
            echo "❌ Coverage report generation failed: " . $e->getMessage() . "\n";
        }
    }

    private function runCoverageTests()
    {
        echo "🔍 Running tests with coverage...\n";
        echo "---------------------------------\n";
        
        // Run tests with coverage
        $command = 'test --coverage --coverage-html=' . $this->reportPath . ' --coverage-text=' . $this->reportPath . '/coverage.txt --coverage-clover=' . $this->reportPath . '/coverage.xml';
        
        $exitCode = 0;
        $output = '';
        
        ob_start();
        $exitCode = \Illuminate\Support\Facades\Artisan::call($command);
        $output = ob_get_clean();
        
        echo "✅ Coverage tests completed\n";
        echo "📁 Reports saved to: {$this->reportPath}\n\n";
    }

    private function analyzeCoverageData()
    {
        echo "📈 Analyzing coverage data...\n";
        echo "-----------------------------\n";
        
        // Parse coverage data from different sources
        $this->parseCloverCoverage();
        $this->parseTextCoverage();
        
        echo "✅ Coverage data analyzed\n\n";
    }

    private function parseCloverCoverage()
    {
        $cloverFile = $this->reportPath . '/coverage.xml';
        
        if (!file_exists($cloverFile)) {
            echo "⚠️  Clover coverage file not found\n";
            return;
        }
        
        $xml = simplexml_load_file($cloverFile);
        
        if ($xml) {
            $this->coverageData['clover'] = [
                'lines_covered' => (int)$xml->project->metrics['coveredstatements'],
                'lines_total' => (int)$xml->project->metrics['statements'],
                'line_coverage' => round(((int)$xml->project->metrics['coveredstatements'] / (int)$xml->project->metrics['statements']) * 100, 2),
                'methods_covered' => (int)$xml->project->metrics['coveredmethods'],
                'methods_total' => (int)$xml->project->metrics['methods'],
                'method_coverage' => round(((int)$xml->project->metrics['coveredmethods'] / (int)$xml->project->metrics['methods']) * 100, 2),
                'classes_covered' => (int)$xml->project->metrics['coveredclasses'],
                'classes_total' => (int)$xml->project->metrics['classes'],
                'class_coverage' => round(((int)$xml->project->metrics['coveredclasses'] / (int)$xml->project->metrics['classes']) * 100, 2)
            ];
        }
    }

    private function parseTextCoverage()
    {
        $textFile = $this->reportPath . '/coverage.txt';
        
        if (!file_exists($textFile)) {
            echo "⚠️  Text coverage file not found\n";
            return;
        }
        
        $content = file_get_contents($textFile);
        
        // Parse text coverage data
        $this->coverageData['text'] = [
            'content' => $content,
            'parsed' => $this->parseTextCoverageContent($content)
        ];
    }

    private function parseTextCoverageContent($content)
    {
        $lines = explode("\n", $content);
        $parsed = [];
        
        foreach ($lines as $line) {
            if (strpos($line, '|') !== false) {
                $parts = explode('|', $line);
                if (count($parts) >= 2) {
                    $file = trim($parts[0]);
                    $coverage = trim($parts[1]);
                    
                    if (is_numeric($coverage)) {
                        $parsed[$file] = (float)$coverage;
                    }
                }
            }
        }
        
        return $parsed;
    }

    private function generateHtmlReport()
    {
        echo "🌐 Generating HTML report...\n";
        echo "---------------------------\n";
        
        $htmlContent = $this->createHtmlReport();
        $htmlFile = $this->reportPath . '/coverage-summary.html';
        
        file_put_contents($htmlFile, $htmlContent);
        
        echo "✅ HTML report generated: {$htmlFile}\n";
    }

    private function createHtmlReport()
    {
        $cloverData = $this->coverageData['clover'] ?? [];
        $textData = $this->coverageData['text']['parsed'] ?? [];
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZenaManage Test Coverage Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .metric h3 { margin: 0 0 10px 0; color: #333; }
        .metric .value { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .excellent { color: #28a745; }
        .good { color: #17a2b8; }
        .fair { color: #ffc107; }
        .poor { color: #dc3545; }
        .file-list { margin-top: 30px; }
        .file-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .file-name { font-family: monospace; }
        .coverage-bar { width: 100px; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .coverage-fill { height: 100%; transition: width 0.3s ease; }
        .coverage-excellent { background: #28a745; }
        .coverage-good { background: #17a2b8; }
        .coverage-fair { background: #ffc107; }
        .coverage-poor { background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 ZenaManage Test Coverage Report</h1>
            <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
        </div>
        
        <div class="summary">
            <div class="metric">
                <h3>Line Coverage</h3>
                <div class="value ' . $this->getCoverageClass($cloverData['line_coverage'] ?? 0) . '">' . ($cloverData['line_coverage'] ?? 0) . '%</div>
                <p>' . ($cloverData['lines_covered'] ?? 0) . ' / ' . ($cloverData['lines_total'] ?? 0) . ' lines</p>
            </div>
            
            <div class="metric">
                <h3>Method Coverage</h3>
                <div class="value ' . $this->getCoverageClass($cloverData['method_coverage'] ?? 0) . '">' . ($cloverData['method_coverage'] ?? 0) . '%</div>
                <p>' . ($cloverData['methods_covered'] ?? 0) . ' / ' . ($cloverData['methods_total'] ?? 0) . ' methods</p>
            </div>
            
            <div class="metric">
                <h3>Class Coverage</h3>
                <div class="value ' . $this->getCoverageClass($cloverData['class_coverage'] ?? 0) . '">' . ($cloverData['class_coverage'] ?? 0) . '%</div>
                <p>' . ($cloverData['classes_covered'] ?? 0) . ' / ' . ($cloverData['classes_total'] ?? 0) . ' classes</p>
            </div>
        </div>
        
        <div class="file-list">
            <h2>📁 File Coverage Details</h2>';
        
        foreach ($textData as $file => $coverage) {
            $html .= '<div class="file-item">
                <span class="file-name">' . htmlspecialchars($file) . '</span>
                <div class="coverage-bar">
                    <div class="coverage-fill coverage-' . $this->getCoverageClass($coverage) . '" style="width: ' . $coverage . '%"></div>
                </div>
                <span>' . $coverage . '%</span>
            </div>';
        }
        
        $html .= '</div>
    </div>
</body>
</html>';
        
        return $html;
    }

    private function getCoverageClass($coverage)
    {
        if ($coverage >= 90) return 'excellent';
        if ($coverage >= 80) return 'good';
        if ($coverage >= 70) return 'fair';
        return 'poor';
    }

    private function generateTextReport()
    {
        echo "📄 Generating text report...\n";
        echo "---------------------------\n";
        
        $textContent = $this->createTextReport();
        $textFile = $this->reportPath . '/coverage-summary.txt';
        
        file_put_contents($textFile, $textContent);
        
        echo "✅ Text report generated: {$textFile}\n";
    }

    private function createTextReport()
    {
        $cloverData = $this->coverageData['clover'] ?? [];
        
        $report = "ZENA MANAGE - TEST COVERAGE SUMMARY\n";
        $report .= "===================================\n\n";
        $report .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        $report .= "OVERALL COVERAGE:\n";
        $report .= "-----------------\n";
        $report .= "Line Coverage: " . ($cloverData['line_coverage'] ?? 0) . "% (" . ($cloverData['lines_covered'] ?? 0) . "/" . ($cloverData['lines_total'] ?? 0) . ")\n";
        $report .= "Method Coverage: " . ($cloverData['method_coverage'] ?? 0) . "% (" . ($cloverData['methods_covered'] ?? 0) . "/" . ($cloverData['methods_total'] ?? 0) . ")\n";
        $report .= "Class Coverage: " . ($cloverData['class_coverage'] ?? 0) . "% (" . ($cloverData['classes_covered'] ?? 0) . "/" . ($cloverData['classes_total'] ?? 0) . ")\n\n";
        
        $report .= "QUALITY ASSESSMENT:\n";
        $report .= "------------------\n";
        
        $overallCoverage = $cloverData['line_coverage'] ?? 0;
        if ($overallCoverage >= 90) {
            $report .= "🏆 EXCELLENT: Test coverage is outstanding!\n";
        } elseif ($overallCoverage >= 80) {
            $report .= "✅ GOOD: Test coverage is good with room for improvement\n";
        } elseif ($overallCoverage >= 70) {
            $report .= "⚠️  FAIR: Test coverage needs improvement\n";
        } else {
            $report .= "❌ POOR: Test coverage is insufficient\n";
        }
        
        $report .= "\nRECOMMENDATIONS:\n";
        $report .= "---------------\n";
        
        if ($overallCoverage < 90) {
            $report .= "• Increase test coverage for critical components\n";
            $report .= "• Add tests for edge cases and error scenarios\n";
            $report .= "• Focus on untested methods and classes\n";
        }
        
        if (($cloverData['method_coverage'] ?? 0) < 90) {
            $report .= "• Add unit tests for uncovered methods\n";
        }
        
        if (($cloverData['class_coverage'] ?? 0) < 90) {
            $report .= "• Add tests for uncovered classes\n";
        }
        
        return $report;
    }

    private function displayCoverageSummary()
    {
        echo "📊 COVERAGE SUMMARY\n";
        echo "===================\n\n";
        
        $cloverData = $this->coverageData['clover'] ?? [];
        
        if (empty($cloverData)) {
            echo "❌ No coverage data available\n";
            return;
        }
        
        echo "📈 Overall Coverage:\n";
        echo "  - Line Coverage: " . ($cloverData['line_coverage'] ?? 0) . "%\n";
        echo "  - Method Coverage: " . ($cloverData['method_coverage'] ?? 0) . "%\n";
        echo "  - Class Coverage: " . ($cloverData['class_coverage'] ?? 0) . "%\n\n";
        
        echo "📁 Reports Generated:\n";
        echo "  - HTML Report: {$this->reportPath}/coverage-summary.html\n";
        echo "  - Text Report: {$this->reportPath}/coverage-summary.txt\n";
        echo "  - Detailed HTML: {$this->reportPath}/index.html\n";
        echo "  - Clover XML: {$this->reportPath}/coverage.xml\n\n";
        
        $overallCoverage = $cloverData['line_coverage'] ?? 0;
        echo "🎯 Quality Assessment: ";
        
        if ($overallCoverage >= 90) {
            echo "🏆 EXCELLENT\n";
        } elseif ($overallCoverage >= 80) {
            echo "✅ GOOD\n";
        } elseif ($overallCoverage >= 70) {
            echo "⚠️  FAIR\n";
        } else {
            echo "❌ POOR\n";
        }
        
        echo "\n🎉 Coverage report generation completed!\n";
    }
}

// Generate coverage report
$coverageGenerator = new TestCoverageReportGenerator();
$coverageGenerator->generateCoverageReport();
