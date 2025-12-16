#!/usr/bin/env node

/**
 * PR: Performance Metrics Collection
 * 
 * This script collects performance metrics from various sources and outputs them
 * in a format suitable for budget validation.
 * 
 * Usage:
 *   node scripts/collect-performance-metrics.js <output-file>
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const OUTPUT_FILE = process.argv[2] || 'test-results/performance-metrics.json';

// Metrics structure
const metrics = {
  timestamp: new Date().toISOString(),
  api: {},
  pages: {},
  websocket: {},
  cache: {},
  database: {},
  memory: {},
};

/**
 * Collect API metrics from test results or logs
 */
function collectApiMetrics() {
  console.log('📊 Collecting API metrics...');
  
  // Try to run Laravel command to export metrics
  try {
    const laravelMetrics = execSync('php artisan metrics:export --output=test-results/laravel-metrics.json 2>&1', { encoding: 'utf8' });
    const laravelPath = 'test-results/laravel-metrics.json';
    if (fs.existsSync(laravelPath)) {
      const laravelData = JSON.parse(fs.readFileSync(laravelPath, 'utf8'));
      if (laravelData.api && Object.keys(laravelData.api).length > 0) {
        metrics.api = laravelData.api;
        console.log('  ✅ Loaded API metrics from Laravel');
        return;
      }
    }
  } catch (error) {
    // Laravel command might not be available in all environments
    console.log('  ℹ️  Laravel metrics export not available');
  }
  
  // Try to read from test results
  const testResultsPath = 'test-results/api-metrics.json';
  if (fs.existsSync(testResultsPath)) {
    try {
      const testMetrics = JSON.parse(fs.readFileSync(testResultsPath, 'utf8'));
      metrics.api = testMetrics;
      console.log('  ✅ Loaded API metrics from test results');
      return;
    } catch (error) {
      console.log('  ⚠️  Failed to load API metrics from test results');
    }
  }
  
  // Try to extract from Laravel logs (if available)
  const logPath = 'storage/logs/laravel.log';
  if (fs.existsSync(logPath)) {
    try {
      // Extract API response times from logs
      // This is a simplified example - in production, use proper log parsing
      const logContent = fs.readFileSync(logPath, 'utf8');
      const apiMatches = logContent.match(/API.*response.*time.*(\d+\.?\d*)ms/gi);
      
      if (apiMatches && apiMatches.length > 0) {
        const times = apiMatches.map(m => parseFloat(m.match(/(\d+\.?\d*)/)[1]));
        const sorted = times.sort((a, b) => a - b);
        const p95Index = Math.floor(sorted.length * 0.95);
        const p99Index = Math.floor(sorted.length * 0.99);
        
        metrics.api['/api/v1/app/projects'] = {
          p50: sorted[Math.floor(sorted.length * 0.5)],
          p95: sorted[p95Index] || sorted[sorted.length - 1],
          p99: sorted[p99Index] || sorted[sorted.length - 1],
          max: Math.max(...sorted),
          count: sorted.length,
        };
        
        console.log('  ✅ Extracted API metrics from logs');
      }
    } catch (error) {
      console.log('  ⚠️  Failed to extract API metrics from logs');
    }
  }
  
  // Default: empty metrics (will be skipped in budget check)
  if (Object.keys(metrics.api).length === 0) {
    console.log('  ℹ️  No API metrics found');
  }
}

/**
 * Collect page metrics from Lighthouse or Playwright
 */
function collectPageMetrics() {
  console.log('📄 Collecting page metrics...');
  
  // Try to run Laravel command to export metrics
  try {
    const laravelPath = 'test-results/laravel-metrics.json';
    if (fs.existsSync(laravelPath)) {
      const laravelData = JSON.parse(fs.readFileSync(laravelPath, 'utf8'));
      if (laravelData.pages && Object.keys(laravelData.pages).length > 0) {
        metrics.pages = laravelData.pages;
        console.log('  ✅ Loaded page metrics from Laravel');
        return;
      }
    }
  } catch (error) {
    console.log('  ℹ️  Laravel metrics export not available');
  }
  
  // Try to read from Playwright test results
  const playwrightResultsPath = 'test-results/page-metrics.json';
  if (fs.existsSync(playwrightResultsPath)) {
    try {
      const pageMetrics = JSON.parse(fs.readFileSync(playwrightResultsPath, 'utf8'));
      metrics.pages = pageMetrics;
      console.log('  ✅ Loaded page metrics from test results');
      return;
    } catch (error) {
      console.log('  ⚠️  Failed to load page metrics from test results');
    }
  }
  
  // Try to read from Lighthouse results
  const lighthouseResultsPath = 'test-results/lighthouse-results.json';
  if (fs.existsSync(lighthouseResultsPath)) {
    try {
      const lighthouse = JSON.parse(fs.readFileSync(lighthouseResultsPath, 'utf8'));
      
      // Extract metrics from Lighthouse
      if (lighthouse.audits) {
        const fcp = lighthouse.audits['first-contentful-paint']?.numericValue;
        const lcp = lighthouse.audits['largest-contentful-paint']?.numericValue;
        const ttfb = lighthouse.audits['server-response-time']?.numericValue;
        
        if (fcp || lcp || ttfb) {
          metrics.pages[lighthouse.requestedUrl || '/app/dashboard'] = {
            fcp: fcp,
            lcp: lcp,
            ttfb: ttfb,
            p95: lcp || fcp || ttfb, // Use LCP as primary metric
          };
          console.log('  ✅ Extracted page metrics from Lighthouse');
        }
      }
    } catch (error) {
      console.log('  ⚠️  Failed to extract page metrics from Lighthouse');
    }
  }
  
  if (Object.keys(metrics.pages).length === 0) {
    console.log('  ℹ️  No page metrics found');
  }
}

