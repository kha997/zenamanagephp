<?php declare(strict_types=1);

/**
 * Script kiểm tra các cổng đang hoạt động
 * Kiểm tra các service quan trọng như web server, database, etc.
 */

class PortChecker
{
    /**
     * Danh sách các cổng cần kiểm tra
     * @var array
     */
    private array $ports = [
        80 => 'HTTP (Apache/Nginx)',
        443 => 'HTTPS (Apache/Nginx)',
        3306 => 'MySQL Database',
        8080 => 'Alternative HTTP',
        8000 => 'Laravel Development Server',
        5432 => 'PostgreSQL',
        6379 => 'Redis',
        11211 => 'Memcached',
        22 => 'SSH',
        21 => 'FTP'
    ];

    /**
     * Kiểm tra một cổng cụ thể
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @return bool
     */
    public function checkPort(string $host, int $port, int $timeout = 3): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if ($connection) {
            fclose($connection);
            return true;
        }
        
        return false;
    }

    /**
     * Kiểm tra tất cả các cổng trong danh sách
     * @param string $host
     * @return array
     */
    public function checkAllPorts(string $host = 'localhost'): array
    {
        $results = [];
        
        foreach ($this->ports as $port => $description) {
            $isOpen = $this->checkPort($host, $port);
            $results[] = [
                'port' => $port,
                'description' => $description,
                'status' => $isOpen ? 'OPEN' : 'CLOSED',
                'is_open' => $isOpen
            ];
        }
        
        return $results;
    }

    /**
     * Hiển thị kết quả kiểm tra dưới dạng bảng
     * @param array $results
     */
    public function displayResults(array $results): void
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "           KIỂM TRA CÁC CỔNG ĐANG HOẠT ĐỘNG\n";
        echo str_repeat('=', 70) . "\n";
        echo sprintf("%-8s %-25s %-10s\n", 'CỔNG', 'MÔ TẢ', 'TRẠNG THÁI');
        echo str_repeat('-', 70) . "\n";
        
        foreach ($results as $result) {
            $status = $result['is_open'] ? '✅ OPEN' : '❌ CLOSED';
            echo sprintf(
                "%-8d %-25s %-10s\n", 
                $result['port'], 
                $result['description'], 
                $status
            );
        }
        
        echo str_repeat('=', 70) . "\n";
    }

    /**
     * Kiểm tra các process đang chạy
     * @return array
     */
    public function checkRunningProcesses(): array
    {
        $processes = [];
        
        // Kiểm tra Apache
        $apache = shell_exec('pgrep -f httpd || pgrep -f apache2');
        if ($apache !== null && !empty(trim($apache))) {
            $processes[] = 'Apache Web Server';
        }
        
        // Kiểm tra MySQL
        $mysql = shell_exec('pgrep -f mysqld');
        if ($mysql !== null && !empty(trim($mysql))) {
            $processes[] = 'MySQL Database';
        }
        
        // Kiểm tra Nginx
        $nginx = shell_exec('pgrep -f nginx');
        if ($nginx !== null && !empty(trim($nginx))) {
            $processes[] = 'Nginx Web Server';
        }
        
        // Kiểm tra PHP-FPM
        $phpfpm = shell_exec('pgrep -f php-fpm');
        if ($phpfpm !== null && !empty(trim($phpfpm))) {
            $processes[] = 'PHP-FPM';
        }
        
        return $processes;
    }

    /**
     * Lấy thông tin về các cổng đang được sử dụng
     * @return string
     */
    public function getNetstatInfo(): string
    {
        // Sử dụng netstat để lấy thông tin chi tiết
        $netstat = shell_exec('netstat -tuln 2>/dev/null || ss -tuln 2>/dev/null');
        return $netstat ?: 'Không thể lấy thông tin netstat';
    }

    /**
     * Kiểm tra kết nối database Laravel
     * @return bool
     */
    public function checkDatabaseConnection(): bool
    {
        try {
            // Kiểm tra file .env có tồn tại không
            if (!file_exists(__DIR__ . '/.env')) {
                return false;
            }
            
            // Đọc thông tin database từ .env
            $env = file_get_contents(__DIR__ . '/.env');
            preg_match('/DB_HOST=(.*)/', $env, $host_matches);
            preg_match('/DB_PORT=(.*)/', $env, $port_matches);
            preg_match('/DB_DATABASE=(.*)/', $env, $db_matches);
            preg_match('/DB_USERNAME=(.*)/', $env, $user_matches);
            preg_match('/DB_PASSWORD=(.*)/', $env, $pass_matches);
            
            $host = trim($host_matches[1] ?? 'localhost');
            $port = trim($port_matches[1] ?? '3306');
            $database = trim($db_matches[1] ?? '');
            $username = trim($user_matches[1] ?? '');
            $password = trim($pass_matches[1] ?? '');
            
            if (empty($database) || empty($username)) {
                return false;
            }
            
            // Thử kết nối database
            $dsn = "mysql:host={$host};port={$port};dbname={$database}";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
}

// Chạy kiểm tra
try {
    $checker = new PortChecker();
    
    echo "\n🔍 Bắt đầu kiểm tra các cổng và services...\n";
    
    // Kiểm tra các cổng
    $results = $checker->checkAllPorts();
    $checker->displayResults($results);
    
    // Kiểm tra các process đang chạy
    echo "\n📋 CÁC PROCESS ĐANG CHẠY:\n";
    echo str_repeat('-', 40) . "\n";
    $processes = $checker->checkRunningProcesses();
    if (!empty($processes)) {
        foreach ($processes as $process) {
            echo "✅ {$process}\n";
        }
    } else {
        echo "❌ Không tìm thấy process nào đang chạy\n";
    }
    
    // Kiểm tra kết nối database Laravel
    echo "\n🗄️  KIỂM TRA DATABASE:\n";
    echo str_repeat('-', 40) . "\n";
    $dbStatus = $checker->checkDatabaseConnection();
    echo $dbStatus ? "✅ Database connection: OK\n" : "❌ Database connection: FAILED\n";
    
    // Hiển thị thông tin chi tiết về các cổng đang mở
    echo "\n📊 THÔNG TIN CHI TIẾT CÁC CỔNG:\n";
    echo str_repeat('-', 70) . "\n";
    echo $checker->getNetstatInfo();
    
    echo "\n✅ Hoàn thành kiểm tra!\n\n";
    
} catch (Exception $e) {
    echo "❌ Lỗi khi kiểm tra: " . $e->getMessage() . "\n";
}