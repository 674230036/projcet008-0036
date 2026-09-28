<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'npru_room_booking');
define('DB_USER', 'root');
define('DB_PASS', '');

// Auto-detect dynamic base URL
if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectDir = str_replace('\\', '/', dirname(__DIR__));
    
    if (!empty($docRoot) && strpos($projectDir, $docRoot) === 0) {
        $rel = substr($projectDir, strlen($docRoot));
    } else {
        $rel = '/' . basename($projectDir);
    }
    $rel = '/' . trim($rel, '/');
    define('BASE_URL', rtrim($rel, '/') . '/public/');
    define('PROJECT_URL', rtrim($rel, '/') . '/');
}
