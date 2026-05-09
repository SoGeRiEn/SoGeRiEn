<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function por_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function por_s(mixed $value): string
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

function por_t(string $key): string
{
    return Sogerien::Lang()->get($key);
}

function por_gen_portmint_id(int $len = 9): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($alphabet) - 1;
    $out = 'portmint';
    for ($i = 0; $i < $len; $i++) {
        $idx = random_int(0, $max);
        $out .= $alphabet[$idx] ?? '';
    }
    return $out;
}

function por_gen_proxymintcom_id(int $socks_port, int $random_len = 10): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($alphabet) - 1;
    $random = '';
    for ($i = 0; $i < $random_len; $i++) {
        $idx = random_int(0, $max);
        $random .= $alphabet[$idx] ?? '';
    }

    // Proxysmart requires alphanumeric portID (no '-' allowed).
    // Required format example:
    // proxymintcom5001randA1b2C3d4E5
    return 'proxymintcom' . max(0, (int)$socks_port) . 'rand' . $random;
}

function por_normalize_proxymintcom_id(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    // Required format: proxymintcom{SOCKS_PORT}rand{a-Z0-9} (alphanumeric only)
    if (preg_match('/^proxymintcom\d+rand[a-zA-Z0-9]+$/', $raw) === 1) {
        return $raw;
    }

    return '';
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;

$isPost = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
$orderSubmit = $isPost && por_s($request['order_submit'] ?? '') !== '';

$imei = por_s($request['IMEI'] ?? $request['imei'] ?? '');
$httpPortRaw = $request['HTTP_PORT'] ?? $request['http_port'] ?? 0;
$socksPortRaw = $request['SOCKS_PORT'] ?? $request['socks_port'] ?? 0;
$login = por_s($request['LOGIN'] ?? $request['login'] ?? '');
$password = por_s($request['PASSWORD'] ?? $request['password'] ?? '');

$portIDFromQuery = por_s($request['portID'] ?? $request['port_id'] ?? $request['portid'] ?? '');
$portNameFromQuery = por_s($request['portName'] ?? $request['port_name'] ?? '');

$httpPort = (int)$httpPortRaw;
$socksPort = (int)$socksPortRaw;

function por_parse_port_pair(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d+)\|(\d+)$/', $raw, $m) !== 1) {
        return null;
    }
    $http = (int)($m[1] ?? 0);
    $socks = (int)($m[2] ?? 0);
    if ($http <= 0 || $socks <= 0) {
        return null;
    }
    return ['http_port' => $http, 'socks_port' => $socks];
}

/**
 * @return array<int,array{http_port:int,socks_port:int}>
 */
function por_extract_free_port_pairs(mixed $resp): array
{
    $out = [];
    $seen = [];
    if (!is_array($resp)) {
        return [];
    }

    $queue = [$resp];
    $steps = 0;
    while ($queue !== [] && $steps < 500) {
        $steps++;
        $cur = array_shift($queue);
        if (!is_array($cur)) {
            continue;
        }

        $hasHttp = array_key_exists('HTTP_PORT', $cur) || array_key_exists('http_port', $cur);
        $hasSocks = array_key_exists('SOCKS_PORT', $cur) || array_key_exists('socks_port', $cur);

        if ($hasHttp && $hasSocks) {
            $http = (int)($cur['HTTP_PORT'] ?? $cur['http_port'] ?? 0);
            $socks = (int)($cur['SOCKS_PORT'] ?? $cur['socks_port'] ?? 0);
            if ($http > 0 && $socks > 0) {
                $key = $http . '|' . $socks;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $out[] = ['http_port' => $http, 'socks_port' => $socks];
                }
            }
            continue;
        }

        foreach ($cur as $v) {
            if (is_array($v)) {
                $queue[] = $v;
            }
        }
    }

    return $out;
}

$portPairs = [];
$portPairsOptions = [];
$selectedPortPair = '';

// Prefer to use Proxysmart free ports (avoid "busy" and "not within range" errors).
$api = Sogerien::Api()->Proxysmartorg();
$freePortsResp = $api->get_free_tcp_ports();
$portPairs = por_extract_free_port_pairs($freePortsResp);

