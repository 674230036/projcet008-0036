<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Room.php';
require_once __DIR__ . '/../classes/Booking.php';

Auth::start();
$currentUser = Auth::user();

// Check if user is staff or admin
if (!$currentUser || !in_array($currentUser['role'], ['admin', 'academic', 'building'])) {
    if ($currentUser && $currentUser['role'] === 'student') {
        header('Location: ../public/index.php?error=no_permission');
    } else {
        header('Location: ../public/login.php?error=unauthorized');
    }
    exit;
}

$db = (new Database())->connect();
$roomModel = new Room($db);
$bookingModel = new Booking($db);

require_once __DIR__ . '/../classes/Notification.php';

$successMsg = '';
$errorMsg = '';
$notifModel = new Notification($db);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($action === 'academic_approve') {
        if ($bookingModel->updateStatus($bookingId, 'approved', $notes ?: 'ผ่านการตรวจสอบโดยฝ่ายวิชาการ', 'academic')) {
            $successMsg = "อนุมัติคำขอ #$bookingId เรียบร้อยแล้ว (ส่งต่อฝ่ายอาคารฯ เพื่อเตรียมเปิดห้อง)";

            // Send notification to the student
            $bStmt = $db->prepare("SELECT b.*, r.room_code, r.room_name FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?");
            $bStmt->execute([$bookingId]);
            $bInfo = $bStmt->fetch();

            if ($bInfo && !empty($bInfo['user_id'])) {
                $notifModel->create(
                    (int)$bInfo['user_id'],
                    $bookingId,
                    '🎉 คำขอจองห้อง ' . $bInfo['room_code'] . ' ได้รับการอนุมัติแล้ว',
                    'ฝ่ายวิชาการได้อนุมัติคำขอจองห้อง ' . $bInfo['room_code'] . ' (' . $bInfo['topic'] . ') สำหรับวันที่ ' . $bInfo['booking_date'] . ' เรียบร้อยแล้ว ขณะนี้ส่งต่อให้ฝ่ายอาคารฯ จัดเตรียมเปิดห้อง',
                    'approved'
                );
            }

            // Notify building staff
            if ($bInfo) {
                $notifModel->notifyRole(
                    'building',
                    $bookingId,
                    '🏢 มีห้อง ' . $bInfo['room_code'] . ' ผ่านการอนุมัติแล้ว',
                    'คำขอจองห้อง ' . $bInfo['room_code'] . ' (' . $bInfo['topic'] . ') วันที่ ' . $bInfo['booking_date'] . ' ผ่านการอนุมัติแล้ว รอฝ่ายอาคารฯ เตรียมเปิดใช้งาน',
                    'info'
                );
            }
        } else {
            $errorMsg = 'เกิดข้อผิดพลาดในการอนุมัติ';
        }
    } elseif ($action === 'academic_reject') {
        if ($bookingModel->updateStatus($bookingId, 'rejected', $notes ?: 'ไม่ผ่านการอนุมัติ', 'academic')) {
            $successMsg = "ปฏิเสธคำขอ #$bookingId เรียบร้อยแล้ว";

            // Send notification to the student
            $bStmt = $db->prepare("SELECT b.*, r.room_code FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?");
            $bStmt->execute([$bookingId]);
            $bInfo = $bStmt->fetch();

            if ($bInfo && !empty($bInfo['user_id'])) {
                $notifModel->create(
                    (int)$bInfo['user_id'],
                    $bookingId,
                    '❌ คำขอจองห้อง ' . $bInfo['room_code'] . ' ไม่ผ่านการอนุมัติ',
                    'ฝ่ายวิชาการไม่อนุมัติคำขอจองห้อง: ' . ($notes ?: 'เนื่องจากไม่เป็นไปตามระเบียบการใช้ห้อง'),
                    'rejected'
                );
            }
        } else {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึก';
        }
    } elseif ($action === 'building_open') {
        if ($bookingModel->updateStatus($bookingId, 'ready', $notes ?: 'ฝ่ายอาคารฯ ตรวจสอบและเปิดห้องเรียนเรียบร้อย', 'building')) {
            $successMsg = "ยืนยันเปิดใช้งานห้องสำหรับคำขอ #$bookingId เรียบร้อยแล้ว";

            // Send notification to the student
            $bStmt = $db->prepare("SELECT b.*, r.room_code, r.room_name FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?");
            $bStmt->execute([$bookingId]);
            $bInfo = $bStmt->fetch();

            if ($bInfo && !empty($bInfo['user_id'])) {
                $notifModel->create(
                    (int)$bInfo['user_id'],
                    $bookingId,
                    '🔑 ห้อง ' . $bInfo['room_code'] . ' พร้อมใช้งานแล้ว!',
                    'เจ้าหน้าที่ฝ่ายอาคารสถานที่ได้ทำการเปิดห้องเรียน ' . $bInfo['room_code'] . ' และจัดเตรียมอุปกรณ์เรียบร้อยแล้วสำหรับ "' . $bInfo['topic'] . '" ท่านสามารถเข้าใช้งานห้องได้ตามเวลาที่กำหนด',
                    'ready'
                );
            }
        } else {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึก';
        }
    } elseif ($action === 'toggle_room_status') {
        $roomId = (int)$_POST['room_id'];
        $newStatus = $_POST['new_status'] === 'maintenance' ? 'maintenance' : 'available';
        $stmt = $db->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $roomId]);
        $successMsg = 'อัปเดตสถานะห้องเรียบร้อยแล้ว';
    }
}

