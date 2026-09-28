<?php
class Booking {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getToday(): array {
        $sql = "SELECT b.*, r.room_code, r.room_name, r.room_type, r.capacity, u.name as user_name
                FROM bookings b
                JOIN rooms r ON r.id = b.room_id
                LEFT JOIN users u ON u.id = b.user_id
                WHERE b.booking_date = CURDATE()
                AND b.status IN ('pending', 'approved', 'ready')
                ORDER BY b.start_time ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getByDate(string $date): array {
        $sql = "SELECT b.*, r.room_code, r.room_name, r.room_type, r.capacity, u.name as user_name
                FROM bookings b
                JOIN rooms r ON r.id = b.room_id
                LEFT JOIN users u ON u.id = b.user_id
                WHERE b.booking_date = ?
                ORDER BY b.start_time ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    public function getByRoomAndDate(int $roomId, string $date): array {
        $sql = "SELECT b.*, r.room_code, r.room_name
                FROM bookings b
                JOIN rooms r ON r.id = b.room_id
                WHERE b.room_id = ?
                AND b.booking_date = ?
                AND b.status IN ('pending', 'approved', 'ready')
                ORDER BY b.start_time ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roomId, $date]);
        return $stmt->fetchAll();
    }

    public function getUserBookings(?int $userId, ?string $email = null): array {
        if (!$userId && !$email) {
            return [];
        }
        $sql = "SELECT b.*, r.room_code, r.room_name, r.room_type, r.capacity, r.building
                FROM bookings b
                JOIN rooms r ON r.id = b.room_id
                WHERE (b.user_id = ? OR b.requester_email = ?)
                ORDER BY b.booking_date DESC, b.start_time DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId ?: 0, $email ?: '']);
        return $stmt->fetchAll();
    }

    public function countUserBookings(?int $userId, ?string $email = null): int {
        if (!$userId && !$email) return 0;
        $sql = "SELECT COUNT(*) FROM bookings WHERE (user_id = ? OR requester_email = ?) AND status IN ('pending', 'approved', 'ready')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId ?: 0, $email ?: '']);
        return (int)$stmt->fetchColumn();
    }

    public function getAll(int $limit = 100): array {
        $sql = "SELECT b.*, r.room_code, r.room_name, r.room_type, r.capacity, u.student_id
                FROM bookings b
                JOIN rooms r ON r.id = b.room_id
                LEFT JOIN users u ON u.id = b.user_id
                ORDER BY b.booking_date DESC, b.start_time DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function isConflict(int $roomId, string $date, string $start, string $end, ?int $excludeId = null): bool {
        // Ensure proper time format
        $start = substr($start, 0, 5) . ':00';
        $end = substr($end, 0, 5) . ':00';

        $sql = "SELECT COUNT(*) FROM bookings
                WHERE room_id = ?
                AND booking_date = ?
                AND status IN ('pending', 'approved', 'ready')
                AND (start_time < ? AND end_time > ?)";
        
        $params = [$roomId, $date, $end, $start];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(
        int $roomId,
        ?int $userId,
        string $name,
        string $email,
        string $phone,
        string $topic,
        string $date,
        string $start,
        string $end,
        int $people
    ): array {
        $name = trim($name);
        $email = trim($email);
        $topic = trim($topic);
        $phone = trim($phone);

        if (empty($name) || empty($email) || empty($topic)) {
            return ['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }

        // Validate time
        if (strtotime($start) >= strtotime($end)) {
            return ['success' => false, 'message' => 'เวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด'];
        }

        // Validate date (not before today)
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => 'ไม่สามารถเลือกวันที่ย้อนหลังได้'];
        }

        // Check room capacity
        $rStmt = $this->db->prepare("SELECT capacity, status FROM rooms WHERE id = ?");
        $rStmt->execute([$roomId]);
        $room = $rStmt->fetch();
        if (!$room) {
            return ['success' => false, 'message' => 'ไม่พบข้อมูลห้องที่เลือก'];
        }
        if ($room['status'] === 'maintenance') {
            return ['success' => false, 'message' => 'ห้องนี้ปิดปรับปรุงชั่วคราว ไม่สามารถจองได้'];
        }
        if ($people > (int)$room['capacity']) {
            return ['success' => false, 'message' => 'จำนวนคน (' . $people . ' คน) เกินความจุของห้อง (' . $room['capacity'] . ' คน)'];
        }

        // Check conflict
        if ($this->isConflict($roomId, $date, $start, $end)) {
            return ['success' => false, 'message' => 'ช่วงเวลาดังกล่าวมีผู้จองแล้ว กรุณาเลือกช่วงเวลาอื่น'];
        }

        try {
            $sql = "INSERT INTO bookings
                    (room_id, user_id, requester_name, requester_email, requester_phone,
                     topic, booking_date, start_time, end_time, people, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $roomId,
                $userId ?: null,
                $name,
                $email,
                $phone,
                $topic,
                $date,
                $start,
                $end,
                $people
            ]);

            return [
                'success' => true,
                'message' => 'ส่งคำขอจองห้องสำเร็จ! เจ้าหน้าที่ฝ่ายวิชาการจะทำการตรวจสอบต่อไป',
                'booking_id' => (int)$this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()];
        }
    }

    public function updateStatus(int $bookingId, string $status, ?string $notes = null, ?string $role = null): bool {
        $allowed = ['pending', 'approved', 'ready', 'completed', 'rejected', 'cancelled'];
        if (!in_array($status, $allowed)) {
            return false;
        }

        if ($role === 'academic') {
            $sql = "UPDATE bookings SET status = ?, academic_notes = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$status, $notes, $bookingId]);
        } elseif ($role === 'building') {
            $sql = "UPDATE bookings SET status = ?, building_notes = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$status, $notes, $bookingId]);
        } else {
            $sql = "UPDATE bookings SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$status, $bookingId]);
        }
    }

    public function countToday(): int {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM bookings
             WHERE booking_date = CURDATE()
             AND status IN ('pending', 'approved', 'ready')"
        );
        return (int)$stmt->fetchColumn();
    }

    public function countBookedRoomsToday(): int {
        $stmt = $this->db->query(
            "SELECT COUNT(DISTINCT room_id) FROM bookings
             WHERE booking_date = CURDATE()
             AND status IN ('pending', 'approved', 'ready')"
        );
        return (int)$stmt->fetchColumn();
    }

    public function getRoomOccupancyToday(int $roomId): array {
        $sql = "SELECT b.start_time, b.end_time, b.topic, b.requester_name, b.status
                FROM bookings b
                WHERE b.room_id = ?
                AND b.booking_date = CURDATE()
                AND b.status IN ('pending', 'approved', 'ready')
                ORDER BY b.start_time ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roomId]);
        $bookings = $stmt->fetchAll();

        $currentTime = date('H:i:s');
        $isOccupiedNow = false;
        $currentBooking = null;

        foreach ($bookings as $b) {
            if ($currentTime >= $b['start_time'] && $currentTime <= $b['end_time']) {
                $isOccupiedNow = true;
                $currentBooking = $b;
                break;
            }
        }

        return [
            'bookings' => $bookings,
            'count' => count($bookings),
            'has_bookings_today' => count($bookings) > 0,
            'is_occupied_now' => $isOccupiedNow,
            'current_booking' => $currentBooking
        ];
    }
}
