<?php
// ตั้งค่า Session ให้ปลอดภัย
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ฟังก์ชันป้องกัน XSS (Cross-Site Scripting)
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// สร้าง CSRF Token
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ตรวจสอบ CSRF Token
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// บังคับต้อง Login ก่อนเข้าหน้าเว็บ
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }
    // ตรวจสอบ Session Timeout (30 นาที)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header("Location: /login.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}