<?php
declare(strict_types=1);

$page = new ClientDashboardPages();
$page->init_db_alias(trim((string)Sogerien::AccessCheck()->db_alias));
$path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
$page->render($path === 'client/change-password' ? 'change_password' : 'profile');
