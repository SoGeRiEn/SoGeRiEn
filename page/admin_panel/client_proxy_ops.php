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
$services = $shop->list_user_services($userId);

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
        .pm-access-card{border:1px solid rgba(148,163,184,.3);border-radius:8px;padding:12px;margin-bottom:10px}
        @media(max-width:800px){.pm-ops-grid{grid-template-columns:1fr}.pm-ops-head{display:block}}
    </style>
    <div class="pm-ops-head">
        <div>
            <h1><?= cpo_h($titles[$pageKey][0]) ?></h1>
            <p><?= cpo_h($titles[$pageKey][1]) ?></p>
        </div>
        <a class="btn btn-primary" href="/client/all_proxy">Order proxies</a>
    </div>

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
            <form method="post" action="/client/my/proxies" class="row g-3 align-items-end">
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
                    <label class="form-label" for="cpoListName">List name</label>
                    <input class="form-control" id="cpoListName" name="list_name" value="client-main-us">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cpoAuthMode">Authorization</label>
                    <select class="form-select" id="cpoAuthMode" name="auth_mode">
                        <option value="login_password">Login / password</option>
                        <option value="ip_whitelist">IP whitelist</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoLogin">Login</label>
                    <input class="form-control" id="cpoLogin" name="login" placeholder="Auto">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoPassword">Password</label>
                    <input class="form-control" id="cpoPassword" name="password" placeholder="Auto">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cpoNetwork">IP whitelist</label>
                    <input class="form-control" id="cpoNetwork" name="network" placeholder="1.2.3.4, 5.6.7.0/24">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoCountries">Countries</label>
                    <input class="form-control" id="cpoCountries" name="countries" placeholder="US, DE, NL">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoRegion">Region</label>
                    <input class="form-control" id="cpoRegion" name="region" placeholder="California">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cpoCity">City</label>
                    <input class="form-control" id="cpoCity" name="city" placeholder="New York">
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
                            <div><code><?= cpo_h(cpo_s($list['login'] ?? '-')) ?>:<?= cpo_h(cpo_s($list['password'] ?? '-')) ?></code></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div></section>
        <?php endforeach; ?>
    <?php else: ?>
        <section class="card shadow-sm mb-3"><div class="card-header">Scraper gateway test</div><div class="card-body">
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
        <section class="card shadow-sm"><div class="card-body text-muted">Client keys are not exposed here. Correct production flow is client - ProxyMint gateway - Infatica scraper API.</div></section>
    <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
