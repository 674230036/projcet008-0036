<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'npru_room_booking');
define('DB_USER', 'root');
define('DB_PASS', '');

// Auto-detect dynamic base URL
if (!defined('BASE_URL')) {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectDir = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    
    if (!empty($docRoot) && $docRoot === $projectDir . '/public') {
        define('BASE_URL', '/');
        define('PROJECT_URL', '/');
    } elseif (!empty($docRoot) && strpos($projectDir, $docRoot) === 0) {
        $rel = '/' . trim(substr($projectDir, strlen($docRoot)), '/');
        define('BASE_URL', rtrim($rel, '/') . '/public/');
        define('PROJECT_URL', rtrim($rel, '/') . '/');
    } else {
        $rel = '/' . basename($projectDir);
        define('BASE_URL', rtrim($rel, '/') . '/public/');
        define('PROJECT_URL', rtrim($rel, '/') . '/');
    }
}
