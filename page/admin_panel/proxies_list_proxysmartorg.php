<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function plp_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function plp_s(mixed $value): string
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

function plp_t(string $key, string $fallback = ''): string
{
    $value = Sogerien::Lang()->get($key);
    if ($fallback !== '' && $value === $key) {
        return $fallback;
    }

    return $value;
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}
Sogerien::AccessCheck()->init_db_alias($dbAlias);
$accessOk = Sogerien::AccessCheck()->check_access_or_show_login_form('proxies_list', 0, []);
if (!$accessOk) {
    Sogerien::Page()->title = plp_t('auth.access_denied', 'Access denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">РќРµС‚ РїСЂР°РІ РґРѕСЃС‚СѓРїР° Рє СЂР°Р·РґРµР»Сѓ.</div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

function plp_is_available_proxy(array $row): bool
{
    $is_expired = strtolower(plp_s($row['IS_EXPIRED'] ?? ''));
    if ($is_expired === '1' || $is_expired === 'true' || $is_expired === 'yes') {
        return false;
    }

    $is_over_quota = strtolower(plp_s($row['IS_OVER_QUOTA'] ?? ''));
    if ($is_over_quota === '1' || $is_over_quota === 'true' || $is_over_quota === 'yes') {
        return false;
    }

    $status = strtolower(plp_s($row['status'] ?? ''));
    if ($status !== '' && in_array($status, ['expired', 'inactive', 'failed', 'error', 'stopped', 'down'], true)) {
        return false;
    }

    return true;
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function plp_sort_rows(array &$rows, string $sortBy, string $sortDir): void
{
    usort(
        $rows,
        static function (array $a, array $b) use ($sortBy, $sortDir): int {
            $left = $a[$sortBy] ?? '';
            $right = $b[$sortBy] ?? '';
            $cmp = 0;

            if (is_numeric((string)$left) && is_numeric((string)$right)) {
                $cmp = (float)$left <=> (float)$right;
            } else {
                $cmp = strcasecmp(plp_s($left), plp_s($right));
            }

            return $sortDir === 'desc' ? -$cmp : $cmp;
        }
    );
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;

$sortBy = plp_s($request['sort_by'] ?? 'portID');
$sortDir = strtolower(plp_s($request['sort_dir'] ?? 'asc'));
$limit = (int)($request['limit'] ?? 50);
$offset = (int)($request['offset'] ?? 0);
$showAll = plp_s($request['show_all'] ?? '') === '1';
$allowedPageLimits = [10, 25, 50, 100, 250, 500, 1000];

if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'asc';
}
if (!in_array($limit, $allowedPageLimits, true)) {
    $limit = 50;
}
if ($offset < 0) {
    $offset = 0;
}

$cacheTtlSeconds = 180;
$now = time();

$cache = Sogerien::Cache();
$proxy_list_cache_file = 'Proxysmartorg_proxy_list_cache.json';
$proxy_list_cache_updated_at = $cache->get_last_update($proxy_list_cache_file);
$hasCacheByTime = $proxy_list_cache_updated_at > 0;
$cacheFresh = $hasCacheByTime && ($now - $proxy_list_cache_updated_at) <= $cacheTtlSeconds;

/** @var array<string,mixed>|null $proxy_list_cached */
$proxy_list_cached = null;
$hasCache = false;

if ($cacheFresh) {
    $proxy_list_cached = $cache->load($proxy_list_cache_file, $proxy_list_cache_updated_at);
    $hasCache = is_array($proxy_list_cached);
    $cacheFresh = $hasCache;
}

$apiError = '';
$apiWarning = '';
$columns = [];
$rows = [];
/** @var array<string,mixed>|null $listResp */
$listResp = null;
$usedFreshApi = false;

if ($cacheFresh) {
    $listResp = $proxy_list_cached;
} else {
    $freshResp = Sogerien::Api()->Proxysmartorg()->proxiesList([
        'limit' => 10000,
        'offset' => $offset,
    ]);

    if (($freshResp['ok'] ?? false) === true && isset($freshResp['data']) && is_array($freshResp['data'])) {
        $listResp = $freshResp;
        $cache->save($freshResp, $proxy_list_cache_file, $now);
        $usedFreshApi = true;
    } else {
        if (!$hasCache) {
            $proxy_list_cached = $cache->load($proxy_list_cache_file, $proxy_list_cache_updated_at);
            $hasCache = is_array($proxy_list_cached);
        }

        if ($hasCache) {
            $listResp = $proxy_list_cached;
            $apiError = plp_s($freshResp['error'] ?? plp_t('proxy.api_request_failed', 'Proxy list request failed'));
        } else {
            $listResp = $freshResp;
        }
    }
}

if (($listResp['ok'] ?? false) === true && isset($listResp['data']) && is_array($listResp['data'])) {
    $columns = isset($listResp['data']['columns']) && is_array($listResp['data']['columns']) ? array_values(array_map('strval', $listResp['data']['columns'])) : [];
    $rows = isset($listResp['data']['rows']) && is_array($listResp['data']['rows']) ? array_values(array_filter($listResp['data']['rows'], static fn($row): bool => is_array($row))) : [];
    $apiWarning = plp_s($listResp['warning'] ?? '');

    if (!$showAll) {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => plp_is_available_proxy($row)));
    }
} else {
    if ($apiError === '') {
        $apiError = plp_s($listResp['error'] ?? plp_t('proxy.api_request_failed', 'Proxy list request failed'));
    }
    if ($apiError === '') {
        $apiError = plp_t('proxy.api_request_failed', 'Proxy list request failed');
    }
}

if ($columns === []) {
    $columnsSet = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $col) {
            $columnsSet[(string)$col] = true;
        }
    }
    $columns = array_keys($columnsSet);
}

