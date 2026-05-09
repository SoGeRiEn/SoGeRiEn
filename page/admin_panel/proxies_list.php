<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function pl_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pl_s(mixed $value): string
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

function pl_normalize_access_type(mixed $value): string
{
    $raw = strtolower(pl_s($value));
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

function pl_t(string $key): string
{
    return Sogerien::Lang()->get($key);
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}
Sogerien::AccessCheck()->init_db_alias($dbAlias);
$accessOk = Sogerien::AccessCheck()->check_access_or_show_login_form('proxies_list', 0, []);
if (!$accessOk) {
    Sogerien::Page()->title = pl_t('auth.access_denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">РќРµС‚ РїСЂР°РІ РґРѕСЃС‚СѓРїР° Рє СЂР°Р·РґРµР»Сѓ.</div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

/**
 * @return array<int,string>
 */
function pl_country_codes(mixed $value): array
{
    $raw = strtoupper(pl_s($value));
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
function pl_collect_country_facet_values(array $rows): array
{
    $set = [];
    foreach ($rows as $row) {
        $codes = pl_country_codes($row['location_country_code'] ?? '');
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
function pl_sort_rows(array &$rows, string $sortBy, string $sortDir): void
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
                $cmp = strcasecmp(pl_s($left), pl_s($right));
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

$sortBy = pl_s($request['sort_by'] ?? 'price_usd');
$sortDir = strtolower(pl_s($request['sort_dir'] ?? 'asc'));
$limit = (int)($request['limit'] ?? 10);
$allowedPageLimits = [10, 25, 50, 100, 200];

if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'asc';
}
if (!in_array($limit, $allowedPageLimits, true)) {
    $limit = 10;
}

$maxRows = 200;

$apiError = '';
$columns = [];
$rows = [];
/** @var array<string,mixed>|null $listResp */
$listResp = Sogerien::API()->Cyberyozh()->proxiesList([
    'limit' => $maxRows,
    'offset' => 0,
]);
if (!is_array($listResp)) {
    $listResp = [
        'ok' => false,
        'error' => Sogerien::API()->Cyberyozh()->error !== '' ? Sogerien::API()->Cyberyozh()->error : 'CyberYozh API request failed.',
    ];
}

if (($listResp['ok'] ?? false) === true && isset($listResp['data']) && is_array($listResp['data'])) {
    $columns = isset($listResp['data']['columns']) && is_array($listResp['data']['columns']) ? array_values(array_map('strval', $listResp['data']['columns'])) : [];
    $rows = isset($listResp['data']['rows']) && is_array($listResp['data']['rows']) ? array_values(array_filter($listResp['data']['rows'], static fn($row): bool => is_array($row))) : [];
    if (count($rows) > $maxRows) {
        $rows = array_slice($rows, 0, $maxRows);
    }
} else {
    if ($apiError === '') {
        $apiError = pl_s($listResp['error'] ?? pl_t('proxy.action_failed'));
    }
    if ($apiError === '') {
        $apiError = pl_t('proxy.action_failed');
    }
}

foreach ($rows as &$row) {
    if (array_key_exists('access_type', $row)) {
        $row['access_type'] = pl_normalize_access_type($row['access_type']);
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
    $sortBy = in_array('price_usd', $columns, true) ? 'price_usd' : ($columns[0] ?? 'id');
}
if ($sortBy !== '') {
    pl_sort_rows($rows, $sortBy, $sortDir);
}

$headers = [];
foreach ($columns as $col) {
    $headers[$col] = $col;
}
if (isset($headers['location_country_code'])) {
    $headers['location_country_code'] = pl_t('common.country');
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
    $headers['price_per_day'] = pl_t('proxy.price_per_day');
}
if (isset($headers['traffic_limitation'])) {
    $headers['traffic_limitation'] = pl_t('proxy.traffic_gb');
}
if (isset($headers['price_per_gb'])) {
    $headers['price_per_gb'] = '$ per 1 Gb';
}
if (isset($headers['is_auto_renewal_possible'])) {
    $headers['is_auto_renewal_possible'] = pl_t('proxy.auto_renewal');
}
if (isset($headers['access_type'])) {
    $headers['access_type'] = pl_t('proxy.access_short');
}
if (isset($headers['buy_action'])) {
    $headers['buy_action'] = 'buy';
}

Sogerien::Page()->title = pl_t('proxy.catalog_title');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($apiError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= pl_h($apiError) ?></div>
    <?php endif; ?>

    <?php
    $tr = Sogerien::TableRenderer();
    $tr->set_params = new SetParams();
    $tr->set_params->data = $rows;
    $tr->set_params->columns = $columns;
    $tr->set_params->headers = $headers;
    $tr->set_params->gridId = 'proxies_catalog_grid';
    $tr->set_params->searchCols = $columns;
    $tr->set_params->perPage = $limit;
    $tr->set_params->columnsOrder = $columns;
    $tr->set_params->column_view['location_country_code'] = [
        'width' => '60px',
        'ellipsis' => true,
    ];

    $facets = [];
    if (in_array('proxy_category', $columns, true)) {
        $facets[] = ['title' => pl_t('proxy.category'), 'column' => 'proxy_category', 'type' => 'dropdown_multi', 'slot' => 'side', 'search' => true, 'class' => 'tr-facet--compact'];
    }
    if (in_array('location_country_code', $columns, true)) {
        $facets[] = [
            'title' => pl_t('common.country'),
            'column' => 'location_country_code',
            'type' => 'dropdown_multi',
            'match' => 'csv_token',
            'values' => pl_collect_country_facet_values($rows),
            'search' => true,
            'slot' => 'side',
            'class' => 'tr-facet--compact',
        ];
    }
    if (in_array('stock_status', $columns, true)) {
        $facets[] = ['title' => pl_t('proxy.stock'), 'column' => 'stock_status', 'type' => 'dropdown_multi', 'slot' => 'side', 'class' => 'tr-facet--compact'];
    }
    $filtersFromApi = ($listResp['data'] ?? [])['filters'] ?? [];
    $rangeNumFacet = static function (string $col, string $titleKey) use ($filtersFromApi): array {
        $facet = ['title' => pl_t($titleKey), 'column' => $col, 'type' => 'range_number', 'class' => 'tr-facet--wide'];
        $vals = $filtersFromApi[$col] ?? null;
        if (is_array($vals) && $vals !== []) {
            $numVals = array_values(array_filter(array_map(static fn($v) => is_numeric((string)$v) ? (float)$v : null, $vals)));
            if ($numVals !== []) {
                sort($numVals, SORT_NUMERIC);
                $facet['values'] = array_values(array_unique($numVals));
            }
        }
        return $facet;
    };
    if (in_array('price_usd', $columns, true)) {
        $facets[] = $rangeNumFacet('price_usd', 'proxy.price_usd');
    }
    if (in_array('price_per_day', $columns, true)) {
        $facets[] = $rangeNumFacet('price_per_day', 'proxy.price_per_day');
    }
    if (in_array('days', $columns, true)) {
        $facets[] = ['title' => pl_t('proxy.days'), 'column' => 'days', 'type' => 'dropdown_multi', 'slot' => 'side', 'class' => 'tr-facet--compact'];
    }
    if (in_array('access_type', $columns, true)) {
        $facets[] = ['title' => pl_t('proxy.access'), 'column' => 'access_type', 'type' => 'dropdown_multi', 'slot' => 'side', 'class' => 'tr-facet--compact'];
    }
    if (in_array('price_per_gb', $columns, true)) {
        $facets[] = $rangeNumFacet('price_per_gb', 'proxy.price_per_gb');
    }
    $tr->set_params->facets = $facets;

    $tr->set_params->formatters['location_country_code'] = static function ($value): string {
        $codes = pl_country_codes($value);
        if ($codes === []) {
            return '';
        }

        $flat = implode(',', $codes);
        $full = implode(', ', $codes);
        $dialogBody = '';
        foreach ($codes as $code) {
            $dialogBody .= '<div class="mb-1"><strong>' . pl_h($code) . '</strong></div>';
        }

        $dialogButtons = json_encode(
            [
                ['label' => 'Close', 'role' => 'cancel', 'kind' => 'secondary'],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($dialogButtons)) {
            $dialogButtons = '[]';
        }

        return '<span class="d-none">' . pl_h($flat . ',') . '</span>'
            . '<a href="#" class="tr-action proxy-country-list-link"'
            . ' data-action-type="GET"'
            . ' data-href="#"'
            . ' data-has-dialog="1"'
            . ' data-dialog-title="' . pl_h(pl_t('proxy.countries')) . '"'
            . ' data-dialog-msg="' . pl_h($dialogBody) . '"'
            . ' data-dialog-buttons="' . pl_h($dialogButtons) . '">'
            . pl_h($full)
            . '</a>';
    };
    $tr->set_params->formatters['is_auto_renewal_possible'] = static function ($value): string {
        $v = pl_s($value);
        if ($v === '1') {
            return pl_h(pl_t('common.yes'));
        }
        if ($v === '' || $v === '0') {
            return pl_h(pl_t('common.no'));
        }
        return pl_h($v);
    };
    $tr->set_params->formatters['access_type'] = static function ($value): string {
        return pl_h(pl_normalize_access_type($value));
    };
    $tr->set_params->formatters['traffic_limitation'] = static function ($value): string {
        $v = $value;
        if ($v === null || $v === '') {
            return '';
        }
        if ((int)$v === -1) {
            return pl_h(pl_t('proxy.unlimited'));
        }
        return pl_h(number_format((float)$v, 2) . ' GB');
    };
    $tr->set_params->formatters['price_per_day'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return pl_h(number_format((float)$value, 4));
    };
    $tr->set_params->formatters['price_per_gb'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ((int)$value === -1) {
            return pl_h('-1');
        }
        return pl_h(number_format((float)$value, 4));
    };
    $tr->set_params->formatters['buy_action'] = static function ($value, array $row) use ($buyerUserId): string {
        $id = pl_s($row['id'] ?? '');
        $title = pl_s($row['title'] ?? '');
        $stock = strtolower(pl_s($row['stock_status'] ?? ''));
        $priceUsd = pl_s($row['price_usd'] ?? '');
        $days = pl_s($row['days'] ?? '');
        $country = pl_s($row['location_country_code'] ?? '');
        $category = pl_s($row['proxy_category'] ?? '');
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

        $query = http_build_query([
            'id' => $id,
            'title' => $title,
            'price_usd' => $priceUsd,
            'days' => $days,
            'country' => $country,
            'category' => $category,
            'auto_renew_possible' => $autoRenewPossible,
        ]);

        return '<a class="btn btn-sm btn-success" href="/proxies/order/cyberyozh?' . pl_h($query) . '">Buy now</a>';
    };
    $tr->render();
    ?>
</main>

<?php

//echo "<pre>";
//Sogerien::Debager()->print();
//echo "</pre>";
Sogerien::Page()->footer();



