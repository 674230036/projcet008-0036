<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Booking.php';

Auth::start();
$currentUser = Auth::user();

$db = (new Database())->connect();
$bookingModel = new Booking($db);
$allBookings = $bookingModel->getAll(150);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ประวัติการจอง - ระบบจองห้อง อาคาร C มหาวิทยาลัยราชภัฏนครปฐม</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="css/style.css">
<style>
.container {
    max-width: 1240px;
    margin: 30px auto;
    padding: 0 20px;
    font-family: 'Sarabun', sans-serif;
}
.header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 18px 25px;
    border-radius: 18px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}
.header-bar h1 {
    font-size: 22px;
    margin: 0;
    color: #0c4d3e;
}
.history-table {
    width: 100%;
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    border-collapse: collapse;
}
.history-table th {
    background: #08755e;
    color: #fff;
    padding: 14px 18px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
}
.history-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #edf2f0;
    font-size: 13.5px;
    color: #2c3e39;
}
.history-table tr:hover td {
    background: #f8faf9;
}
.badge-status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #dbeafe; color: #1e40af; }
.status-ready { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.status-cancelled { background: #f3f4f6; color: #6b7280; }
.back-link {
    color: #08755e;
    text-decoration: none;
    font-weight: 600;
}
</style>
</head>
<body style="background: #f4f7f6; margin: 0;">

<div class="container">
    <div class="header-bar">
        <div>
            <h1>⏱ ประวัติการใช้งานและการจองห้อง อาคาร C</h1>
            <small style="color: #657570;">แสดงประวัติการทำรายการจองทั้งหมดในระบบ</small>
        </div>
        <div>
            <a href="index.php" class="back-link">⌂ กลับหน้าหลัก</a>
        </div>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th>ห้อง</th>
                <th>วันที่</th>
                <th>เวลา</th>
                <th>หัวข้อ / วัตถุประสงค์</th>
                <th>ผู้ขอใช้</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allBookings)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #72847f;">ไม่มีข้อมูลประวัติการจอง</td>
                </tr>
            <?php else: ?>
                <?php foreach ($allBookings as $b): ?>
                    <tr>
                        <td>
                            <strong style="color: #08755e;"><?= htmlspecialchars($b['room_code']) ?></strong>
                            <div style="font-size: 11.5px; color: #72847f;"><?= htmlspecialchars($b['room_name']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($b['booking_date']) ?></td>
                        <td><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?> น.</td>
                        <td>
                            <strong><?= htmlspecialchars($b['topic']) ?></strong>
                            <div style="font-size: 11.5px; color: #72847f;">จำนวน <?= (int)$b['people'] ?> คน</div>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['requester_name']) ?>
                            <div style="font-size: 11.5px; color: #72847f;"><?= htmlspecialchars($b['requester_email']) ?></div>
                        </td>
                        <td>
                            <?php if ($b['status'] === 'pending'): ?>
                                <span class="badge-status status-pending">รอตรวจสอบ</span>
                            <?php elseif ($b['status'] === 'approved'): ?>
                                <span class="badge-status status-approved">อนุมัติแล้ว</span>
                            <?php elseif ($b['status'] === 'ready'): ?>
                                <span class="badge-status status-ready">เปิดใช้งานแล้ว</span>
                            <?php elseif ($b['status'] === 'rejected'): ?>
                                <span class="badge-status status-rejected">ไม่อนุมัติ</span>
                            <?php else: ?>
                                <span class="badge-status status-cancelled">ยกเลิกแล้ว</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