$rooms = $roomModel->getAll();
$todayBookings = $bookingModel->getToday();
$allBookings = $bookingModel->getAll(100);

// Categorize requests
$pendingAcademic = array_filter($allBookings, fn($b) => $b['status'] === 'pending');
$approvedWaitingBuilding = array_filter($allBookings, fn($b) => $b['status'] === 'approved');
$readyBookings = array_filter($allBookings, fn($b) => $b['status'] === 'ready');

// Fetch registered users
$users = $db->query("SELECT * FROM users ORDER BY role, student_id ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบจัดการสำหรับเจ้าหน้าที่ - อาคาร C มรภ.นครปฐม</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap">
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: 'Sarabun', sans-serif;
    background: #f0f4f2;
    color: #1a2e29;
}
.admin-navbar {
    background: #094d3e;
    color: #fff;
    padding: 14px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.brand-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}
.brand-wrap img {
    height: 48px;
    width: auto;
}
.brand-wrap h1 {
    font-size: 19px;
    margin: 0;
    font-weight: 700;
}
.brand-wrap span {
    font-size: 12px;
    opacity: 0.85;
}
.user-badge-wrap {
    display: flex;
    align-items: center;
    gap: 15px;
}
.user-role-pill {
    background: rgba(255,255,255,0.18);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    border: 1px solid rgba(255,255,255,0.3);
}
.nav-link {
    color: #fff;
    text-decoration: none;
    font-size: 13.5px;
    background: rgba(255,255,255,0.12);
    padding: 6px 14px;
    border-radius: 8px;
    transition: 0.2s;
}
.nav-link:hover { background: rgba(255,255,255,0.25); }
.admin-container {
    max-width: 1400px;
    margin: 25px auto;
    padding: 0 20px;
}
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 25px;
}
.stat-box {
    background: #fff;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid #e1ebe6;
}
.stat-box .num {
    font-size: 28px;
    font-weight: 700;
    color: #094d3e;
    margin: 0;
}
.stat-box .lbl {
    font-size: 13.5px;
    color: #637772;
    margin: 2px 0 0;
}
.tabs-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.tab-btn {
    background: #fff;
    border: 1.5px solid #d2ded9;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #3b504a;
    transition: 0.2s;
}
.tab-btn.active, .tab-btn:hover {
    background: #08755e;
    color: #fff;
    border-color: #08755e;
}
.panel-card {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    border: 1px solid #e2ece7;
    margin-bottom: 25px;
}
.panel-card h2 {
    margin: 0 0 16px;
    font-size: 19px;
    color: #0c4d3e;
    display: flex;
    align-items: center;
    gap: 8px;
}
table.custom-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
table.custom-table th {
    background: #edf5f2;
    padding: 12px 14px;
    text-align: left;
    color: #173830;
    font-weight: 600;
    border-bottom: 2px solid #dae7e2;
}
table.custom-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #eaf0ed;
    color: #263833;
}
table.custom-table tr:hover td {
    background: #f9fbfb;
}
.badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 11.5px;
    font-weight: 600;
    display: inline-block;
}
.b-pending { background: #fef3c7; color: #92400e; }
.b-approved { background: #dbeafe; color: #1e40af; }
.b-ready { background: #dcfce7; color: #166534; }
.b-rejected { background: #fee2e2; color: #991b1b; }
.b-cancelled { background: #f3f4f6; color: #6b7280; }
.action-btn {
    border: none;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}
.btn-approve { background: #10b981; color: #fff; }
.btn-approve:hover { background: #059669; }
.btn-reject { background: #ef4444; color: #fff; }
.btn-reject:hover { background: #dc2626; }
.btn-ready { background: #3b82f6; color: #fff; }
.btn-ready:hover { background: #2563eb; }
.alert-bar {
    padding: 13px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
}
.alert-ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-bad { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.workflow-box {
    background: #f6faf8;
    border: 1px solid #cde3da;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #2c4a41;
}
</style>
</head>
<body>

<header class="admin-navbar">
    <div class="brand-wrap">
        <a href="../public/index.php"><img src="../public/images/npru-logo.png" alt="NPRU"></a>
        <div>
            <h1>ระบบจัดการห้องเรียนและห้องประชุม อาคาร C</h1>
            <span>มหาวิทยาลัยราชภัฏนครปฐม | ฝ่ายวิชาการ & ฝ่ายอาคารสถานที่</span>
        </div>
    </div>
    <div class="user-badge-wrap">
        <span class="user-role-pill">
            👤 <?= htmlspecialchars($currentUser['name']) ?>
            (<b><?= strtoupper($currentUser['role']) ?></b>)
        </span>
        <a href="../public/index.php" class="nav-link">⌂ หน้าหลักผู้ใช้</a>
        <a href="../public/logout.php" class="nav-link" style="background: rgba(239,68,68,0.3);">🚪 ออกจากระบบ</a>
    </div>
</header>

<main class="admin-container">

    <?php if ($successMsg): ?>
        <div class="alert-bar alert-ok">✅ <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert-bar alert-bad">⚠️ <?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="workflow-box">
        <strong>📌 ขั้นตอนการทำงานตามระบบ (Workflow):</strong>
        <span style="margin-left: 8px;">
            1. ผู้ใช้งานส่งคำขอจอง → 
            2. <b>ฝ่ายวิชาการ</b> ตรวจสอบและอนุมัติ → 
            3. <b>ฝ่ายอาคารฯ</b> ตรวจสอบรายการและกดยืนยัน "เปิดใช้งานห้องเรียน" → 
            4. ผู้ใช้งานเข้าใช้งานห้องตามกำหนด
        </span>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div style="font-size: 32px;">📋</div>
            <div>
                <div class="num"><?= count($pendingAcademic) ?></div>
                <div class="lbl">รอฝ่ายวิชาการตรวจสอบ</div>
            </div>
        </div>
        <div class="stat-box">
            <div style="font-size: 32px;">🏢</div>
            <div>
                <div class="num"><?= count($approvedWaitingBuilding) ?></div>
                <div class="lbl">รอฝ่ายอาคารฯ เปิดห้อง</div>
            </div>
        </div>
        <div class="stat-box">
            <div style="font-size: 32px;">📅</div>
            <div>
                <div class="num"><?= count($todayBookings) ?></div>
                <div class="lbl">การจองในวันนี้</div>
            </div>
        </div>
        <div class="stat-box">
            <div style="font-size: 32px;">🚪</div>
            <div>
                <div class="num"><?= count($rooms) ?></div>
                <div class="lbl">ห้องทั้งหมด (C301 - C310)</div>
            </div>
        </div>
    </div>

    <div class="tabs-nav">
        <?php if (in_array($currentUser['role'], ['admin', 'academic'])): ?>
            <button class="tab-btn <?= in_array($currentUser['role'], ['admin', 'academic']) ? 'active' : '' ?>" onclick="showTab('tab-academic', this)">📋 คำขอรอฝ่ายวิชาการตรวจสอบ (<?= count($pendingAcademic) ?>)</button>
        <?php endif; ?>

        <?php if (in_array($currentUser['role'], ['admin', 'building'])): ?>
            <button class="tab-btn <?= $currentUser['role'] === 'building' ? 'active' : '' ?>" onclick="showTab('tab-building', this)">🏢 รายการรอฝ่ายอาคารเปิดห้อง (<?= count($approvedWaitingBuilding) ?>)</button>
        <?php endif; ?>

        <button class="tab-btn" onclick="showTab('tab-all', this)">📜 รายการจองทั้งหมด (<?= count($allBookings) ?>)</button>

        <?php if (in_array($currentUser['role'], ['admin', 'building', 'academic'])): ?>
            <button class="tab-btn" onclick="showTab('tab-rooms', this)">🚪 สถานะห้องเรียน (<?= count($rooms) ?>)</button>
        <?php endif; ?>

        <?php if ($currentUser['role'] === 'admin'): ?>
            <button class="tab-btn" onclick="showTab('tab-users', this)">👥 รายชื่อผู้ใช้งาน & นักศึกษา (<?= count($users) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Tab 1: Academic Approval -->
    <div id="tab-academic" class="tab-content" style="<?= $currentUser['role'] === 'building' ? 'display: none;' : '' ?>">
        <div class="panel-card">
            <h2>📋 คำขอจองห้องรอฝ่ายวิชาการตรวจสอบ (Pending Academic Review)</h2>
            <?php if (empty($pendingAcademic)): ?>
                <p style="text-align: center; color: #738782; padding: 30px;">ไม่มีคำขอที่รอการตรวจสอบในขณะนี้</p>
            <?php else: ?>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ห้อง</th>
                            <th>วันที่ขอใช้</th>
                            <th>ช่วงเวลา</th>
                            <th>หัวข้อ / วัตถุประสงค์</th>
                            <th>ผู้ขอจอง</th>
                            <th>จำนวนคน</th>
                            <th>จัดการคำขอ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingAcademic as $b): ?>
                            <tr>
                                <td>#<?= (int)$b['id'] ?></td>
                                <td><b style="color: #08755e;"><?= htmlspecialchars($b['room_code']) ?></b> (<?= htmlspecialchars($b['room_name']) ?>)</td>
                                <td><?= htmlspecialchars($b['booking_date']) ?></td>
                                <td><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?> น.</td>
                                <td><?= htmlspecialchars($b['topic']) ?></td>
                                <td>
                                    <?= htmlspecialchars($b['requester_name']) ?><br>
                                    <small style="color: #6d807b;"><?= htmlspecialchars($b['requester_email']) ?></small>
                                </td>
                                <td><?= (int)$b['people'] ?> คน</td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="academic_approve">
                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="action-btn btn-approve" onclick="return confirm('ยืนยันอนุมัติคำขอจองห้องนี้หรือไม่?')">✓ อนุมัติ</button>
                                        </form>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="academic_reject">
                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="action-btn btn-reject" onclick="return confirm('ยืนยันปฏิเสธคำขอนี้หรือไม่?')">✕ ไม่อนุมัติ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 2: Building Staff Open Room -->
    <div id="tab-building" class="tab-content" style="<?= $currentUser['role'] === 'building' ? 'display: block;' : 'display: none;' ?>">
        <div class="panel-card">
            <h2>🏢 คำขอที่อนุมัติแล้ว - ฝ่ายอาคารฯ ตรวจสอบเพื่อเปิดใช้งานห้องเรียน</h2>
            <?php if (empty($approvedWaitingBuilding)): ?>
                <p style="text-align: center; color: #738782; padding: 30px;">ไม่มีรายการที่รอฝ่ายอาคารเปิดใช้งานห้อง</p>
            <?php else: ?>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ห้อง</th>
                            <th>วันที่ใช้งาน</th>
                            <th>เวลา</th>
                            <th>หัวข้อ</th>
                            <th>ผู้ขอจอง</th>
                            <th>สถานะฝ่ายวิชาการ</th>
                            <th>การเปิดห้อง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedWaitingBuilding as $b): ?>
                            <tr>
                                <td>#<?= (int)$b['id'] ?></td>
                                <td><b style="color: #08755e;"><?= htmlspecialchars($b['room_code']) ?></b></td>
                                <td><?= htmlspecialchars($b['booking_date']) ?></td>
                                <td><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?> น.</td>
                                <td><?= htmlspecialchars($b['topic']) ?></td>
                                <td><?= htmlspecialchars($b['requester_name']) ?></td>
                                <td><span class="badge b-approved">✓ อนุมัติแล้ว</span></td>
                                <td>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="building_open">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="action-btn btn-ready" onclick="return confirm('ยืนยันว่าได้เตรียมห้องและพร้อมเปิดใช้งาน?')">🔑 เปิดใช้งานห้องเรียน</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 3: All Bookings -->
    <div id="tab-all" class="tab-content" style="display: none;">
        <div class="panel-card">
            <h2>📜 ประวัติและคำขอจองทั้งหมด (All Bookings)</h2>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ห้อง</th>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>หัวข้อ</th>
                        <th>ผู้จอง</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allBookings as $b): ?>
                        <tr>
                            <td>#<?= (int)$b['id'] ?></td>
                            <td><b><?= htmlspecialchars($b['room_code']) ?></b></td>
                            <td><?= htmlspecialchars($b['booking_date']) ?></td>
                            <td><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?> น.</td>
                            <td><?= htmlspecialchars($b['topic']) ?></td>
                            <td><?= htmlspecialchars($b['requester_name']) ?></td>
                            <td>
                                <?php if ($b['status'] === 'pending'): ?>
                                    <span class="badge b-pending">รอวิชาการตรวจ</span>
                                <?php elseif ($b['status'] === 'approved'): ?>
                                    <span class="badge b-approved">อนุมัติ (รอเปิดห้อง)</span>
                                <?php elseif ($b['status'] === 'ready'): ?>
                                    <span class="badge b-ready">เปิดใช้งานแล้ว</span>
                                <?php elseif ($b['status'] === 'rejected'): ?>
                                    <span class="badge b-rejected">ไม่อนุมัติ</span>
                                <?php else: ?>
                                    <span class="badge b-cancelled">ยกเลิกแล้ว</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 4: Rooms Management -->
    <div id="tab-rooms" class="tab-content" style="display: none;">
        <div class="panel-card">
            <h2>🚪 รายการและสถานะห้องเรียน อาคาร C (C301 - C310)</h2>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>รหัสห้อง</th>
                        <th>ชื่อห้อง</th>
                        <th>ประเภท</th>
                        <th>ความจุ</th>
                        <th>ชั้น / สถานที่</th>
                        <th>สิ่งอำนวยความสะดวก</th>
                        <th>สถานะ</th>
                        <th>เปลี่ยนสถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $r): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($r['room_code']) ?></b></td>
                            <td><?= htmlspecialchars($r['room_name']) ?></td>
                            <td><?= htmlspecialchars($r['room_type']) ?></td>
                            <td><?= (int)$r['capacity'] ?> คน</td>
                            <td><?= htmlspecialchars($r['floor'] ?? 'ชั้น 3') ?></td>
                            <td><small style="color: #657570;"><?= htmlspecialchars($r['facilities'] ?? '') ?></small></td>
                            <td>
                                <?php if ($r['status'] === 'available'): ?>
                                    <span class="badge b-ready">พร้อมใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge b-rejected">ปิดปรับปรุง</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="toggle_room_status">
                                    <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $r['status'] === 'available' ? 'maintenance' : 'available' ?>">
                                    <button type="submit" class="action-btn" style="background: #e2e8f0; color: #334155;">
                                        <?= $r['status'] === 'available' ? 'ตั้งเป็น ปิดปรับปรุง' : 'ตั้งเป็น พร้อมใช้งาน' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 5: Users list -->
    <div id="tab-users" class="tab-content" style="display: none;">
        <div class="panel-card">
            <h2>👥 รายชื่อผู้ใช้งานในระบบ (นักศึกษา และ เจ้าหน้าที่)</h2>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ - นามสกุล</th>
                        <th>อีเมลเว็บเมล์</th>
                        <th>เบอร์โทรศัพท์</th>
                        <th>สิทธิ์ใช้งาน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $idx => $u): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><b><?= htmlspecialchars($u['student_id'] ?? '-') ?></b></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge" style="background:#e0e7ff; color:#3730a3;">ผู้ดูแลระบบ (Admin)</span>
                                <?php elseif ($u['role'] === 'academic'): ?>
                                    <span class="badge" style="background:#ecfdf5; color:#065f46;">ฝ่ายวิชาการ</span>
                                <?php elseif ($u['role'] === 'building'): ?>
                                    <span class="badge" style="background:#eff6ff; color:#1d4ed8;">ฝ่ายอาคารสถานที่</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569;">นักศึกษา</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    btn.classList.add('active');
}
</script>

</body>
</html>
