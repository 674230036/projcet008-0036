<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Room.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Notification.php';

Auth::start();
$currentUser = Auth::user();

$db = (new Database())->connect();
$roomModel = new Room($db);
$bookingModel = new Booking($db);
$notifModel = new Notification($db);

$message = '';
$error = '';

$userBookingsCount = 0;
$userUnreadNotifs = 0;
if ($currentUser) {
    $userBookingsCount = $bookingModel->countUserBookings((int)$currentUser['id'], $currentUser['email']);
    $userUnreadNotifs = $notifModel->countUnread((int)$currentUser['id']);
}

if (isset($_GET['error']) && $_GET['error'] === 'no_permission') {
    $error = 'บัญชีนักศึกษาไม่มีสิทธิ์เข้าถึงส่วนงานของผู้ดูแลระบบหรือเจ้าหน้าที่';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $date = $_POST['date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $people = (int)($_POST['people'] ?? 1);
    $userId = $currentUser ? (int)$currentUser['id'] : null;

    $result = $bookingModel->create($roomId, $userId, $name, $email, $phone, $topic, $date, $startTime, $endTime, $people);
    if ($result['success']) {
        $message = $result['message'];
        $newBookingId = (int)($result['booking_id'] ?? 0);
        if ($userId && $newBookingId) {
            $notifModel->create(
                $userId,
                $newBookingId,
                '📝 ส่งคำขอจองห้องสำเร็จ',
                'ระบบได้รับคำขอจองห้องของคุณแล้ว ขณะนี้อยู่ระหว่างรอฝ่ายวิชาการตรวจสอบ',
                'info'
            );
        }
        $notifModel->notifyRole(
            'academic',
            $newBookingId,
            '📋 มีคำขอจองห้องใหม่รอการตรวจสอบ',
            'มีคำขอจองห้องใหม่จาก ' . $name . ' (' . $topic . ') วันที่ ' . $date . ' รอการตรวจสอบ',
            'new_booking'
        );
        // Refresh counts
        if ($currentUser) {
            $userBookingsCount = $bookingModel->countUserBookings((int)$currentUser['id'], $currentUser['email']);
            $userUnreadNotifs = $notifModel->countUnread((int)$currentUser['id']);
        }
    } else {
        $error = $result['message'];
    }
}

// Fetch all rooms and availability
$rooms = $roomModel->getRoomsWithAvailability($bookingModel);
$todayBookings = $bookingModel->getToday();

$totalRooms = count($rooms);
$bookingsToday = $bookingModel->countToday();
$bookedRooms = $bookingModel->countBookedRoomsToday();
$availableRooms = max(0, $totalRooms - $bookedRooms);

// Thai month names
$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$currentMonth = (int)date('n');
$currentYearBE = (int)date('Y') + 543;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบจองห้อง อาคาร C - มหาวิทยาลัยราชภัฏนครปฐม</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Top Navigation Bar -->
<header class="topbar">
    <div class="topbar-left">
        <a href="index.php" class="brand">
            <img src="images/npru-logo.png" alt="มหาวิทยาลัยราชภัฏนครปฐม">
            <div class="brand-text">
                <h1>มหาวิทยาลัยราชภัฏนครปฐม</h1>
                <span>NAKHON PATHOM RAJABHAT UNIVERSITY</span>
            </div>
        </a>

        <div class="top-title">
            <strong>ระบบจองห้อง อาคาร C</strong>
            <small>ROOM BOOKING SYSTEM - BUILDING C</small>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Notification Bell -->
        <div class="notif-wrapper" title="การแจ้งเตือน">
            <button class="notif-btn" onclick="toggleNotifModal()">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="notif-badge" id="topNotifBadge" style="<?= ($currentUser && $userUnreadNotifs > 0) || (!$currentUser && $bookingsToday > 0) ? '' : 'display:none;' ?>"><?= $currentUser ? $userUnreadNotifs : ($bookingsToday ?: 3) ?></span>
            </button>
        </div>

        <!-- Auth Button / User Dropdown -->
        <?php if ($currentUser): ?>
            <div class="user-menu-wrapper">
                <button class="user-pill-btn" onclick="toggleUserDropdown()">
                    <span class="user-avatar-icon">👤</span>
                    <span class="user-pill-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                    <span class="caret">▾</span>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                        <small><?= htmlspecialchars($currentUser['email']) ?></small>
                        <span class="role-badge"><?= strtoupper($currentUser['role']) ?></span>
                    </div>
                    <a href="my-bookings.php">📋 คำขอจองของฉัน</a>
                    <a href="history.php">⏱ ประวัติการจอง</a>
                    <?php if (in_array($currentUser['role'], ['admin', 'academic', 'building'])): ?>
                        <a href="../admin/index.php" style="color: #08755e; font-weight: 600;">⚙ ระบบสำหรับเจ้าหน้าที่</a>
                    <?php endif; ?>
                    <hr>
                    <a href="logout.php" class="logout-link">🚪 ออกจากระบบ</a>
                </div>
            </div>
        <?php else: ?>
            <a class="login-btn" href="login.php">
                <span style="font-size: 16px;">👤</span> เข้าสู่ระบบ
            </a>
        <?php endif; ?>
    </div>
</header>

<!-- Main Wrapper with Sidebar and Content -->
<div class="main-wrapper">
    <!-- Left Sidebar -->
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a class="active" href="index.php">
                <span class="icon">⌂</span>
                <span>หน้าหลัก</span>
            </a>
            <a href="#calendar">
                <span class="icon">📅</span>
                <span>ปฏิทินการจอง</span>
            </a>
            <a href="#rooms">
                <span class="icon">🚪</span>
                <span>รายการห้อง (C301-C310)</span>
            </a>

            <?php if ($currentUser && $currentUser['role'] === 'student'): ?>
                <!-- Student Navigation -->
                <a href="my-bookings.php" class="student-nav-link">
                    <span class="icon">📋</span>
                    <span>คำขอจองของฉัน</span>
                    <?php if ($userBookingsCount > 0): ?>
                        <span class="sidebar-count-badge"><?= $userBookingsCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="history.php">
                    <span class="icon">⏱</span>
                    <span>ประวัติการจอง</span>
                </a>
            <?php elseif ($currentUser && $currentUser['role'] === 'academic'): ?>
                <!-- Academic Staff Navigation -->
                <a href="history.php">
                    <span class="icon">⏱</span>
                    <span>ประวัติการจอง</span>
                </a>
                <hr class="nav-divider">
                <a href="../admin/index.php" style="background:#e0f2fe; color:#0369a1; font-weight:700;">
                    <span class="icon">📋</span>
                    <span>ระบบฝ่ายวิชาการ ↗</span>
                </a>
            <?php elseif ($currentUser && $currentUser['role'] === 'building'): ?>
                <!-- Building Staff Navigation -->
                <a href="history.php">
                    <span class="icon">⏱</span>
                    <span>ประวัติการจอง</span>
                </a>
                <hr class="nav-divider">
                <a href="../admin/index.php" style="background:#ecfdf5; color:#047857; font-weight:700;">
                    <span class="icon">🏢</span>
                    <span>ระบบฝ่ายอาคารฯ ↗</span>
                </a>
            <?php elseif ($currentUser && $currentUser['role'] === 'admin'): ?>
                <!-- Admin Navigation -->
                <a href="my-bookings.php">
                    <span class="icon">📋</span>
                    <span>คำขอจองห้อง</span>
                </a>
                <a href="history.php">
                    <span class="icon">⏱</span>
                    <span>ประวัติการจอง</span>
                </a>
                <hr class="nav-divider">
                <a href="../admin/index.php" style="background:#ede9fe; color:#6d28d9; font-weight:700;">
                    <span class="icon">⚙</span>
                    <span>สำหรับผู้ดูแลระบบ ↗</span>
                </a>
            <?php else: ?>
                <!-- Guest Navigation -->
                <a href="login.php">
                    <span class="icon">📋</span>
                    <span>คำขอจองของฉัน</span>
                </a>
                <a href="history.php">
                    <span class="icon">⏱</span>
                    <span>ประวัติการจอง</span>
                </a>
                <hr class="nav-divider">
                <a href="login.php">
                    <span class="icon">⚙</span>
                    <span>สำหรับผู้ดูแล / เจ้าหน้าที่</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer with NPRU Emblem and Building C Photo -->
        <div class="sidebar-footer">
            <div class="footer-bg-wrap">
                <img src="images/building_c.jpg" alt="Building C" class="footer-building-img">
            </div>
            <div class="footer-text">
                <h4 class="footer-npru-title">"NPRU"</h4>
                <p class="footer-npru-sub">Smart University<br>for Local Development</p>
                <div class="footer-branding">
                    <img src="images/npru-logo.png" alt="NPRU">
                    <span>มหาวิทยาลัยราชภัฏนครปฐม</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Content Area -->
    <main class="content-area">

        <!-- Role Specific Status Card -->
        <?php if ($currentUser): ?>
            <div class="user-zone-card user-zone-<?= htmlspecialchars($currentUser['role']) ?>">
                <div class="zone-left">
                    <?php if ($currentUser['role'] === 'student'): ?>
                        <div class="zone-avatar">👨‍🎓</div>
                        <div>
                            <h4>โซนนักศึกษา (Student Portal)</h4>
                            <p>ยินดีต้อนรับ: <b><?= htmlspecialchars($currentUser['name']) ?></b> | รหัสนักศึกษา: <b><?= htmlspecialchars($currentUser['student_id'] ?? '-') ?></b> | อีเมล: <?= htmlspecialchars($currentUser['email']) ?></p>
                        </div>
                    <?php elseif ($currentUser['role'] === 'academic'): ?>
                        <div class="zone-avatar">📋</div>
                        <div>
                            <h4>โซนเจ้าหน้าที่ฝ่ายวิชาการ (Academic Staff Portal)</h4>
                            <p>ผู้ใช้งาน: <b><?= htmlspecialchars($currentUser['name']) ?></b> | สิทธิ์: ตรวจสอบและอนุมัติคำขอใช้งานห้องเรียน</p>
                        </div>
                    <?php elseif ($currentUser['role'] === 'building'): ?>
                        <div class="zone-avatar">🏢</div>
                        <div>
                            <h4>โซนเจ้าหน้าที่ฝ่ายอาคารสถานที่ (Building Staff Portal)</h4>
                            <p>ผู้ใช้งาน: <b><?= htmlspecialchars($currentUser['name']) ?></b> | สิทธิ์: ตรวจสอบรายการและเปิดใช้งานห้องเรียน อาคาร C</p>
                        </div>
                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                        <div class="zone-avatar">⚙️</div>
                        <div>
                            <h4>โซนผู้ดูแลระบบสูงสุด (Administrator Portal)</h4>
                            <p>ผู้ใช้งาน: <b><?= htmlspecialchars($currentUser['name']) ?></b> | สิทธิ์: ควบคุมและบริหารจัดการระบบทุกส่วน</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="zone-right">
                    <?php if ($currentUser['role'] === 'student'): ?>
                        <a href="my-bookings.php" class="btn-zone-action">
                            📋 คำขอของฉัน (<?= $userBookingsCount ?>)
                        </a>
                    <?php else: ?>
                        <a href="../admin/index.php" class="btn-zone-action">
                            🚀 ไปยังระบบจัดการเจ้าหน้าที่ →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Banner / Hero Card (Matches Image 3) -->
        <section class="hero-card">
            <div class="hero-left">
                <img src="images/npru-logo.png" alt="NPRU" class="hero-emblem">
                <div class="hero-info">
                    <h2>ระบบจองห้อง อาคาร C</h2>
                    <h3>มหาวิทยาลัยราชภัฏนครปฐม</h3>
                    <div class="hero-gold-line"></div>
                    <p class="hero-sub">— สำหรับจองห้องเรียนและห้องประชุม อาคาร C (C301-C310)</p>
                </div>
            </div>
            <div class="hero-right">
                <div class="building-badge-overlay">
                    <span class="b-letter">C</span>
                    <div class="b-text">
                        <strong>อาคาร C</strong>
                        <small>Building C</small>
                    </div>
                </div>
                <img src="images/building_c.jpg" alt="อาคาร C มหาวิทยาลัยราชภัฏนครปฐม" class="hero-building-photo">
            </div>
        </section>

        <!-- Notification Alerts -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stat Summary Cards (4 Cards in a row) -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                        <circle cx="14" cy="12" r="1"></circle>
                    </svg>
                </div>
                <div class="stat-data">
                    <span class="stat-number"><?= $totalRooms ?></span>
                    <span class="stat-label">ห้องทั้งหมด</span>
                    <span class="stat-sub">C301 - C310</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <div class="stat-data">
                    <span class="stat-number"><?= $bookingsToday ?></span>
                    <span class="stat-label">การจองวันนี้</span>
                    <span class="stat-sub">ทั้งหมด</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div class="stat-data">
                    <span class="stat-number"><?= $availableRooms ?></span>
                    <span class="stat-label">ห้องว่างในวันนี้</span>
                    <span class="stat-sub">ห้อง</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="stat-data">
                    <span class="stat-number"><?= $bookedRooms ?></span>
                    <span class="stat-label">ห้องไม่ว่างในวันนี้</span>
                    <span class="stat-sub">ห้อง</span>
                </div>
            </div>
        </section>

        <!-- Two Columns Layout: Rooms Area + Right Widgets -->
        <div class="dashboard-columns">
            <!-- Left Column: Rooms Area -->
            <div class="rooms-container" id="rooms">
                <div class="rooms-header-bar">
                    <div class="header-left">
                        <span class="sec-icon">🚪</span>
                        <h2>รายการห้อง อาคาร C</h2>
                    </div>
                    <div class="header-filters">
                        <div class="search-wrap">
                            <span class="search-icon">🔍</span>
                            <input type="search" id="roomSearch" placeholder="ค้นหาห้อง...">
                        </div>
                        <select id="buildingFilter" class="filter-select">
                            <option value="อาคาร C">🏢 อาคาร C</option>
                        </select>
                        <select id="typeFilter" class="filter-select">
                            <option value="all">☰ ทุกประเภท</option>
                            <option value="ห้องประชุม">ห้องประชุม</option>
                            <option value="ห้องเรียน">ห้องเรียน</option>
                        </select>
                        <a href="#rooms" class="see-all-link">ดูทั้งหมด →</a>
                    </div>
                </div>

                <!-- Room Cards Grid -->
                <div class="room-cards-grid">
                    <?php foreach ($rooms as $room): ?>
                        <article class="room-card <?= $room['room_code'] === 'C301' ? 'is-c301' : '' ?>" 
                                 data-room-code="<?= htmlspecialchars($room['room_code']) ?>"
                                 data-room-type="<?= htmlspecialchars($room['room_type']) ?>"
                                 data-room-id="<?= (int)$room['id'] ?>">
                            <div class="card-image-wrap">
                                <img src="images/<?= htmlspecialchars($room['room_code']) ?>.jpg" 
                                     alt="<?= htmlspecialchars($room['room_name']) ?>"
                                     onerror="this.src='images/room-placeholder.svg'">
                                <?php if ($room['room_code'] === 'C301'): ?>
                                    <span class="badge-auditorium">ห้องประชุมใหญ่</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-content">
                                <h3 class="room-code"><?= htmlspecialchars($room['room_code']) ?></h3>
                                <p class="room-title"><?= htmlspecialchars($room['room_name']) ?></p>

                                <div class="room-tags-row">
                                    <span class="cap-tag">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                        <?= (int)$room['capacity'] ?> คน
                                    </span>
                                    <span class="type-tag"><?= htmlspecialchars($room['room_type']) ?></span>
                                </div>

                                <!-- Availability indicator -->
                                <div class="room-status-indicator">
                                    <?php if ($room['is_occupied_now']): ?>
                                        <span class="status-occupied">
                                            <span class="dot-indicator dot-red"></span> กำลังใช้งาน
                                        </span>
                                    <?php elseif ($room['is_booked_today']): ?>
                                        <span class="status-partial">
                                            <span class="dot-indicator dot-orange"></span> มีการจองวันนี้ (<?= $room['today_bookings_count'] ?> รายการ)
                                        </span>
                                    <?php else: ?>
                                        <span class="status-free">
                                            <span class="dot-indicator dot-green"></span> ว่างตลอดทั้งวัน
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <button class="btn-book-room" onclick="openRoomSchedule(<?= (int)$room['id'] ?>, '<?= htmlspecialchars($room['room_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($room['room_name'], ENT_QUOTES) ?>', <?= (int)$room['capacity'] ?>)">
                                    📅 ดูตาราง / จองห้อง
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column: Calendar Widget + Today's Activities -->
            <aside class="right-widgets">
                <!-- Calendar Widget -->
                <div class="widget-card" id="calendar">
                    <div class="widget-header">
                        <div class="w-title">
                            <span class="w-icon">📅</span>
                            <h3>ปฏิทินการจอง</h3>
                        </div>
                        <a href="javascript:void(0)" onclick="resetCalendarToToday()" class="w-link">ดูปฏิทินเต็ม ↗</a>
                    </div>

                    <div class="calendar-month-controls">
                        <button class="cal-nav-btn" onclick="prevMonth()">‹</button>
                        <strong id="calMonthLabel"><?= $thaiMonths[$currentMonth] ?> <?= $currentYearBE ?></strong>
                        <button class="cal-nav-btn" onclick="nextMonth()">›</button>
                    </div>

                    <div class="calendar-grid">
                        <div class="day-head">อา</div>
                        <div class="day-head">จ</div>
                        <div class="day-head">อ</div>
                        <div class="day-head">พ</div>
                        <div class="day-head">พฤ</div>
                        <div class="day-head">ศ</div>
                        <div class="day-head">ส</div>
                        <div id="calendarDays" class="cal-days-container"></div>
                    </div>

                    <div class="calendar-legend">
                        <span class="legend-item"><i class="leg-dot dot-green"></i> ห้องว่าง</span>
                        <span class="legend-item"><i class="leg-dot dot-red"></i> ห้องไม่ว่าง</span>
                        <span class="legend-item"><i class="leg-dot dot-gray"></i> ยังไม่มีการจอง</span>
                    </div>
                </div>

                <!-- Today's Activities Widget -->
                <div class="widget-card" id="today">
                    <div class="widget-header">
                        <div class="w-title">
                            <span class="w-icon">📋</span>
                            <h3>กิจกรรมวันนี้</h3>
                        </div>
                        <a href="history.php" class="w-link">ดูทั้งหมด</a>
                    </div>

                    <div class="activities-list">
                        <?php if (empty($todayBookings)): ?>
                            <div class="no-activity">วันนี้ยังไม่มีกิจกรรมที่ได้รับการอนุมัติ</div>
                        <?php else: ?>
                            <?php foreach ($todayBookings as $b): ?>
                                <div class="activity-row">
                                    <div class="act-time-col">
                                        <span class="act-time"><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?></span>
                                    </div>
                                    <div class="act-room-col">
                                        <span class="act-room-pill"><?= htmlspecialchars($b['room_code']) ?></span>
                                    </div>
                                    <div class="act-topic-col">
                                        <p class="act-topic" title="<?= htmlspecialchars($b['topic']) ?>"><?= htmlspecialchars($b['topic']) ?></p>
                                        <small class="act-user"><?= htmlspecialchars($b['requester_name']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>

    </main>
</div>

<!-- Modal: Room Schedule & Booking Form -->
<div class="modal-backdrop" id="bookingModal">
    <div class="modal-window">
        <button class="modal-close-btn" onclick="closeBookingModal()">✕</button>

        <div class="modal-room-header">
            <div>
                <span class="modal-badge-building">อาคาร C - ชั้น 3</span>
                <h2 id="modalRoomTitle">ห้อง C301 (ห้องประชุมใหญ่)</h2>
                <p id="modalRoomSubtitle" style="color: #5b6f69; margin: 4px 0 0; font-size: 14px;">ความจุ 200 คน • ห้องประชุม</p>
            </div>
            <div id="modalRoomFacilities" class="modal-fac-chips"></div>
        </div>

        <div class="modal-split">
            <!-- Left: Visual Timetable for Selected Date -->
            <div class="modal-schedule-panel">
                <div class="sched-filter-row">
                    <label for="schedDate"><b>ตารางการใช้ห้องประจำวันที่:</b></label>
                    <input type="date" id="schedDate" onchange="loadRoomSchedule()" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="timeline-legend">
                    <span><i class="dot-green"></i> ว่าง สามารถจองได้</span>
                    <span><i class="dot-red"></i> มีผู้จองแล้ว</span>
                </div>

                <div class="timeline-container" id="timelineSlots">
                    <div style="text-align: center; padding: 20px; color: #888;">กำลังโหลดตารางการใช้งาน...</div>
                </div>
            </div>

            <!-- Right: Booking Form -->
            <div class="modal-form-panel">
                <h3 style="margin-top:0; font-size: 17px; color: #0c4d3e;">✍️ แบบฟอร์มขอใช้ห้อง</h3>
                
                <form method="POST" id="bookingForm" onsubmit="return validateBookingSubmit()">
                    <input type="hidden" name="action" value="book">
                    <input type="hidden" name="room_id" id="formRoomId">

                    <div class="form-field">
                        <label>ชื่อผู้ขอจอง / ผู้รับผิดชอบ <span style="color:red">*</span></label>
                        <input type="text" name="name" id="formName" required 
                               value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" 
                               placeholder="เช่น นายศุภณัฐ จันทร์เปรม">
                    </div>

                    <div class="form-row-2">
                        <div class="form-field">
                            <label>อีเมลติดต่อ <span style="color:red">*</span></label>
                            <input type="email" name="email" id="formEmail" required 
                                   value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" 
                                   placeholder="เช่น 674230036@webmail.npru.ac.th">
                        </div>
                        <div class="form-field">
                            <label>เบอร์โทรศัพท์ <span style="color:red">*</span></label>
                            <input type="tel" name="phone" id="formPhone" required 
                                   value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" 
                                   placeholder="เช่น 0812345678">
                        </div>
                    </div>

                    <div class="form-field">
                        <label>หัวข้อ / วัตถุประสงค์ในการใช้ห้อง <span style="color:red">*</span></label>
                        <input type="text" name="topic" id="formTopic" required 
                               placeholder="เช่น ติวหนังสือสอบปลายภาค, ประชุมสโมสรนักศึกษา">
                    </div>

                    <div class="form-row-2">
                        <div class="form-field">
                            <label>วันที่ต้องการจอง <span style="color:red">*</span></label>
                            <input type="date" name="date" id="formDate" required value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-field">
                            <label>จำนวนผู้เข้าร่วม (คน) <span style="color:red">*</span></label>
                            <input type="number" name="people" id="formPeople" min="1" value="1" required>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-field">
                            <label>เวลาเริ่ม <span style="color:red">*</span></label>
                            <input type="time" name="start_time" id="formStartTime" required value="09:00">
                        </div>
                        <div class="form-field">
                            <label>เวลาสิ้นสุด <span style="color:red">*</span></label>
                            <input type="time" name="end_time" id="formEndTime" required value="12:00">
                        </div>
                    </div>

                    <div id="conflictAlert" class="conflict-alert" style="display:none;"></div>

                    <button type="submit" class="btn-submit-booking">
                        🚀 ส่งคำขอจองห้อง (ส่งต่อฝ่ายวิชาการ)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Notifications -->
<div class="modal-backdrop" id="notifModal">
    <div class="modal-window notif-modal-window">
        <button class="modal-close-btn" onclick="toggleNotifModal()">✕</button>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-right:25px;">
            <h3 style="margin:0; color: #0c4d3e;">🔔 การแจ้งเตือน<?= $currentUser ? 'ของคุณ' : 'ระบบ' ?></h3>
            <?php if ($currentUser): ?>
                <button type="button" class="btn-mark-all" onclick="markAllNotifsRead()">✓ อ่านทั้งหมด</button>
            <?php endif; ?>
        </div>

        <div class="notif-list" id="notifListContainer">
            <div style="text-align:center; padding:20px; color:#888;">กำลังโหลดการแจ้งเตือน...</div>
        </div>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
