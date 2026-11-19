<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';

require_login();

$message = '';
$error = '';

// การเพิ่มโฆษณาใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ad') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF Token';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target_url = trim($_POST['target_url'] ?? '');

        // ตรวจสอบ URL ป้องกัน javascript: XSS payload
        if (!filter_var($target_url, FILTER_VALIDATE_URL) || !in_array(parse_url($target_url, PHP_URL_SCHEME), ['http', 'https'])) {
            $error = 'กรุณาระบุ URL ปลายทางให้ถูกต้อง (ต้องขึ้นต้นด้วย http:// หรือ https://)';
        } elseif ($title === '' || $description === '') {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } elseif (!isset($_FILES['ad_image']) || $_FILES['ad_image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'กรุณาอัปโหลดรูปภาพโฆษณา';
        } else {
            // ตรวจสอบไฟล์รูปภาพอย่างเข้มงวด (ป้องกันการปลอมนามสกุลไฟล์)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['ad_image']['tmp_name']);
            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!array_key_exists($mime, $allowed_mimes)) {
                $error = 'อนุญาตเฉพาะไฟล์รูปภาพ JPG, PNG, หรือ WEBP เท่านั้น';
            } elseif ($_FILES['ad_image']['size'] > 5 * 1024 * 1024) {
                $error = 'ขนาดไฟล์ต้องไม่เกิน 5MB';
            } else {
                // สุ่มชื่อไฟล์ใหม่ทั้งหมด ป้องกัน Path Traversal
                $ext = $allowed_mimes[$mime];
                $new_filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $destination = __DIR__ . '/uploads/' . $new_filename;

                if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $destination)) {
                    $stmt = $pdo->prepare("INSERT INTO ads (title, description, target_url, image_path, created_by) VALUES (:title, :desc, :url, :img, :uid)");
                    $stmt->execute([
                        'title' => $title,
                        'desc'  => $description,
                        'url'   => $target_url,
                        'img'   => '/uploads/' . $new_filename,
                        'uid'   => $_SESSION['user_id']
                    ]);
                    $message = 'ลงโฆษณาสำเร็จ!';
                } else {
                    $error = 'ไม่สามารถบันทึกไฟล์รูปภาพได้';
                }
            }
        }
    }
}

// การลบโฆษณา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ad') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $ad_id = (int)($_POST['ad_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT image_path FROM ads WHERE id = :id");
        $stmt->execute(['id' => $ad_id]);
        $ad = $stmt->fetch();

        if ($ad) {
            $file_path = __DIR__ . '/uploads/' . basename($ad['image_path']);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $del = $pdo->prepare("DELETE FROM ads WHERE id = :id");
            $del->execute(['id' => $ad_id]);
            $message = 'ลบโฆษณาเรียบร้อยแล้ว';
        }
    }
}

// ดึงรายการโฆษณาทั้งหมด
$ads = $pdo->query("SELECT ads.*, users.username FROM ads JOIN users ON ads.created_by = users.id ORDER BY ads.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบจัดการโฆษณา - Ad Portal</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f9; margin: 0; padding: 0; }
        .navbar { background: #1e293b; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="url"], textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 18px; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button.btn-danger { background: #dc2626; padding: 6px 12px; font-size: 13px; }
        .ad-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .ad-item { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; }
        .ad-item img { width: 100%; height: 180px; object-fit: cover; }
        .ad-body { padding: 15px; }
        .alert-success { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Ad Portal</strong> (Host: <?= e($_SERVER['HTTP_HOST'] ?? '') ?>)</div>
    <div>
        สวัสดี, <?= e($_SESSION['username']) ?> | 
        <a href="/logout.php" style="color: #93c5fd; text-decoration: none;">ออกจากระบบ</a>
    </div>
</div>

<div class="container">
    <?php if ($message): ?><div class="alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>+ เพิ่มแบนเนอร์โฆษณาใหม่</h3>
        <form method="POST" action="/dashboard.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="create_ad">
            
            <div class="form-group">
                <label>หัวข้อโฆษณา</label>
                <input type="text" name="title" maxlength="150" required>
            </div>
            <div class="form-group">
                <label>รายละเอียดโฆษณา</label>
                <textarea name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>ลิงก์ปลายทาง (URL)</label>
                <input type="url" name="target_url" placeholder="https://example.com" required>
            </div>
            <div class="form-group">
                <label>รูปภาพแบนเนอร์ (JPG, PNG, WEBP ไม่เกิน 5MB)</label>
                <input type="file" name="ad_image" accept="image/jpeg,image/png,image/webp" required>
            </div>
            <button type="submit">บันทึกโฆษณา</button>
        </form>
    </div>

    <h3>รายการโฆษณาทั้งหมด (เฉพาะสมาชิกที่เข้าสู่ระบบ)</h3>
    <div class="ad-grid">
        <?php foreach ($ads as $ad): ?>
            <div class="ad-item">
                <img src="<?= e($ad['image_path']) ?>" alt="<?= e($ad['title']) ?>">
                <div class="ad-body">
                    <h4 style="margin-top:0;"><?= e($ad['title']) ?></h4>
                    <p style="font-size:14px; color:#475569;"><?= e($ad['description']) ?></p>
                    <p><a href="<?= e($ad['target_url']) ?>" target="_blank" rel="noopener noreferrer">ไปยังลิงก์โฆษณา &rarr;</a></p>
                    <small style="color:#94a3b8; display:block; margin-bottom:10px;">โดย: <?= e($ad['username']) ?></small>
                    
                    <form method="POST" action="/dashboard.php" onsubmit="return confirm('ยืนยันการลบโฆษณานี้?');">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_ad">
                        <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                        <button type="submit" class="btn-danger">ลบโฆษณา</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>