if (!in_array($sortBy, $columns, true)) {
    $sortBy = in_array('portID', $columns, true) ? 'portID' : ($columns[0] ?? 'id');
}
if ($sortBy !== '') {
    plp_sort_rows($rows, $sortBy, $sortDir);
}

$headers = [];
foreach ($columns as $col) {
    $headers[$col] = $col;
}

Sogerien::Page()->title = plp_t('proxy.catalog_title_proxysmart', 'Proxy Catalog (Proxysmart)');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if (!$showAll): ?>
        <div class="alert alert-info" role="alert"><?= plp_h(plp_t('proxy.show_available_only', 'Showing only available proxies.')) ?> <?= plp_h(plp_t('proxy.show_all_hint', 'Add ?show_all=1 to see all.')) ?></div>
    <?php endif; ?>

    <?php if ($apiError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= plp_h($apiError) ?></div>
    <?php endif; ?>
    <?php if ($apiWarning !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= plp_h($apiWarning) ?></div>
    <?php endif; ?>

    <?php
    $tr = Sogerien::TableRenderer();
    $tr->set_params = new SetParams();
    $tr->set_params->data = $rows;
    $tr->set_params->columns = $columns;
    $tr->set_params->headers = $headers;
    $tr->set_params->gridId = 'proxies_catalog_proxysmartorg_grid';
    $tr->set_params->searchCols = $columns;
    $tr->set_params->perPage = $limit;
    $tr->set_params->columnsOrder = $columns;

    $facets = [];
    if (in_array('status', $columns, true)) {
        $facets[] = ['title' => plp_t('common.status', 'Status'), 'column' => 'status', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    if (in_array('IMEI', $columns, true)) {
        $facets[] = ['title' => 'IMEI', 'column' => 'IMEI', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    if (in_array('LOGIN', $columns, true)) {
        $facets[] = ['title' => plp_t('common.login', 'Login'), 'column' => 'LOGIN', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    $tr->set_params->facets = $facets;

    $current_lang = Sogerien::Lang()->get_current_lang();
    if (!preg_match('/^[a-z]{2}$/', $current_lang)) {
        $current_lang = 'ru';
    }
    $table_cache_file = 'Proxysmartorg_proxilist_Tablerenderer_Cache_' . $current_lang . '.json';
    $tableHtml = '';

    // TEMP: table cache disabled - keep API cache only.
    // $tableHtml = Sogerien::Cache()->TableRendererCache->load($table_cache_file);

    if ($tableHtml === '') {
        ob_start();
        $tr->render();
        $tableHtml = (string)(ob_get_clean() ?: '');

        // TEMP: table cache disabled - keep API cache only.
        // if ($tableHtml !== '') {
        //     Sogerien::Cache()->TableRendererCache->save($tableHtml, $table_cache_file);
        // }
    }

    echo $tableHtml;
    ?>
    <script>
    (function () {
        const gid = 'proxies_catalog_proxysmartorg_grid';
        const tbl = document.getElementById(gid + '__tbl');
        if (!tbl) return;

        function cellText(tr, col) {
            if (!col) return '';
            const td = tr.querySelector("td[data-col='" + col + "']");
            return td ? String(td.textContent || '').trim() : '';
        }

        function pickFirst(tr, cols) {
            for (let c of cols) {
                const v = cellText(tr, c);
                if (v) return v;
            }
            return '';
        }

        tbl.addEventListener('click', function (e) {
            const tr = e.target ? e.target.closest('tr.tr-row') : null;
            if (!tr) return;

            // If user clicked a link inside the row - don't hijack.
            const a = e.target.closest('a');
            if (a && a.getAttribute('href')) return;

            const imei = pickFirst(tr, ['IMEI', 'imei']);
            const httpPort = pickFirst(tr, ['HTTP_PORT', 'http_port', 'port']);
            const socksPort = pickFirst(tr, ['SOCKS_PORT', 'socks_port']);
            const login = pickFirst(tr, ['LOGIN', 'login']);
            const password = pickFirst(tr, ['PASSWORD', 'password']);

            if (!imei) return;

            const url = '/proxies/order/proxysmartorg'
                + '?IMEI=' + encodeURIComponent(imei)
                + '&HTTP_PORT=' + encodeURIComponent(httpPort)
                + '&SOCKS_PORT=' + encodeURIComponent(socksPort)
                + '&LOGIN=' + encodeURIComponent(login)
                + '&PASSWORD=' + encodeURIComponent(password);

            window.location.href = url;
        }, { passive: true });
    })();
    </script>
</main>

<?php
Sogerien::Page()->footer();

