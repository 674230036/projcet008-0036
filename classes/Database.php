<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private string $host = DB_HOST;
    private string $dbName = DB_NAME;
    private string $username = DB_USER;
    private string $password = DB_PASS;
    private ?PDO $conn = null;

    public function connect(): PDO {
        if ($this->conn === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        }
        return $this->conn;
    }
}
