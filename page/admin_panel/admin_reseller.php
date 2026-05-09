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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ar_s((Sogerien::InputRequest()->request_post_get_cookie_json['action'] ?? '')) === 'run_usage_guard') {
    $guardResult = $shop->run_usage_guard();
}
$post = Sogerien::InputRequest()->request_post_get_cookie_json;
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
$services = $shop->list_all_services();
$orders = $shop->list_all_orders();
$payments = $shop->list_all_payments();
$totals = $shop->reseller_totals();

$titleMap = [
    'provider' => ['Provider Dashboard', 'Mobile, residential, ISP and reseller available-to-sell state.'],
    'orders' => ['Proxy Orders', 'Paid orders, pending fulfillment, provider failures and fulfilled orders.'],
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
        .pm-admin-head p{margin:0;color:#64748b}
        .pm-admin-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-admin-kpi{font-size:22px;font-weight:700}
        .pm-admin-section{margin-bottom:16px}
        .pm-resale-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-resale-kpi{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px 16px}
        .pm-resale-kpi-label{font-size:12px;color:#64748b;margin-bottom:6px}
        .pm-resale-kpi-value{font-size:26px;line-height:1.15;font-weight:800;color:#0f172a}
        .pm-resale-kpi-note{font-size:12px;color:#64748b;margin-top:6px}
        .pm-resale-kpi.pm-low .pm-resale-kpi-value{color:#b91c1c}
        .pm-resale-products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-resale-product{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px 16px}
        .pm-resale-product-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px}
        .pm-resale-product-title{font-weight:700;color:#0f172a}
        .pm-resale-product-state{font-size:12px;color:#64748b;text-align:right}
        .pm-resale-product-main{font-size:22px;font-weight:800;color:#0f172a;margin-bottom:8px}
        .pm-resale-product.pm-low .pm-resale-product-main{color:#b91c1c}
        .pm-resale-product-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;color:#64748b}
        .pm-resale-product-grid strong{display:block;color:#0f172a;font-size:14px}
        .pm-reset-card{border:1px solid #dbeafe;background:#eff6ff;border-radius:8px;padding:14px 16px;margin-bottom:16px}
        .pm-reset-card form{display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) auto;gap:10px;align-items:end}
        .pm-reset-card label{font-size:12px;color:#475569;margin-bottom:4px}
        .pm-reset-inline{display:flex;gap:6px;align-items:center;min-width:230px}
        .pm-reset-inline .form-control{width:150px}
        @media(max-width:1100px){.pm-resale-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:900px){.pm-admin-kpis,.pm-resale-products{grid-template-columns:1fr 1fr}.pm-admin-head{display:block}}
        @media(max-width:720px){.pm-resale-summary{grid-template-columns:1fr 1fr}.pm-reset-card form{grid-template-columns:1fr}.pm-reset-inline{min-width:0}}
        @media(max-width:560px){.pm-admin-kpis,.pm-resale-summary,.pm-resale-products{grid-template-columns:1fr}}
    </style>
    <div class="pm-admin-head">
        <div>
            <h1><?= ar_h($titleMap[$pageKey][0]) ?></h1>
            <p><?= ar_h($titleMap[$pageKey][1]) ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/support/tickets">Support tickets</a>
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
    <section class="pm-reset-card" aria-label="Client password reset">
        <form method="post" action="/<?= ar_h($path) ?>">
            <input type="hidden" name="action" value="reset_client_password">
            <div>
                <label for="pm-client-identity">Client login or email</label>
                <input id="pm-client-identity" class="form-control" type="text" name="client_identity" placeholder="email or login">
            </div>
            <div>
                <label for="pm-client-password">New password</label>
                <input id="pm-client-password" class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="8" required>
            </div>
            <button class="btn btn-primary" type="submit">Set password</button>
        </form>
    </section>

    <?php if ($pageKey === 'provider'): ?>
        <?php
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
    <?php elseif ($pageKey === 'services' || $pageKey === 'traffic'): ?>
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
                'actions' => ar_reset_password_form($service['user_id'] ?? 0, '/' . $path),
            ];
        }
        ar_table($pageKey === 'traffic' ? 'admin_traffic_grid' : 'admin_services_grid', $rows, ['service', 'user_id', 'type', 'country', 'status', 'package_key', 'limit_gb', 'used_gb', 'left_gb', 'expires', 'actions'], [
            'actions' => 'Password',
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
                ];
            }
        }
        ar_table('admin_access_grid', $rows, ['service', 'user_id', 'list_id', 'name', 'login', 'country', 'rotation', 'status']);
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
