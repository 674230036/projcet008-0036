<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::start();
$currentUser = Auth::user();

// If already logged in, redirect
if ($currentUser) {
    if (in_array($currentUser['role'], ['admin', 'academic', 'building'])) {
        header('Location: ../admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = 'สมัครสมาชิกสำเร็จ! ท่านสามารถเข้าสู่ระบบด้วยรหัสนักศึกษาและรหัสผ่านได้ทันที';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้/อีเมล และรหัสผ่าน';
    } elseif (Auth::login($identifier, $password)) {
        $user = Auth::user();
        if (in_array($user['role'], ['admin', 'academic', 'building'])) {
            header('Location: ../admin/index.php');
        } else {
            header('Location: index.php?login=success');
        }
        exit;
    } else {
        $error = 'อีเมล รหัสนักศึกษา หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ - ระบบจองห้อง อาคาร C มหาวิทยาลัยราชภัฏนครปฐม</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="css/style.css">
<style>
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at top right, #e8f5f0, #e2ece7, #d4e7e0);
    padding: 20px;
    font-family: 'Sarabun', sans-serif;
}
.login-card {
    background: #ffffff;
    width: 100%;
    max-width: 460px;
    border-radius: 24px;
    padding: 40px 36px;
    box-shadow: 0 20px 50px rgba(8, 77, 62, 0.12);
    border: 1px solid rgba(8, 117, 94, 0.1);
    position: relative;
}
.login-header {
    text-align: center;
    margin-bottom: 26px;
}
.login-header img {
    width: 85px;
    height: auto;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
}
.login-header h1 {
    font-size: 22px;
    color: #0c4d3e;
    margin: 12px 0 4px;
    font-weight: 700;
}
.login-header p {
    font-size: 14px;
    color: #5d716c;
    margin: 0;
}
.form-group {
    margin-bottom: 18px;
    text-align: left;
}
.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e39;
    margin-bottom: 7px;
}
.form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #d4e0dc;
    border-radius: 12px;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.form-group input:focus {
    border-color: #08755e;
    box-shadow: 0 0 0 3px rgba(8, 117, 94, 0.15);
}
.btn-primary {
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
}
.btn-primary:hover {
    background: #065a48;
    transform: translateY(-1px);
}
.quick-logins {
    background: #f7faf9;
    border-radius: 14px;
    padding: 14px;
    margin-top: 22px;
    border: 1px dashed #cde0d8;
    font-size: 12px;
    color: #495e59;
}
.quick-logins strong {
    display: block;
    color: #0c4d3e;
    margin-bottom: 6px;
    font-size: 13px;
}
.quick-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.quick-tag {
    background: #e5f3ed;
    color: #07624e;
    border: 1px solid #bce2d3;
    padding: 4px 9px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
}
.quick-tag:hover {
    background: #08755e;
    color: #ffffff;
}
.login-links {
    margin-top: 20px;
    text-align: center;
    font-size: 14px;
}
.login-links a {
    color: #08755e;
    font-weight: 600;
    text-decoration: none;
}
.login-links a:hover {
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
.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
</style>
</head>
<body class="login-container">

<div class="login-card">
    <div class="login-header">
        <a href="index.php"><img src="images/npru-logo.png" alt="NPRU Logo"></a>
        <h1>เข้าสู่ระบบจองห้อง อาคาร C</h1>
        <p>มหาวิทยาลัยราชภัฏนครปฐม</p>
    </div>

    <?php if ($error): ?>
        <div class="alert-box alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-box alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <div class="form-group">
            <label for="username">อีเมลเว็บเมล์ หรือ รหัสนักศึกษา / บัญชีเจ้าหน้าที่</label>
            <input type="text" id="username" name="username" placeholder="เช่น 674230036@webmail.npru.ac.th หรือ 674230036" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" placeholder="รหัสนักศึกษา หรือ รหัสผ่านของคุณ" required>
        </div>

        <button type="submit" class="btn-primary">เข้าสู่ระบบ</button>
    </form>

    <div class="login-links">
        ยังไม่มีบัญชีนักศึกษา? <a href="register.php">สมัครสมาชิกรักษาความปลอดภัย</a>
        <br><br>
        <a href="index.php" style="color: #657570;">← กลับสู่หน้าหลักระบบจองห้อง</a>
    </div>

    <div class="quick-logins">
        <strong>💡 เลือกบัญชีทดลองเข้าสู่ระบบ (คลิกเพื่อกรอกอัตโนมัติ):</strong>
        <div class="quick-tags">
            <span class="quick-tag" onclick="quickFill('674230036@webmail.npru.ac.th', '674230036')">👨‍🎓 ศุภณัฐ (674230036)</span>
            <span class="quick-tag" onclick="quickFill('674230002@webmail.npru.ac.th', '674230002')">👨‍🎓 ธนพล (674230002)</span>
            <span class="quick-tag" onclick="quickFill('674230030@webmail.npru.ac.th', '674230030')">👩‍🎓 สุธาทิพย์ (674230030)</span>
            <span class="quick-tag" onclick="quickFill('academic@npru.ac.th', '1234')">📋 ฝ่ายวิชาการ (academic)</span>
            <span class="quick-tag" onclick="quickFill('building@npru.ac.th', '1234')">🏢 ฝ่ายอาคารฯ (building)</span>
            <span class="quick-tag" onclick="quickFill('admin', '1234')">⚙️ ผู้ดูแลระบบ (admin)</span>
        </div>
    </div>
</div>

<script>
function quickFill(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
    document.getElementById('loginForm').submit();
}
</script>
</body>
</html>
