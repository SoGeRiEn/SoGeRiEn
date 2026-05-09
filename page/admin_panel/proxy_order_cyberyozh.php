<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function pcy_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pcy_s(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

function pcy_json(mixed $value): string
{
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT
    );
    return is_string($json) ? $json : '{}';
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}
Sogerien::AccessCheck()->init_db_alias($dbAlias);
$accessOk = Sogerien::AccessCheck()->check_access('proxies_list', 0, []);
if (!$accessOk) {
    Sogerien::Page()->title = Sogerien::Lang()->get('auth.access_denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">Access denied.</div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$isPost = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);

$id = pcy_s($request['id'] ?? '');
$title = pcy_s($request['title'] ?? '');
$priceUsd = pcy_s($request['price_usd'] ?? '');
$days = pcy_s($request['days'] ?? '');
$country = pcy_s($request['country'] ?? '');
$category = pcy_s($request['category'] ?? '');
$autoRenewPossible = pcy_s($request['auto_renew_possible'] ?? '') === '1';
$autoRenew = $autoRenewPossible && pcy_s($request['auto_renew'] ?? '') === '1';
$promoCode = pcy_s($request['promo_code'] ?? '');

$api = Sogerien::API()->Cyberyozh();
$api->debug_enabled = true;
$api->debug = [];
$api->debug_history = [];

$alertType = '';
$alertText = '';
$buyPayload = [];
$buyResponse = null;
$pageDump = [
    'page_input' => [
        'id' => $id,
        'title' => $title,
        'price_usd' => $priceUsd,
        'days' => $days,
        'country' => $country,
        'category' => $category,
        'auto_renew_possible' => $autoRenewPossible,
        'auto_renew' => $autoRenew,
        'promo_code' => $promoCode,
    ],
    'api_call' => null,
    'api_debug_history' => [],
    'api_last_debug' => [],
    'api_response' => null,
    'api_error' => '',
    'api_last_http_code' => 0,
    'api_last_url' => '',
    'order_result' => null,
    'rendered_at' => date('c'),
];

if ($isPost) {
    if ($userId <= 0) {
        $alertType = 'danger';
        $alertText = 'You need to sign in first.';
    } elseif ($id === '') {
        $alertType = 'danger';
        $alertText = 'Proxy id is empty.';
    } else {
        $buyPayload = [
            'id' => $id,
            'catalog_id' => $id,
            'title' => $title,
            'price_usd' => $priceUsd,
            'days' => $days,
            'country' => $country,
            'location_country_code' => $country,
            'category' => $category,
            'proxy_category' => $category,
            'auto_renew_possible' => $autoRenewPossible,
            'is_auto_renewal_possible' => $autoRenewPossible,
            'auto_renew' => $autoRenew,
        ];
        if ($promoCode !== '') {
            $buyPayload['promo_code'] = $promoCode;
        }

        $pageDump['api_call'] = [
            'method' => 'direct_cyberyozh_order',
            'args' => [
                'user_id' => $userId,
                'item' => $buyPayload,
            ],
        ];

        $buyResponse = $shop->direct_cyberyozh_order($userId, $buyPayload);
        $pageDump['order_result'] = $buyResponse;
        $pageDump['api_response'] = $buyResponse['vendor_response'] ?? null;
        $pageDump['api_debug_history'] = $api->debug_history;
        $pageDump['api_last_debug'] = $api->debug;
        $pageDump['api_error'] = $api->error;
        $pageDump['api_last_http_code'] = $api->last_http_code;
        $pageDump['api_last_url'] = $api->last_url;

        if (($buyResponse['ok'] ?? false) === true) {
            $alertType = 'success';
            $alertText = 'CyberYozh order stored in DB. Inspect dump below.';
        } else {
            $alertType = 'danger';
            $alertText = (string)($buyResponse['error'] ?? ($api->error !== '' ? $api->error : 'CyberYozh order request failed.'));
        }
    }
}

$dumpJson = pcy_json($pageDump);
$dumpJsonJs = json_encode($pageDump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($dumpJsonJs)) {
    $dumpJsonJs = '{}';
}

Sogerien::Page()->title = 'CyberYozh order';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <?php if ($userId <= 0): ?>
                <div class="alert alert-warning" role="alert">You need to sign in first.</div>
            <?php endif; ?>
            <?php if ($alertText !== ''): ?>
                <div class="alert alert-<?= pcy_h($alertType !== '' ? $alertType : 'info') ?>" role="alert">
                    <?= pcy_h($alertText) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/proxies/order/cyberyozh" class="row g-3">
                <input type="hidden" name="id" value="<?= pcy_h($id) ?>">
                <input type="hidden" name="title" value="<?= pcy_h($title) ?>">
                <input type="hidden" name="price_usd" value="<?= pcy_h($priceUsd) ?>">
                <input type="hidden" name="days" value="<?= pcy_h($days) ?>">
                <input type="hidden" name="country" value="<?= pcy_h($country) ?>">
                <input type="hidden" name="category" value="<?= pcy_h($category) ?>">
                <input type="hidden" name="auto_renew_possible" value="<?= $autoRenewPossible ? '1' : '0' ?>">

                <div class="col-md-4">
                    <label class="form-label">Proxy ID</label>
                    <input class="form-control" type="text" value="<?= pcy_h($id) ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Title</label>
                    <input class="form-control" type="text" value="<?= pcy_h($title) ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Price USD</label>
                    <input class="form-control" type="text" value="<?= pcy_h($priceUsd) ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Days</label>
                    <input class="form-control" type="text" value="<?= pcy_h($days) ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Country</label>
                    <input class="form-control" type="text" value="<?= pcy_h($country) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <input class="form-control" type="text" value="<?= pcy_h($category) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Promo code</label>
                    <input class="form-control" type="text" name="promo_code" value="<?= pcy_h($promoCode) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block">Auto renew</label>
                    <div class="form-check form-switch pt-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="auto_renew"
                            value="1"
                            <?= $autoRenew ? 'checked' : '' ?>
                            <?= $autoRenewPossible ? '' : 'disabled' ?>
                        >
                        <label class="form-check-label"><?= $autoRenewPossible ? 'Enabled by API' : 'Not supported' ?></label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-success" <?= $userId > 0 ? '' : 'disabled' ?>>Send buy_proxies request</button>
                    <a class="btn btn-outline-primary" href="/my/proxies">My Proxies</a>
                    <a class="btn btn-outline-secondary" href="/proxies">Back to live list</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3">Server dump</h2>
            <pre class="cyberyozh-dump-pre mb-0"><?= pcy_h($dumpJson) ?></pre>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3">Browser dump</h2>
            <div id="cyberyozhBrowserDump" class="cyberyozh-browser-dump text-break"></div>
        </div>
    </div>

    <style>
        .cyberyozh-dump-pre,
        .cyberyozh-browser-dump pre {
            margin: 0;
            padding: 16px;
            border-radius: 12px;
            background: #0f172a;
            color: #dbeafe;
            overflow: auto;
            font-size: 12px;
            line-height: 1.5;
        }
    </style>

    <script>
        (() => {
            const dump = <?= $dumpJsonJs ?>;
            window.cyberyozhOrderDump = dump;
            const target = document.getElementById('cyberyozhBrowserDump');
            if (!target) {
                return;
            }

            const pre = document.createElement('pre');
            pre.textContent = JSON.stringify({
                dump_generated_in_browser_at: new Date().toISOString(),
                dump
            }, null, 2);
            target.innerHTML = '';
            target.appendChild(pre);
        })();
    </script>
</main>
<?php
Sogerien::Page()->footer();
