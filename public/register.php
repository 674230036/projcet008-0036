<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::start();
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::register($_POST);
    if ($result['success']) {
        header('Location: login.php?registered=1');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมัครสมาชิกนักศึกษา - ระบบจองห้อง อาคาร C มหาวิทยาลัยราชภัฏนครปฐม</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="css/style.css">
<style>
.register-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at top right, #e8f5f0, #e2ece7, #d4e7e0);
    padding: 30px 20px;
    font-family: 'Sarabun', sans-serif;
}
.register-card {
    background: #ffffff;
    width: 100%;
    max-width: 520px;
    border-radius: 24px;
    padding: 36px 36px;
    box-shadow: 0 20px 50px rgba(8, 77, 62, 0.12);
    border: 1px solid rgba(8, 117, 94, 0.1);
}
.register-header {
    text-align: center;
    margin-bottom: 24px;
}
.register-header img {
    width: 75px;
    height: auto;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
}
.register-header h1 {
    font-size: 22px;
    color: #0c4d3e;
    margin: 10px 0 4px;
    font-weight: 700;
}
.register-header p {
    font-size: 13.5px;
    color: #5d716c;
    margin: 0;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.form-group {
    margin-bottom: 16px;
    text-align: left;
}
.form-group.full {
    grid-column: 1 / -1;
}
.form-group label {
    display: block;
    font-size: 13.5px;
    font-weight: 600;
    color: #2c3e39;
    margin-bottom: 6px;
}
.form-group input {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #d4e0dc;
    border-radius: 11px;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.form-group input:focus {
    border-color: #08755e;
    box-shadow: 0 0 0 3px rgba(8, 117, 94, 0.15);
}
.field-hint {
    font-size: 11.5px;
    color: #72847f;
    margin-top: 4px;
    display: block;
}
.btn-submit {
    width: 100%;
    background: #08755e;
    color: #ffffff;
    border: none;
    padding: 13px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(8, 117, 94, 0.25);
    transition: all 0.2s ease;
    margin-top: 10px;
}
.btn-submit:hover {
    background: #065a48;
    transform: translateY(-1px);
}
.register-footer {
    margin-top: 20px;
    text-align: center;
    font-size: 14px;
}
.register-footer a {
    color: #08755e;
    font-weight: 600;
    text-decoration: none;
}
.register-footer a:hover {
    text-decoration: underline;
}
.alert-box {
    padding: 12px 15px;
    border-radius: 10px;
    font-size: 13.5px;
    margin-bottom: 18px;
    text-align: left;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
</style>
</head>
<body class="register-container">

<div class="register-card">
    <div class="register-header">
        <a href="index.php"><img src="images/npru-logo.png" alt="NPRU Logo"></a>
        <h1>สมัครสมาชิกนักศึกษา</h1>
        <p>ระบบจองห้อง อาคาร C - มหาวิทยาลัยราชภัฏนครปฐม</p>
    </div>

    <?php if ($error): ?>
        <div class="alert-box alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="regForm" onsubmit="return validateForm()">
        <div class="form-grid">
            <div class="form-group full">
                <label for="student_id">รหัสนักศึกษา <span style="color:red">*</span></label>
                <input type="text" id="student_id" name="student_id" maxlength="9" placeholder="เช่น 674230099 (9 หลัก)" required value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" oninput="autoFillEmail(this.value)">
                <span class="field-hint">ระบบจะนำไปสร้างอีเมลเว็บเมล์อัตโนมัติ</span>
            </div>

            <div class="form-group full">
                <label for="name">ชื่อ - นามสกุล <span style="color:red">*</span></label>
                <input type="text" id="name" name="name" placeholder="เช่น นายสมคิด จิตอาสา" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group full">
                <label for="email">อีเมลเว็บเมล์ NPRU <span style="color:red">*</span></label>
                <input type="email" id="email" name="email" placeholder="เช่น 674230099@webmail.npru.ac.th" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group full">
                <label for="phone">เบอร์โทรศัพท์ติดต่อ</label>
                <input type="tel" id="phone" name="phone" placeholder="เช่น 0812345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่าน <span style="color:red">*</span></label>
                <input type="password" id="password" name="password" minlength="4" placeholder="อย่างน้อย 4 ตัวอักษร" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">ยืนยันรหัสผ่าน <span style="color:red">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="4" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
            </div>
        </div>

        <button type="submit" class="btn-submit">ลงทะเบียนใช้งานระบบ</button>
    </form>

    <div class="register-footer">
        มีบัญชีนักศึกษาอยู่แล้ว? <a href="login.php">เข้าสู่ระบบทันที</a>
        <br><br>
        <a href="index.php" style="color: #657570;">← กลับสู่หน้าหลักระบบจองห้อง</a>
    </div>
</div>

<script>
function autoFillEmail(stuId) {
    const clean = stuId.replace(/[^0-9]/g, '');
    document.getElementById('student_id').value = clean;
    if (clean.length >= 8) {
        document.getElementById('email').value = clean + '@webmail.npru.ac.th';
    }
}

function validateForm() {
    const stuId = document.getElementById('student_id').value.trim();
    const p1 = document.getElementById('password').value;
    const p2 = document.getElementById('confirm_password').value;

    if (!/^[0-9]{9}$/.test(stuId)) {
        alert('กรุณากรอกรหัสนักศึกษาให้ถูกต้องเป็นตัวเลข 9 หลัก');
        document.getElementById('student_id').focus();
        return false;
    }

    if (p1 !== p2) {
        alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
        document.getElementById('confirm_password').focus();
        return false;
    }

    return true;
}
</script>
</body>
</html>
