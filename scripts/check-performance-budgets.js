#!/usr/bin/env node

/**
 * PR: Performance Budgets Enforcement in CI
 * 
 * This script validates performance metrics against budgets defined in performance-budgets.json
 * 
 * Usage:
 *   node scripts/check-performance-budgets.js <budgets-file> <metrics-file> <report-file>
 */

const fs = require('fs');
const path = require('path');

const BUDGETS_FILE = process.argv[2] || 'performance-budgets.json';
const METRICS_FILE = process.argv[3] || 'test-results/performance-metrics.json';
const REPORT_FILE = process.argv[4] || 'test-results/performance-budget-report.json';

// Load budgets
let budgets;
try {
  budgets = JSON.parse(fs.readFileSync(BUDGETS_FILE, 'utf8'));
} catch (error) {
  console.error(`❌ Failed to load budgets file: ${BUDGETS_FILE}`);
  console.error(error.message);
  process.exit(1);
}

// Load metrics
let metrics;
try {
  if (fs.existsSync(METRICS_FILE)) {
    metrics = JSON.parse(fs.readFileSync(METRICS_FILE, 'utf8'));
  } else {
    console.warn(`⚠️  Metrics file not found: ${METRICS_FILE}`);
    metrics = { api: {}, pages: {}, websocket: {}, cache: {}, database: {}, memory: {} };
  }
} catch (error) {
  console.error(`❌ Failed to load metrics file: ${METRICS_FILE}`);
  console.error(error.message);
  process.exit(1);
}

// Report structure
const report = {
  timestamp: new Date().toISOString(),
  budgetsFile: BUDGETS_FILE,
  metricsFile: METRICS_FILE,
  violations: [],
  warnings: [],
  summary: {
    total: 0,
    passed: 0,
    failed: 0,
    warned: 0,
  },
};

/**
 * Check if value exceeds budget
 */
function checkBudget(category, metric, value, budget, context = {}) {
  report.summary.total++;
  
  const threshold = budgets.enforcement?.thresholds?.warning || 0.8;
  const warningThreshold = budget * threshold;
  
  let status = 'passed';
  let message = '';
  
  if (value > budget) {
    status = 'failed';
    message = `${category}.${metric}: ${value}ms exceeds budget of ${budget}ms (${((value / budget - 1) * 100).toFixed(1)}% over)`;
    report.violations.push({
      category,
      metric,
      value,
      budget,
      overage: value - budget,
      overagePercent: ((value / budget - 1) * 100).toFixed(1),
      context,
    });
    report.summary.failed++;
  } else if (value > warningThreshold) {
    status = 'warned';
    message = `${category}.${metric}: ${value}ms is ${((value / budget) * 100).toFixed(1)}% of budget (${budget}ms)`;
    report.warnings.push({
      category,
      metric,
      value,
      budget,
      usagePercent: ((value / budget) * 100).toFixed(1),
      context,
    });
    report.summary.warned++;
  } else {
    report.summary.passed++;
  }
  
  return { status, message };
}

/**
 * Check API performance budgets
 */
function checkApiBudgets() {
  const apiMetrics = metrics.api || {};
  const apiBudgets = budgets.budgets.api || {};
  const defaultBudget = apiBudgets.default || {};
  
  console.log('\n📊 Checking API performance budgets...');
  
  // Check each endpoint
  if (apiBudgets.endpoints) {
    for (const [endpoint, endpointBudget] of Object.entries(apiBudgets.endpoints)) {
      const endpointMetrics = apiMetrics[endpoint] || {};
      
      // Check p95 (primary metric)
      if (endpointMetrics.p95 !== undefined && endpointBudget.p95) {
        const result = checkBudget('api', endpoint, endpointMetrics.p95, endpointBudget.p95, {
          endpoint,
          percentile: 'p95',
        });
        if (result.status !== 'passed') {
          console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
        }
      }
      
      // Check p99
      if (endpointMetrics.p99 !== undefined && endpointBudget.p99) {
        const result = checkBudget('api', endpoint, endpointMetrics.p99, endpointBudget.p99, {
          endpoint,
          percentile: 'p99',
        });
        if (result.status !== 'passed') {
          console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
        }
      }
    }
  }
  
  // Check default if no specific endpoint metrics
  if (Object.keys(apiMetrics).length === 0 && defaultBudget.p95) {
    console.log('  ℹ️  No API metrics found, skipping API budget checks');
  }
}

/**
 * Check page performance budgets
 */
function checkPageBudgets() {
  const pageMetrics = metrics.pages || {};
  const pageBudgets = budgets.budgets.pages || {};
  const defaultBudget = pageBudgets.default || {};
  
  console.log('\n📄 Checking page performance budgets...');
  
  // Check each route
  if (pageBudgets.routes) {
    for (const [route, routeBudget] of Object.entries(pageBudgets.routes)) {
      const routeMetrics = pageMetrics[route] || {};
      
      // Check p95 (primary metric)
      if (routeMetrics.p95 !== undefined && routeBudget.p95) {
        const result = checkBudget('pages', route, routeMetrics.p95, routeBudget.p95, {
          route,
          percentile: 'p95',
        });
        if (result.status !== 'passed') {
          console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
        }
      }
      
      // Check LCP if available
      if (routeMetrics.lcp !== undefined && routeBudget.lcp) {
        const result = checkBudget('pages', route, routeMetrics.lcp, routeBudget.lcp, {
          route,
          metric: 'lcp',
        });
        if (result.status !== 'passed') {
          console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
        }
      }
    }
  }
  
  // Check default if no specific route metrics
  if (Object.keys(pageMetrics).length === 0 && defaultBudget.p95) {
    console.log('  ℹ️  No page metrics found, skipping page budget checks');
  }
}

