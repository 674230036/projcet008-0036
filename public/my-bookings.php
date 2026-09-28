<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Booking.php';

Auth::start();
$currentUser = Auth::user();

if (!$currentUser) {
    header('Location: login.php?redirect=my-bookings.php');
    exit;
}

$db = (new Database())->connect();
$bookingModel = new Booking($db);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $cancelId = (int)$_POST['booking_id'];
    $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND (user_id = ? OR requester_email = ?) AND status = 'pending'");
    $stmt->execute([$cancelId, $currentUser['id'], $currentUser['email']]);
    if ($stmt->rowCount() > 0) {
        $msg = 'ยกเลิกคำขอจองห้องเรียบร้อยแล้ว';
    } else {
        $err = 'ไม่สามารถยกเลิกได้ หรือรายการได้รับการอนุมัติแล้ว';
    }
}

$bookings = $bookingModel->getUserBookings($currentUser['id'], $currentUser['email']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>คำขอจองของฉัน - ระบบจองห้อง อาคาร C มหาวิทยาลัยราชภัฏนครปฐม</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="css/style.css">
<style>
.container {
    max-width: 1200px;
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
.booking-card-item {
    background: #ffffff;
    border-radius: 16px;
    padding: 22px;
    margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    border: 1px solid #e7efe9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.room-badge {
    background: #e2f2eb;
    color: #086b55;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 16px;
}
.badge-status {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #dbeafe; color: #1e40af; }
.status-ready { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.status-cancelled { background: #f3f4f6; color: #6b7280; }
.cancel-btn {
    background: #fee2e2;
    color: #b91c1c;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.cancel-btn:hover { background: #fecaca; }
.back-link {
    color: #08755e;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
</style>
</head>
<body style="background: #f4f7f6; margin: 0;">

<div class="container">
    <div class="header-bar">
        <div>
            <h1>📋 คำขอจองห้องของฉัน</h1>
            <small style="color: #657570;">ผู้ใช้งาน: <?= htmlspecialchars($currentUser['name']) ?> (<?= htmlspecialchars($currentUser['student_id'] ?? $currentUser['email']) ?>)</small>
        </div>
        <div>
            <a href="index.php" class="back-link">⌂ กลับหน้าหลัก</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 12px 18px; border-radius: 10px; margin-bottom: 18px;">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 12px 18px; border-radius: 10px; margin-bottom: 18px;">⚠️ <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div style="background: #fff; padding: 50px; text-align: center; border-radius: 18px; color: #72847f;">
            <p style="font-size: 18px;">ท่านยังไม่มีประวัติการส่งคำขอจองห้องเรียน</p>
            <a href="index.php#rooms" style="background: #08755e; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 10px; display: inline-block; margin-top: 10px;">เลือกห้องและทำการจอง</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $b): ?>
            <div class="booking-card-item">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span class="room-badge"><?= htmlspecialchars($b['room_code']) ?></span>
                        <strong style="font-size: 16px;"><?= htmlspecialchars($b['room_name']) ?></strong>
                        <span style="color: #72847f; font-size: 13px;">(<?= htmlspecialchars($b['building']) ?>)</span>
                    </div>
                    <div style="font-size: 15px; color: #1d332d; font-weight: 600; margin-bottom: 6px;">
                        หัวข้อ: <?= htmlspecialchars($b['topic']) ?>
                    </div>
                    <div style="font-size: 13.5px; color: #586a65;">
                        📅 วันที่: <b><?= htmlspecialchars($b['booking_date']) ?></b> |
                        ⏱ เวลา: <b><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?> น.</b> |
                        👥 ผู้เข้าร่วม: <b><?= (int)$b['people'] ?> คน</b>
                    </div>
                    <?php if (!empty($b['academic_notes'])): ?>
                        <div style="font-size: 12.5px; color: #0284c7; margin-top: 6px;">
                            💬 ฝ่ายวิชาการ: <?= htmlspecialchars($b['academic_notes']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($b['building_notes'])): ?>
                        <div style="font-size: 12.5px; color: #16a34a; margin-top: 4px;">
                            🏢 ฝ่ายอาคารฯ: <?= htmlspecialchars($b['building_notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
                    <?php if ($b['status'] === 'pending'): ?>
                        <span class="badge-status status-pending">⏳ รอฝ่ายวิชาการตรวจสอบ</span>
                        <form method="POST" onsubmit="return confirm('ยืนยันที่จะยกเลิกคำขอนี้หรือไม่?')">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="cancel-btn">ยกเลิกคำขอ</button>
                        </form>
                    <?php elseif ($b['status'] === 'approved'): ?>
                        <span class="badge-status status-approved">✓ ผ่านอนุมัติ (รอฝ่ายอาคารฯ เปิดห้อง)</span>
                    <?php elseif ($b['status'] === 'ready'): ?>
                        <span class="badge-status status-ready">🎉 ห้องพร้อมใช้งานแล้ว</span>
                    <?php elseif ($b['status'] === 'rejected'): ?>
                        <span class="badge-status status-rejected">✕ ไม่อนุมัติ</span>
                    <?php elseif ($b['status'] === 'cancelled'): ?>
                        <span class="badge-status status-cancelled">ยกเลิกแล้ว</span>
                    <?php endif; ?>
                    <small style="color: #92a09c; font-size: 11.5px;">ทำรายการเมื่อ <?= htmlspecialchars($b['created_at']) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
