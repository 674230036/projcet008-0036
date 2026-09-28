<?php
class Room {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM rooms ORDER BY room_code ASC");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        $room = $stmt->fetch();
        return $room ?: null;
    }

    public function findByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE room_code = ?");
        $stmt->execute([$code]);
        $room = $stmt->fetch();
        return $room ?: null;
    }

    public function getRoomsWithAvailability(Booking $bookingModel): array {
        $rooms = $this->getAll();
        foreach ($rooms as &$r) {
            $occupancy = $bookingModel->getRoomOccupancyToday((int)$r['id']);
            $r['occupancy'] = $occupancy;
            $r['is_booked_today'] = $occupancy['has_bookings_today'];
            $r['is_occupied_now'] = $occupancy['is_occupied_now'];
            $r['today_bookings_count'] = $occupancy['count'];
        }
        return $rooms;
    }
}
