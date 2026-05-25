<?php
declare(strict_types=1);

function cpo_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cpo_s(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;
if ($userId <= 0) {
    $_GET['next'] = (string)($_SERVER['REQUEST_URI'] ?? '/client/dashboard');
    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

$path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
if (str_starts_with($path, 'client/')) {
    $path = substr($path, 7);
}
$pageKey = match ($path) {
    'traffic' => 'traffic',
    'access-lists' => 'access',
    'scraper/playground' => 'scraper_playground',
    default => 'traffic',
};

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$isAjaxRequest = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
if ($isAjaxRequest && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pageKey === 'access') {
    $serviceId = cpo_s($request['service_id'] ?? '');
    $targetService = null;
    foreach ($shop->list_user_services($userId) as $row) {
        if (is_array($row) && cpo_s($row['service_id'] ?? '') === $serviceId) {
            $targetService = $row;
            break;
        }
    }
    $values = [];
    if (is_array($targetService)) {
        $category = cpo_s($targetService['provider_pool_category'] ?? '');
        $country = cpo_s($request['country'] ?? '');
        $values = cpo_s($request['action'] ?? '') === 'geo_cities'
            ? $shop->infatica_access_cities($category, $country, cpo_s($request['region'] ?? ''))
            : $shop->infatica_access_regions($category, $country);
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => true, 'values' => $values], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    Sogerien::markDone();
    return;
}
$alertType = '';
$alertText = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pageKey === 'access') {
    $serviceId = cpo_s($request['service_id'] ?? '');
    $action = cpo_s($request['action'] ?? '');
    if ($serviceId !== '' && $action === 'generate_proxy_list') {
        $result = $shop->service_action($userId, $serviceId, $action, is_array($request) ? $request : []);
        if (($result['ok'] ?? false) === true) {
            $alertType = 'success';
            $alertText = 'Proxy access list generated.';
        } else {
            $alertType = 'danger';
            $alertText = cpo_s($result['error'] ?? 'Action failed.');
        }
    }
}
$services = $shop->list_user_services($userId);
$geoOptions = ['countries' => [], 'regions' => [], 'cities' => []];
foreach ($services as $service) {
    if (!is_array($service)) {
        continue;
    }
    $options = $shop->infatica_access_geo_options(cpo_s($service['provider_pool_category'] ?? ''));
    $geoOptions['countries'] = array_merge($geoOptions['countries'], $options['countries']);
    $geoOptions['regions'] = array_merge($geoOptions['regions'], $options['regions']);
    $geoOptions['cities'] = array_merge($geoOptions['cities'], $options['cities']);
}
ksort($geoOptions['countries']);
asort($geoOptions['regions']);
asort($geoOptions['cities']);

$titles = [
    'traffic' => ['Traffic Usage', 'Usage, consumption speed placeholders and yearly traffic balance.'],
    'access' => ['Access Lists', 'Proxy access lists, generated credentials, country and rotation state.'],
    'scraper_playground' => ['Scraper Playground', 'Test forms for URL scrape, JS render, SERP and AI search gateway.'],
];

Sogerien::Page()->title = $titles[$pageKey][0];
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui client-ops-page">
    <style>
        .pm-ops-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
        .pm-ops-head h1{font-size:28px;margin:0 0 6px}
        .pm-ops-head p{margin:0;color:rgb(100,116,139)}
        .pm-ops-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-ops-stat{font-size:22px;font-weight:700}
        .pm-ops-head-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pm-access-card{border:1px solid rgba(148,163,184,.3);border-radius:8px;padding:12px;margin-bottom:10px}
        .pm-scraper-console{border:1px solid rgba(148,163,184,.35);border-radius:8px;background:#fff;overflow:hidden}
        .pm-scraper-console-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:18px 20px;border-bottom:1px solid rgba(148,163,184,.28)}
        .pm-scraper-console h2{margin:0 0 6px;font-size:24px;color:#0f172a}
        .pm-scraper-console p{margin:0;color:#64748b}
        .pm-scraper-modes{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .pm-scraper-modes span{border:1px solid rgba(37,99,235,.28);border-radius:6px;padding:5px 9px;font-size:12px;font-weight:700;color:#1d4ed8;background:#eff6ff}
        .pm-scraper-flow{display:grid;grid-template-columns:1.2fr .8fr;gap:0}
        .pm-scraper-pane{padding:18px 20px}
        .pm-scraper-pane + .pm-scraper-pane{border-left:1px solid rgba(148,163,184,.28);background:#f8fafc}
        .pm-scraper-kv{display:grid;grid-template-columns:120px 1fr;gap:8px 14px;font-size:14px}
        .pm-scraper-kv span{color:#64748b}
        .pm-scraper-endpoint{display:block;margin-top:12px;padding:10px 12px;border:1px solid rgba(148,163,184,.35);border-radius:6px;background:#0f172a;color:#e2e8f0;white-space:normal;word-break:break-all}
        @media(max-width:800px){.pm-ops-grid{grid-template-columns:1fr}.pm-ops-head{display:block}.pm-scraper-console-head{display:block}.pm-scraper-modes{justify-content:flex-start;margin-top:12px}.pm-scraper-flow{grid-template-columns:1fr}.pm-scraper-pane + .pm-scraper-pane{border-left:0;border-top:1px solid rgba(148,163,184,.28)}}
    </style>
    <div class="pm-ops-head">
        <div>
            <h1><?= cpo_h($titles[$pageKey][0]) ?></h1>
            <p><?= cpo_h($titles[$pageKey][1]) ?></p>
        </div>
        <div class="pm-ops-head-actions">
            <a class="btn btn-primary" href="/client/change-password">Change password</a>
            <a class="btn btn-outline-primary" href="/client/all_proxy">Order proxies</a>
        </div>
    </div>
    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= cpo_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= cpo_h($alertText) ?></div>
    <?php endif; ?>

    <?php if ($pageKey === 'traffic'): ?>
        <?php
        $total = 0.0;
        $used = 0.0;
        $left = 0.0;
        $rows = [];
        foreach ($services as $service) {
            $limit = (float)cpo_s($service['traffic_total_gb'] ?? 0);
            $spent = (float)cpo_s($service['traffic_used_gb'] ?? 0);
            $remain = (float)cpo_s($service['traffic_remaining_gb'] ?? 0);
            $total += $limit;
            $used += $spent;
            $left += $remain;
            $rows[] = [
                'title' => cpo_s($service['title'] ?? 'Proxy service'),
                'type' => cpo_s($service['provider_pool_category'] ?? '-'),
                'country' => cpo_s($service['country'] ?? '-'),
                'limit_gb' => number_format($limit, 2, '.', ''),
                'used_gb' => number_format($spent, 2, '.', ''),
                'left_gb' => number_format($remain, 2, '.', ''),
                'updated' => cpo_s($service['traffic_updated_at'] ?? '-'),
                'actions' => '<a class="pm-pill-btn is-active" href="/client/proxy/manage?service_id=' . cpo_h(rawurlencode(cpo_s($service['service_id'] ?? ''))) . '">Manage</a>',
            ];
        }
        ?>
        <div class="pm-ops-grid">
            <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Purchased traffic</div><div class="pm-ops-stat"><?= cpo_h(number_format($total, 2)) ?> GB</div></div></section>
            <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Used</div><div class="pm-ops-stat"><?= cpo_h(number_format($used, 2)) ?> GB</div></div></section>
            <section class="card shadow-sm"><div class="card-body"><div class="text-muted small">Left</div><div class="pm-ops-stat"><?= cpo_h(number_format($left, 2)) ?> GB</div></div></section>
        </div>
        <?php
        $tr = Sogerien::TableRenderer();
        $tr->set_params = new SetParams();
        $tr->set_params->data = $rows;
        $tr->set_params->columns = ['actions', 'title', 'type', 'country', 'limit_gb', 'used_gb', 'left_gb', 'updated'];
        $tr->set_params->headers = ['actions' => 'Actions', 'title' => 'Service', 'type' => 'Type', 'country' => 'Country', 'limit_gb' => 'Limit', 'used_gb' => 'Used', 'left_gb' => 'Left', 'updated' => 'Updated'];
        $tr->set_params->gridId = 'client_traffic_grid';
        $tr->set_params->searchCols = $tr->set_params->columns;
        $tr->set_params->perPage = 100;
        $tr->set_params->formatters['actions'] = static fn($value): string => (string)$value;
        $tr->render();
        ?>
    <?php elseif ($pageKey === 'access'): ?>
        <section class="card shadow-sm mb-3"><div class="card-header">Generate access</div><div class="card-body">
            <form method="post" action="/client/access-lists" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="generate_proxy_list">
                <div class="col-md-4">
                    <label class="form-label" for="cpoServiceId">Service</label>
                    <select class="form-select" id="cpoServiceId" name="service_id" required>
                        <?php foreach ($services as $service): ?>
                            <?php $sid = cpo_s($service['service_id'] ?? ''); ?>
                            <?php if ($sid === '') { continue; } ?>
                            <option value="<?= cpo_h($sid) ?>"><?= cpo_h(cpo_s($service['title'] ?? $sid)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cpoAuthMode">Authorization</label>
                    <select class="form-select" id="cpoAuthMode" name="auth_mode">
                        <option value="login_password">Login / password</option>
                        <option value="ip_whitelist">IP whitelist</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cpoNetwork">IP whitelist</label>
                    <input class="form-control" id="cpoNetwork" name="network" placeholder="1.2.3.4, 5.6.7.0/24">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoCountries">Countries</label>
                    <select class="form-select" id="cpoCountries" name="countries">
                        <?php foreach (($geoOptions['countries'] ?? []) as $code => $label): ?>
                            <option value="<?= cpo_h((string)$code) ?>"><?= cpo_h((string)$code . ' - ' . (string)$label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoRegion">Region</label>
                    <select class="form-select" id="cpoRegion" name="region" disabled>
                        <option value="">All regions</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoCity">City</label>
                    <select class="form-select" id="cpoCity" name="city" disabled>
                        <option value="">All cities</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoIsp">ISP</label>
                    <input class="form-control" id="cpoIsp" name="isp" placeholder="All or provider id">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="cpoZip">ZIP</label>
                    <input class="form-control" id="cpoZip" name="zip" placeholder="10001">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="cpoRotation">Rotation</label>
                    <select class="form-select" id="cpoRotation" name="rotation_period">
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
                    <label class="form-label" for="cpoRotationMode">Failure mode</label>
                    <select class="form-select" id="cpoRotationMode" name="rotation_mode">
                        <option value="0">Instant</option>
                        <option value="1">5 seconds</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cpoFormat">Proxy format</label>
                    <select class="form-select" id="cpoFormat" name="format">
                        <option value="1">login:password@host:port</option>
                        <option value="2">host,port,login,password</option>
                        <option value="3">http://login:password@host:port</option>
                        <option value="4">socks5://login:password@host:port</option>
                        <option value="5">login:password:host:port</option>
                        <option value="6">host:port:login:password</option>
                        <option value="7">login@password@host@port</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Generate access</button>
                </div>
            </form>
        </div></section>
        <?php foreach ($services as $service): ?>
            <section class="card shadow-sm mb-3"><div class="card-header"><?= cpo_h(cpo_s($service['title'] ?? 'Service')) ?></div><div class="card-body">
                <?php $lists = isset($service['proxy_lists']) && is_array($service['proxy_lists']) ? $service['proxy_lists'] : []; ?>
                <?php if ($lists === []): ?>
                    <div class="text-muted">No access lists yet.</div>
                <?php endif; ?>
                <?php foreach ($lists as $list): ?>
                    <?php if (!is_array($list)) { continue; } ?>
                    <div class="pm-access-card">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <?php $targetBits = array_filter([
                                cpo_s($list['country'] ?? '-'),
                                cpo_s($list['region'] ?? ''),
                                cpo_s($list['city'] ?? ''),
                                cpo_s($list['isp_id'] ?? $list['isp'] ?? ''),
                                cpo_s($list['zip'] ?? ''),
                            ]); ?>
                            <div><strong><?= cpo_h(cpo_s($list['name'] ?? '-')) ?></strong><div class="small text-muted"><?= cpo_h(implode(' / ', $targetBits)) ?> - rotation <?= cpo_h(cpo_s($list['rotation_period'] ?? '0')) ?></div></div>
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <code><?= cpo_h(cpo_s($list['login'] ?? '-')) ?>:<?= cpo_h(cpo_s($list['password'] ?? '-')) ?></code>
                                <?php $detailId = 'cpoAccessDetails' . preg_replace('/[^A-Za-z0-9_:-]/', '', cpo_s($list['vendor_list_id'] ?? $list['id'] ?? md5(cpo_s($list['name'] ?? '')))); ?>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= cpo_h($detailId) ?>">Details</button>
                            </div>
                        </div>
                        <div class="collapse mt-2" id="<?= cpo_h($detailId) ?>">
                            <?php
                            $host = cpo_s($service['connection_host'] ?? '');
                            $port = cpo_s($service['connection_port'] ?? '');
                            $login = cpo_s($list['login'] ?? '');
                            $password = cpo_s($list['password'] ?? '');
                            $details = 'http://' . $login . ':' . $password . '@' . $host . ':' . $port . "\n"
                                . 'socks5://' . $login . ':' . $password . '@' . $host . ':' . $port . "\n"
                                . $host . ':' . $port . ':' . $login . ':' . $password;
                            ?>
                            <pre class="mb-0 small"><?= cpo_h($details) ?></pre>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div></section>
        <?php endforeach; ?>
    <?php else: ?>
        <section class="pm-scraper-console mb-3">
            <div class="pm-scraper-console-head">
                <div>
                    <h2>Scraper playground</h2>
                    <p>Gateway test console for scraping modes without exposing provider keys.</p>
                </div>
                <div class="pm-scraper-modes">
                    <span>URL scrape</span><span>JS render</span><span>SERP</span><span>AI search</span>
                </div>
            </div>
            <div class="pm-scraper-flow">
                <div class="pm-scraper-pane">
                    <div class="pm-scraper-kv">
                        <span>Route</span><strong>Client - ProxyMint gateway - Infatica API</strong>
                        <span>Auth</span><strong>Client API key only</strong>
                        <span>Billing</span><strong>Successful requests</strong>
                    </div>
                </div>
                <div class="pm-scraper-pane">
                    <div class="small text-muted">Endpoint</div>
                    <code class="pm-scraper-endpoint">POST /client/scraper/playground</code>
                </div>
            </div>
        </section>
        <section class="card shadow-sm mb-3"><div class="card-header">Gateway request</div><div class="card-body">
            <?php
            $form = new Forms(['id' => 'scraper_playground_form', 'action' => '/client/scraper/playground', 'method' => 'POST', 'ajax' => false]);
            $form->addSelect('mode', 'Mode', [
                ['value' => 'scrape', 'label' => 'URL scrape'],
                ['value' => 'render', 'label' => 'JS render'],
                ['value' => 'serp', 'label' => 'SERP'],
                ['value' => 'chatgpt', 'label' => 'ChatGPT search'],
                ['value' => 'gemini', 'label' => 'Gemini search'],
                ['value' => 'perplexity', 'label' => 'Perplexity search'],
            ])
                ->addInput('target', 'URL or query', 'text', [], 'https://example.com')
                ->addTextarea('payload', 'Payload JSON', ['rows' => '6'], '{}')
                ->addButton('Run test', ['type' => 'button']);
            $form->render();
            ?>
        </div></section>
        <section class="card shadow-sm"><div class="card-body text-muted">Client keys are not exposed here. Production flow: client - ProxyMint gateway - Infatica scraper API.</div></section>
    <?php endif; ?>
</main>
<?php if ($pageKey === 'access'): ?>
<script>
(function(){
    var service = document.getElementById('cpoServiceId');
    var country = document.getElementById('cpoCountries');
    var region = document.getElementById('cpoRegion');
    var city = document.getElementById('cpoCity');
    if (!service || !country || !region || !city) return;
    function fill(select, values, label){
        select.innerHTML = '<option value="">' + label + '</option>';
        (values || []).forEach(function(value){
            var option = document.createElement('option');
            option.value = String(value);
            option.textContent = String(value);
            select.appendChild(option);
        });
        select.disabled = false;
    }
    function load(action, select, label){
        var form = new FormData();
        form.append('action', action);
        form.append('service_id', service.value);
        form.append('country', country.value);
        if (action === 'geo_cities') form.append('region', region.value);
        select.disabled = true;
        fetch(window.location.pathname, {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin',
            body: form
        }).then(function(response){ return response.json(); })
          .then(function(payload){ fill(select, payload && payload.ok ? payload.values : [], label); })
          .catch(function(){ fill(select, [], label); });
    }
    function refreshRegions(){
        fill(city, [], 'All cities');
        load('geo_regions', region, 'All regions');
    }
    country.addEventListener('change', refreshRegions);
    service.addEventListener('change', refreshRegions);
    region.addEventListener('change', function(){
        if (!region.value) { fill(city, [], 'All cities'); return; }
        load('geo_cities', city, 'All cities');
    });
    if (country.value) refreshRegions();
})();
</script>
<?php endif; ?>
<?php
Sogerien::Page()->footer();
