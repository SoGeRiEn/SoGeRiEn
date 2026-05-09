<?php
declare(strict_types=1);

$page = new ClientDashboardPages();
$page->init_db_alias(trim((string)Sogerien::AccessCheck()->db_alias));
$page->render('email_history');
