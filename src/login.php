<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid Security Token (CSRF Detected)';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password_hash, failed_attempts, locked_until FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                // ตรวจสอบว่าบัญชีถูกล็อกชั่วคราวหรือไม่ (ป้องกัน Brute-force)
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = 'บัญชีถูกล็อกชั่วคราวเนื่องจากใส่รหัสผิดหลายครั้ง กรุณารอ 5 นาที';
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Login สำเร็จ: รีเซ็ตจำนวนครั้งที่ผิด และเปลี่ยน Session ID ใหม่ทันที
                    $update = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id");
                    $update->execute(['id' => $user['id']]);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_activity'] = time();

                    header("Location: /dashboard.php");
                    exit;
                } else {
                    // รหัสผิด: เพิ่มตัวนับ
                    $attempts = $user['failed_attempts'] + 1;
                    $lockTime = ($attempts >= 5) ? date('Y-m-d H:i:s', strtotime('+5 minutes')) : null;
                    $update = $pdo->prepare("UPDATE users SET failed_attempts = :attempts, locked_until = :lock WHERE id = :id");
                    $update->execute(['attempts' => $attempts, 'lock' => $lockTime, 'id' => $user['id']]);
                    
                    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                }
            } else {
                // ป้องกัน Username Enumeration ด้วยข้อความเดียวกัน
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบจัดการโฆษณา - Ad Portal</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 340px; }
        h2 { margin-top: 0; text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #0056b3; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #004494; }
        .error { background: #ffe6e6; color: #cc0000; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
<div class="login-box">
    <h2>เข้าสู่ระบบโฆษณา</h2>
    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/login.php">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <div class="form-group">
            <label>ชื่อผู้ใช้ (Username)</label>
            <input type="text" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label>รหัสผ่าน (Password)</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit">เข้าสู่ระบบ</button>
    </form>
</div>
</body>
</html>