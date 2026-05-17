<?php
declare(strict_types=1);

function ar_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ar_s(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

function ar_user_link(mixed $value): string
{
    $userId = (int)ar_s($value);
    if ($userId <= 0) {
        return ar_h($value);
    }

    return '<a href="/client/dashboard?user_id=' . $userId . '" target="_blank" rel="noopener noreferrer">' . $userId . '</a>';
}

function ar_user_account_link(int $userId, mixed $label): string
{
    $text = ar_s($label);
    if ($userId <= 0 || $text === '') {
        return $text !== '' ? ar_h($text) : '-';
    }

    return '<a href="/client/dashboard?user_id=' . $userId . '" target="_blank" rel="noopener noreferrer">' . ar_h($text) . '</a>';
}

function ar_reset_password_form(mixed $userId, string $target = ''): string
{
    $uid = (int)ar_s($userId);
    if ($uid <= 0) {
        return '-';
    }

    return '<form class="pm-reset-inline" method="post" action="' . ar_h($target) . '">'
        . '<input type="hidden" name="action" value="reset_client_password">'
        . '<input type="hidden" name="client_user_id" value="' . $uid . '">'
        . '<input class="form-control form-control-sm" type="password" name="new_password" placeholder="New password" autocomplete="new-password" minlength="8" required>'
        . '<button class="btn btn-sm btn-outline-primary" type="submit">Set</button>'
        . '</form>';
}

function ar_default_payment_method_id(array $user): string
{
    $stored = ar_s($user['billing_default_payment_method_id'] ?? '');
    if ($stored !== '') {
        return $stored;
    }

    $methods = $user['payment_methods'] ?? [];
    if (!is_array($methods)) {
        return '';
    }

    $first = '';
    foreach ($methods as $method) {
        if (!is_array($method)) {
            continue;
        }
        $id = ar_s($method['id'] ?? '');
        if ($id === '') {
            continue;
        }
        if ($first === '') {
            $first = $id;
        }
        if (ar_s($method['is_default'] ?? '') === '1') {
            return $id;
        }
    }

    return $first;
}

function ar_has_stripe_card(array $user): bool
{
    return ar_s($user['stripe_customer_id'] ?? '') !== '' && ar_default_payment_method_id($user) !== '';
}

function ar_autopay_enabled(array $user): bool
{
    if (!ar_has_stripe_card($user)) {
        return false;
    }

    $flag = ar_s($user['billing_autopay_enabled'] ?? '');
    return $flag === '' || $flag === '1' || strtolower($flag) === 'true';
}

function ar_autopay_badge(array $user): string
{
    $hasCard = ar_has_stripe_card($user);
    $enabled = ar_autopay_enabled($user);
    $pmId = ar_default_payment_method_id($user);
    if ($hasCard && $enabled) {
        return '<span class="pm-autopay-ok" title="' . ar_h('Автопродление активно, карта ' . substr($pmId, 0, 10)) . '">✓</span>';
    }

    return '<span class="pm-autopay-empty" title="' . ar_h($hasCard ? 'Карта есть, автопродление выключено' : 'Карта не привязана') . '">-</span>';
}

function ar_traffic_topup_form(array $service, string $target = ''): string
{
    $serviceId = ar_s($service['service_id'] ?? '');
    if ($serviceId === '') {
        $serviceId = ar_s($service['vendor_package_key'] ?? '');
    }
    if ($serviceId === '') {
        $serviceId = ar_s($service['package_key'] ?? '');
    }
    if ($serviceId === '') {
        $serviceId = ar_s($service['title'] ?? '');
    }
    $userId = (int)ar_s($service['user_id'] ?? 0);
    $category = strtolower(ar_s($service['provider_pool_category'] ?? ''));
    if ($serviceId === '' || $userId <= 0 || !in_array($category, ['mobile', 'residential', 'residential_ipv6'], true)) {
        return '-';
    }

    return '<form class="pm-reset-inline" method="post" action="' . ar_h($target) . '">'
        . '<input type="hidden" name="action" value="admin_add_traffic">'
        . '<input type="hidden" name="service_id" value="' . ar_h($serviceId) . '">'
        . '<input type="hidden" name="client_user_id" value="' . $userId . '">'
        . '<input class="form-control form-control-sm" type="number" name="add_gb" placeholder="GB" min="0.01" step="0.01" required>'
        . '<button class="btn btn-sm btn-outline-primary" type="submit">Topup</button>'
        . '</form>';
}

function ar_table(string $gridId, array $rows, array $columns, array $headers = [], array $facets = []): void
{
    $tr = new TableRenderer();
    $tr->set_params = new SetParams();
    $tr->set_params->data = $rows;
    $tr->set_params->columns = $columns;
    $tr->set_params->headers = $headers;
    $tr->set_params->facets = $facets;
    $tr->set_params->gridId = $gridId;
    $tr->set_params->searchCols = $columns;
    $tr->set_params->perPage = 100;
    foreach ($columns as $column) {
        if ($column === 'user_id') {
            $tr->set_params->formatters[$column] = static fn($value): string => ar_user_link($value);
        } elseif (str_ends_with($column, '_html') || $column === 'actions') {
            $tr->set_params->formatters[$column] = static fn($value): string => (string)$value;
        }
    }
    $tr->render();
}

function ar_gb(mixed $value): string
{
    if (!is_int($value) && !is_float($value) && !is_string($value)) {
        return '-';
    }
    $raw = str_replace(',', '.', trim((string)$value));
    if ($raw === '' || !is_numeric($raw)) {
        return '-';
    }
    return number_format((float)$raw, 2, '.', '') . ' GB';
}

function ar_float(mixed $value): float
{
    if (!is_int($value) && !is_float($value) && !is_string($value)) {
        return 0.0;
    }
    $raw = str_replace(',', '.', trim((string)$value));
    return is_numeric($raw) ? (float)$raw : 0.0;
}

function ar_date_value(mixed $value): string
{
    $raw = ar_s($value);
    if ($raw === '') {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    return $dt instanceof DateTimeImmutable ? $dt->format('Y-m-d') : '';
}

function ar_order_time(array $order): int
{
    $raw = ar_s($order['paid_at'] ?? '') !== '' ? ar_s($order['paid_at'] ?? '') : ar_s($order['created_at'] ?? '');
    $ts = $raw !== '' ? strtotime($raw) : false;
    return is_int($ts) ? $ts : 0;
}

function ar_order_is_paid(array $order): bool
{
    $checkout = strtolower(ar_s($order['checkout_status'] ?? ''));
    $fulfillment = strtolower(ar_s($order['fulfillment_status'] ?? ''));
    return $checkout === 'paid' || in_array($fulfillment, ['fulfilled', 'provider_failed'], true);
}

function ar_order_in_date_range(array $order, string $dateFrom, string $dateTo): bool
{
    $ts = ar_order_time($order);
    if ($ts <= 0) {
        return false;
    }
    if ($dateFrom !== '' && $ts < strtotime($dateFrom . ' 00:00:00')) {
        return false;
    }
    if ($dateTo !== '' && $ts > strtotime($dateTo . ' 23:59:59')) {
        return false;
    }
    return true;
}

function ar_order_item_category(array $item): string
{
    $category = strtolower(ar_s($item['provider_pool_category'] ?? $item['proxy_category'] ?? $item['category'] ?? $item['proxy_api_type'] ?? ''));
    $category = str_replace('-', '_', $category);
    return $category !== '' ? $category : 'other';
}

function ar_order_item_gb(array $item): float
{
    foreach (['traffic_gb', 'traffic_total_gb', 'traffic', 'gb'] as $key) {
        $value = ar_float($item[$key] ?? null);
        if ($value > 0.0) {
            return $value;
        }
    }
    return 0.0;
}

function ar_order_nested_services(array $order): array
{
    $services = isset($order['services']) && is_array($order['services']) ? $order['services'] : [];
    $rows = [];
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }
        $service['user_id'] = $service['user_id'] ?? ($order['user_id'] ?? '');
        $service['created_at'] = $service['created_at'] ?? ($order['paid_at'] ?? $order['created_at'] ?? '');
        $rows[] = $service;
    }
    return $rows;
}

