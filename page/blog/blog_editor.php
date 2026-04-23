<?php
declare(strict_types=1);
$adminUrl = trim((string)($_SERVER['BLOG_ADMIN_URL'] ?? '/api/news_admin.php'));
if ($adminUrl === '') {
    $adminUrl = '/api/news_admin.php';
}
header('Location: ' . $adminUrl, true, 302);
exit;

