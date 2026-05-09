<?php
declare(strict_types=1);

$profileTemplate = __DIR__ . '/admin_panel/profile.php';

if (!is_file($profileTemplate)) {
    http_response_code(500);
    echo 'template not found - ' . $profileTemplate;
    return;
}

require $profileTemplate;
