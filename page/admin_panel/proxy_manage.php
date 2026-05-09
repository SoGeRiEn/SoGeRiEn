<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function pm_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pm_s(mixed $value): string
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
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/proxy/manage');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '/proxy/manage');
    if (!str_starts_with($requestPath, '/') || str_starts_with($requestPath, '//')) {
        $requestUri = '/proxy/manage';
    }

    if (!isset($_GET['next']) || trim((string)$_GET['next']) === '') {
        $_GET['next'] = $requestUri;
    }

    require __DIR__ . '/login_form_v2.php';
    Sogerien::markDone();
    return;
}

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);

$serviceId = pm_s($request['service_id'] ?? '');
$alertType = '';
$alertText = '';
$actionDump = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $userId > 0) {
    $serviceId = pm_s($request['service_id'] ?? '');
    $action = pm_s($request['action'] ?? '');
    if ($serviceId !== '' && $action !== '') {
        $result = $shop->service_action($userId, $serviceId, $action, is_array($request) ? $request : []);
        $actionDump = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (($result['ok'] ?? false) === true) {
            $alertType = 'success';
            $alertText = 'Action completed.';
        } else {
            $alertType = 'danger';
            $alertText = (string)($result['error'] ?? 'Action failed.');
        }
    }
}

$service = null;
if ($userId > 0 && $serviceId !== '') {
    foreach ($shop->list_user_services($userId) as $row) {
        if (is_array($row) && pm_s($row['service_id'] ?? '') === $serviceId) {
            $service = $row;
            break;
        }
    }
}

Sogerien::Page()->title = 'Manage Proxy';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= pm_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= pm_h($alertText) ?></div>
    <?php endif; ?>

    <?php if ($serviceId === ''): ?>
        <div class="alert alert-secondary" role="alert">Open this page with `service_id` from My Proxies.</div>
    <?php elseif (!is_array($service)): ?>
        <div class="alert alert-danger" role="alert">Service not found.</div>
    <?php else: ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h1 class="h4 mb-1"><?= pm_h($service['title'] ?? '') ?></h1>
                        <div class="text-muted small">Service ID: <code><?= pm_h($serviceId) ?></code></div>
                        <div class="text-muted small">Vendor ID: <code><?= pm_h($service['vendor_history_id'] ?? '-') ?></code></div>
                    </div>
                    <a class="btn btn-outline-secondary" href="/my/proxies">Back to My Proxies</a>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted">Host</div>
                        <div><?= pm_h($service['connection_host'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="small text-muted">Port</div>
                        <div><?= pm_h($service['connection_port'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Login</div>
                        <div><?= pm_h($service['connection_login'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Password</div>
                        <div><?= pm_h($service['connection_password'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Country</div>
                        <div><?= pm_h($service['country'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Status</div>
                        <div><?= pm_h($service['status'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Expires</div>
                        <div><?= pm_h($service['expires_at'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Auto-renew</div>
                        <div><?= !empty($service['auto_renew_request']) ? 'On' : 'Off' ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Public IP</div>
                        <div><?= pm_h($service['public_ipaddress'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Traffic remains</div>
                        <div><?= pm_h($service['traffic_remains'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">OVPN config</div>
                        <div>
                            <?php $ovpn = pm_s($service['ovpn_config_link'] ?? ''); ?>
                            <?php if ($ovpn !== ''): ?>
                                <a href="<?= pm_h($ovpn) ?>" target="_blank" rel="noopener noreferrer"><?= pm_h($ovpn) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="small text-muted">Xray settings</div>
                        <pre class="mb-0"><?= pm_h($service['xray_settings_str'] ?? '-') ?></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ([
                'restart' => ['label' => 'Restart proxy', 'class' => 'btn-warning'],
                (!empty($service['auto_renew_request']) ? 'auto_renew_off' : 'auto_renew_on') => ['label' => !empty($service['auto_renew_request']) ? 'Disable auto-renew' : 'Enable auto-renew', 'class' => 'btn-primary'],
                'reboot_modem' => ['label' => 'Reboot modem', 'class' => 'btn-danger'],
            ] as $action => $meta): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-grid">
                            <form method="post" action="/proxy/manage">
                                <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                                <input type="hidden" name="action" value="<?= pm_h($action) ?>">
                                <button type="submit" class="btn <?= pm_h($meta['class']) ?> w-100"><?= pm_h($meta['label']) ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($actionDump !== ''): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header">Last action response</div>
                <div class="card-body">
                    <pre class="mb-0"><?= pm_h($actionDump) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
