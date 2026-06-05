<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function mp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mp_s(mixed $value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;

if ($userId <= 0) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/client/my/proxies');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '/client/my/proxies');
    if (!str_starts_with($requestPath, '/') || str_starts_with($requestPath, '//')) {
        $requestUri = '/client/my/proxies';
    }

    if (!isset($_GET['next']) || trim((string)$_GET['next']) === '') {
        $_GET['next'] = $requestUri;
    }

    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);

$alertType = '';
$alertText = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $userId > 0) {
    $serviceId = mp_s($request['service_id'] ?? '');
    $action = mp_s($request['action'] ?? '');
    if ($serviceId !== '' && $action !== '') {
        if ($action === 'add_traffic' || $action === 'set_traffic_limit') {
            $alertType = 'danger';
            $alertText = 'Traffic is added only through purchase or admin panel.';
        } else {
            $actionResult = $shop->service_action($userId, $serviceId, $action, is_array($request) ? $request : []);
            if (($actionResult['ok'] ?? false) === true) {
                $alertType = 'success';
                $alertText = 'Action completed.';
            } else {
                $alertType = 'danger';
                $alertText = (string)($actionResult['error'] ?? 'Action failed.');
            }
        }
    }
}

$balanceUsd = $userId > 0 ? $shop->get_user_balance_usd($userId) : '0.00';
$services = $userId > 0 ? $shop->list_user_services($userId) : [];

$tableRows = [];
foreach ($services as $service) {
    if (!is_array($service)) {
        continue;
    }

    $serviceId = mp_s($service['service_id'] ?? '');
    $actionsHtml = '<div class="d-flex flex-wrap gap-1">'
        . '<a class="pm-pill-btn is-active" href="/client/proxy/manage?service_id=' . mp_h(rawurlencode($serviceId)) . '">Manage</a>';
    $actionsHtml .= '</div>';

    $tableRows[] = [
        'title' => mp_s($service['title'] ?? ''),
        'order_id' => mp_s($service['order_id'] ?? ''),
        'password' => mp_s($service['connection_password'] ?? '-'),
        'country' => mp_s($service['country'] ?? '-'),
        'status' => mp_s($service['status'] ?? '-'),
        'expires_at' => mp_s($service['expires_at'] ?? '-'),
        'traffic' => mp_s($service['traffic_remaining_gb'] ?? $service['traffic_remains'] ?? '-'),
        'traffic_used' => mp_s($service['traffic_used_gb'] ?? '0.00'),
        'traffic_total' => mp_s($service['traffic_total_gb'] ?? '-'),
        'actions_html' => $actionsHtml,
    ];
}

Sogerien::Page()->title = 'My Proxies';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <style>
        [id="my_proxies_grid__tbl_wrapper"] .dt-buttons .btn-excel,
        [id="my_proxies_grid__tbl_wrapper"] .dt-buttons .btn-print {
            display: none !important;
        }
    </style>

    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= mp_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= mp_h($alertText) ?></div>
    <?php endif; ?>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-muted small">Client</div>
                        <div class="h5 mb-0">User #<?= (int)$userId ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Balance USD</div>
                        <div class="h5 mb-0"><?= mp_h($balanceUsd) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Services</div>
                        <div class="h5 mb-0"><?= count($services) ?></div>
                    </div>
                    <div>
                        <a class="btn btn-primary" href="/client/all_proxy">Buy more</a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($tableRows === []): ?>
            <div class="alert alert-secondary" role="alert">No purchased services yet.</div>
        <?php else: ?>
            <?php
            $columns = ['actions_html', 'title', 'password', 'country', 'status', 'expires_at', 'traffic_total', 'traffic_used', 'traffic'];
            $headers = [
                'title' => 'Title',
                'password' => 'Password',
                'country' => 'Country',
                'status' => 'Status',
                'expires_at' => 'Expires',
                'traffic_total' => 'Traffic limit',
                'traffic_used' => 'Traffic used',
                'traffic' => 'Traffic left',
                'actions_html' => 'Actions',
            ];

            $tr = Sogerien::TableRenderer();
            $tr->set_params = new SetParams();
            $tr->set_params->data = $tableRows;
            $tr->set_params->columns = $columns;
            $tr->set_params->headers = $headers;
            $tr->set_params->gridId = 'my_proxies_grid';
            $tr->set_params->searchCols = $columns;
            $tr->set_params->perPage = 100;
            $tr->set_params->columnsOrder = $columns;
            $tr->set_params->autoHideEmptyCols = false;

            $defaultVisibleColumns = [
                'title' => true,
                'country' => true,
                'status' => true,
                'expires_at' => true,
                'traffic_total' => true,
                'traffic_used' => true,
                'traffic' => true,
                'actions_html' => true,
            ];
            foreach ($columns as $columnName) {
                $tr->set_params->column_view[$columnName]['visible'] = isset($defaultVisibleColumns[$columnName]);
            }
            $tr->set_params->column_view['title'] = [
                'width' => '260px',
                'ellipsis' => true,
                'visible' => true,
            ];
            $tr->set_params->column_view['actions_html'] = [
                'visible' => true,
            ];

            $facets = [];
            if (in_array('status', $columns, true)) {
                $facets[] = ['title' => 'Status', 'column' => 'status', 'type' => 'dropdown_multi', 'slot' => 'side', 'search' => true];
            }
            $tr->set_params->facets = $facets;

            $tr->set_params->formatters['title'] = static function ($value, array $row): string {
                return '<div class="fw-semibold">' . mp_h($value) . '</div><div class="small text-muted">Order: ' . mp_h((string)($row['order_id'] ?? '')) . '</div>';
            };
            $tr->set_params->formatters['actions_html'] = static function ($value): string {
                return (string)$value;
            };

            $tr->render();
            ?>
        <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
