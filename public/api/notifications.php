<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Notification.php';

header('Content-Type: application/json; charset=utf-8');

Auth::start();
$currentUser = Auth::user();

if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$db = (new Database())->connect();
$notifModel = new Notification($db);
$userId = (int)$currentUser['id'];

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    $notifications = $notifModel->getByUser($userId, false, 20);
    $unreadCount = $notifModel->countUnread($userId);

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'booking_id' => $n['booking_id'] ? (int)$n['booking_id'] : null,
                'title' => $n['title'],
                'message' => $n['message'],
                'type' => $n['type'],
                'is_read' => (int)$n['is_read'],
                'created_at' => $n['created_at'],
                'room_code' => $n['room_code'] ?? null
            ];
        }, $notifications)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'mark_read') {
    $notifId = (int)($_POST['id'] ?? 0);
    if ($notifId > 0) {
        $notifModel->markAsRead($notifId, $userId);
    }
    $unreadCount = $notifModel->countUnread($userId);
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount
    ]);
    exit;
}

if ($action === 'mark_all_read') {
    $notifModel->markAllAsRead($userId);
    echo json_encode([
        'success' => true,
        'unread_count' => 0
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