function ar_service_gb(array $service): float
{
    foreach (['traffic_total_gb', 'traffic_limit_gb', 'traffic_gb', 'gb'] as $key) {
        $value = ar_float($service[$key] ?? null);
        if ($value > 0.0) {
            return $value;
        }
    }
    return 0.0;
}

function ar_record_in_date_range(array $row, string $dateFrom, string $dateTo): bool
{
    $raw = ar_s($row['created_at'] ?? $row['paid_at'] ?? '');
    if ($raw === '') {
        return $dateFrom === '' && $dateTo === '';
    }
    $ts = strtotime($raw);
    if (!is_int($ts)) {
        return $dateFrom === '' && $dateTo === '';
    }
    if ($dateFrom !== '' && $ts < strtotime($dateFrom . ' 00:00:00')) {
        return false;
    }
    if ($dateTo !== '' && $ts > strtotime($dateTo . ' 23:59:59')) {
        return false;
    }
    return true;
}

function ar_status_label(string $status): string
{
    return match ($status) {
        'actual' => 'активный',
        'archive' => 'архивный',
        'delete' => 'удаленный',
        default => $status !== '' ? $status : '-',
    };
}

function ar_category_label(string $category): string
{
    return match ($category) {
        'mobile' => 'mobile',
        'residential' => 'residential',
        'residential_ipv6' => 'residential ipv6',
        'isp' => 'isp',
        'dc' => 'dc',
        'dc_shared' => 'dc shared',
        'scraper' => 'scraper',
        default => str_replace('_', ' ', $category),
    };
}

function ar_json_block(mixed $value): string
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return '<pre class="pm-admin-json">' . ar_h($json === false ? (string)$value : $json) . '</pre>';
}

function ar_service_select(array $services, string $name = 'service_id', string $selected = ''): string
{
    $html = '<select class="form-select" name="' . ar_h($name) . '">';
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }
        $id = ar_s($service['service_id'] ?? '');
        if ($id === '') {
            continue;
        }
        $label = ar_s($service['title'] ?? 'Service') . ' - user ' . ar_s($service['user_id'] ?? '-') . ' - ' . ar_s($service['provider_pool_category'] ?? '-') . ' - ' . ar_s($service['vendor_package_key'] ?? '');
        $html .= '<option value="' . ar_h($id) . '"' . ($id === $selected ? ' selected' : '') . '>' . ar_h($label) . '</option>';
    }
    return $html . '</select>';
}

