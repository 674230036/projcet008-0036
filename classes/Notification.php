<?php
require_once __DIR__ . '/Database.php';

class Notification {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(int $userId, ?int $bookingId, string $title, string $message, string $type = 'info'): bool {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notifications (user_id, booking_id, title, message, type, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())"
            );
            return $stmt->execute([$userId, $bookingId, $title, $message, $type]);
        } catch (Exception $e) {
            error_log("Notification create error: " . $e->getMessage());
            return false;
        }
    }

    public function notifyRole(string $role, ?int $bookingId, string $title, string $message, string $type = 'info'): void {
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE role = ?");
            $stmt->execute([$role]);
            $users = $stmt->fetchAll();
            foreach ($users as $u) {
                $this->create((int)$u['id'], $bookingId, $title, $message, $type);
            }
        } catch (Exception $e) {
            error_log("Notification notifyRole error: " . $e->getMessage());
        }
    }

    public function getByUser(int $userId, bool $unreadOnly = false, int $limit = 30): array {
        try {
            $sql = "SELECT n.*, b.topic, r.room_code, r.room_name 
                    FROM notifications n
                    LEFT JOIN bookings b ON b.id = n.booking_id
                    LEFT JOIN rooms r ON r.id = b.room_id
                    WHERE n.user_id = ?";
            if ($unreadOnly) {
                $sql .= " AND n.is_read = 0";
            }
            $sql .= " ORDER BY n.created_at DESC, n.id DESC LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Notification getByUser error: " . $e->getMessage());
            return [];
        }
    }

    public function countUnread(int $userId): int {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function markAsRead(int $id, int $userId): bool {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            return $stmt->execute([$id, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function markAllAsRead(int $userId): bool {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            return false;
        }
    }
}
