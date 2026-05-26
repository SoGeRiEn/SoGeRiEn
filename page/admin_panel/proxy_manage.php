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

$isAjaxRequest = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
if ($isAjaxRequest && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $userId > 0) {
    $ajaxAction = pm_s($request['action'] ?? '');
    $ajaxServiceId = pm_s($request['service_id'] ?? '');
    if ($ajaxAction === 'view_proxy_list' && $ajaxServiceId !== '') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $result = $shop->service_action($userId, $ajaxServiceId, 'view_proxy_list', is_array($request) ? $request : []);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Sogerien::markDone();
        return;
    }
    if (($ajaxAction === 'geo_regions' || $ajaxAction === 'geo_cities') && $ajaxServiceId !== '') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $targetService = null;
        foreach ($shop->list_user_services($userId) as $row) {
            if (is_array($row) && pm_s($row['service_id'] ?? '') === $ajaxServiceId) {
                $targetService = $row;
                break;
            }
        }
        $values = [];
        if (is_array($targetService)) {
            $targetCategory = pm_s($targetService['provider_pool_category'] ?? '');
            $targetCountry = pm_s($request['country'] ?? '');
            $values = $ajaxAction === 'geo_cities'
                ? $shop->infatica_access_cities($targetCategory, $targetCountry, pm_s($request['region'] ?? ''))
                : $shop->infatica_access_regions($targetCategory, $targetCountry);
        }
        echo json_encode(['ok' => true, 'values' => $values], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Sogerien::markDone();
        return;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $userId > 0) {
    $serviceId = pm_s($request['service_id'] ?? '');
    $action = pm_s($request['action'] ?? '');
    if ($serviceId !== '' && $action !== '') {
        if ($action === 'add_traffic' || $action === 'set_traffic_limit') {
            $alertType = 'danger';
            $alertText = 'Traffic is added only through purchase or admin panel.';
        } else {
            $result = $shop->service_action($userId, $serviceId, $action, is_array($request) ? $request : []);
            if (($result['ok'] ?? false) === true) {
                $alertType = 'success';
                $alertText = $action === 'generate_proxy_list' ? 'Proxy access list generated.' : 'Action completed.';
            } else {
                $alertType = 'danger';
                $alertText = (string)($result['error'] ?? 'Action failed.');
            }
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

Sogerien::Page()->title = 'Detailed product information';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui pm-service-page">
    <style>
        .pm-service-page{--pm-service-border:rgba(148,163,184,.28);--pm-service-accent:#367fe8;--pm-service-ink:var(--text,#17243d);--pm-service-muted:var(--muted,#68758a);max-width:1180px}
        .pm-service-page .pm-service-card{border:1px solid var(--pm-service-border);border-radius:9px;background:var(--surface,#fff);box-shadow:var(--shadow,0 5px 18px rgba(15,23,42,.05));margin-bottom:16px}
        .pm-service-page .pm-service-card > .card-header{font-size:18px;font-weight:700;padding:18px 20px 0;background:transparent;border:0;color:var(--pm-service-ink)}
        .pm-service-page .pm-service-card > .card-body{padding:18px 20px}
        .pm-service-heading h1{font-size:27px;font-weight:800;line-height:1.18;margin:0 0 7px;color:var(--pm-service-ink)}
        .pm-service-heading p{margin:0;color:var(--pm-service-muted)}
        .pm-service-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
        .pm-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:20px;padding-top:18px;border-top:1px solid var(--pm-service-border)}
        .pm-detail-grid .label,.pm-usage-stat .label{font-size:12px;color:var(--pm-service-muted);margin-bottom:5px}
        .pm-detail-grid .value{font-weight:600;overflow-wrap:anywhere}
        .pm-usage-header{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}
        .pm-usage-header h2{font-size:18px;font-weight:700;margin:0}
        .pm-usage-track{height:18px;background:#eaf3ff;border-radius:3px;overflow:hidden;position:relative}
        .pm-usage-fill{height:100%;background:var(--pm-service-accent);min-width:0;transition:width .25s ease}
        .pm-usage-scale{display:flex;justify-content:space-between;font-size:11px;color:var(--pm-service-muted);margin-bottom:6px}
        .pm-usage-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:15px}
        .pm-usage-stat{border:1px solid var(--pm-service-border);border-radius:7px;padding:9px 12px}
        .pm-usage-stat strong{font-size:17px;color:var(--pm-service-ink)}
        .pm-list-table th{font-size:12px;color:var(--pm-service-muted);font-weight:700;white-space:nowrap}
        .pm-list-table td{vertical-align:middle}
        .pm-chart-card .card-body{padding-top:12px}
        .pm-chart-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px}
        .pm-chart-tabs{display:flex;gap:7px}
        .pm-chart-tabs button{border:1px solid #9fc3fb;background:transparent;color:var(--pm-service-accent);border-radius:5px;padding:5px 12px;font-size:12px;font-weight:600}
        .pm-chart-tabs button.is-active{background:var(--pm-service-accent);border-color:var(--pm-service-accent);color:#fff}
        .pm-chart{height:300px;border:1px solid var(--pm-service-border);border-radius:7px}
        @media(max-width:820px){.pm-detail-grid,.pm-usage-values{grid-template-columns:1fr 1fr}}
        @media(max-width:520px){.pm-detail-grid,.pm-usage-values{grid-template-columns:1fr}.pm-service-heading h1{font-size:23px}.pm-chart{height:250px}}
    </style>
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
        <?php if ($isTrafficService && pm_s($service['vendor_package_key'] ?? '') !== '' && !isset($service['provider_traffic_details'])): ?>
            <?php $shop->service_action($userId, $serviceId, 'refresh_traffic'); ?>
            <?php foreach ($shop->list_user_services($userId) as $freshService): ?>
                <?php if (is_array($freshService) && pm_s($freshService['service_id'] ?? '') === $serviceId) { $service = $freshService; break; } ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php $geoOptions = $shop->infatica_access_geo_options($category); ?>
        <?php
        $trafficTotal = max(0.0, (float)pm_s($service['traffic_total_gb'] ?? 0));
        $trafficUsed = max(0.0, (float)pm_s($service['traffic_used_gb'] ?? 0));
        $trafficRemaining = max(0.0, (float)pm_s($service['traffic_remaining_gb'] ?? $service['traffic_remains'] ?? 0));
        $trafficPercent = $trafficTotal > 0.0 ? min(100.0, round(($trafficUsed / $trafficTotal) * 100, 2)) : 0.0;
        $trafficDetails = isset($service['provider_traffic_details']) && is_array($service['provider_traffic_details']) ? $service['provider_traffic_details'] : [];
        $trafficDetailsJson = json_encode($trafficDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
        <div class="card pm-service-card">
            <div class="card-body">
                <div class="pm-service-heading">
                    <h1>Detailed product information</h1>
                    <p><?= pm_h($service['title'] ?? '-') ?> - <code><?= pm_h($serviceId) ?></code></p>
                    <div class="pm-service-actions">
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
                <div class="pm-detail-grid">
                    <div>
                        <div class="label">Product name</div>
                        <div class="value"><?= pm_h($service['title'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="label">Proxy type</div>
                        <div class="value"><?= pm_h(ucfirst($category !== '' ? $category : '-')) ?></div>
                    </div>
                    <div>
                        <div class="label">Country</div>
                        <div class="value"><?= pm_h($service['country'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="label">Status</div>
                        <div class="value"><?= pm_h($service['status'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="label">Host</div>
                        <div class="value"><?= pm_h($service['connection_host'] ?? '-') ?>:<?= pm_h($service['connection_port'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="label">Expires</div>
                        <div class="value"><?= pm_h($service['expires_at'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="label">Auto-renew</div>
                        <div class="value"><?= !empty($service['auto_renew_request']) ? 'On' : 'Off' ?></div>
                    </div>
                    <div>
                        <div class="label"><?= $isStaticIpService ? 'IP count' : 'Last traffic update' ?></div>
                        <div class="value"><?= $isStaticIpService ? pm_h($service['ip_count'] ?? '-') : '<span data-pm-local-time="' . pm_h($service['traffic_updated_at'] ?? '') . '">' . pm_h($service['traffic_updated_at'] ?? '-') . '</span>' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isTrafficService): ?>
            <section class="card pm-service-card" aria-label="Traffic statistics">
                <div class="card-body">
                    <div class="pm-usage-header">
                        <h2>Traffic statistics</h2>
                        <span class="small text-muted"><?= pm_h(number_format($trafficPercent, 2)) ?>% used</span>
                    </div>
                    <div class="pm-usage-scale"><span>0%</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span></div>
                    <div class="pm-usage-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= pm_h($trafficPercent) ?>">
                        <div class="pm-usage-fill" style="width:<?= pm_h($trafficPercent) ?>%"></div>
                    </div>
                    <div class="pm-usage-values">
                        <div class="pm-usage-stat"><div class="label">Used traffic</div><strong><?= pm_h(number_format($trafficUsed, 2)) ?> GB</strong></div>
                        <div class="pm-usage-stat"><div class="label">Available</div><strong><?= pm_h(number_format($trafficRemaining, 2)) ?> GB</strong></div>
                        <div class="pm-usage-stat"><div class="label">Traffic package</div><strong><?= pm_h(number_format($trafficTotal, 2)) ?> GB</strong></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isStaticIpService): ?>
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

        <?php $proxyLists = isset($service['proxy_lists']) && is_array($service['proxy_lists']) ? $service['proxy_lists'] : []; ?>
        <section class="card pm-service-card" aria-label="Generated proxy lists">
            <div class="card-header">Generated proxy lists</div>
            <div class="card-body">
                <?php if ($proxyLists === []): ?>
                    <p class="text-muted mb-0">No proxy lists generated yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0 pm-list-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Country</th>
                                    <th>Protocol</th>
                                    <th>Status</th>
                                    <th>Used</th>
                                    <th>Created</th>
                                    <th>Details</th>
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
                                    <td><?= pm_h($list['status'] ?? 'active') ?></td>
                                    <td><?= pm_h($list['traffic_used_gb'] ?? '0.0000') ?> GB</td>
                                    <td><?= pm_h($list['created_at'] ?? '-') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary pm-proxy-details-btn" type="button"
                                            data-list-id="<?= pm_h($list['vendor_list_id'] ?? $list['id'] ?? '') ?>"
                                            data-list-name="<?= pm_h($list['name'] ?? '') ?>">Details</button>
                                    </td>
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
                <?php endif; ?>
            </div>
        </section>

        <div class="card pm-service-card">
            <div class="card-header">Generate proxy list</div>
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
                            <label class="form-label" for="pmAuthMode">Authorization</label>
                            <select class="form-select" id="pmAuthMode" name="auth_mode">
                                <option value="login_password">Login / password</option>
                                <option value="ip_whitelist">IP whitelist</option>
                            </select>
                        </div>
                        <div class="col-md-6 pm-auth-ip-field d-none">
                            <label class="form-label" for="pmListNetwork">IP whitelist</label>
                            <input class="form-control" id="pmListNetwork" name="network" placeholder="1.2.3.4, 5.6.7.0/24">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pmListCountries">Countries</label>
                            <select class="form-select" id="pmListCountries" name="countries">
                                <?php foreach (($geoOptions['countries'] ?? []) as $code => $label): ?>
                                    <?php $selected = strtoupper(pm_s($service['country'] ?? '')) === strtoupper((string)$code) ? ' selected' : ''; ?>
                                    <option value="<?= pm_h($code) ?>"<?= $selected ?>><?= pm_h($code . ' - ' . $label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Leave empty for World Mix.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pmListRegion">Region</label>
                            <select class="form-select" id="pmListRegion" name="region" disabled>
                                <option value="">All regions</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="pmListCity">City</label>
                            <select class="form-select" id="pmListCity" name="city" disabled>
                                <option value="">All cities</option>
                            </select>
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
                                <option value="3" selected>http://login:password@host:port</option>
                                <option value="1">login:password@host:port</option>
                                <option value="2">host,port,login,password</option>
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

        <?php if ($isTrafficService): ?>
            <section class="card pm-service-card pm-chart-card" aria-label="Internet speed usage charts">
                <div class="card-header">Infographic diagrams - internet speed usage</div>
                <div class="card-body">
                    <div class="pm-chart-head">
                        <span class="text-muted small">Average download speed from provider traffic buckets</span>
                        <div class="pm-chart-tabs" data-chart-tabs="speed">
                            <button class="is-active" type="button" data-period="day">Daily</button>
                            <button type="button" data-period="week">Weekly</button>
                            <button type="button" data-period="month">Monthly</button>
                        </div>
                    </div>
                    <div class="pm-chart" id="pmSpeedChart" aria-label="Internet speed usage chart"></div>
                </div>
            </section>
            <section class="card pm-service-card pm-chart-card" aria-label="Traffic usage charts">
                <div class="card-header">Infographic diagrams - traffic usage</div>
                <div class="card-body">
                    <div class="pm-chart-head">
                        <span class="text-muted small">Used traffic balance by selected period</span>
                        <div class="pm-chart-tabs" data-chart-tabs="traffic">
                            <button class="is-active" type="button" data-period="day">Daily</button>
                            <button type="button" data-period="week">Weekly</button>
                            <button type="button" data-period="month">Monthly</button>
                        </div>
                    </div>
                    <div class="pm-chart" id="pmTrafficChart" aria-label="Traffic usage chart"></div>
                </div>
            </section>
        <?php endif; ?>

    <?php endif; ?>

    <div id="pmDetailsModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pmDetailsTitle" data-service-id="<?= pm_h($serviceId) ?>">
        <div class="panel" tabindex="-1">
            <div class="head">
                <strong id="pmDetailsTitle">Details</strong>
                <input id="pmDetailsSearch" type="text" placeholder="Start typing..." aria-label="Search">
                <button class="close" id="pmDetailsClose" type="button" aria-label="Close">Esc</button>
            </div>
            <div class="list" id="pmDetailsList" style="padding:10px 12px"></div>
            <div class="hint" style="padding:8px 12px">Text is selected manually or copied by Copy. Esc - close</div>
        </div>
    </div>
</main>
<?php if (isset($isTrafficService) && $isTrafficService): ?>
<script>
(function(){
    var raw = <?= $trafficDetailsJson !== false ? $trafficDetailsJson : '{}' ?>;
    var speedEl = document.getElementById('pmSpeedChart');
    var trafficEl = document.getElementById('pmTrafficChart');
    if (!speedEl || !trafficEl || typeof echarts === 'undefined') return;

    function aggregate(period){
        var source = raw[period === 'day' ? 'daily' : (period === 'week' ? 'weekly' : 'monthly')] || {};
        var rows = source.results && typeof source.results === 'object' ? source.results : source;
        var grouped = {};
        Object.keys(rows || {}).forEach(function(login){
            var points = rows[login];
            if (!points || typeof points !== 'object') return;
            Object.keys(points).forEach(function(label){
                var bytes = Number(points[label] || 0);
                if (!Number.isFinite(bytes)) return;
                grouped[label] = (grouped[label] || 0) + bytes;
            });
        });
        var labels = Object.keys(grouped).sort();
        var bucketSeconds = period === 'day' ? 3600 : 86400;
        var usage = labels.map(function(label){ return Number((grouped[label] / 1024 / 1024 / 1024).toFixed(4)); });
        var speed = labels.map(function(label){ return Number(((grouped[label] * 8) / bucketSeconds / 1000 / 1000).toFixed(4)); });
        return {labels: labels, usage: usage, speed: speed};
    }

    var speedChart = echarts.init(speedEl);
    var trafficChart = echarts.init(trafficEl);
    function options(title, labels, values, color, unit){
        return {
            color: [color],
            tooltip: {trigger: 'axis', valueFormatter: function(value){ return Number(value).toFixed(4) + ' ' + unit; }},
            grid: {left: 56, right: 28, top: 44, bottom: 44},
            legend: {data: [title], top: 12, right: 20},
            xAxis: {type: 'category', boundaryGap: false, data: labels, axisLabel: {color: '#64748b'}},
            yAxis: {type: 'value', name: unit, axisLabel: {color: '#64748b'}, splitLine: {lineStyle: {color: '#e6edf6'}}},
            series: [{name: title, type: 'line', smooth: true, showSymbol: true, symbolSize: 7, areaStyle: {opacity: .1}, data: values}]
        };
    }
    function render(period){
        var data = aggregate(period);
        speedChart.setOption(options('Download speed', data.labels, data.speed, '#397eee', 'Mbps'), true);
        trafficChart.setOption(options('Traffic used', data.labels, data.usage, '#6b52e5', 'GB'), true);
    }
    document.querySelectorAll('.pm-chart-tabs button').forEach(function(button){
        button.addEventListener('click', function(){
            var period = button.getAttribute('data-period') || 'day';
            document.querySelectorAll('.pm-chart-tabs button[data-period="' + period + '"]').forEach(function(match){
                match.parentElement.querySelectorAll('button').forEach(function(peer){ peer.classList.remove('is-active'); });
                match.classList.add('is-active');
            });
            render(period);
        });
    });
    render('day');
    window.addEventListener('resize', function(){ speedChart.resize(); trafficChart.resize(); });
})();
</script>
<?php endif; ?>
<script>
(function(){
    document.querySelectorAll('[data-pm-local-time]').forEach(function(element){
        var rawTime = element.getAttribute('data-pm-local-time') || '';
        var date = new Date(rawTime);
        if (rawTime === '' || Number.isNaN(date.getTime())) return;
        element.textContent = new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'medium'
        }).format(date);
        element.title = rawTime;
    });

    var authModeEl = document.getElementById('pmAuthMode');
    var loginFields = Array.prototype.slice.call(document.querySelectorAll('.pm-auth-login-field'));
    var ipFields = Array.prototype.slice.call(document.querySelectorAll('.pm-auth-ip-field'));
    var networkInput = document.getElementById('pmListNetwork');
    var countryInput = document.getElementById('pmListCountries');
    var regionInput = document.getElementById('pmListRegion');
    var cityInput = document.getElementById('pmListCity');

    function syncAuthFields(){
        if (!authModeEl) return;
        var isWhitelist = authModeEl.value === 'ip_whitelist';
        loginFields.forEach(function(el){ el.classList.toggle('d-none', isWhitelist); });
        ipFields.forEach(function(el){ el.classList.toggle('d-none', !isWhitelist); });
        if (networkInput) {
            networkInput.disabled = !isWhitelist;
            networkInput.required = isWhitelist;
        }
    }

    if (authModeEl) {
        authModeEl.addEventListener('change', syncAuthFields);
        syncAuthFields();
    }

    function setGeoOptions(select, values, emptyLabel){
        if (!select) return;
        select.innerHTML = '<option value="">' + emptyLabel + '</option>';
        (values || []).forEach(function(value){
            var option = document.createElement('option');
            option.value = String(value);
            option.textContent = String(value);
            select.appendChild(option);
        });
        select.disabled = false;
    }

    function loadGeo(action, country, region, select, emptyLabel){
        if (!select) return;
        select.disabled = true;
        var fd = new FormData();
        fd.append('action', action);
        fd.append('service_id', '<?= pm_h($serviceId) ?>');
        fd.append('country', country || '');
        if (region) fd.append('region', region);
        fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            credentials: 'same-origin'
        }).then(function(response){ return response.json(); }).then(function(payload){
            setGeoOptions(select, payload && payload.ok ? payload.values : [], emptyLabel);
        }).catch(function(){ setGeoOptions(select, [], emptyLabel); });
    }

    if (countryInput && regionInput && cityInput) {
        countryInput.addEventListener('change', function(){
            setGeoOptions(cityInput, [], 'All cities');
            loadGeo('geo_regions', countryInput.value, '', regionInput, 'All regions');
        });
        regionInput.addEventListener('change', function(){
            if (!regionInput.value) {
                setGeoOptions(cityInput, [], 'All cities');
                return;
            }
            loadGeo('geo_cities', countryInput.value, regionInput.value, cityInput, 'All cities');
        });
        if (countryInput.value) {
            loadGeo('geo_regions', countryInput.value, '', regionInput, 'All regions');
        }
    }

    var modal    = document.getElementById('pmDetailsModal');
    if (!modal) return;
    var titleEl  = document.getElementById('pmDetailsTitle');
    var searchEl = document.getElementById('pmDetailsSearch');
    var listEl   = document.getElementById('pmDetailsList');
    var closeEl  = document.getElementById('pmDetailsClose');
    var serviceId = modal.getAttribute('data-service-id') || '';

    var allRows = [];
    var activeTab = 'text';

    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
        });
    }

    function openModal(name){
        titleEl.textContent = name ? ('Details - ' + name) : 'Details';
        searchEl.value = '';
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        window.setTimeout(function(){ searchEl.focus(); }, 0);
    }

    function closeModal(){
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
    }

    function setMessage(html){ listEl.innerHTML = html; }

    function renderRows(rows, filter){
        var q = String(filter || '').trim().toLowerCase();
        var visible = q ? rows.filter(function(r){ return String(r).toLowerCase().indexOf(q) !== -1; }) : rows;

        if (!visible.length){
            setMessage('<div class="text-muted small p-2">Nothing found.</div>');
            return;
        }
        var textValue = visible.join('\n');
        var jsonValue = JSON.stringify(visible.map(proxyLineToObject), null, 2);
        var value = activeTab === 'json' ? jsonValue : textValue;
        listEl.innerHTML =
            '<div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">'
          + '<div class="btn-group btn-group-sm" role="tablist" aria-label="Proxy output format">'
          + '<button class="btn ' + (activeTab === 'text' ? 'btn-primary' : 'btn-outline-primary') + ' pm-details-tab" type="button" data-tab="text">Text</button>'
          + '<button class="btn ' + (activeTab === 'json' ? 'btn-primary' : 'btn-outline-primary') + ' pm-details-tab" type="button" data-tab="json">JSON</button>'
          + '</div>'
          + '<button class="btn btn-sm btn-outline-primary pm-copy-btn" type="button">Copy</button>'
          + '</div>'
          + '<textarea class="form-control pm-details-textarea" spellcheck="false" rows="18" style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.45;white-space:pre;overflow:auto;">' + esc(value) + '</textarea>'
          + '<div class="text-muted small mt-2">' + visible.length + ' proxies</div>';
    }

    function proxyLineToObject(line){
        var raw = String(line || '').trim();
        var out = {
            protocol: '',
            server: '',
            username: '',
            password: '',
            host: '',
            port: null,
            url: raw
        };

        try {
            var parsed = new URL(raw);
            out.protocol = parsed.protocol.replace(/:$/, '');
            if (['http', 'https', 'socks4', 'socks5'].indexOf(out.protocol) !== -1) {
                out.username = decodeURIComponent(parsed.username || '');
                out.password = decodeURIComponent(parsed.password || '');
                out.host = parsed.hostname || '';
                out.port = parsed.port !== '' ? Number(parsed.port) : null;
                out.server = out.protocol && out.host && out.port ? (out.protocol + '://' + out.host + ':' + out.port) : '';
                return out;
            }
        } catch(e) {}

        if (raw.indexOf(',') !== -1) {
            var csv = raw.split(',').map(function(part){ return part.trim(); });
            if (csv.length >= 4) {
                return buildProxyObject(out, csv[2], csv[3], csv[0], csv[1]);
            }
        }

        var atParts = raw.split('@');
        if (atParts.length === 2) {
            var auth = atParts[0].split(':');
            var hostPort = atParts[1].split(':');
            return buildProxyObject(out, auth[0], auth.slice(1).join(':'), hostPort[0], hostPort[1]);
        }
        if (atParts.length === 4) {
            return buildProxyObject(out, atParts[0], atParts[1], atParts[2], atParts[3]);
        }

        var colon = raw.split(':');
        if (colon.length === 4) {
            if (looksLikePort(colon[1])) {
                return buildProxyObject(out, colon[2], colon[3], colon[0], colon[1]);
            }
            return buildProxyObject(out, colon[0], colon[1], colon[2], colon[3]);
        }

        return out;
    }

    function buildProxyObject(out, username, password, host, port){
        out.protocol = 'http';
        out.username = String(username || '');
        out.password = String(password || '');
        out.host = String(host || '');
        out.port = looksLikePort(port) ? Number(port) : null;
        out.server = out.host && out.port ? ('http://' + out.host + ':' + out.port) : '';
        return out;
    }

    function looksLikePort(value){
        var n = Number(value);
        return Number.isInteger(n) && n > 0 && n <= 65535;
    }

    function extractRows(payload){
        var resp = payload && payload.response;
        var rows = [];
        var pushRows = function(arr){
            if (!Array.isArray(arr)) return;
            arr.forEach(function(item){
                if (item == null) return;
                if (typeof item === 'string') rows.push(item);
                else if (typeof item === 'object'){
                    if (typeof item.proxy === 'string') rows.push(item.proxy);
                    else if (item.host && item.port) rows.push((item.login||'') + ':' + (item.password||'') + '@' + item.host + ':' + item.port);
                    else rows.push(JSON.stringify(item));
                }
            });
        };
        if (resp){
            if (Array.isArray(resp)) pushRows(resp);
            else if (typeof resp === 'object'){
                ['proxies','proxy_list','proxy-list','list','items','data','access','accesses'].forEach(function(k){
                    if (resp[k]) pushRows(resp[k]);
                });
                if (!rows.length && Array.isArray(resp['proxy-list-data'])) pushRows(resp['proxy-list-data']);
            }
        }
        return rows;
    }

    function fetchDetails(btn){
        var listId   = btn.getAttribute('data-list-id') || '';
        var listName = btn.getAttribute('data-list-name') || '';

        openModal(listName);
        setMessage('<div class="text-muted small p-2">Loading...</div>');
        allRows = [];
        activeTab = 'text';

        var fd = new FormData();
        fd.append('action', 'view_proxy_list');
        fd.append('service_id', serviceId);
        fd.append('list_id', listId);
        fd.append('list_name', listName);

        fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            credentials: 'same-origin'
        }).then(function(r){
            return r.text().then(function(txt){
                try { return { status: r.status, json: JSON.parse(txt), text: txt }; }
                catch(e){ return { status: r.status, json: null, text: txt }; }
            });
        }).then(function(res){
            if (!res.json || res.json.ok === false){
                var err = (res.json && res.json.error) ? res.json.error : ('HTTP ' + res.status);
                setMessage('<div class="alert alert-danger small mb-0">' + esc(err) + '</div><pre class="small mt-2" style="max-height:240px;overflow:auto;background:transparent">' + esc(res.text.slice(0, 4000)) + '</pre>');
                return;
            }
            allRows = extractRows(res.json);
            if (!allRows.length){
                setMessage('<div class="alert alert-warning small mb-0">No proxies in response.</div><pre class="small mt-2" style="max-height:320px;overflow:auto;background:transparent">' + esc(JSON.stringify(res.json.response, null, 2)) + '</pre>');
                return;
            }
            renderRows(allRows, '');
        }).catch(function(err){
            setMessage('<div class="alert alert-danger small mb-0">Network error: ' + esc(String(err && err.message || err)) + '</div>');
        });
    }

    function flash(btn, ok){
        var prev = btn.getAttribute('data-prev') || btn.textContent;
        btn.setAttribute('data-prev', prev);
        btn.textContent = ok ? 'Copied' : 'Error';
        window.clearTimeout(btn.__t);
        btn.__t = window.setTimeout(function(){ btn.textContent = prev; }, 1200);
    }

    function copyValue(value, cb){
        if (navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(value).then(function(){ cb(true); }, function(){ fallback(); });
        } else { fallback(); }
        function fallback(){
            try {
                var ta = document.createElement('textarea');
                ta.value = value; ta.style.position='fixed'; ta.style.opacity='0';
                document.body.appendChild(ta); ta.focus(); ta.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                cb(!!ok);
            } catch(e){ cb(false); }
        }
    }

    document.addEventListener('click', function(e){
        var detBtn = e.target.closest('.pm-proxy-details-btn');
        if (detBtn){ e.preventDefault(); fetchDetails(detBtn); return; }

        var copyBtn = e.target.closest('.pm-copy-btn');
        if (copyBtn){
            e.preventDefault(); e.stopPropagation();
            var area = listEl.querySelector('.pm-details-textarea');
            copyValue(area ? area.value : '', function(ok){ flash(copyBtn, ok); });
            return;
        }
        var tabBtn = e.target.closest('.pm-details-tab');
        if (tabBtn){
            e.preventDefault();
            activeTab = tabBtn.getAttribute('data-tab') === 'json' ? 'json' : 'text';
            renderRows(allRows, searchEl.value);
        }
    });

    var searchTimer = 0;
    searchEl.addEventListener('input', function(){
        window.clearTimeout(searchTimer);
        var q = searchEl.value;
        searchTimer = window.setTimeout(function(){ if (allRows.length) renderRows(allRows, q); }, 80);
    });

    closeEl.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    window.addEventListener('keydown', function(e){
        if (modal.getAttribute('aria-hidden') === 'true') return;
        if (e.key === 'Escape'){ e.preventDefault(); closeModal(); }
    });
})();
</script>
<?php
Sogerien::Page()->footer();
