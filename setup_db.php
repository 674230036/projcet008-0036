<?php
require_once __DIR__ . '/config/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    // 1. Ensure rooms table
    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) NOT NULL UNIQUE,
        room_name VARCHAR(100) NOT NULL,
        building VARCHAR(50) NOT NULL DEFAULT 'อาคาร C',
        room_type VARCHAR(50) NOT NULL,
        capacity INT NOT NULL DEFAULT 40,
        floor VARCHAR(20) DEFAULT 'ชั้น 3',
        facilities VARCHAR(255) DEFAULT 'โปรเจกเตอร์, ไมโครโฟน, เครื่องปรับอากาศ, ไวไฟ',
        status ENUM('available','maintenance') DEFAULT 'available'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Ensure users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(20) NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        role ENUM('student','academic','building','admin') DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Ensure bookings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        user_id INT NULL,
        requester_name VARCHAR(100) NOT NULL,
        requester_email VARCHAR(150) NOT NULL,
        requester_phone VARCHAR(20) DEFAULT NULL,
        topic VARCHAR(255) NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        people INT NOT NULL DEFAULT 1,
        status ENUM('pending','approved','ready','completed','rejected','cancelled') DEFAULT 'pending',
        academic_notes VARCHAR(255) NULL,
        building_notes VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booking_date (booking_date),
        INDEX idx_status (status),
        CONSTRAINT fk_booking_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Ensure notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        booking_id INT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'approved', 'rejected', 'ready', 'new_booking') DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read),
        CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_notif_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Populate / update rooms
    $rooms = [
        ['C301', 'ห้องประชุมใหญ่', 'อาคาร C', 'ห้องประชุม', 200, 'ชั้น 3', 'โปรเจกเตอร์ความคมชัดสูง, จอขนาดใหญ่, ระบบเสียงรอบทิศทาง, ไมค์ลอย 4 ตัว, โต๊ะประชุม'],
        ['C302', 'ห้องเรียน C302', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C303', 'ห้องเรียน C303', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C304', 'ห้องเรียน C304', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C305', 'ห้องเรียน C305', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C306', 'ห้องเรียน C306', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C307', 'ห้องเรียน C307', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C308', 'ห้องเรียน C308', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C309', 'ห้องเรียน C309', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
        ['C310', 'ห้องเรียน C310', 'อาคาร C', 'ห้องเรียน', 40, 'ชั้น 3', 'โปรเจกเตอร์, เครื่องปรับอากาศ, สมาร์ททีวี, ไมโครโฟน, ไวท์บอร์ด'],
    ];

    $roomStmt = $pdo->prepare("INSERT INTO rooms (room_code, room_name, building, room_type, capacity, floor, facilities, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
        ON DUPLICATE KEY UPDATE 
            room_name = VALUES(room_name),
            building = VALUES(building),
            room_type = VALUES(room_type),
            capacity = VALUES(capacity),
            floor = VALUES(floor),
            facilities = VALUES(facilities),
            status = 'available'");

    foreach ($rooms as $r) {
        $roomStmt->execute($r);
    }

    // Populate students list from the screenshot + extra active students
    $students = [
        ['674230002', 'นายธนพล น้อยประสาน', '674230002@webmail.npru.ac.th', '0812345002'],
        ['674230003', 'นายธนภัทร ลิ้มสกุล', '674230003@webmail.npru.ac.th', '0812345003'],
        ['674230008', 'นายรัชต์พงษ์ ตันติประสพลาภ', '674230008@webmail.npru.ac.th', '0812345008'],
        ['674230011', 'นายสมพงษ์ พูตุ้ย', '674230011@webmail.npru.ac.th', '0812345011'],
        ['674230013', 'นายจิรเมธ ทองเปลว', '674230013@webmail.npru.ac.th', '0812345013'],
        ['674230015', 'นายถิรวุฒิ หลวงพิทักษ์', '674230015@webmail.npru.ac.th', '0812345015'],
        ['674230019', 'นายณัฐดนัย มหาไทย', '674230019@webmail.npru.ac.th', '0812345019'],
        ['674230024', 'นายโยธิน สุขโสภา', '674230024@webmail.npru.ac.th', '0812345024'],
        ['674230026', 'นายวีรวัฒน์ กรวิเชียร', '674230026@webmail.npru.ac.th', '0812345026'],
        ['674230027', 'นายสราวุฒิ อุ้ยยาหาญ', '674230027@webmail.npru.ac.th', '0812345027'],
        ['674230028', 'นายธีรพัฒน์ คงสกุลถาวร', '674230028@webmail.npru.ac.th', '0812345028'],
        ['674230030', 'นางสาวสุธาทิพย์ รอดทวีสุข', '674230030@webmail.npru.ac.th', '0812345030'],
        ['674230034', 'นางสาวปภัสสร แย้มชื่น', '674230034@webmail.npru.ac.th', '0812345034'],
        ['674230035', 'นายภูธเนศ ชำเฮียง', '674230035@webmail.npru.ac.th', '0812345035'],
        ['674230036', 'นายศุภณัฐ จันทร์เปรม', '674230036@webmail.npru.ac.th', '0812345036'],
        // Extra registered students
        ['674230040', 'นายกิตติศักดิ์ เจริญพร', '674230040@webmail.npru.ac.th', '0812345040'],
        ['674230045', 'นางสาวอารียา บุญส่ง', '674230045@webmail.npru.ac.th', '0812345045'],
        ['674230050', 'นายพงศธร ศรีสวัสดิ์', '674230050@webmail.npru.ac.th', '0812345050'],
        ['674230055', 'นางสาวกานดา วิเศษสุข', '674230055@webmail.npru.ac.th', '0812345055'],
        ['674230060', 'นายวรวุฒิ สิทธิชัย', '674230060@webmail.npru.ac.th', '0812345060'],
    ];

    $userStmt = $pdo->prepare("INSERT INTO users (student_id, name, email, password, phone, role)
        VALUES (?, ?, ?, ?, ?, 'student')
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            email = VALUES(email),
            password = VALUES(password),
            phone = VALUES(phone),
            role = 'student'");

    foreach ($students as $stu) {
        $hash = password_hash($stu[0], PASSWORD_DEFAULT);
        $userStmt->execute([$stu[0], $stu[1], $stu[2], $hash, $stu[3]]);
    }

    // Insert staff and admin users
    $staff = [
        [null, 'ผู้ดูแลระบบสูงสุด', 'admin@npru.ac.th', password_hash('1234', PASSWORD_DEFAULT), '034109300', 'admin'],
        [null, 'อาจารย์วิภาดา รักษ์วิชาการ (ฝ่ายวิชาการ)', 'academic@npru.ac.th', password_hash('1234', PASSWORD_DEFAULT), '034109301', 'academic'],
        [null, 'นายสมเกียรติ มั่นคง (ฝ่ายอาคารสถานที่)', 'building@npru.ac.th', password_hash('1234', PASSWORD_DEFAULT), '034109302', 'building'],
    ];

    $staffStmt = $pdo->prepare("INSERT INTO users (student_id, name, email, password, phone, role)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password = VALUES(password),
            phone = VALUES(phone),
            role = VALUES(role)");

    foreach ($staff as $st) {
        $staffStmt->execute($st);
    }

    // Fetch user IDs for linking
    $uSuphanut = $pdo->query("SELECT id FROM users WHERE student_id = '674230036'")->fetchColumn();
    $uThanaphon = $pdo->query("SELECT id FROM users WHERE student_id = '674230002'")->fetchColumn();
    $uSuthathip = $pdo->query("SELECT id FROM users WHERE student_id = '674230030'")->fetchColumn();
    $uYothin = $pdo->query("SELECT id FROM users WHERE student_id = '674230024'")->fetchColumn();
    $uJiramed = $pdo->query("SELECT id FROM users WHERE student_id = '674230013'")->fetchColumn();
    $uKittisak = $pdo->query("SELECT id FROM users WHERE student_id = '674230040'")->fetchColumn();
    $uAreeya = $pdo->query("SELECT id FROM users WHERE student_id = '674230045'")->fetchColumn();

    // Fetch room IDs
    $rC301 = $pdo->query("SELECT id FROM rooms WHERE room_code = 'C301'")->fetchColumn();
    $rC302 = $pdo->query("SELECT id FROM rooms WHERE room_code = 'C302'")->fetchColumn();
    $rC303 = $pdo->query("SELECT id FROM rooms WHERE room_code = 'C303'")->fetchColumn();
    $rC305 = $pdo->query("SELECT id FROM rooms WHERE room_code = 'C305'")->fetchColumn();

    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    // Reset bookings to seed rich, bug-free, fully booked room dataset
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM bookings");

    $bookStmt = $pdo->prepare("INSERT INTO bookings 
        (room_id, user_id, requester_name, requester_email, requester_phone, topic, booking_date, start_time, end_time, people, status, academic_notes, building_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // === C301: FULLY BOOKED FOR THE ENTIRE DAY (08:00 to 19:00) ===
    // Slot 1: 08:00 - 10:00 (Ready)
    $bookStmt->execute([
        $rC301, $uSuphanut, 'นายศุภณัฐ จันทร์เปรม', '674230036@webmail.npru.ac.th', '0812345036',
        'โครงการปฐมนิเทศนักศึกษาใหม่และอบรมจริยธรรม', $today, '08:00:00', '10:00:00', 180, 'ready',
        'อนุมัติโครงการเพื่อการศึกษา', 'ฝ่ายอาคารฯ ตรวจสอบระบบเสียง ไมค์ และแอร์เรียบร้อย'
    ]);
    $b1 = $pdo->lastInsertId();

    // Slot 2: 10:00 - 12:00 (Ready)
    $bookStmt->execute([
        $rC301, null, 'ผศ.ดร.ประเสริฐ สุขสม', 'prasert@npru.ac.th', '0819876543',
        'ประชุมคณะกรรมการบริหารวิชาการ มหาวิทยาลัยราชภัฏนครปฐม', $today, '10:00:00', '12:00:00', 80, 'ready',
        'อนุมัติการประชุมคณะกรรมการ', 'จัดโต๊ะประชุมและจอภาพโปรเจกเตอร์พร้อม'
    ]);
    $b2 = $pdo->lastInsertId();

    // Slot 3: 12:00 - 13:00 (Ready)
    $bookStmt->execute([
        $rC301, $uThanaphon, 'นายธนพล น้อยประสาน', '674230002@webmail.npru.ac.th', '0812345002',
        'เตรียมความพร้อมและทดสอบระบบโสตทัศนูปกรณ์งานสัมมนา', $today, '12:00:00', '13:00:00', 15, 'ready',
        'อนุมัติการทดสอบระบบ', 'เปิดไฟและระบบเครื่องเสียงล่วงหน้า'
    ]);
    $b3 = $pdo->lastInsertId();

    // Slot 4: 13:00 - 15:00 (Ready)
    $bookStmt->execute([
        $rC301, null, 'อาจารย์สมชาย ใจดี', 'somchai@npru.ac.th', '0823456789',
        'การบรรยายพิเศษเทคโนโลยี AI และ Generative Systems สำหรับนักศึกษา', $today, '13:00:00', '15:00:00', 195, 'ready',
        'อนุมัติโครงการบรรยายวิชาการ', 'ห้องพร้อมใช้งานเต็มรูปแบบ'
    ]);
    $b4 = $pdo->lastInsertId();

    // Slot 5: 15:00 - 17:00 (Approved / Waiting building)
    $bookStmt->execute([
        $rC301, $uSuthathip, 'นางสาวสุธาทิพย์ รอดทวีสุข', '674230030@webmail.npru.ac.th', '0812345030',
        'กิจกรรมสโมสรนักศึกษาและวางแผนค่ายอาสาพัฒนาท้องถิ่น', $today, '15:00:00', '17:00:00', 120, 'approved',
        'อนุมัติกิจกรรมพัฒนานักศึกษา', null
    ]);
    $b5 = $pdo->lastInsertId();

    // Slot 6: 17:00 - 19:00 (Approved / Waiting building)
    $bookStmt->execute([
        $rC301, $uYothin, 'นายโยธิน สุขโสภา', '674230024@webmail.npru.ac.th', '0812345024',
        'ซ้อมการแสดงคอนเสิร์ตวงดนตรีมหาวิทยาลัยราชภัฏนครปฐม', $today, '17:00:00', '19:00:00', 50, 'approved',
        'อนุมัติการซ้อมวงดนตรี', null
    ]);
    $b6 = $pdo->lastInsertId();

    // === C302: Other Bookings ===
    $bookStmt->execute([
        $rC302, $uJiramed, 'นายจิรเมธ ทองเปลว', '674230013@webmail.npru.ac.th', '0812345013',
        'การเรียนการสอนและฝึกปฏิบัติการ CS3201', $today, '09:00:00', '12:00:00', 38, 'ready',
        'อนุมัติรายวิชาตามตารางสอน', 'ห้องเรียนและโปรเจกเตอร์พร้อม'
    ]);
    $b7 = $pdo->lastInsertId();

    $bookStmt->execute([
        $rC302, $uKittisak, 'นายกิตติศักดิ์ เจริญพร', '674230040@webmail.npru.ac.th', '0812345040',
        'ติวหนังสือเตรียมสอบกลางภาควิชาฟิสิกส์', $today, '13:00:00', '16:00:00', 35, 'approved',
        'อนุมัติการติวหนังสือ', null
    ]);
    $b8 = $pdo->lastInsertId();

    // === C303 & C305: Pending requests awaiting academic review ===
    $bookStmt->execute([
        $rC303, $uAreeya, 'นางสาวอารียา บุญส่ง', '674230045@webmail.npru.ac.th', '0812345045',
        'ประชุมกลุ่มทำงานโครงงานวิจัยเทคโนโลยีชีวภาพ', $today, '14:00:00', '17:00:00', 25, 'pending',
        null, null
    ]);
    $b9 = $pdo->lastInsertId();

    $bookStmt->execute([
        $rC305, $uSuphanut, 'นายศุภณัฐ จันทร์เปรม', '674230036@webmail.npru.ac.th', '0812345036',
        'สัมมนาเชิงปฏิบัติการการพัฒนาโมเดลภาษาขนาดใหญ่', $tomorrow, '10:00:00', '12:00:00', 30, 'pending',
        null, null
    ]);
    $b10 = $pdo->lastInsertId();

    // === SEED NOTIFICATIONS FOR STUDENTS ===
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, booking_id, title, message, type, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    // Notifications for Suphanut (674230036)
    $notifStmt->execute([
        $uSuphanut, $b1,
        '🔑 ห้อง C301 พร้อมใช้งานแล้ว!',
        'ฝ่ายอาคารสถานที่ได้ทำการเปิดห้องเรียน C301 และจัดเตรียมระบบโสตทัศนูปกรณ์พร้อมใช้งานสำหรับ "โครงการปฐมนิเทศนักศึกษาใหม่"',
        'ready', 0, date('Y-m-d H:i:s', strtotime('-1 hour'))
    ]);

    $notifStmt->execute([
        $uSuphanut, $b1,
        '🎉 คำขอจองห้อง C301 ได้รับการอนุมัติแล้ว',
        'ฝ่ายวิชาการได้ทำการตรวจสอบและอนุมัติคำขอจองห้อง C301 เรียบร้อยแล้ว (ส่งต่อฝ่ายอาคารฯ เพื่อเตรียมห้อง)',
        'approved', 0, date('Y-m-d H:i:s', strtotime('-2 hours'))
    ]);

    $notifStmt->execute([
        $uSuphanut, $b10,
        '📝 ส่งคำขอจองห้อง C305 เรียบร้อย',
        'ระบบได้รับคำขอจองห้อง C305 วันที่ ' . $tomorrow . ' เวลา 10:00 - 12:00 น. อยู่ระหว่างรอฝ่ายวิชาการตรวจสอบ',
        'info', 0, date('Y-m-d H:i:s', strtotime('-10 minutes'))
    ]);

    // Notifications for Suthathip (674230030)
    $notifStmt->execute([
        $uSuthathip, $b5,
        '🎉 คำขอจองห้อง C301 ผ่านการอนุมัติแล้ว',
        'ฝ่ายวิชาการได้อนุมัติคำขอ "กิจกรรมสโมสรนักศึกษา" เรียบร้อยแล้ว ขณะนี้รอฝ่ายอาคารฯ เปิดห้อง',
        'approved', 0, date('Y-m-d H:i:s', strtotime('-30 minutes'))
    ]);

    // Notifications for Thanaphon (674230002)
    $notifStmt->execute([
        $uThanaphon, $b3,
        '🔑 ห้อง C301 พร้อมใช้งานแล้ว',
        'ฝ่ายอาคารฯ ยืนยันเปิดห้อง C301 พร้อมทดสอบระบบเสียงเรียบร้อย',
        'ready', 0, date('Y-m-d H:i:s', strtotime('-40 minutes'))
    ]);

    echo "Database setup & rich seeding completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