/**
 * Collect WebSocket metrics
 */
function collectWebSocketMetrics() {
  console.log('🔌 Collecting WebSocket metrics...');
  
  // Try to read from test results
  const wsResultsPath = 'test-results/websocket-metrics.json';
  if (fs.existsSync(wsResultsPath)) {
    try {
      const wsMetrics = JSON.parse(fs.readFileSync(wsResultsPath, 'utf8'));
      metrics.websocket = wsMetrics;
      console.log('  ✅ Loaded WebSocket metrics from test results');
      return;
    } catch (error) {
      console.log('  ⚠️  Failed to load WebSocket metrics from test results');
    }
  }
  
  if (Object.keys(metrics.websocket).length === 0) {
    console.log('  ℹ️  No WebSocket metrics found');
  }
}

/**
 * Collect cache metrics
 */
function collectCacheMetrics() {
  console.log('💾 Collecting cache metrics...');
  
  // Try to read from Laravel cache stats (if available)
  try {
    // This would require a Laravel command to export cache stats
    // For now, we'll use empty metrics
    console.log('  ℹ️  Cache metrics collection requires Laravel integration');
  } catch (error) {
    console.log('  ⚠️  Failed to collect cache metrics');
  }
}

/**
 * Collect database metrics
 */
function collectDatabaseMetrics() {
  console.log('🗄️  Collecting database metrics...');
  
  // Try to read from Laravel query log (if available)
  try {
    // This would require a Laravel command to export query stats
    // For now, we'll use empty metrics
    console.log('  ℹ️  Database metrics collection requires Laravel integration');
  } catch (error) {
    console.log('  ⚠️  Failed to collect database metrics');
  }
}

/**
 * Collect memory metrics
 */
function collectMemoryMetrics() {
  console.log('🧠 Collecting memory metrics...');
  
  // Get system memory usage
  try {
    if (process.platform === 'linux' || process.platform === 'darwin') {
      const memInfo = execSync('free -m 2>/dev/null || vm_stat 2>/dev/null || echo ""', { encoding: 'utf8' });
      
      // Simplified memory collection
      // In production, use proper system monitoring
      const totalMemory = 8192; // Default 8GB
      const usedMemory = process.memoryUsage().heapUsed / 1024 / 1024; // MB
      const peakUsage = (usedMemory / totalMemory) * 100;
      
      metrics.memory = {
        peak_usage: peakUsage,
        heap_used: usedMemory,
        heap_total: process.memoryUsage().heapTotal / 1024 / 1024,
      };
      
      console.log('  ✅ Collected memory metrics');
    } else {
      console.log('  ℹ️  Memory metrics collection not supported on this platform');
    }
  } catch (error) {
    console.log('  ⚠️  Failed to collect memory metrics');
  }
}

// Main execution
console.log('🚀 Starting performance metrics collection...\n');

collectApiMetrics();
collectPageMetrics();
collectWebSocketMetrics();
collectCacheMetrics();
collectDatabaseMetrics();
collectMemoryMetrics();

// Write metrics to file
const outputDir = path.dirname(OUTPUT_FILE);
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

fs.writeFileSync(OUTPUT_FILE, JSON.stringify(metrics, null, 2), 'utf8');

console.log(`\n✅ Performance metrics collected and written to: ${OUTPUT_FILE}`);
console.log(`\n📊 Metrics Summary:`);
console.log(`   API endpoints: ${Object.keys(metrics.api).length}`);
console.log(`   Pages: ${Object.keys(metrics.pages).length}`);
console.log(`   WebSocket: ${Object.keys(metrics.websocket).length}`);
console.log(`   Cache: ${Object.keys(metrics.cache).length}`);
console.log(`   Database: ${Object.keys(metrics.database).length}`);
console.log(`   Memory: ${Object.keys(metrics.memory).length}`);

