<?php

/**
 * PHASE 5: Script phân tích và tối ưu hóa database
 */

$basePath = '/Applications/XAMPP/xamppfiles/htdocs/zenamanage';

echo "⚡ PHASE 5: TỐI ƯU LOGIC & DB\n";
echo "============================\n\n";

// 1. Phân tích migrations để tìm missing indexes
echo "1️⃣ Phân tích migrations để tìm missing indexes...\n";

$migrationFiles = glob($basePath . '/database/migrations/*.php');
$missingIndexes = [];
$foreignKeys = [];
$tables = [];

foreach ($migrationFiles as $migrationFile) {
    $content = file_get_contents($migrationFile);
    $filename = basename($migrationFile);
    
    // Tìm table names
    if (preg_match('/create_(\w+)_table/', $filename, $matches)) {
        $tableName = $matches[1];
        $tables[] = $tableName;
    }
    
    // Tìm foreign keys
    if (preg_match_all('/foreign\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
        foreach ($matches[1] as $column) {
            $foreignKeys[] = $column;
        }
    }
    
    // Tìm columns có thể cần index
    if (preg_match_all('/\$table->(string|integer|bigInteger|timestamp|boolean)\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[2] as $column) {
            // Các column thường cần index
            if (in_array($column, ['user_id', 'project_id', 'task_id', 'tenant_id', 'status', 'created_at', 'updated_at'])) {
                if (!preg_match('/index\([\'"]([^\'"]*)[\'"]\)/', $content)) {
                    $missingIndexes[] = [
                        'file' => $filename,
                        'table' => $tableName ?? 'unknown',
                        'column' => $column
                    ];
                }
            }
        }
    }
}

echo "   📊 Tables found: " . count($tables) . "\n";
echo "   📊 Foreign keys found: " . count($foreignKeys) . "\n";
echo "   📊 Potential missing indexes: " . count($missingIndexes) . "\n\n";

// 2. Phân tích Eloquent models để tìm N+1 queries
echo "2️⃣ Phân tích Eloquent models để tìm N+1 queries...\n";

$modelFiles = glob($basePath . '/app/Models/*.php');
$nPlusOneIssues = [];
$eagerLoadingOpportunities = [];

foreach ($modelFiles as $modelFile) {
    $content = file_get_contents($modelFile);
    $filename = basename($modelFile);
    
    // Tìm relationships
    $relationships = [];
    if (preg_match_all('/public function (\w+)\(\)/', $content, $matches)) {
        foreach ($matches[1] as $method) {
            if (preg_match('/public function ' . $method . '\(\)[^{]*{([^}]+)}/', $content, $methodMatch)) {
                $methodContent = $methodMatch[1];
                if (strpos($methodContent, 'belongsTo') !== false || 
                    strpos($methodContent, 'hasMany') !== false || 
                    strpos($methodContent, 'belongsToMany') !== false) {
                    $relationships[] = $method;
                }
            }
        }
    }
    
    // Tìm potential N+1 issues
    if (count($relationships) > 0) {
        $nPlusOneIssues[] = [
            'file' => $filename,
            'relationships' => $relationships,
            'count' => count($relationships)
        ];
    }
}

echo "   📊 Models analyzed: " . count($modelFiles) . "\n";
echo "   📊 Models with relationships: " . count($nPlusOneIssues) . "\n\n";

// 3. Phân tích Controllers để tìm inefficient queries
echo "3️⃣ Phân tích Controllers để tìm inefficient queries...\n";

$controllerFiles = glob($basePath . '/app/Http/Controllers/**/*.php');
$inefficientQueries = [];

foreach ($controllerFiles as $controllerFile) {
    $content = file_get_contents($controllerFile);
    $filename = basename($controllerFile);
    
    $issues = [];
    
    // Tìm queries không có pagination
    if (preg_match('/::all\(\)/', $content)) {
        $issues[] = 'Using ::all() without pagination';
    }
    
    // Tìm queries không có eager loading
    if (preg_match('/->get\(\)/', $content) && !preg_match('/->with\(/', $content)) {
        $issues[] = 'Potential N+1 query (get() without with())';
    }
    
    // Tìm queries không có select
    if (preg_match('/->get\(\)/', $content) && !preg_match('/->select\(/', $content)) {
        $issues[] = 'Not using select() for specific columns';
    }
    
    // Tìm queries không có where conditions
    if (preg_match('/->get\(\)/', $content) && !preg_match('/->where\(/', $content)) {
        $issues[] = 'No where conditions (potential full table scan)';
    }
    
    if (!empty($issues)) {
        $inefficientQueries[] = [
            'file' => $filename,
            'issues' => $issues
        ];
    }
}

echo "   📊 Controllers analyzed: " . count($controllerFiles) . "\n";
echo "   📊 Controllers with inefficient queries: " . count($inefficientQueries) . "\n\n";

// 4. Phân tích Services để tìm performance issues
echo "4️⃣ Phân tích Services để tìm performance issues...\n";

$serviceFiles = glob($basePath . '/app/Services/*.php');
$performanceIssues = [];

foreach ($serviceFiles as $serviceFile) {
    $content = file_get_contents($serviceFile);
    $filename = basename($serviceFile);
    
    $issues = [];
    
    // Tìm loops với database queries
    if (preg_match('/foreach\s*\([^)]+\)\s*{[^}]*->(save|create|update|delete)\(/', $content)) {
        $issues[] = 'Database queries inside loops';
    }
    
    // Tìm missing caching
    if (preg_match('/->get\(\)/', $content) && !preg_match('/cache\(/', $content)) {
        $issues[] = 'Missing caching for repeated queries';
    }
    
    // Tìm large data processing
    if (preg_match('/->get\(\)/', $content) && !preg_match('/->chunk\(/', $content)) {
        $issues[] = 'Potential memory issue (not using chunk())';
    }
    
    if (!empty($issues)) {
        $performanceIssues[] = [
            'file' => $filename,
            'issues' => $issues
        ];
    }
}

echo "   📊 Services analyzed: " . count($serviceFiles) . "\n";
echo "   📊 Services with performance issues: " . count($performanceIssues) . "\n\n";

// 5. Tạo báo cáo chi tiết
echo "📋 BÁO CÁO CHI TIẾT:\n";
echo "==================\n\n";

if (!empty($missingIndexes)) {
    echo "🔍 MISSING INDEXES:\n";
    foreach (array_slice($missingIndexes, 0, 10) as $index) {
        echo "   - {$index['table']}.{$index['column']} in {$index['file']}\n";
    }
    if (count($missingIndexes) > 10) {
        echo "   ... và " . (count($missingIndexes) - 10) . " indexes khác\n";
    }
    echo "\n";
}

if (!empty($nPlusOneIssues)) {
    echo "🔄 N+1 QUERY ISSUES:\n";
    foreach (array_slice($nPlusOneIssues, 0, 10) as $issue) {
        echo "   - {$issue['file']}: " . implode(', ', $issue['relationships']) . "\n";
    }
    if (count($nPlusOneIssues) > 10) {
        echo "   ... và " . (count($nPlusOneIssues) - 10) . " models khác\n";
    }
    echo "\n";
}

if (!empty($inefficientQueries)) {
    echo "❌ INEFFICIENT QUERIES:\n";
    foreach (array_slice($inefficientQueries, 0, 10) as $query) {
        echo "   - {$query['file']}:\n";
        foreach ($query['issues'] as $issue) {
            echo "     * {$issue}\n";
        }
    }
    if (count($inefficientQueries) > 10) {
        echo "   ... và " . (count($inefficientQueries) - 10) . " controllers khác\n";
    }
    echo "\n";
}

if (!empty($performanceIssues)) {
    echo "⚡ PERFORMANCE ISSUES:\n";
    foreach (array_slice($performanceIssues, 0, 10) as $issue) {
        echo "   - {$issue['file']}:\n";
        foreach ($issue['issues'] as $detail) {
            echo "     * {$detail}\n";
        }
    }
    if (count($performanceIssues) > 10) {
        echo "   ... và " . (count($performanceIssues) - 10) . " services khác\n";
    }
    echo "\n";
}

// 6. Tính tổng số issues
$totalIssues = count($missingIndexes) + count($nPlusOneIssues) + count($inefficientQueries) + count($performanceIssues);

echo "📊 TỔNG KẾT:\n";
echo "============\n";
echo "  🔍 Missing indexes: " . count($missingIndexes) . "\n";
echo "  🔄 N+1 query issues: " . count($nPlusOneIssues) . "\n";
echo "  ❌ Inefficient queries: " . count($inefficientQueries) . "\n";
echo "  ⚡ Performance issues: " . count($performanceIssues) . "\n";
echo "  📊 Total issues: " . $totalIssues . "\n\n";

echo "🎯 Hoàn thành phân tích PHASE 5!\n";