function ar_admin_action_form(string $title, string $action, string $body, string $button = 'Run'): void
{
    echo '<section class="pm-admin-tool"><h3>' . ar_h($title) . '</h3><form method="post" action="/admin/provider">'
        . '<input type="hidden" name="action" value="' . ar_h($action) . '">'
        . $body
        . '<button class="btn btn-primary btn-sm" type="submit">' . ar_h($button) . '</button>'
        . '</form></section>';
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;
$groups = is_array($users->user_group) ? $users->user_group : [];
if ($userId <= 0 || !isset($groups['admin'])) {
    http_response_code(403);
    Sogerien::Page()->title = 'Access denied';
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger">Admin access required.</div></main>';
    Sogerien::Page()->footer();
    return;
}

$path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
$pageKey = match ($path) {
    'admin/orders' => 'orders',
    'admin/statistics' => 'statistics',
    'admin/services' => 'services',
    'admin/traffic' => 'traffic',
    'admin/access-lists' => 'access',
    'admin/billing' => 'billing',
    'admin/guard' => 'guard',
    default => 'provider',
};

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$guardResult = null;
$passwordResetResult = null;
$trafficTopupResult = null;
$adminActionResult = null;
$prefillClientUserId = (int)ar_s($_GET['client_user_id'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ar_s((Sogerien::InputRequest()->request_post_get_cookie_json['action'] ?? '')) === 'run_usage_guard') {
    $guardResult = $shop->run_usage_guard();
}
$post = Sogerien::InputRequest()->request_post_get_cookie_json;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ar_s(($post['action'] ?? '')) === 'admin_add_traffic') {
    $clientUserId = (int)ar_s($post['client_user_id'] ?? 0);
    $serviceId = ar_s($post['service_id'] ?? '');
    $addGb = (float)str_replace(',', '.', ar_s($post['add_gb'] ?? '0'));
    if ($clientUserId <= 0 || $serviceId === '' || $addGb <= 0.0) {
        $trafficTopupResult = ['ok' => false, 'message' => 'Client, service and GB amount are required.'];
    } else {
        $result = $shop->service_action($clientUserId, $serviceId, 'add_traffic', [
            'add_gb' => (string)$addGb,
            'resume_after_topup' => '1',
        ]);
        $trafficTopupResult = [
            'ok' => ($result['ok'] ?? false) === true,
            'message' => ($result['ok'] ?? false) === true ? 'Traffic added.' : ar_s($result['error'] ?? 'Traffic topup failed.'),
        ];
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ar_s(($post['action'] ?? '')) === 'reset_client_password') {
    $clientUserId = (int)ar_s($post['client_user_id'] ?? 0);
    $clientIdentity = ar_s($post['client_identity'] ?? '');
    $newPassword = (string)($post['new_password'] ?? '');
    $resetUsers = Sogerien::Users();
    $resetUsers->init_db_alias($dbAlias);

    if ($clientUserId > 0) {
        $client = $resetUsers->get_user_for_edit($clientUserId);
        if (is_array($client)) {
            $clientIdentity = ar_s($client['email'] ?? '') !== '' ? ar_s($client['email'] ?? '') : ar_s($client['login'] ?? '');
        }
    }

    if ($clientIdentity === '') {
        $passwordResetResult = ['ok' => false, 'message' => 'Client email/login not found.'];
    } elseif (mb_strlen($newPassword) < 8) {
        $passwordResetResult = ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    } else {
        $ok = $resetUsers->reset_password($clientIdentity, $newPassword);
        $passwordResetResult = [
            'ok' => $ok,
            'message' => $ok ? 'Password changed for ' . $clientIdentity . '.' : ($resetUsers->error !== '' ? $resetUsers->error : 'Password update failed.'),
        ];
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $adminActions = [
        'admin_service_action' => true,
        'create_traffic_package' => true,
        'create_static_package' => true,
        'provider_balance' => true,
        'provider_catalog' => true,
        'ip_block_check' => true,
        'ip_unblock' => true,
        'scraper_test' => true,
        'proxy_test' => true,
        'integration_snippet' => true,
        'client_api_diagnostics' => true,
        'raw_api' => true,
    ];
    $postedAction = ar_s($post['action'] ?? '');
    if (isset($adminActions[$postedAction])) {
        $adminActionResult = $shop->admin_provider_action($userId, is_array($post) ? $post : []);
    }
}
$services = $shop->list_all_services();
$orders = $shop->list_all_orders();
$payments = $shop->list_all_payments();
$totals = $shop->reseller_totals();
$statsDateFrom = ar_date_value($_GET['date_from'] ?? '');
$statsDateTo = ar_date_value($_GET['date_to'] ?? '');

$titleMap = [
    'provider' => ['Provider Dashboard', 'Mobile, residential, ISP and reseller available-to-sell state.'],
    'orders' => ['Proxy Orders', 'Paid orders, pending fulfillment, provider failures and fulfilled orders.'],
    'statistics' => ['Статистика', 'Сводка по клиентам, статусам и купленным типам прокси.'],
    'services' => ['Client Services', 'All client mobile, residential, ISP and scraper services.'],
    'traffic' => ['Traffic', 'Usage graph source data, consumption speed and cron sync state.'],
    'access' => ['Access Lists', 'All generated proxy logins and provider list ids.'],
    'billing' => ['Billing', 'Client revenue, provider cost, profit, topups and renewals.'],
    'guard' => ['Guard', 'Oversell protection, auto suspend, auto resume and provider API errors.'],
];

Sogerien::Page()->title = $titleMap[$pageKey][0];
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui admin-reseller-page">
    <style>
        .pm-admin-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
        .pm-admin-head h1{font-size:28px;margin:0 0 6px}
        .pm-admin-head p{margin:0;color:var(--muted)}
        .pm-admin-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-admin-kpi{font-size:22px;font-weight:700}
        .pm-admin-section{margin-bottom:16px}
        .admin-reseller-page .card,
        .pm-admin-panel{border:1px solid var(--line);background:var(--surface);color:var(--text);box-shadow:var(--shadow)}
        .admin-reseller-page .card{border-radius:8px}
        .pm-resale-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-resale-kpi{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:14px 16px;color:var(--text);box-shadow:var(--shadow)}
        .pm-resale-kpi-label{font-size:12px;color:var(--muted);margin-bottom:6px}
        .pm-resale-kpi-value{font-size:26px;line-height:1.15;font-weight:800;color:var(--text)}
        .pm-resale-kpi-note{font-size:12px;color:var(--muted);margin-top:6px}
        .pm-resale-kpi.pm-low .pm-resale-kpi-value{color:var(--danger)}
        .pm-resale-products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-resale-product{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:14px 16px;color:var(--text);box-shadow:var(--shadow)}
        .pm-resale-product-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px}
        .pm-resale-product-title{font-weight:700;color:var(--text)}
        .pm-resale-product-state{font-size:12px;color:var(--muted);text-align:right}
        .pm-resale-product-main{font-size:22px;font-weight:800;color:var(--text);margin-bottom:8px}
        .pm-resale-product.pm-low .pm-resale-product-main{color:var(--danger)}
        .pm-resale-product-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;color:var(--muted)}
        .pm-resale-product-grid strong{display:block;color:var(--text);font-size:14px}
        .pm-reset-card{border:1px solid var(--line);background:var(--surface);border-radius:8px;padding:14px 16px;margin-bottom:16px;color:var(--text);box-shadow:var(--shadow)}
        .pm-reset-card form{display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) auto;gap:10px;align-items:end}
        .pm-reset-card label{font-size:12px;color:var(--muted);margin-bottom:4px}
        .pm-reset-inline{display:flex;gap:6px;align-items:center;min-width:230px}
        .pm-reset-inline .form-control{width:150px}
        .pm-autopay-ok{display:inline-grid;place-items:center;width:26px;height:26px;border-radius:999px;background:#16a34a;color:#fff;font-size:18px;font-weight:900;line-height:1;box-shadow:0 6px 14px rgba(22,163,74,.28)}
        .pm-autopay-empty{display:inline-grid;place-items:center;width:26px;height:26px;color:var(--muted);font-weight:700}
        .pm-user-actions{display:flex;align-items:center;gap:8px;white-space:nowrap}
        .pm-admin-head-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pm-admin-tools{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:16px 0}
        .pm-admin-tool{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:14px;color:var(--text);box-shadow:var(--shadow)}
        .pm-admin-tool h3{font-size:15px;margin:0 0 10px;color:var(--text)}
        .pm-admin-tool form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;align-items:end}
        .pm-admin-tool .full{grid-column:1/-1}
        .pm-admin-tool label{font-size:12px;color:var(--muted);margin-bottom:3px}
        .pm-admin-tool label.full{display:flex;align-items:center;gap:8px;color:var(--muted)}
        .pm-admin-json{max-height:420px;overflow:auto;background:#0f172a;color:#e2e8f0;border-radius:8px;padding:12px;font-size:12px}
        .pm-stat-filter{border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:14px 16px;margin-bottom:16px;color:var(--text);box-shadow:var(--shadow)}
        .pm-stat-filter form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
        .pm-stat-filter label{font-size:12px;color:var(--muted);margin-bottom:4px}
        .pm-stat-totals{margin-top:16px;border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:14px 16px;box-shadow:var(--shadow);overflow:auto}
        .pm-stat-totals h2{font-size:16px;margin:0 0 10px;color:var(--text)}
        .pm-stat-totals table{width:100%;border-collapse:collapse;min-width:620px}
        .pm-stat-totals th,.pm-stat-totals td{border-top:1px solid var(--line);padding:8px 10px;text-align:right;white-space:nowrap}
        .pm-stat-totals th:first-child,.pm-stat-totals td:first-child{text-align:left}
        .pm-stat-totals th{font-size:12px;color:var(--muted);font-weight:600}
        .pm-stat-totals td{font-weight:700;color:var(--text)}
        @media(max-width:1100px){.pm-resale-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:900px){.pm-admin-kpis,.pm-resale-products,.pm-admin-tools{grid-template-columns:1fr 1fr}.pm-admin-head{display:block}}
        @media(max-width:720px){.pm-resale-summary{grid-template-columns:1fr 1fr}.pm-reset-card form{grid-template-columns:1fr}.pm-reset-inline{min-width:0}}
        @media(max-width:560px){.pm-admin-kpis,.pm-resale-summary,.pm-resale-products,.pm-admin-tools,.pm-admin-tool form{grid-template-columns:1fr}}
    </style>
    <div class="pm-admin-head">
        <div>
            <h1><?= ar_h($titleMap[$pageKey][0]) ?></h1>
            <p><?= ar_h($titleMap[$pageKey][1]) ?></p>
        </div>
        <div class="pm-admin-head-actions">
            <a class="btn btn-outline-primary" href="/admin/statistics">Статистика</a>
            <a class="btn btn-outline-secondary" href="/admin/support/tickets">Support tickets</a>
        </div>
    </div>
    <div class="pm-admin-kpis">
        <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Services</div><div class="pm-admin-kpi"><?= (int)$totals['services'] ?></div></div></section>
        <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Revenue</div><div class="pm-admin-kpi">$<?= ar_h(number_format((float)$totals['revenue'], 2)) ?></div></div></section>
        <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Provider cost</div><div class="pm-admin-kpi">$<?= ar_h(number_format((float)$totals['cost'], 2)) ?></div></div></section>
        <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Profit</div><div class="pm-admin-kpi">$<?= ar_h(number_format((float)$totals['profit'], 2)) ?></div></div></section>
    </div>
    <?php if (is_array($passwordResetResult)): ?>
        <div class="alert <?= $passwordResetResult['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= ar_h($passwordResetResult['message']) ?></div>
    <?php endif; ?>
    <?php if (is_array($trafficTopupResult)): ?>
        <div class="alert <?= $trafficTopupResult['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= ar_h($trafficTopupResult['message']) ?></div>
    <?php endif; ?>
    <?php if (is_array($adminActionResult)): ?>
        <div class="alert <?= ($adminActionResult['ok'] ?? false) ? 'alert-success' : 'alert-danger' ?>">
            <div class="fw-semibold"><?= ($adminActionResult['ok'] ?? false) ? 'Provider action completed.' : ar_h($adminActionResult['error'] ?? 'Provider action failed.') ?></div>
            <?= ar_json_block($adminActionResult) ?>
        </div>
    <?php endif; ?>
    <?php if ($pageKey === 'password'): ?>
        <section class="pm-reset-card" aria-label="Client password reset">
            <form method="post" action="/<?= ar_h($path) ?>">
                <input type="hidden" name="action" value="reset_client_password">
                <?php if ($prefillClientUserId > 0): ?>
                    <input type="hidden" name="client_user_id" value="<?= (int)$prefillClientUserId ?>">
                <?php endif; ?>
                <div>
                    <label for="pm-client-identity">Client login or email</label>
                    <input id="pm-client-identity" class="form-control" type="text" name="client_identity" placeholder="<?= $prefillClientUserId > 0 ? 'User #' . (int)$prefillClientUserId : 'email or login' ?>">
                </div>
                <div>
                    <label for="pm-client-password">New password</label>
                    <input id="pm-client-password" class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                </div>
                <button class="btn btn-primary" type="submit">Set password</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($pageKey === 'provider'): ?>
        <?php
        echo '<section class="pm-admin-section"><h2 class="h5 mb-3">Provider operations console</h2><div class="pm-admin-tools">';
        ar_admin_action_form('Issue traffic package', 'create_traffic_package',
            '<div><label>Client user id</label><input class="form-control" name="client_user_id" type="number" min="1"></div>'
            . '<div><label>Attach service</label>' . ar_service_select($services) . '</div>'
            . '<div><label>Type</label><select class="form-select" name="category"><option value="mobile">mobile</option><option value="residential">residential</option><option value="residential_ipv6">residential_ipv6</option></select></div>'
            . '<div><label>Traffic GB</label><input class="form-control" name="traffic_gb" type="number" min="0.01" step="0.01" required></div>'
            . '<div><label>Country</label><input class="form-control" name="country" placeholder="US"></div>'
            . '<div><label>Expires at</label><input class="form-control" name="expires_at" type="text" inputmode="none" autocomplete="off" placeholder="YYYY-MM-DD HH:mm:ss" data-pm-datetime-picker required></div>'
            . '<div class="full"><label>Title</label><input class="form-control" name="title" placeholder="Admin issued package"></div>',
            'Create'
        );
        ar_admin_action_form('Service lifecycle / traffic / access', 'admin_service_action',
            '<div class="full"><label>Service</label>' . ar_service_select($services) . '</div>'
            . '<div><label>Action</label><select class="form-select" name="provider_action">'
            . '<option value="refresh_traffic">sync now</option><option value="add_traffic">add traffic</option><option value="set_traffic_limit">set traffic limit</option><option value="prolongate">prolongate</option>'
            . '<option value="suspend">suspend</option><option value="resume">resume</option><option value="deactivate">deactivate</option><option value="cancel">cancel</option><option value="uncancel">uncancel</option>'
            . '<option value="generate_proxy_list">generate access list</option><option value="update_proxy_list">update access list</option><option value="regenerate_proxy_password">regenerate password</option><option value="view_proxy_list">provider viewlist</option><option value="disable_proxy_list">remove access list</option><option value="api_tool_access">API-tool access</option>'
            . '</select></div>'
            . '<div><label>GB / limit</label><input class="form-control" name="add_gb" type="number" min="0" step="0.01" placeholder="for add"></div>'
            . '<div><label>Exact limit GB</label><input class="form-control" name="limit_gb" type="number" min="0" step="0.01"></div>'
            . '<div><label>Expires at</label><input class="form-control" name="expires_at" type="text" inputmode="none" autocomplete="off" placeholder="YYYY-MM-DD HH:mm:ss" data-pm-datetime-picker></div>'
            . '<div><label>List id</label><input class="form-control" name="list_id"></div>'
            . '<div><label>List name</label><input class="form-control" name="list_name"></div>'
            . '<div><label>Auth</label><select class="form-select" name="auth_mode"><option value="login_password">login/password</option><option value="ip_whitelist">IP whitelist</option></select></div>'
            . '<div><label>Login</label><input class="form-control" name="login"></div>'
            . '<div><label>Password</label><input class="form-control" name="password"></div>'
            . '<div><label>IP/CIDR whitelist</label><input class="form-control" name="network" placeholder="1.2.3.4/32"></div>'
            . '<div><label>Country</label><input class="form-control" name="country" placeholder="US"></div>'
            . '<div><label>Region</label><input class="form-control" name="region"></div>'
            . '<div><label>City</label><input class="form-control" name="city"></div>'
            . '<div><label>ISP / ASN</label><input class="form-control" name="isp"></div>'
            . '<div><label>ZIP</label><input class="form-control" name="zip"></div>'
            . '<div><label>Rotation seconds</label><input class="form-control" name="rotation_period" type="number" min="0" step="1"></div>'
            . '<div><label>Format</label><input class="form-control" name="format" placeholder="3 http, 4 socks5"></div>'
            . '<label class="full"><input type="checkbox" name="resume_after_topup" value="1" checked> auto-resume after topup</label>',
            'Execute'
        );
        ar_admin_action_form('ISP/DC procurement', 'create_static_package',
            '<div><label>Type</label><select class="form-select" name="category"><option value="isp">isp</option><option value="dc">dc</option></select></div>'
            . '<div><label>Country</label><input class="form-control" name="country" placeholder="US" required></div>'
            . '<div><label>IP count</label><input class="form-control" name="ip_count" type="number" min="1" value="1"></div>',
            'Create'
        );
        ar_admin_action_form('Provider balance / keys', 'provider_balance',
            '<div><label>Type</label><select class="form-select" name="category"><option value="mobile">mobile</option><option value="residential">residential</option><option value="residential_ipv6">residential_ipv6</option><option value="isp">isp</option><option value="dc">dc</option></select></div>',
            'Load'
        );
        ar_admin_action_form('Geo targeting catalog', 'provider_catalog',
            '<div><label>Type</label><select class="form-select" name="category"><option value="mobile">mobile</option><option value="residential">residential</option><option value="residential_ipv6">residential_ipv6</option><option value="isp">isp</option><option value="dc">dc</option></select></div>'
            . '<div><label>Method</label><select class="form-select" name="catalog_method"><option value="geos">geos</option><option value="detailed_geos">detailed_geos</option><option value="ipv6_detailed_geos">ipv6_detailed_geos</option><option value="subdivision_codes">subdivision_codes</option><option value="isp_codes">isp_codes</option><option value="zip_codes">zip_codes</option><option value="geo_db">geo_db</option><option value="online_statistics">online_statistics</option><option value="countries">countries</option><option value="online_nodes">online_nodes</option></select></div>'
            . '<div><label>Country for ZIP</label><input class="form-control" name="country" placeholder="US"></div>',
            'Load'
        );
        ar_admin_action_form('IP block diagnostics', 'ip_block_check',
            '<div><label>Type</label><select class="form-select" name="category"><option value="residential">residential</option><option value="mobile">mobile</option></select></div>'
            . '<div><label>IP</label><input class="form-control" name="ip" required></div>'
            . '<div><label>Mode</label><select class="form-select" name="block_action"><option value="ip_block_check">check</option><option value="ip_unblock">unblock</option></select></div>',
            'Run'
        );
        ar_admin_action_form('Scraper API console', 'scraper_test',
            '<div><label>Method</label><select class="form-select" name="scraper_method"><option value="scrape">scrape</option><option value="render">render</option><option value="serp">serp</option><option value="chatgpt">chatgpt</option><option value="gemini">gemini</option><option value="perplexity">perplexity</option></select></div>'
            . '<div><label>URL</label><input class="form-control" name="url" placeholder="https://example.com"></div>'
            . '<div class="full"><label>Query for AI</label><input class="form-control" name="query"></div>'
            . '<div class="full"><label>Payload JSON</label><textarea class="form-control" name="payload_json" rows="4"></textarea></div>',
            'Test'
        );
        ar_admin_action_form('Proxy health / snippets', 'proxy_test',
            '<div><label>Proxy URL</label><input class="form-control" name="proxy_url" placeholder="http://login:pass@host:port"></div>'
            . '<div><label>Target URL</label><input class="form-control" name="target_url" value="http://ip-api.com/json"></div>'
            . '<div><label>Login</label><input class="form-control" name="login"></div>'
            . '<div><label>Password</label><input class="form-control" name="password"></div>'
            . '<div><label>Country/city/session</label><input class="form-control" name="country" placeholder="US"></div>',
            'Test'
        );
        ar_admin_action_form('Curl / integration snippets', 'integration_snippet',
            '<div><label>Login</label><input class="form-control" name="login" required></div>'
            . '<div><label>Password</label><input class="form-control" name="password" required></div>'
            . '<div><label>Country</label><input class="form-control" name="country" placeholder="US"></div>'
            . '<div><label>City / ISP</label><input class="form-control" name="city"></div>'
            . '<div class="full"><label>Target URL</label><input class="form-control" name="target_url" value="https://example.com"></div>',
            'Generate'
        );
        ar_admin_action_form('Client API diagnostics', 'client_api_diagnostics',
            '<div><label>Type</label><select class="form-select" name="category"><option value="residential">residential</option><option value="residential_ipv6">residential_ipv6</option><option value="mobile">mobile</option><option value="dc">dc</option></select></div>'
            . '<div><label>PID/package</label><input class="form-control" name="pid"></div>'
            . '<div><label>Login</label><input class="form-control" name="login"></div>'
            . '<div><label>Country</label><input class="form-control" name="country" placeholder="US"></div>',
            'Check'
        );
        ar_admin_action_form('Raw API allowlist', 'raw_api',
            '<div><label>Scope</label><select class="form-select" name="scope"><option value="mobile">mobile</option><option value="residential">residential</option><option value="residential_ipv6">residential_ipv6</option><option value="isp">isp</option><option value="dc">dc</option></select></div>'
            . '<div><label>Method</label><input class="form-control" name="method_name" placeholder="package_info"></div>'
            . '<div><label>Arg 1</label><input class="form-control" name="arg1" placeholder="package_key"></div>'
            . '<div><label>Arg 2</label><input class="form-control" name="arg2" placeholder="period"></div>',
            'Call'
        );
        echo '</div></section>';

        $inventoryRows = $shop->reseller_provider_inventory($services);
        $trafficRows = [];
        $trafficTotals = [
            'provider_limit' => 0.0,
            'provider_used' => 0.0,
            'provider_left' => 0.0,
            'client_sold' => 0.0,
            'client_used' => 0.0,
            'client_left' => 0.0,
            'available_to_sell' => 0.0,
        ];
        $providerRows = [];
        foreach ($inventoryRows as $row) {
            $available = $row['available_to_sell_gb'] ?? null;
            if ($available !== null) {
                $providerLimit = (float)($row['provider_limit_gb'] ?? 0.0);
                $providerUsed = (float)($row['provider_used_gb'] ?? 0.0);
                $providerLeft = (float)($row['provider_left_gb'] ?? 0.0);
                $clientSold = (float)($row['client_sold_gb'] ?? 0.0);
                $clientUsed = (float)($row['client_used_gb'] ?? 0.0);
                $clientLeft = (float)($row['client_left_gb'] ?? 0.0);
                $availableFloat = (float)$available;
                $trafficTotals['provider_limit'] += $providerLimit;
                $trafficTotals['provider_used'] += $providerUsed;
                $trafficTotals['provider_left'] += $providerLeft;
                $trafficTotals['client_sold'] += $clientSold;
                $trafficTotals['client_used'] += $clientUsed;
                $trafficTotals['client_left'] += $clientLeft;
                $trafficTotals['available_to_sell'] += $availableFloat;
                $trafficRows[] = [
                    'product' => ar_s($row['product'] ?? '-'),
                    'provider_limit' => $providerLimit,
                    'provider_used' => $providerUsed,
                    'provider_left' => $providerLeft,
                    'client_sold' => $clientSold,
                    'client_used' => $clientUsed,
                    'client_left' => $clientLeft,
                    'available_to_sell' => $availableFloat,
                    'state' => ar_s($row['provider_state'] ?? '-'),
                ];
            }
            $keys = isset($row['provider_keys']) && is_array($row['provider_keys']) ? $row['provider_keys'] : [];
            $providerRows[] = [
                'product' => ar_s($row['product'] ?? '-'),
                'provider_limit' => ar_gb($row['provider_limit_gb'] ?? null),
                'provider_used' => ar_gb($row['provider_used_gb'] ?? null),
                'provider_left' => ar_gb($row['provider_left_gb'] ?? null),
                'client_sold' => ar_gb($row['client_sold_gb'] ?? null),
                'client_used' => ar_gb($row['client_used_gb'] ?? null),
                'client_left' => ar_gb($row['client_left_gb'] ?? null),
                'available_to_sell' => ($row['available_to_sell_gb'] ?? null) === null ? '-' : ar_gb($row['available_to_sell_gb']),
                'provider_state' => ar_s($row['provider_state'] ?? '-'),
                'package_keys_html' => $keys === [] ? '-' : '<code>' . ar_h(implode(', ', array_map('strval', $keys))) . '</code>',
                'alert' => ar_s($row['alert'] ?? '-'),
            ];
        }
        ?>
        <section class="pm-admin-section" aria-label="Traffic left for resale">
            <div class="pm-resale-summary">
                <div class="pm-resale-kpi <?= $trafficTotals['available_to_sell'] <= 0.05 ? 'pm-low' : '' ?>">
                    <div class="pm-resale-kpi-label">Traffic left for resale</div>
                    <div class="pm-resale-kpi-value"><?= ar_h(ar_gb($trafficTotals['available_to_sell'])) ?></div>
                    <div class="pm-resale-kpi-note">Safe capacity after sold client limits</div>
                </div>
                <div class="pm-resale-kpi">
                    <div class="pm-resale-kpi-label">Provider traffic bought</div>
                    <div class="pm-resale-kpi-value"><?= ar_h(ar_gb($trafficTotals['provider_limit'])) ?></div>
                    <div class="pm-resale-kpi-note">Live API /stats total</div>
                </div>
                <div class="pm-resale-kpi">
                    <div class="pm-resale-kpi-label">Provider traffic left</div>
                    <div class="pm-resale-kpi-value"><?= ar_h(ar_gb($trafficTotals['provider_left'])) ?></div>
                    <div class="pm-resale-kpi-note">Used <?= ar_h(ar_gb($trafficTotals['provider_used'])) ?></div>
                </div>
                <div class="pm-resale-kpi">
                    <div class="pm-resale-kpi-label">Client ordered traffic</div>
                    <div class="pm-resale-kpi-value"><?= ar_h(ar_gb($trafficTotals['client_sold'])) ?></div>
                    <div class="pm-resale-kpi-note">All sold client limits</div>
                </div>
                <div class="pm-resale-kpi">
                    <div class="pm-resale-kpi-label">Client traffic used</div>
                    <div class="pm-resale-kpi-value"><?= ar_h(ar_gb($trafficTotals['client_used'])) ?></div>
                    <div class="pm-resale-kpi-note">Client left <?= ar_h(ar_gb($trafficTotals['client_left'])) ?></div>
                </div>
            </div>
            <div class="pm-resale-products">
                <?php foreach ($trafficRows as $trafficRow): ?>
                    <article class="pm-resale-product <?= ((float)$trafficRow['available_to_sell']) <= 0.05 ? 'pm-low' : '' ?>">
                        <div class="pm-resale-product-head">
                            <div class="pm-resale-product-title"><?= ar_h($trafficRow['product']) ?></div>
                            <div class="pm-resale-product-state"><?= ar_h($trafficRow['state']) ?></div>
                        </div>
                        <div class="pm-resale-product-main"><?= ar_h(ar_gb($trafficRow['available_to_sell'])) ?> for resale</div>
                        <div class="pm-resale-product-grid">
                            <div>Provider bought<strong><?= ar_h(ar_gb($trafficRow['provider_limit'])) ?></strong></div>
                            <div>Provider left<strong><?= ar_h(ar_gb($trafficRow['provider_left'])) ?></strong></div>
                            <div>Provider used<strong><?= ar_h(ar_gb($trafficRow['provider_used'])) ?></strong></div>
                            <div>Client ordered<strong><?= ar_h(ar_gb($trafficRow['client_sold'])) ?></strong></div>
                            <div>Client used<strong><?= ar_h(ar_gb($trafficRow['client_used'])) ?></strong></div>
                            <div>Client left<strong><?= ar_h(ar_gb($trafficRow['client_left'])) ?></strong></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        ar_table('admin_provider_grid', $providerRows, ['product', 'provider_limit', 'provider_used', 'provider_left', 'client_sold', 'client_used', 'client_left', 'available_to_sell', 'provider_state', 'package_keys_html', 'alert'], [
            'provider_limit' => 'Provider traffic bought',
            'provider_used' => 'Provider traffic used',
            'provider_left' => 'Provider traffic left',
            'client_sold' => 'Client ordered',
            'client_used' => 'Client used',
            'client_left' => 'Client left',
            'available_to_sell' => 'Traffic left for resale',
            'package_keys_html' => 'Provider package',
        ]);
        $serviceRows = [];
        foreach ($services as $service) {
            $limit = (float)ar_s($service['traffic_total_gb'] ?? 0);
            $used = (float)ar_s($service['traffic_used_gb'] ?? 0);
            $left = (float)ar_s($service['traffic_remaining_gb'] ?? 0);
            if ($left <= 0.0 && $limit > 0.0) {
                $left = max(0.0, $limit - $used);
            }
            $serviceRows[] = [
                'service' => ar_s($service['title'] ?? '-'),
                'user_id' => ar_s($service['user_id'] ?? '-'),
                'type' => ar_s($service['provider_pool_category'] ?? $service['proxy_category'] ?? '-'),
                'status' => ar_s($service['status'] ?? '-'),
                'package_key_html' => '<code>' . ar_h(ar_s($service['vendor_package_key'] ?? '-')) . '</code>',
                'ordered' => ar_gb($limit),
                'used' => ar_gb($used),
                'left' => ar_gb($left),
                'updated' => ar_s($service['traffic_updated_at'] ?? $service['updated_at'] ?? '-'),
                'actions' => ar_reset_password_form($service['user_id'] ?? 0, '/' . $path),
            ];
        }
        echo '<h2 class="h5 mt-4 mb-3">Client traffic by service</h2>';
        ar_table('admin_provider_services_grid', $serviceRows, ['service', 'user_id', 'type', 'status', 'package_key_html', 'ordered', 'used', 'left', 'updated', 'actions'], [
            'package_key_html' => 'Provider package',
            'ordered' => 'Client ordered',
            'used' => 'Client used',
            'left' => 'Client left',
            'actions' => 'Password',
        ]);
        $auditRows = [];
        foreach ($shop->provider_audit_log(50) as $audit) {
            $auditRows[] = [
                'created' => ar_s($audit['created_at'] ?? '-'),
                'admin' => ar_s($audit['admin_user_id'] ?? '-'),
                'action' => ar_s($audit['action'] ?? '-'),
                'ok' => (($audit['result']['ok'] ?? false) === true) ? 'yes' : 'no',
                'details_html' => ar_json_block($audit['result'] ?? []),
            ];
        }
        echo '<h2 class="h5 mt-4 mb-3">Provider API audit</h2>';
        ar_table('admin_provider_audit_grid', $auditRows, ['created', 'admin', 'action', 'ok', 'details_html'], [
            'details_html' => 'Result',
        ]);
        ?>
    <?php elseif ($pageKey === 'orders'): ?>
        <?php
        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                'created' => ar_s($order['created_at'] ?? '-'),
                'order_id' => '<code>' . ar_h(ar_s($order['order_id'] ?? '-')) . '</code>',
                'user_id' => ar_s($order['user_id'] ?? '-'),
                'checkout_status' => ar_s($order['checkout_status'] ?? '-'),
                'fulfillment_status' => ar_s($order['fulfillment_status'] ?? '-'),
                'amount' => ar_s($order['amount_usd'] ?? '0.00') . ' ' . strtoupper(ar_s($order['currency'] ?? 'USD')),
            ];
        }
        ar_table('admin_orders_grid', $rows, ['created', 'order_id', 'user_id', 'checkout_status', 'fulfillment_status', 'amount'], [
            'checkout_status' => 'Checkout Status',
            'fulfillment_status' => 'Fulfillment Status',
        ], [
            ['title' => 'Checkout Status', 'column' => 'checkout_status', 'type' => 'dropdown_multi', 'slot' => 'side', 'search' => true],
        ]);
        ?>
    <?php elseif ($pageKey === 'statistics'): ?>
        <section class="pm-stat-filter">
            <form method="get" action="/admin/statistics">
                <div>
                    <label for="pm-stats-date-from">Дата с</label>
                    <input id="pm-stats-date-from" class="form-control" type="date" name="date_from" value="<?= ar_h($statsDateFrom) ?>">
                </div>
                <div>
                    <label for="pm-stats-date-to">Дата по</label>
                    <input id="pm-stats-date-to" class="form-control" type="date" name="date_to" value="<?= ar_h($statsDateTo) ?>">
                </div>
                <button class="btn btn-primary" type="submit">Показать</button>
                <a class="btn btn-outline-secondary" href="/admin/statistics">Сбросить</a>
            </form>
        </section>
        <?php
        $statsUsers = Sogerien::Users();
        $statsUsers->init_db_alias($dbAlias);
        $userRows = $statsUsers->get_users_list('all');
        $categorySet = [
            'mobile' => true,
            'residential' => true,
            'residential_ipv6' => true,
            'isp' => true,
            'dc' => true,
            'scraper' => true,
        ];
        $userMap = [];
        $stats = [];
        foreach ($userRows as $userRow) {
            $uid = (int)($userRow['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $fullUserRow = $statsUsers->get_user_by_id($uid);
            $userValue = is_array($fullUserRow) ? ($fullUserRow['table_value'] ?? []) : [];
            if (is_string($userValue)) {
                $decoded = json_decode($userValue, true);
                $userValue = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($userValue)) {
                $userValue = [];
            }
            $userMap[$uid] = [
                'name' => ar_s($userRow['name'] ?? ''),
                'email' => ar_s($userRow['email'] ?? ''),
                'login' => ar_s($userRow['login'] ?? ''),
                'status' => ar_status_label(ar_s($userRow['status'] ?? '')),
                'balance_usd' => $shop->get_user_balance_usd($uid),
                'stripe_customer_id' => ar_s($userValue['stripe_customer_id'] ?? ''),
                'payment_methods' => is_array($userValue['payment_methods'] ?? null) ? $userValue['payment_methods'] : [],
                'billing_autopay_enabled' => ar_s($userValue['billing_autopay_enabled'] ?? ''),
                'billing_default_payment_method_id' => ar_s($userValue['billing_default_payment_method_id'] ?? ''),
                'categories' => [],
            ];
        }
        foreach ($orders as $order) {
            if (!is_array($order) || !ar_order_is_paid($order) || !ar_order_in_date_range($order, $statsDateFrom, $statsDateTo)) {
                continue;
            }
            $uid = (int)ar_s($order['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if (!isset($stats[$uid])) {
                $stats[$uid] = $userMap[$uid] ?? ['name' => '', 'email' => '', 'login' => '', 'status' => '-', 'balance_usd' => '0.00', 'stripe_customer_id' => '', 'payment_methods' => [], 'billing_autopay_enabled' => '', 'billing_default_payment_method_id' => '', 'categories' => []];
            }
            $items = isset($order['items']) && is_array($order['items']) ? $order['items'] : [];
            if ($items === []) {
                $items = [[
                    'provider_pool_category' => $order['provider_pool_category'] ?? $order['proxy_category'] ?? 'other',
                    'price_usd' => $order['amount_usd'] ?? 0,
                ]];
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $category = ar_order_item_category($item);
                $categorySet[$category] = true;
                if (!isset($stats[$uid]['categories'][$category])) {
                    $stats[$uid]['categories'][$category] = ['amount' => 0.0, 'gb' => 0.0];
                }
                $stats[$uid]['categories'][$category]['amount'] += ar_float($item['price_usd'] ?? $item['amount_usd'] ?? 0);
            }
            foreach (ar_order_nested_services($order) as $service) {
                if (!ar_record_in_date_range($service, $statsDateFrom, $statsDateTo)) {
                    continue;
                }
                $category = ar_order_item_category($service);
                $categorySet[$category] = true;
                if (!isset($stats[$uid]['categories'][$category])) {
                    $stats[$uid]['categories'][$category] = ['amount' => 0.0, 'gb' => 0.0];
                }
                $stats[$uid]['categories'][$category]['gb'] += ar_service_gb($service);
            }
        }
        foreach ($services as $service) {
            if (!is_array($service) || !ar_record_in_date_range($service, $statsDateFrom, $statsDateTo)) {
                continue;
            }
            $uid = (int)ar_s($service['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if (!isset($stats[$uid])) {
                $stats[$uid] = $userMap[$uid] ?? ['name' => '', 'email' => '', 'login' => '', 'status' => '-', 'balance_usd' => '0.00', 'stripe_customer_id' => '', 'payment_methods' => [], 'billing_autopay_enabled' => '', 'billing_default_payment_method_id' => '', 'categories' => []];
            }
            $category = ar_order_item_category($service);
            $categorySet[$category] = true;
            if (!isset($stats[$uid]['categories'][$category])) {
                $stats[$uid]['categories'][$category] = ['amount' => 0.0, 'gb' => 0.0];
            }
            $stats[$uid]['categories'][$category]['gb'] += ar_service_gb($service);
        }
        $categoryOrder = array_values(array_unique(array_merge(
            ['mobile', 'residential', 'residential_ipv6', 'isp', 'dc', 'scraper'],
            array_keys($categorySet)
        )));
        $columns = ['email_html', 'name_html', 'login_html', 'status', 'balance_usd', 'autopay_html'];
        $headers = [
            'email_html' => 'email',
            'name_html' => 'ФИО пользователя',
            'login_html' => 'логин',
            'status' => 'статус',
            'balance_usd' => 'balance $',
            'autopay_html' => 'автопродление',
        ];
        foreach ($categoryOrder as $category) {
            $amountKey = $category . '_amount';
            $gbKey = $category . '_gb';
            $columns[] = $amountKey;
            $columns[] = $gbKey;
            $headers[$amountKey] = ar_category_label($category) . ' $';
            $headers[$gbKey] = ar_category_label($category) . ' gb';
        }
        $rows = [];
        $statsTotals = [];
        $balanceTotal = 0.0;
        foreach ($stats as $statsUserId => $stat) {
            $statsUserId = (int)$statsUserId;
            $hasPeriodData = false;
            $row = [
                'email_html' => ar_user_account_link($statsUserId, $stat['email'] ?? ''),
                'name_html' => ar_user_account_link($statsUserId, $stat['name'] ?? ''),
                'login_html' => ar_user_account_link($statsUserId, $stat['login'] ?? ''),
                'status' => ar_s($stat['status'] ?? ''),
                'balance_usd' => number_format(ar_float($stat['balance_usd'] ?? 0), 2, '.', ''),
                'autopay_html' => ar_autopay_badge($stat),
            ];
            foreach ($categoryOrder as $category) {
                $amount = (float)($stat['categories'][$category]['amount'] ?? 0.0);
                $gb = (float)($stat['categories'][$category]['gb'] ?? 0.0);
                if (abs($amount) > 0.000001 || abs($gb) > 0.000001) {
                    $hasPeriodData = true;
                }
                $row[$category . '_amount'] = number_format($amount, 2, '.', '');
                $row[$category . '_gb'] = number_format($gb, 2, '.', '');
                $statsTotals[$category]['amount'] = (float)($statsTotals[$category]['amount'] ?? 0.0) + $amount;
                $statsTotals[$category]['gb'] = (float)($statsTotals[$category]['gb'] ?? 0.0) + $gb;
            }
            if (!$hasPeriodData) {
                continue;
            }
            $balanceTotal += ar_float($stat['balance_usd'] ?? 0);
            $rows[] = $row;
        }
        ar_table('admin_statistics_grid', $rows, $columns, $headers, [
            ['title' => 'статус', 'column' => 'status', 'type' => 'dropdown_multi', 'slot' => 'side', 'search' => true],
        ]);
        ?>
        <section class="pm-stat-totals" aria-label="Statistics totals">
            <h2>Итого за период</h2>
            <table>
                <thead>
                    <tr>
                        <th>Услуга</th>
                        <th>Сумма $</th>
                        <th>Трафик gb</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows !== []): ?>
                        <tr>
                            <td>balance</td>
                            <td><?= ar_h(number_format($balanceTotal, 2, '.', '')) ?></td>
                            <td>-</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($categoryOrder as $category): ?>
                        <?php
                        $amountTotal = (float)($statsTotals[$category]['amount'] ?? 0.0);
                        $gbTotal = (float)($statsTotals[$category]['gb'] ?? 0.0);
                        if (abs($amountTotal) <= 0.000001 && abs($gbTotal) <= 0.000001) {
                            continue;
                        }
                        ?>
                        <tr>
                            <td><?= ar_h(ar_category_label($category)) ?></td>
                            <td><?= ar_h(number_format($amountTotal, 2, '.', '')) ?></td>
                            <td><?= ar_h(number_format($gbTotal, 2, '.', '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="3">Нет данных за выбранный период</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($pageKey === 'services' || $pageKey === 'traffic'): ?>
        <?php if ($pageKey === 'traffic'): ?>
            <section class="card shadow-sm pm-admin-section">
                <div class="card-header">Add client traffic</div>
                <div class="card-body">
                    <form method="post" action="/admin/traffic" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="admin_add_traffic">
                        <div class="col-md-6">
                            <label class="form-label" for="arTopupService">Service</label>
                            <select class="form-select" id="arTopupService" name="service_id" required>
                                <?php foreach ($services as $service): ?>
                                    <?php
                                    $category = strtolower(ar_s($service['provider_pool_category'] ?? ''));
                                    if (!in_array($category, ['mobile', 'residential', 'residential_ipv6'], true)) {
                                        continue;
                                    }
                                    $serviceKey = ar_s($service['service_id'] ?? '');
                                    if ($serviceKey === '') {
                                        $serviceKey = ar_s($service['vendor_package_key'] ?? $service['package_key'] ?? $service['title'] ?? '');
                                    }
                                    $clientUserId = (int)ar_s($service['user_id'] ?? 0);
                                    if ($serviceKey === '' || $clientUserId <= 0) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= ar_h($serviceKey) ?>" data-user-id="<?= (int)$clientUserId ?>">
                                        <?= ar_h('User #' . $clientUserId . ' - ' . ar_s($service['title'] ?? $serviceKey) . ' - ' . ar_s($service['country'] ?? '-')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="arTopupUser">User ID</label>
                            <input class="form-control" id="arTopupUser" name="client_user_id" inputmode="numeric" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="arTopupGb">GB</label>
                            <input class="form-control" id="arTopupGb" type="number" name="add_gb" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary" type="submit">Topup</button>
                        </div>
                    </form>
                    <script>
                        (() => {
                            const service = document.getElementById('arTopupService');
                            const user = document.getElementById('arTopupUser');
                            const sync = () => {
                                if (service && user) {
                                    user.value = service.selectedOptions[0]?.dataset.userId || '';
                                }
                            };
                            service?.addEventListener('change', sync);
                            sync();
                        })();
                    </script>
                </div>
            </section>
        <?php endif; ?>
        <?php
        $rows = [];
        foreach ($services as $service) {
            $limit = (float)ar_s($service['traffic_total_gb'] ?? 0);
            $used = (float)ar_s($service['traffic_used_gb'] ?? 0);
            $left = (float)ar_s($service['traffic_remaining_gb'] ?? 0);
            $rows[] = [
                'service' => ar_s($service['title'] ?? '-'),
                'user_id' => ar_s($service['user_id'] ?? '-'),
                'type' => ar_s($service['provider_pool_category'] ?? '-'),
                'country' => ar_s($service['country'] ?? '-'),
                'status' => ar_s($service['status'] ?? '-'),
                'package_key' => '<code>' . ar_h(ar_s($service['vendor_package_key'] ?? '-')) . '</code>',
                'limit_gb' => number_format($limit, 2, '.', ''),
                'used_gb' => number_format($used, 2, '.', ''),
                'left_gb' => number_format($left, 2, '.', ''),
                'expires' => ar_s($service['expires_at'] ?? '-'),
                'actions' => $pageKey === 'traffic'
                    ? ar_traffic_topup_form($service, '/' . $path)
                    : ar_reset_password_form($service['user_id'] ?? 0, '/' . $path),
            ];
        }
        ar_table($pageKey === 'traffic' ? 'admin_traffic_grid' : 'admin_services_grid', $rows, ['service', 'user_id', 'type', 'country', 'status', 'package_key', 'limit_gb', 'used_gb', 'left_gb', 'expires', 'actions'], [
            'actions' => $pageKey === 'traffic' ? 'Topup' : 'Password',
        ]);
        ?>
    <?php elseif ($pageKey === 'access'): ?>
        <?php
        $rows = [];
        foreach ($services as $service) {
            $lists = isset($service['proxy_lists']) && is_array($service['proxy_lists']) ? $service['proxy_lists'] : [];
            foreach ($lists as $list) {
                if (!is_array($list)) {
                    continue;
                }
                $rows[] = [
                    'service' => ar_s($service['title'] ?? '-'),
                    'user_id' => ar_s($service['user_id'] ?? '-'),
                    'list_id' => ar_s($list['vendor_list_id'] ?? $list['id'] ?? '-'),
                    'name' => ar_s($list['name'] ?? '-'),
                    'login' => ar_s($list['login'] ?? '-'),
                    'country' => ar_s($list['country'] ?? '-'),
                    'rotation' => ar_s($list['rotation_period'] ?? '0'),
                    'status' => ar_s($list['status'] ?? '-'),
                    'actions' => ar_reset_password_form($service['user_id'] ?? 0, '/' . $path),
                ];
            }
        }
        ar_table('admin_access_grid', $rows, ['service', 'user_id', 'list_id', 'name', 'login', 'country', 'rotation', 'status', 'actions'], [
            'actions' => 'Password',
        ]);
        ?>
    <?php elseif ($pageKey === 'billing'): ?>
        <?php
        $rows = [];
        foreach ($payments as $payment) {
            $rows[] = [
                'created' => ar_s($payment['created_at'] ?? '-'),
                'payment_id' => '<code>' . ar_h(ar_s($payment['payment_id'] ?? '-')) . '</code>',
                'order_id' => ar_s($payment['order_id'] ?? '-'),
                'user_id' => ar_s($payment['user_id'] ?? '-'),
                'status' => ar_s($payment['payment_status'] ?? $payment['status'] ?? '-'),
                'amount' => ar_s($payment['amount_usd'] ?? '0.00') . ' ' . strtoupper(ar_s($payment['currency'] ?? 'USD')),
            ];
        }
        ar_table('admin_billing_grid', $rows, ['created', 'payment_id', 'order_id', 'user_id', 'status', 'amount']);
        ?>
    <?php else: ?>
        <?php if (is_array($guardResult)): ?>
            <div class="alert alert-info">Guard checked <?= (int)$guardResult['checked'] ?> services, suspended <?= (int)$guardResult['suspended'] ?>, errors <?= (int)$guardResult['errors'] ?>.</div>
        <?php endif; ?>
        <section class="card shadow-sm pm-admin-section">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-semibold">Usage guard</div>
                    <div class="text-muted small">Suspends mobile/residential services when used traffic is greater than or equal to limit.</div>
                </div>
                <form method="post" action="/admin/guard">
                    <input type="hidden" name="action" value="run_usage_guard">
                    <button type="submit" class="btn btn-warning">Run guard now</button>
                </form>
            </div>
        </section>
        <?php
        $rows = [];
        foreach ($services as $service) {
            $limit = (float)ar_s($service['traffic_total_gb'] ?? 0);
            $used = (float)ar_s($service['traffic_used_gb'] ?? 0);
            $left = (float)ar_s($service['traffic_remaining_gb'] ?? 0);
            $rows[] = [
                'service' => ar_s($service['title'] ?? '-'),
                'user_id' => ar_s($service['user_id'] ?? '-'),
                'status' => ar_s($service['status'] ?? '-'),
                'guard_rule' => $limit > 0.0 && $used >= $limit ? 'Suspend now' : 'OK',
                'left_gb' => number_format($left, 2, '.', ''),
                'last_sync' => ar_s($service['traffic_updated_at'] ?? '-'),
                'provider_error' => ar_s($service['provider_error'] ?? $service['disable_reason'] ?? '-'),
            ];
        }
        ar_table('admin_guard_grid', $rows, ['service', 'user_id', 'status', 'guard_rule', 'left_gb', 'last_sync', 'provider_error']);
        ?>
    <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
