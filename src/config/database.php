<?php
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'ad_portal_db';
$user = getenv('DB_USER') ?: 'ad_app_user';
$pass = getenv('DB_PASSWORD') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // ปิด Emulate เพื่อให้ DB เตรียม Statement จริง ป้องกัน SQLi
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // ห้ามแสดงรายละเอียด Error ของ Database ให้ผู้ใช้เห็นเด็ดขาด
    error_log($e->getMessage());
    http_response_code(500);
    die("เกิดข้อผิดพลาดในการเชื่อมต่อระบบ กรุณาลองใหม่ภายหลัง");
}