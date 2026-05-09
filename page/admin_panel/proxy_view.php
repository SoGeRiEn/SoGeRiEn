<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function pv_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pv_str(mixed $value): string
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
    if (is_array($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }
    return '';
}

function pv_t(string $key): string
{
    return Sogerien::Lang()->get($key);
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>|null
 */
function pv_pick_proxy(array $payload, int $proxyId): ?array
{
    $candidates = [];

    if (isset($payload['data']) && is_array($payload['data'])) {
        $candidates[] = $payload['data'];
    }
    if (isset($payload['proxy']) && is_array($payload['proxy'])) {
        $candidates[] = $payload['proxy'];
    }
    $candidates[] = $payload;

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        if (isset($candidate['proxy_id']) || isset($candidate['id'])) {
            $id = (int)($candidate['proxy_id'] ?? $candidate['id'] ?? 0);
            if ($proxyId <= 0 || $id === $proxyId) {
                return $candidate;
            }
        }

        foreach (['items', 'results', 'proxies', 'rows'] as $key) {
            $list = $candidate[$key] ?? null;
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int)($row['proxy_id'] ?? $row['id'] ?? 0);
                if ($proxyId > 0 && $id === $proxyId) {
                    return $row;
                }
            }
        }
    }

    return null;
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$proxyId = (int)($request['id'] ?? $request['proxy_id'] ?? 0);

$alertType = '';
$alertText = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($request['order_proxy_id'])) {
    $orderProxyId = (int)($request['order_proxy_id'] ?? 0);
    if ($orderProxyId > 0) {
        $orderResp = Sogerien::Api()->Cyberyozh()->orderCreate([
            'proxy_id' => $orderProxyId,
        ]);
        if (($orderResp['ok'] ?? false) === true) {
            $alertType = 'success';
            $alertText = pv_t('proxy.order_created');
        } else {
            $alertType = 'danger';
            $alertText = pv_str($orderResp['error'] ?? pv_t('proxy.order_create_failed'));
            if ($alertText === '') {
                $alertText = pv_t('proxy.order_create_failed');
            }
        }
    }
}

$proxy = null;
$apiError = '';

if ($proxyId > 0) {
    $infoResp = Sogerien::Api()->Cyberyozh()->proxyInfo([
        'proxy_id' => $proxyId,
    ]);
    if (($infoResp['ok'] ?? false) === true) {
        $payload = $infoResp['data'] ?? [];
        if (is_array($payload)) {
            $proxy = pv_pick_proxy($payload, $proxyId);
        }
    } else {
        $apiError = pv_str($infoResp['error'] ?? pv_t('proxy.action_failed'));
    }
}

$proxyRow = is_array($proxy) ? $proxy : [];
$protocolsRaw = $proxyRow['protocols'] ?? $proxyRow['protocol'] ?? [];
$protocols = '';
if (is_array($protocolsRaw)) {
    $parts = [];
    foreach ($protocolsRaw as $p) {
        $s = pv_str($p);
        if ($s !== '') {
            $parts[] = $s;
        }
    }
    $protocols = implode(', ', $parts);
} else {
    $protocols = pv_str($protocolsRaw);
}

$viewData = [
    'proxy_id' => (string)(int)($proxyRow['proxy_id'] ?? $proxyRow['id'] ?? $proxyId),
    'ip' => pv_str($proxyRow['ip'] ?? ''),
    'port' => pv_str($proxyRow['port'] ?? ''),
    'country' => strtoupper(pv_str($proxyRow['country'] ?? $proxyRow['country_code'] ?? '')),
    'city' => pv_str($proxyRow['city'] ?? ''),
    'isp' => pv_str($proxyRow['isp'] ?? $proxyRow['provider'] ?? ''),
    'speed' => pv_str($proxyRow['speed'] ?? $proxyRow['latency'] ?? ''),
    'uptime' => pv_str($proxyRow['uptime'] ?? $proxyRow['uptime_percent'] ?? ''),
    'price' => pv_str($proxyRow['price'] ?? $proxyRow['price_usd'] ?? $proxyRow['cost'] ?? ''),
    'protocols' => $protocols,
];

$uptimeHistory = $proxyRow['uptime_history'] ?? $proxyRow['history'] ?? null;
$loadInfo = $proxyRow['load'] ?? $proxyRow['usage'] ?? null;

Sogerien::Template()->title = pv_t('proxy.view_title');
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= pv_h($alertType !== '' ? $alertType : 'info') ?>" role="alert"><?= pv_h($alertText) ?></div>
    <?php endif; ?>

    <?php if ($proxyId <= 0): ?>
        <div class="alert alert-warning" role="alert"><?= pv_h(pv_t('common.invalid_id')) ?></div>
    <?php elseif ($apiError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= pv_h($apiError) ?></div>
    <?php elseif ($proxyRow === []): ?>
        <div class="alert alert-warning" role="alert"><?= pv_h(pv_t('proxy.not_found')) ?></div>
    <?php else: ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <table class="table table-sm table-bordered align-middle mb-3">
                    <tbody>
                    <?php foreach ($viewData as $k => $v): ?>
                        <tr>
                            <th style="width: 220px;"><?= pv_h($k) ?></th>
                            <td><?= pv_h($v) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" action="/proxy/view?id=<?= (int)$proxyId ?>" class="d-inline-block">
                    <input type="hidden" name="order_proxy_id" value="<?= (int)$proxyId ?>">
                    <button type="submit" class="btn btn-success"><?= pv_h(pv_t('proxy.order_button')) ?></button>
                </form>
            </div>
        </div>

        <?php if ($uptimeHistory !== null): ?>
            <div class="card mb-3">
                <div class="card-header"><?= pv_h(pv_t('proxy.uptime_history')) ?></div>
                <div class="card-body">
                    <pre class="mb-0"><?= pv_h(pv_str($uptimeHistory)) ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($loadInfo !== null): ?>
            <div class="card mb-3">
                <div class="card-header"><?= pv_h(pv_t('proxy.load_block')) ?></div>
                <div class="card-body">
                    <pre class="mb-0"><?= pv_h(pv_str($loadInfo)) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php
Sogerien::Template()->footer();
