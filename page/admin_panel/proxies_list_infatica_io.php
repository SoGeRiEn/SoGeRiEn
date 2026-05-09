<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function pli_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pli_s(mixed $value): string
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

function pli_t(string $key, string $fallback = ''): string
{
    $value = Sogerien::Lang()->get($key);
    if ($value === $key && $fallback !== '') {
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
    Sogerien::Page()->title = pli_t('auth.access_denied', 'Access denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">РќРµС‚ РїСЂР°РІ РґРѕСЃС‚СѓРїР° Рє СЂР°Р·РґРµР»Сѓ.</div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

function pli_normalize_access_type(mixed $value): string
{
    $raw = strtolower(pli_s($value));
    if ($raw === '') {
        return '';
    }

    if ($raw === 'shared' || $raw === 'public') {
        return 'public';
    }
    if ($raw === 'private') {
        return 'private';
    }

    return $raw;
}

/**
 * @return array<int,string>
 */
function pli_country_codes(mixed $value): array
{
    $raw = strtoupper(pli_s($value));
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $set = [];
    foreach ($parts as $code) {
        $code = trim($code);
        if ($code === '') {
            continue;
        }
        $set[$code] = true;
    }

    $codes = array_keys($set);
    sort($codes, SORT_NATURAL | SORT_FLAG_CASE);
    return $codes;
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,string>
 */
function pli_collect_country_facet_values(array $rows): array
{
    $set = [];
    foreach ($rows as $row) {
        $codes = pli_country_codes($row['location_country_code'] ?? '');
        foreach ($codes as $code) {
            $set[$code] = true;
        }
    }

    $values = array_keys($set);
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    return $values;
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function pli_sort_rows(array &$rows, string $sortBy, string $sortDir): void
{
    usort(
        $rows,
        static function (array $a, array $b) use ($sortBy, $sortDir): int {
            $left = $a[$sortBy] ?? '';
            $right = $b[$sortBy] ?? '';

            if (is_numeric((string)$left) && is_numeric((string)$right)) {
                $cmp = (float)$left <=> (float)$right;
            } else {
                $cmp = strcasecmp(pli_s($left), pli_s($right));
            }

            return $sortDir === 'desc' ? -$cmp : $cmp;
        }
    );
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$buyerUserId = (int)$users->user_id;
$buyerBalanceUsd = '0.00';
if ($buyerUserId > 0) {
    $balanceRaw = $users->get_balance_amount($buyerUserId, 'USD');
    if ($balanceRaw !== null) {
        $buyerBalanceUsd = number_format($balanceRaw, 2, '.', '');
    }
}

$sortBy = pli_s($request['sort_by'] ?? 'price_per_day');
$sortDir = strtolower(pli_s($request['sort_dir'] ?? 'asc'));
$limit = (int)($request['limit'] ?? 10);
$allowedPageLimits = [10, 25, 50, 100, 200];

if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'asc';
}
if (!in_array($limit, $allowedPageLimits, true)) {
    $limit = 10;
}

$cache = Sogerien::Cache();
$proxy_list_cache_file = 'InfaticaIo_proxy_list_cache_v2.json';
$proxy_list_cache_updated_at = $cache->get_last_update($proxy_list_cache_file);
if ($proxy_list_cache_updated_at <= 0) {
    Sogerien::ProxyCatalogCache()->refresh_infatica_cache(200);
    $proxy_list_cache_updated_at = $cache->get_last_update($proxy_list_cache_file);
}
$maxRows = 200;

$apiError = '';
$apiWarning = '';
$columns = [];
$rows = [];
/** @var array<string,mixed>|null $listResp */
$listResp = null;
if ($proxy_list_cache_updated_at > 0) {
    $loaded = $cache->load($proxy_list_cache_file, $proxy_list_cache_updated_at);
    if (is_array($loaded)) {
        $listResp = $loaded;
    }
}
if (!is_array($listResp)) {
    $listResp = [
        'ok' => false,
        'error' => 'Cache is empty. Run cron endpoint /cron/cache/proxies/infatica_io first.',
    ];
}

if (($listResp['ok'] ?? false) === true && isset($listResp['data']) && is_array($listResp['data'])) {
    $columns = isset($listResp['data']['columns']) && is_array($listResp['data']['columns'])
        ? array_values(array_map('strval', $listResp['data']['columns']))
        : [];
    $rows = isset($listResp['data']['rows']) && is_array($listResp['data']['rows'])
        ? array_values(array_filter($listResp['data']['rows'], static fn($row): bool => is_array($row)))
        : [];
    if (count($rows) > $maxRows) {
        $rows = array_slice($rows, 0, $maxRows);
    }
} else {
    $apiError = pli_s($listResp['error'] ?? pli_t('proxy.action_failed', 'Action failed'));
}

foreach ($rows as &$row) {
    if (!is_array($row)) {
        continue;
    }
    if (isset($row['proxy_api_type']) && !isset($row['proxy_category'])) {
        $row['proxy_category'] = $row['proxy_api_type'];
    }
    if (isset($row['traffic_gb']) && !isset($row['traffic_limitation'])) {
        $row['traffic_limitation'] = $row['traffic_gb'];
    }
    unset($row['proxy_api_type'], $row['traffic_gb']);

    if (array_key_exists('access_type', $row)) {
        $row['access_type'] = pli_normalize_access_type($row['access_type']);
    }
    if (!isset($row['API']) || pli_s($row['API']) === '') {
        $row['API'] = 'infatica_io';
    }
    $row['buy_action'] = 'buy';
}
unset($row);

if ($columns === []) {
    $columnsSet = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $col) {
            $columnsSet[(string)$col] = true;
        }
    }
    $preferredCols = ['API', 'id', 'title', 'location_country_code', 'price_usd', 'price_per_day', 'days', 'proxy_category', 'stock_status', 'traffic_limitation', 'price_per_gb', 'is_auto_renewal_possible', 'access_type'];
    $columns = [];
    foreach ($preferredCols as $c) {
        if (isset($columnsSet[$c])) {
            $columns[] = $c;
        }
    }
    foreach (array_keys($columnsSet) as $c) {
        if (!in_array($c, $columns, true)) {
            $columns[] = $c;
        }
    }
}

if (!in_array('buy_action', $columns, true)) {
    $columns[] = 'buy_action';
}

if (!in_array($sortBy, $columns, true)) {
    $sortBy = in_array('price_per_day', $columns, true) ? 'price_per_day' : ($columns[0] ?? 'id');
}
if ($sortBy !== '') {
    pli_sort_rows($rows, $sortBy, $sortDir);
}

$headers = [];
foreach ($columns as $col) {
    $headers[$col] = $col;
}
if (isset($headers['location_country_code'])) {
    $headers['location_country_code'] = pli_t('common.country', 'Country');
}
if (isset($headers['price_usd'])) {
    $headers['price_usd'] = 'Price $';
}
if (isset($headers['proxy_category'])) {
    $headers['proxy_category'] = 'category';
}
if (isset($headers['stock_status'])) {
    $headers['stock_status'] = 'stock';
}
if (isset($headers['price_per_day'])) {
    $headers['price_per_day'] = pli_t('proxy.price_per_day', 'Price per day');
}
if (isset($headers['traffic_limitation'])) {
    $headers['traffic_limitation'] = pli_t('proxy.traffic_gb', 'Traffic GB');
}
if (isset($headers['price_per_gb'])) {
    $headers['price_per_gb'] = '$ per 1 Gb';
}
if (isset($headers['is_auto_renewal_possible'])) {
    $headers['is_auto_renewal_possible'] = pli_t('proxy.auto_renewal', 'auto renewal');
}
if (isset($headers['access_type'])) {
    $headers['access_type'] = pli_t('proxy.access_short', 'access');
}
if (isset($headers['buy_action'])) {
    $headers['buy_action'] = 'buy';
}

Sogerien::Page()->title = pli_t('proxy.catalog_title', 'Proxy Catalog');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($apiError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= pli_h($apiError) ?></div>
    <?php endif; ?>
    <?php if ($apiWarning !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= pli_h($apiWarning) ?></div>
    <?php endif; ?>

    <?php
    $tr = Sogerien::TableRenderer();
    $tr->set_params = new SetParams();
    $tr->set_params->data = $rows;
    $tr->set_params->columns = $columns;
    $tr->set_params->headers = $headers;
    $tr->set_params->gridId = 'proxies_catalog_infatica_grid';
    $tr->set_params->searchCols = $columns;
    $tr->set_params->perPage = $limit;
    $tr->set_params->columnsOrder = $columns;
    $tr->set_params->column_view['location_country_code'] = [
        'width' => '60px',
        'ellipsis' => true,
    ];

    $facets = [];
    if (in_array('proxy_category', $columns, true)) {
        $facets[] = ['title' => pli_t('proxy.category', 'Category'), 'column' => 'proxy_category', 'type' => 'dropdown_multi', 'slot' => 'side', 'search' => true];
    }
    if (in_array('location_country_code', $columns, true)) {
        $facets[] = [
            'title' => pli_t('common.country', 'Country'),
            'column' => 'location_country_code',
            'type' => 'dropdown_multi',
            'match' => 'csv_token',
            'values' => pli_collect_country_facet_values($rows),
            'search' => true,
            'slot' => 'side',
        ];
    }
    if (in_array('stock_status', $columns, true)) {
        $facets[] = ['title' => pli_t('proxy.stock', 'Stock'), 'column' => 'stock_status', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }

    $filtersFromApi = ($listResp['data'] ?? [])['filters'] ?? [];
    $rangeNumFacet = static function (string $col, string $title) use ($filtersFromApi): array {
        $facet = ['title' => $title, 'column' => $col, 'type' => 'range_number'];
        $vals = $filtersFromApi[$col] ?? null;
        if (is_array($vals) && $vals !== []) {
            $numVals = array_values(array_filter(array_map(
                static fn($v) => is_numeric((string)$v) ? (float)$v : null,
                $vals
            )));
            if ($numVals !== []) {
                sort($numVals, SORT_NUMERIC);
                $facet['values'] = array_values(array_unique($numVals));
            }
        }
        return $facet;
    };
    if (in_array('price_usd', $columns, true)) {
        $facets[] = $rangeNumFacet('price_usd', 'Price $');
    }
    if (in_array('price_per_day', $columns, true)) {
        $facets[] = $rangeNumFacet('price_per_day', pli_t('proxy.price_per_day', 'Price per day'));
    }
    if (in_array('days', $columns, true)) {
        $facets[] = ['title' => pli_t('proxy.days', 'Days'), 'column' => 'days', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    if (in_array('access_type', $columns, true)) {
        $facets[] = ['title' => pli_t('proxy.access', 'Access'), 'column' => 'access_type', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    if (in_array('price_per_gb', $columns, true)) {
        $facets[] = $rangeNumFacet('price_per_gb', '$ per 1 Gb');
    }
    $tr->set_params->facets = $facets;

    $tr->set_params->formatters['location_country_code'] = static function ($value): string {
        $codes = pli_country_codes($value);
        if ($codes === []) {
            return '';
        }

        $flat = implode(',', $codes);
        $full = implode(', ', $codes);
        $dialogBody = '';
        foreach ($codes as $code) {
            $dialogBody .= '<div class="mb-1"><strong>' . pli_h($code) . '</strong></div>';
        }

        $dialogButtons = json_encode(
            [['label' => 'Close', 'role' => 'cancel', 'kind' => 'secondary']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($dialogButtons)) {
            $dialogButtons = '[]';
        }

        return '<span class="d-none">' . pli_h($flat . ',') . '</span>'
            . '<a href="#" class="tr-action proxy-country-list-link"'
            . ' data-action-type="GET"'
            . ' data-href="#"'
            . ' data-has-dialog="1"'
            . ' data-dialog-title="' . pli_h(pli_t('proxy.countries', 'Countries')) . '"'
            . ' data-dialog-msg="' . pli_h($dialogBody) . '"'
            . ' data-dialog-buttons="' . pli_h($dialogButtons) . '">'
            . pli_h($full)
            . '</a>';
    };
    $tr->set_params->formatters['is_auto_renewal_possible'] = static function ($value): string {
        $v = pli_s($value);
        if ($v === '1') {
            return pli_h(pli_t('common.yes', 'Yes'));
        }
        if ($v === '' || $v === '0') {
            return pli_h(pli_t('common.no', 'No'));
        }
        return pli_h($v);
    };
    $tr->set_params->formatters['access_type'] = static function ($value): string {
        return pli_h(pli_normalize_access_type($value));
    };
    $tr->set_params->formatters['traffic_limitation'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ((int)$value === -1) {
            return pli_h(pli_t('proxy.unlimited', 'Unlimited'));
        }
        return pli_h(number_format((float)$value, 2) . ' GB');
    };
    $tr->set_params->formatters['price_per_day'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return pli_h(number_format((float)$value, 4));
    };
    $tr->set_params->formatters['price_per_gb'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ((int)$value === -1) {
            return pli_h('-1');
        }
        return pli_h(number_format((float)$value, 4));
    };
    $tr->set_params->formatters['price_usd'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return pli_h(number_format((float)$value, 2));
    };
    $tr->set_params->formatters['buy_action'] = static function ($value, array $row) use ($buyerUserId): string {
        $api = pli_s($row['API'] ?? '');
        $id = pli_s($row['id'] ?? '');
        $title = pli_s($row['title'] ?? '');
        $stock = strtolower(pli_s($row['stock_status'] ?? ''));
        $priceUsd = pli_s($row['price_usd'] ?? '');
        $days = pli_s($row['days'] ?? '');
        $country = pli_s($row['location_country_code'] ?? '');
        $category = pli_s($row['proxy_category'] ?? '');
        $autoRenewPossible = (string)($row['is_auto_renewal_possible'] ?? '') === '1' ? '1' : '0';

        if ($id === '') {
            return '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>n/a</button>';
        }
        if ($stock !== 'in_stock') {
            return '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Out</button>';
        }
        if ($buyerUserId <= 0) {
            return '<a class="btn btn-sm btn-outline-primary" href="/admin">Sign in</a>';
        }

        return '<button type="button" class="btn btn-sm btn-primary proxy-buy-btn"'
            . ' data-id="' . pli_h($id) . '"'
            . ' data-api="' . pli_h($api) . '"'
            . ' data-title="' . pli_h($title) . '"'
            . ' data-price-usd="' . pli_h($priceUsd) . '"'
            . ' data-days="' . pli_h($days) . '"'
            . ' data-country="' . pli_h($country) . '"'
            . ' data-category="' . pli_h($category) . '"'
            . ' data-auto-renew-possible="' . pli_h($autoRenewPossible) . '"'
            . '>Add to cart</button>';
    };

    $tr->render();
    ?>
    <div class="pm-cart-fab-wrap">
        <button type="button" class="btn btn-dark pm-cart-fab" id="pmProxyCartFab" data-bs-toggle="offcanvas" data-bs-target="#pmProxyCartCanvas" aria-controls="pmProxyCartCanvas">
            Cart <span class="badge text-bg-warning ms-2" id="pmProxyCartCount">0</span>
        </button>
    </div>

    <form id="pmProxyCartCheckoutForm" method="post" action="/proxy/checkout" class="d-none">
        <input type="hidden" name="cart_payload" id="pmProxyCartPayload" value="[]">
    </form>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="pmProxyCartCanvas" aria-labelledby="pmProxyCartCanvasLabel">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title" id="pmProxyCartCanvasLabel">Proxy cart</h5>
                <div class="small text-muted">User #<?= (int)$buyerUserId ?> - balance USD <?= pli_h($buyerBalanceUsd) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div id="pmProxyCartEmpty" class="alert alert-secondary">Cart is empty.</div>
            <div id="pmProxyCartItems" class="d-grid gap-2"></div>
            <hr>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Total</strong>
                <strong>$<span id="pmProxyCartTotal">0.00</span></strong>
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-primary" id="pmProxyCartCheckoutBtn">Pay order</button>
                <button type="button" class="btn btn-outline-secondary" id="pmProxyCartClearBtn">Clear cart</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pmProxyAddedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Proxy added</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="pmProxyAddedModalText">Item was added to cart.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="pmProxyContinueShoppingBtn" data-bs-dismiss="modal">Continue shopping</button>
                    <button type="button" class="btn btn-primary" id="pmProxyPlaceOrderBtn">Place order</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .pm-cart-fab-wrap { position: fixed; right: 24px; bottom: 24px; z-index: 1080; }
        .pm-cart-fab { border-radius: 999px; padding: 12px 18px; box-shadow: 0 14px 32px rgba(0,0,0,0.28); }
        .pm-cart-item { border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 12px; background: rgba(255,255,255,0.03); }
        .pm-cart-item-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
        .pm-cart-item-title { font-weight: 600; }
        .pm-cart-item-meta { font-size: 12px; color: #9ca3af; }
        .pm-cart-item-price { font-weight: 700; white-space: nowrap; }
    </style>

    <script>
        (() => {
            const storageKey = 'pm_proxy_cart_infatica_v1';
            const addedModalEl = document.getElementById('pmProxyAddedModal');
            const addedModal = addedModalEl && window.bootstrap ? new bootstrap.Modal(addedModalEl) : null;
            const cartCanvasEl = document.getElementById('pmProxyCartCanvas');
            const cartCanvas = cartCanvasEl && window.bootstrap ? bootstrap.Offcanvas.getOrCreateInstance(cartCanvasEl) : null;

            const loadCart = () => {
                try {
                    const raw = localStorage.getItem(storageKey);
                    const parsed = raw ? JSON.parse(raw) : [];
                    return Array.isArray(parsed) ? parsed : [];
                } catch (_err) {
                    return [];
                }
            };

            const saveCart = (cart) => {
                localStorage.setItem(storageKey, JSON.stringify(cart));
                renderCart();
            };

            const normalizeButtonItem = (button) => ({
                id: button.dataset.id || '',
                title: button.dataset.title || '',
                api: button.dataset.api || 'infatica_io',
                price_usd: button.dataset.priceUsd || '0.00',
                days: button.dataset.days || '',
                country: button.dataset.country || '',
                category: button.dataset.category || '',
                auto_renew: false,
                auto_renew_possible: button.dataset.autoRenewPossible === '1'
            });

            const renderCart = () => {
                const cart = loadCart();
                const itemsEl = document.getElementById('pmProxyCartItems');
                const emptyEl = document.getElementById('pmProxyCartEmpty');
                const countEl = document.getElementById('pmProxyCartCount');
                const totalEl = document.getElementById('pmProxyCartTotal');
                if (!itemsEl || !emptyEl || !countEl || !totalEl) {
                    return;
                }

                countEl.textContent = String(cart.length);
                emptyEl.style.display = cart.length === 0 ? '' : 'none';
                itemsEl.innerHTML = '';

                let total = 0;
                cart.forEach((item, index) => {
                    const price = Number.parseFloat(item.price_usd || '0');
                    total += Number.isFinite(price) ? price : 0;

                    const wrapper = document.createElement('div');
                    wrapper.className = 'pm-cart-item';
                    wrapper.innerHTML = `
                        <div class="pm-cart-item-head">
                            <div>
                                <div class="pm-cart-item-title">${item.title || item.id}</div>
                                <div class="pm-cart-item-meta">${item.api || '-'} - ${item.country || '-'} - ${item.category || '-'} - ${item.days || '-'} days</div>
                            </div>
                            <div class="pm-cart-item-price">$${(Number.isFinite(price) ? price : 0).toFixed(2)}</div>
                        </div>
                        <div class="form-check form-switch mb-2 ${item.auto_renew_possible ? '' : 'd-none'}">
                            <input class="form-check-input pm-cart-auto-renew" type="checkbox" id="pmCartAutoRenew${index}" data-index="${index}" ${item.auto_renew ? 'checked' : ''}>
                            <label class="form-check-label" for="pmCartAutoRenew${index}">Auto-renew</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger pm-cart-remove-btn" data-index="${index}">Remove</button>
                    `;
                    itemsEl.appendChild(wrapper);
                });

                totalEl.textContent = total.toFixed(2);
            };

            document.addEventListener('click', (event) => {
                const buyButton = event.target.closest('.proxy-buy-btn');
                if (buyButton) {
                    const item = normalizeButtonItem(buyButton);
                    if (!item.id) {
                        return;
                    }
                    const cart = loadCart();
                    if (!cart.some((row) => row.id === item.id)) {
                        cart.push(item);
                        saveCart(cart);
                    } else {
                        renderCart();
                    }
                    const textEl = document.getElementById('pmProxyAddedModalText');
                    if (textEl) {
                        textEl.textContent = `${item.title || item.id} added to cart.`;
                    }
                    if (addedModal) {
                        addedModal.show();
                    }
                    return;
                }

                const removeButton = event.target.closest('.pm-cart-remove-btn');
                if (removeButton) {
                    const index = Number.parseInt(removeButton.dataset.index || '-1', 10);
                    const cart = loadCart();
                    if (index >= 0 && index < cart.length) {
                        cart.splice(index, 1);
                        saveCart(cart);
                    }
                }
            });

            document.addEventListener('change', (event) => {
                const renewToggle = event.target.closest('.pm-cart-auto-renew');
                if (!renewToggle) {
                    return;
                }
                const index = Number.parseInt(renewToggle.dataset.index || '-1', 10);
                const cart = loadCart();
                if (index >= 0 && index < cart.length) {
                    cart[index].auto_renew = !!renewToggle.checked;
                    saveCart(cart);
                }
            });

            document.getElementById('pmProxyPlaceOrderBtn')?.addEventListener('click', () => {
                if (addedModal) {
                    addedModal.hide();
                }
                if (cartCanvas) {
                    cartCanvas.show();
                }
            });

            document.getElementById('pmProxyCartClearBtn')?.addEventListener('click', () => {
                saveCart([]);
            });

            document.getElementById('pmProxyCartCheckoutBtn')?.addEventListener('click', () => {
                const cart = loadCart();
                if (!cart.length) {
                    window.alert('Cart is empty.');
                    return;
                }
                const payloadInput = document.getElementById('pmProxyCartPayload');
                const form = document.getElementById('pmProxyCartCheckoutForm');
                if (!payloadInput || !form) {
                    return;
                }
                payloadInput.value = JSON.stringify(cart);
                form.submit();
            });

            renderCart();
        })();
    </script>
</main>

<?php
Sogerien::Page()->footer();



