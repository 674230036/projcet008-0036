<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Room.php';
require_once __DIR__ . '/../../classes/Booking.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
    $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

    if ($roomId <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสห้องไม่ถูกต้อง']);
        exit;
    }

    $db = (new Database())->connect();
    $roomModel = new Room($db);
    $bookingModel = new Booking($db);

    $room = $roomModel->find($roomId);
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบห้องที่ระบุ']);
        exit;
    }

    $bookings = $bookingModel->getByRoomAndDate($roomId, $date);

    // Build timeline slots from 08:00 to 18:00
    $slots = [];
    $startHour = 8;
    $endHour = 18;

    for ($h = $startHour; $h < $endHour; $h++) {
        $slotStart = sprintf('%02d:00:00', $h);
        $slotEnd = sprintf('%02d:00:00', $h + 1);

        $isBooked = false;
        $bookingInfo = null;

        foreach ($bookings as $b) {
            if ($b['start_time'] < $slotEnd && $b['end_time'] > $slotStart) {
                $isBooked = true;
                $bookingInfo = [
                    'id' => $b['id'],
                    'topic' => $b['topic'],
                    'requester_name' => $b['requester_name'],
                    'start_time' => substr($b['start_time'], 0, 5),
                    'end_time' => substr($b['end_time'], 0, 5),
                    'status' => $b['status']
                ];
                break;
            }
        }

        $slots[] = [
            'time_label' => sprintf('%02d:00 - %02d:00', $h, $h + 1),
            'start' => sprintf('%02d:00', $h),
            'end' => sprintf('%02d:00', $h + 1),
            'is_booked' => $isBooked,
            'booking' => $bookingInfo
        ];
    }

    echo json_encode([
        'success' => true,
        'room' => $room,
        'date' => $date,
        'bookings' => array_map(function($b) {
            return [
                'id' => $b['id'],
                'topic' => $b['topic'],
                'requester_name' => $b['requester_name'],
                'start_time' => substr($b['start_time'], 0, 5),
                'end_time' => substr($b['end_time'], 0, 5),
                'status' => $b['status']
            ];
        }, $bookings),
        'timeline' => $slots
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
