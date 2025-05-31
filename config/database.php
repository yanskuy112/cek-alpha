<?php
// Database configuration for Vercel
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    
    public function __construct() {
        // Vercel environment variables
        $this->host = $_ENV['DB_HOST'] ?? 'kegiatan-harian-db-kegiatan-harian.g.aivencloud.com';
        $this->username = $_ENV['DB_USERNAME'] ?? 'avnadmin';
        $this->password = $_ENV['DB_PASSWORD'] ?? 'AVNS_d2aIzvgKWTdqGoyNWWN';
        $this->database = $_ENV['DB_DATABASE'] ?? 'kegiatan_harian';
        
        // Alternative: use getenv() if $_ENV doesn't work
        if (empty($this->host)) {
            $this->host = getenv('DB_HOST') ?: 'kegiatan-harian-db-kegiatan-harian.g.aivencloud.com';
            $this->username = getenv('DB_USERNAME') ?: 'avnadmin';
            $this->password = getenv('DB_PASSWORD') ?: 'AVNS_d2aIzvgKWTdqGoyNWWN';
            $this->database = getenv('DB_DATABASE') ?: 'kegiatan_harian';
        }
    }
    
    public function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // For some cloud databases
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            return $this->connection;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    // Create table if not exists
    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS kegiatan_harian (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            waktu TIME NOT NULL,
            kategori VARCHAR(100) NOT NULL,
            catatan TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->getConnection()->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Table creation failed: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize database connection
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $db->createTable();
} catch (Exception $e) {
    // Fallback to session storage if database fails
    $use_database = false;
    error_log("Database initialization failed, using session storage: " . $e->getMessage());
}
?>