/**
 * Check WebSocket performance budgets
 */
function checkWebSocketBudgets() {
  const wsMetrics = metrics.websocket || {};
  const wsBudgets = budgets.budgets.websocket || {};
  
  console.log('\n🔌 Checking WebSocket performance budgets...');
  
  if (wsBudgets.subscribe && wsMetrics.subscribe) {
    if (wsMetrics.subscribe.p95 !== undefined) {
      const result = checkBudget('websocket', 'subscribe', wsMetrics.subscribe.p95, wsBudgets.subscribe.p95);
      if (result.status !== 'passed') {
        console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
      }
    }
  }
  
  if (wsBudgets.message_delivery && wsMetrics.message_delivery) {
    if (wsMetrics.message_delivery.p95 !== undefined) {
        const result = checkBudget('websocket', 'message_delivery', wsMetrics.message_delivery.p95, wsBudgets.message_delivery.p95);
      if (result.status !== 'passed') {
        console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
      }
    }
  }
  
  if (Object.keys(wsMetrics).length === 0) {
    console.log('  ℹ️  No WebSocket metrics found, skipping WebSocket budget checks');
  }
}

/**
 * Check cache performance budgets
 */
function checkCacheBudgets() {
  const cacheMetrics = metrics.cache || {};
  const cacheBudgets = budgets.budgets.cache || {};
  
  console.log('\n💾 Checking cache performance budgets...');
  
  if (cacheBudgets.hit_rate && cacheMetrics.hit_rate !== undefined) {
    const minHitRate = cacheBudgets.hit_rate.min;
    if (cacheMetrics.hit_rate < minHitRate) {
      const result = checkBudget('cache', 'hit_rate', cacheMetrics.hit_rate, minHitRate);
      if (result.status !== 'passed') {
        console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
      }
    }
  }
  
  if (Object.keys(cacheMetrics).length === 0) {
    console.log('  ℹ️  No cache metrics found, skipping cache budget checks');
  }
}

/**
 * Check database performance budgets
 */
function checkDatabaseBudgets() {
  const dbMetrics = metrics.database || {};
  const dbBudgets = budgets.budgets.database || {};
  
  console.log('\n🗄️  Checking database performance budgets...');
  
  if (dbBudgets.query_time && dbMetrics.query_time) {
    if (dbMetrics.query_time.p95 !== undefined) {
      const result = checkBudget('database', 'query_time', dbMetrics.query_time.p95, dbBudgets.query_time.p95);
      if (result.status !== 'passed') {
        console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
      }
    }
  }
  
  if (Object.keys(dbMetrics).length === 0) {
    console.log('  ℹ️  No database metrics found, skipping database budget checks');
  }
}

/**
 * Check memory performance budgets
 */
function checkMemoryBudgets() {
  const memoryMetrics = metrics.memory || {};
  const memoryBudgets = budgets.budgets.memory || {};
  
  console.log('\n🧠 Checking memory performance budgets...');
  
  if (memoryBudgets.peak_usage && memoryMetrics.peak_usage !== undefined) {
    const maxUsage = memoryBudgets.peak_usage.max;
    if (memoryMetrics.peak_usage > maxUsage) {
      const result = checkBudget('memory', 'peak_usage', memoryMetrics.peak_usage, maxUsage);
      if (result.status !== 'passed') {
        console.log(`  ${result.status === 'failed' ? '❌' : '⚠️ '} ${result.message}`);
      }
    }
  }
  
  if (Object.keys(memoryMetrics).length === 0) {
    console.log('  ℹ️  No memory metrics found, skipping memory budget checks');
  }
}

// Run all checks
console.log('🚀 Starting performance budget validation...\n');

checkApiBudgets();
checkPageBudgets();
checkWebSocketBudgets();
checkCacheBudgets();
checkDatabaseBudgets();
checkMemoryBudgets();

// Write report
const reportDir = path.dirname(REPORT_FILE);
if (!fs.existsSync(reportDir)) {
  fs.mkdirSync(reportDir, { recursive: true });
}

fs.writeFileSync(REPORT_FILE, JSON.stringify(report, null, 2), 'utf8');

// Print summary
console.log('\n📊 Performance Budget Summary:');
console.log(`   Total checks: ${report.summary.total}`);
console.log(`   ✅ Passed: ${report.summary.passed}`);
console.log(`   ⚠️  Warnings: ${report.summary.warned}`);
console.log(`   ❌ Failed: ${report.summary.failed}`);
console.log(`\n📄 Report written to: ${REPORT_FILE}`);

// Exit with error if violations found
if (report.summary.failed > 0) {
  console.log('\n❌ Performance budget violations detected!');
  process.exit(1);
} else if (report.summary.warned > 0) {
  console.log('\n⚠️  Performance budget warnings detected (not blocking)');
  process.exit(0);
} else {
  console.log('\n✅ All performance budgets met!');
  process.exit(0);
}

