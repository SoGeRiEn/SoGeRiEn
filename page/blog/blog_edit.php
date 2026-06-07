<?php
declare(strict_types=1);
$mode = 'edit';
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($postId <= 0) {
    $adminUrl = trim((string)($_SERVER['BLOG_ADMIN_URL'] ?? '/api/news_admin.php'));
    if ($adminUrl === '') {
        $adminUrl = '/api/news_admin.php';
    }
    header('Location: ' . $adminUrl);
    exit;
}
require __DIR__ . '/blog_form.php';

