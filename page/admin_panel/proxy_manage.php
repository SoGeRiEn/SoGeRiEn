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
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/client/proxy/manage');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '/client/proxy/manage');
    if (!str_starts_with($requestPath, '/') || str_starts_with($requestPath, '//')) {
        $requestUri = '/client/proxy/manage';
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
        <?php $category = strtolower(pm_s($service['provider_pool_category'] ?? '')); ?>
        <?php $isTrafficService = $category === 'mobile' || $category === 'residential' || $category === 'residential_ipv6'; ?>
        <?php $isStaticIpService = $category === 'isp' || $category === 'dc'; ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h1 class="h4 mb-1"><?= pm_h($service['title'] ?? '') ?></h1>
                        <div class="text-muted small">Service ID: <code><?= pm_h($serviceId) ?></code></div>
                        <div class="text-muted small">Provider pool: <code><?= pm_h($service['provider_pool_category'] ?? '-') ?></code></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($isTrafficService): ?>
                            <form method="post" action="/client/proxy/manage">
                                <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                                <input type="hidden" name="action" value="refresh_traffic">
                                <button type="submit" class="btn btn-outline-primary">Refresh traffic</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/client/proxy/manage">
                            <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                            <input type="hidden" name="action" value="<?= pm_s($service['status'] ?? '') === 'suspended' ? 'resume' : 'suspend' ?>">
                            <button type="submit" class="btn btn-outline-warning"><?= pm_s($service['status'] ?? '') === 'suspended' ? 'Resume' : 'Suspend' ?></button>
                        </form>
                        <form method="post" action="/client/proxy/manage">
                            <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                            <input type="hidden" name="action" value="deactivate">
                            <button type="submit" class="btn btn-outline-danger">Deactivate</button>
                        </form>
                        <a class="btn btn-outline-secondary" href="/client/my/proxies">Back to My Proxies</a>
                    </div>
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
                    <div class="col-md-6">
                        <div class="small text-muted">Disable reason</div>
                        <div><?= pm_h($service['disable_reason'] ?? '-') ?></div>
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
                    <?php if ($isStaticIpService): ?>
                        <div class="col-md-3">
                            <div class="small text-muted">IP count</div>
                            <div><?= pm_h($service['ip_count'] ?? '-') ?></div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-3">
                            <div class="small text-muted">Traffic limit</div>
                            <div><?= pm_h($service['traffic_total_gb'] ?? '-') ?> GB</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Traffic used</div>
                            <div><?= pm_h($service['traffic_used_gb'] ?? '0.00') ?> GB</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Traffic left</div>
                            <div><?= pm_h($service['traffic_remaining_gb'] ?? $service['traffic_remains'] ?? '-') ?> GB</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Traffic updated</div>
                            <div><?= pm_h($service['traffic_updated_at'] ?? '-') ?></div>
                        </div>
                    <?php endif; ?>
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

        <?php if ($isTrafficService): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header">Traffic controls</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <form method="post" action="/client/proxy/manage" class="row g-2 align-items-end">
                                <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                                <input type="hidden" name="action" value="add_traffic">
                                <div class="col-md-5">
                                    <label class="form-label" for="pmAddGb">Add traffic, GB</label>
                                    <input class="form-control" id="pmAddGb" name="add_gb" type="number" min="0.01" step="0.01" value="1">
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" id="pmResumeAfterTopup" name="resume_after_topup" type="checkbox" value="1" checked>
                                        <label class="form-check-label" for="pmResumeAfterTopup">Resume after topup</label>
                                    </div>
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button type="submit" class="btn btn-primary">Top up</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-lg-6">
                            <form method="post" action="/client/proxy/manage" class="row g-2 align-items-end">
                                <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                                <input type="hidden" name="action" value="set_traffic_limit">
                                <div class="col-md-4">
                                    <label class="form-label" for="pmLimitGb">New limit, GB</label>
                                    <input class="form-control" id="pmLimitGb" name="limit_gb" type="number" min="0.01" step="0.01" value="<?= pm_h($service['traffic_total_gb'] ?? '1') ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="pmExpiresAt">Expires at</label>
                                    <input class="form-control" id="pmExpiresAt" name="expires_at" value="<?= pm_h($service['expires_at'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button type="submit" class="btn btn-outline-primary">Set limit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($isStaticIpService): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header"><?= $category === 'dc' ? 'Dedicated DC lifecycle' : 'ISP lifecycle' ?></div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <form method="post" action="/client/proxy/manage">
                        <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-warning">Cancel renewal</button>
                    </form>
                    <?php if ($category === 'isp'): ?>
                        <form method="post" action="/client/proxy/manage">
                            <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                            <input type="hidden" name="action" value="uncancel">
                            <button type="submit" class="btn btn-outline-primary">Uncancel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-3">
            <div class="card-header">Generate proxy access list</div>
            <div class="card-body">
                <?php if ($isStaticIpService): ?>
                    <div class="alert alert-secondary mb-0"><?= $category === 'dc' ? 'Dedicated DC' : 'ISP' ?> services are managed as country + IP count. Traffic access lists are not used for this product.</div>
                <?php elseif (pm_s($service['vendor_package_key'] ?? '') === ''): ?>
                    <div class="alert alert-warning mb-0">Provider package is not active yet. Generation will be available after provider activation.</div>
                <?php else: ?>
                    <form method="post" action="/client/proxy/manage" class="row g-3 align-items-end">
                        <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                        <input type="hidden" name="action" value="generate_proxy_list">
                        <div class="col-md-3">
                            <label class="form-label" for="pmListName">List name</label>
                            <input class="form-control" id="pmListName" name="list_name" value="ProxyMint <?= pm_h(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmAuthMode">Authorization</label>
                            <select class="form-select" id="pmAuthMode" name="auth_mode">
                                <option value="login_password">Login / password</option>
                                <option value="ip_whitelist">IP whitelist</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmListLogin">Login</label>
                            <input class="form-control" id="pmListLogin" name="login" placeholder="Auto">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmListPassword">Password</label>
                            <input class="form-control" id="pmListPassword" name="password" placeholder="Auto">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pmListNetwork">IP whitelist</label>
                            <input class="form-control" id="pmListNetwork" name="network" placeholder="1.2.3.4, 5.6.7.0/24">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pmListCountries">Countries</label>
                            <input class="form-control" id="pmListCountries" name="countries" value="<?= pm_h($service['country'] ?? '') ?>" placeholder="US, DE, NL">
                            <div class="form-text">Leave empty for World Mix.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pmListRegion">Region</label>
                            <input class="form-control" id="pmListRegion" name="region" placeholder="California">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmListCity">City</label>
                            <input class="form-control" id="pmListCity" name="city" placeholder="New York">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmListIsp">ISP</label>
                            <input class="form-control" id="pmListIsp" name="isp" placeholder="All or provider id">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmListZip">ZIP code</label>
                            <input class="form-control" id="pmListZip" name="zip" placeholder="10001">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="pmRotation">Rotation</label>
                            <select class="form-select" id="pmRotation" name="rotation_period">
                                <option value="0">Each request</option>
                                <option value="-1">Sticky</option>
                                <option value="300">5 minutes</option>
                                <option value="600">10 minutes</option>
                                <option value="900">15 minutes</option>
                                <option value="1200">20 minutes</option>
                                <option value="1800">30 minutes</option>
                                <option value="2400">40 minutes</option>
                                <option value="3000">50 minutes</option>
                                <option value="3600">60 minutes</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="pmRotationMode">Failure mode</label>
                            <select class="form-select" id="pmRotationMode" name="rotation_mode">
                                <option value="0">Instant</option>
                                <option value="1">5 seconds</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pmListFormat">Proxy format</label>
                            <select class="form-select" id="pmListFormat" name="format">
                                <option value="1">login:password@host:port</option>
                                <option value="2">host,port,login,password</option>
                                <option value="3">http://login:password@host:port</option>
                                <option value="4">socks5://login:password@host:port</option>
                                <option value="5">login:password:host:port</option>
                                <option value="6">host:port:login:password</option>
                                <option value="7">login@password@host@port</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary">Generate proxy list</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php $proxyLists = isset($service['proxy_lists']) && is_array($service['proxy_lists']) ? $service['proxy_lists'] : []; ?>
        <?php if ($proxyLists !== []): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header">Generated proxy lists</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Country</th>
                                    <th>Protocol</th>
                                    <th>Login</th>
                                    <th>Password</th>
                                    <th>Status</th>
                                    <th>Used</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_reverse($proxyLists) as $list): ?>
                                <?php if (!is_array($list)) { continue; } ?>
                                <tr>
                                    <td><?= pm_h($list['name'] ?? '-') ?></td>
                                    <td>
                                        <?= pm_h($list['country'] ?? '-') ?>
                                        <?php $targetBits = array_filter([
                                            pm_s($list['region'] ?? ''),
                                            pm_s($list['city'] ?? ''),
                                            pm_s($list['isp_id'] ?? $list['isp'] ?? ''),
                                            pm_s($list['zip'] ?? ''),
                                        ]); ?>
                                        <?php if ($targetBits !== []): ?>
                                            <div class="small text-muted"><?= pm_h(implode(' / ', $targetBits)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= pm_h($list['protocol'] ?? '-') ?></td>
                                    <td><code><?= pm_h($list['login'] ?? '-') ?></code></td>
                                    <td><code><?= pm_h($list['password'] ?? '-') ?></code></td>
                                    <td><?= pm_h($list['status'] ?? 'active') ?></td>
                                    <td><?= pm_h($list['traffic_used_gb'] ?? '0.0000') ?> GB</td>
                                    <td><?= pm_h($list['created_at'] ?? '-') ?></td>
                                    <td>
                                        <?php if (pm_s($list['status'] ?? 'active') === 'active'): ?>
                                            <form method="post" action="/client/proxy/manage" class="m-0">
                                                <input type="hidden" name="service_id" value="<?= pm_h($serviceId) ?>">
                                                <input type="hidden" name="action" value="disable_proxy_list">
                                                <input type="hidden" name="list_id" value="<?= pm_h($list['vendor_list_id'] ?? $list['id'] ?? '') ?>">
                                                <input type="hidden" name="list_name" value="<?= pm_h($list['name'] ?? '') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Disable</button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