if ($portPairs !== []) {
    $map = [];
    foreach ($portPairs as $pp) {
        $val = (string)$pp['http_port'] . '|' . (string)$pp['socks_port'];
        $map[$val] = $pp;
        $portPairsOptions[] = [
            'value' => $val,
            'http_port' => (int)$pp['http_port'],
            'socks_port' => (int)$pp['socks_port'],
        ];
    }

    $requestedPair = por_s($request['PORT_PAIR'] ?? '');
    if ($requestedPair !== '' && isset($map[$requestedPair])) {
        $socksPort = (int)$map[$requestedPair]['socks_port'];
        $httpPort = (int)$map[$requestedPair]['http_port'];
        $selectedPortPair = $requestedPair;
    } else {
        $candidatePair = ($httpPort > 0 && $socksPort > 0) ? ($httpPort . '|' . $socksPort) : '';
        if ($candidatePair !== '' && isset($map[$candidatePair])) {
            $selectedPortPair = $candidatePair;
        } else {
            // Default to the first free port pair.
            $first = $portPairsOptions[0] ?? null;
            if (is_array($first)) {
                $selectedPortPair = (string)($first['value'] ?? '');
                $httpPort = (int)($first['http_port'] ?? 0);
                $socksPort = (int)($first['socks_port'] ?? 0);
            }
        }
    }
}

// Fallback: if free ports couldn't be extracted, still render a single option
// from the current HTTP/SOCKS values (may fail at store_port).
if ($portPairsOptions === [] && $httpPort > 0 && $socksPort > 0) {
    $selectedPortPair = $httpPort . '|' . $socksPort;
    $portPairsOptions = [
        [
            'value' => $selectedPortPair,
            'http_port' => $httpPort,
            'socks_port' => $socksPort,
        ],
    ];
}

// If client provided PORT_PAIR explicitly, always trust it.
$requestedPairParsed = por_parse_port_pair(por_s($request['PORT_PAIR'] ?? ''));
if ($requestedPairParsed !== null) {
    $httpPort = (int)$requestedPairParsed['http_port'];
    $socksPort = (int)$requestedPairParsed['socks_port'];
    $selectedPortPair = $httpPort . '|' . $socksPort;
}

// Required format:
// proxymintcom + SOCKS_PORT (digits) + random[a-zA-Z0-9]
$generatedPortId = por_gen_proxymintcom_id($socksPort, 10);

// If user passes something explicitly and it matches prefix-only format, keep it,
// otherwise enforce the required proxymintcom<SOCKS><random> format.
$portID = por_normalize_proxymintcom_id($portIDFromQuery);
if ($portID === '') {
    $portID = $generatedPortId;
}

$portName = por_normalize_proxymintcom_id($portNameFromQuery);
if ($portName === '') {
    $portName = $generatedPortId;
}

$alertType = '';
$alertText = '';

$dbgEnabled = false;
$storeResp = null;
$applyResp = null;

if ($orderSubmit) {
    $dbgEnabled = true;
    $dbg = Sogerien::Debager()->start(true);
    $api = Sogerien::Api()->Proxysmartorg();

    // Portid/Portname должны соответствовать выбранным SOCKS_PORT/формату.
    // Поэтому на POST принудительно перегенерим их из текущего $socksPort.
    $portID = por_gen_proxymintcom_id($socksPort, 10);
    $portName = $portID;

    $storePayload = [
        'IMEI' => $imei,
        'portID' => $portID,
        'portName' => $portName,
        'proxy_login' => $login,
        'proxy_password' => $password,
        'http_port' => $httpPort,
        'socks_port' => $socksPort,
    ];

    $dbg->log_input('ProxysmartOrder', 'store_port', $storePayload);
    $storeResp = $api->store_port($storePayload);
    $dbg->log_output('ProxysmartOrder', 'store_port', $storeResp);
    $dbg->log_output('ProxysmartOrder', 'store_port_error_meta', [
        'error' => $api->error,
        'last_http_code' => $api->last_http_code,
        'last_url' => $api->last_url,
    ]);

    $applyResp = null;
    if (is_array($storeResp)) {
        $dbg->log_input('ProxysmartOrder', 'apply_port', ['arg' => $portID]);
        $applyResp = $api->apply_port($portID);
        $dbg->log_output('ProxysmartOrder', 'apply_port', $applyResp);
        $dbg->log_output('ProxysmartOrder', 'apply_port_error_meta', [
            'error' => $api->error,
            'last_http_code' => $api->last_http_code,
            'last_url' => $api->last_url,
        ]);
    }

    if (($storeResp['ok'] ?? false) === true && is_array($applyResp)) {
        $alertType = 'success';
        $alertText = por_t('proxy.order_submitted');
    } else {
        $alertType = 'danger';
        $err = is_array($storeResp) ? (string)($storeResp['error'] ?? '') : '';
        $err2 = is_array($applyResp) ? (string)($applyResp['error'] ?? '') : '';
        $metaErr = trim($api->error);
        $alertText = $err !== '' ? $err : ($err2 !== '' ? $err2 : ($metaErr !== '' ? $metaErr : por_t('proxy.order_failed')));
    }
}

Sogerien::Template()->title = por_t('proxy.order_title_proxysmart');
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <?php if ($alertText !== ''): ?>
                <div class="alert alert-<?= por_h($alertType !== '' ? $alertType : 'info') ?>" role="alert">
                    <?= por_h($alertText) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/proxies/order/proxysmartorg">
                <input type="hidden" name="order_submit" value="1">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">IMEI</label>
                        <input class="form-control form-control-sm" type="text" name="IMEI"
                               value="<?= por_h($imei) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label"><?= por_h(por_t('proxy.port_pair_free_ports')) ?></label>
                        <select class="form-select form-select-sm" name="PORT_PAIR" required>
                            <?php foreach ($portPairsOptions as $opt): ?>
                                <?php
                                $val = (string)($opt['value'] ?? '');
                                $http = (int)($opt['http_port'] ?? 0);
                                $socks = (int)($opt['socks_port'] ?? 0);
                                if ($val === '' || $http <= 0 || $socks <= 0) continue;
                                ?>
                                <option value="<?= por_h($val) ?>"<?= $val === $selectedPortPair ? ' selected' : '' ?>
                                        data-http="<?= por_h((string)$http) ?>"
                                        data-socks="<?= por_h((string)$socks) ?>">
                                    HTTP <?= por_h((string)$http) ?> / SOCKS <?= por_h((string)$socks) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label mt-2">HTTP_PORT</label>
                        <input id="order_http_port" class="form-control form-control-sm" type="number" name="HTTP_PORT"
                               value="<?= (int)$httpPort ?>" required readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">SOCKS_PORT</label>
                        <input id="order_socks_port" class="form-control form-control-sm" type="number" name="SOCKS_PORT"
                               value="<?= (int)$socksPort ?>" required readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">LOGIN</label>
                        <input class="form-control form-control-sm" type="text" name="LOGIN"
                               value="<?= por_h($login) ?>" required readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">PASSWORD</label>
                        <input class="form-control form-control-sm" type="password" name="PASSWORD"
                               value="<?= por_h($password) ?>" required readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label"><?= por_h(por_t('proxy.portid_label')) ?></label>
                        <input class="form-control form-control-sm" type="text" name="portID"
                               value="<?= por_h($portID) ?>" required>
                        <div class="form-text"><?= por_h(por_t('common.example')) ?>: <code><?= por_h('proxymintcom5001randA1b2C3d4E5') ?></code></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label"><?= por_h(por_t('proxy.portname')) ?></label>
                        <input class="form-control form-control-sm" type="text" name="portName"
                               value="<?= por_h($portName) ?>" required>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <?= por_h(por_t('common.order')) ?>
                    </button>
                    <a class="btn btn-outline-secondary" href="/proxies/proxysmartorg"><?= por_h(por_t('common.back')) ?></a>
                </div>
            </form>

            <script>
            (function () {
                const sel = document.querySelector('select[name="PORT_PAIR"]');
                const http = document.getElementById('order_http_port');
                const socks = document.getElementById('order_socks_port');
                if (!sel || !http || !socks) return;

                function applyFromSelectedOption() {
                    const opt = sel.options[sel.selectedIndex];
                    if (!opt) return;
                    const httpVal = opt.getAttribute('data-http');
                    const socksVal = opt.getAttribute('data-socks');
                    if (httpVal) http.value = httpVal;
                    if (socksVal) socks.value = socksVal;
                }

                sel.addEventListener('change', applyFromSelectedOption);
                applyFromSelectedOption();
            })();
            </script>
        </div>
    </div>

</main>

<?php if ($dbgEnabled): ?>
    <div class="container mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="h5 mb-2"><?= por_h(por_t('proxy.debager_log')) ?></h3>
                <?php Sogerien::Debager()->print(true); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
Sogerien::Template()->footer();
