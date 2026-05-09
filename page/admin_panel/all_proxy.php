<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function ap_h(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_scalar($value)) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if (!is_string($json)) {
        $json = '';
    }

    return htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ap_s(mixed $value): string
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

function ap_t(string $key, string $fallback = ''): string
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
    Sogerien::Page()->title = ap_t('auth.access_denied', 'Access denied');
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">РќРµС‚ РїСЂР°РІ РґРѕСЃС‚СѓРїР° Рє СЂР°Р·РґРµР»Сѓ.</div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

function ap_normalize_access_type(mixed $value): string
{
    $raw = strtolower(ap_s($value));
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
function ap_country_codes(mixed $value): array
{
    $raw = strtoupper(ap_s($value));
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
function ap_collect_country_facet_values(array $rows): array
{
    $set = [];
    foreach ($rows as $row) {
        $codes = ap_country_codes($row['location_country_code'] ?? '');
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
function ap_sort_rows(array &$rows, string $sortBy, string $sortDir): void
{
    usort(
        $rows,
        static function (array $a, array $b) use ($sortBy, $sortDir): int {
            $left = $a[$sortBy] ?? '';
            $right = $b[$sortBy] ?? '';

            if (is_numeric((string)$left) && is_numeric((string)$right)) {
                $cmp = (float)$left <=> (float)$right;
            } else {
                $cmp = strcasecmp(ap_s($left), ap_s($right));
            }
            return $sortDir === 'desc' ? -$cmp : $cmp;
        }
    );
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,float>
 */
function ap_collect_numeric_facet_values(array $rows, string $column): array
{
    $values = [];
    foreach ($rows as $row) {
        if (!array_key_exists($column, $row)) {
            continue;
        }
        $raw = ap_s($row[$column]);
        if ($raw === '' || !is_numeric($raw)) {
            continue;
        }
        $values[] = (float)$raw;
    }
    if ($values === []) {
        return [];
    }
    sort($values, SORT_NUMERIC);
    return array_values(array_unique($values));
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,string>
 */
function ap_build_columns(array $rows): array
{
    $columnSet = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $col) {
            $columnSet[(string)$col] = true;
        }
    }

    $preferred = [
        'API',
        'id',
        'title',
        'location_country_code',
        'price_usd',
        'price_per_day',
        'days',
        'proxy_category',
        'stock_status',
        'traffic_limitation',
        'price_per_gb',
        'is_auto_renewal_possible',
        'access_type',
    ];

    $columns = [];
    foreach ($preferred as $col) {
        if (isset($columnSet[$col])) {
            $columns[] = $col;
        }
    }
    return $columns;
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
function ap_normalize_rows_for_source(array $rows, string $source): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized = $row;

        if (!isset($normalized['API']) || ap_s($normalized['API']) === '') {
            $normalized['API'] = $source;
        }

        if (isset($normalized['proxy_api_type']) && !isset($normalized['proxy_category'])) {
            $normalized['proxy_category'] = $normalized['proxy_api_type'];
        }
        if (isset($normalized['traffic_gb']) && !isset($normalized['traffic_limitation'])) {
            $normalized['traffic_limitation'] = $normalized['traffic_gb'];
        }

        unset($normalized['proxy_api_type'], $normalized['traffic_gb']);

        if (array_key_exists('access_type', $normalized)) {
            $normalized['access_type'] = ap_normalize_access_type($normalized['access_type']);
        }

        $out[] = $normalized;
    }

    return $out;
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,string>
 */
function ap_collect_column_values(array $rows, string $column): array
{
    $set = [];
    foreach ($rows as $row) {
        $value = ap_s($row[$column] ?? '');
        if ($value === '') {
            continue;
        }
        $set[$value] = true;
    }

    $values = array_keys($set);
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    return $values;
}

/**
 * @return array<int,string>
 */
function ap_request_list(mixed $value): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif ($value !== null) {
        $items = [$value];
    }

    $set = [];
    foreach ($items as $item) {
        $v = ap_s($item);
        if ($v === '') {
            continue;
        }
        $set[$v] = true;
    }

    $values = array_keys($set);
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    return $values;
}

function ap_parse_nullable_float(mixed $value): ?float
{
    $raw = str_replace(',', '.', ap_s($value));
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (float)$raw;
}

function ap_lower(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (!is_string($value)) {
        if (is_scalar($value)) {
            $value = (string)$value;
        } else {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            $value = is_string($json) ? $json : '';
        }
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

/**
 * @param array<int,mixed> $values
 * @return array<string,bool>
 */
function ap_lower_set(array $values): array
{
    $set = [];
    foreach ($values as $value) {
        $set[ap_lower($value)] = true;
    }
    return $set;
}

function ap_row_number(array $row, string $column): ?float
{
    $raw = ap_s($row[$column] ?? '');
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (float)$raw;
}

/**
 * @param array<int,string> $columns
 */
function ap_row_contains_search(array $row, string $needleLower, array $columns): bool
{
    if ($needleLower === '') {
        return true;
    }

    foreach ($columns as $column) {
        if (!array_key_exists($column, $row)) {
            continue;
        }

        $value = $row[$column];
        if (is_scalar($value) || $value === null) {
            $hay = (string)$value;
        } else {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $hay = is_string($json) ? $json : '';
        }

        if ($hay !== '' && strpos(ap_lower($hay), $needleLower) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string,mixed> $baseParams
 * @param array<string,mixed> $overrides
 * @param array<int,string> $removeKeys
 */
function ap_build_url(string $path, array $baseParams, array $overrides = [], array $removeKeys = []): string
{
    $params = $baseParams;

    foreach ($removeKeys as $removeKey) {
        unset($params[$removeKey]);
    }

    foreach ($overrides as $key => $value) {
        $isEmptyArray = is_array($value) && $value === [];
        if ($value === null || $value === '' || $isEmptyArray) {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }

    $query = http_build_query($params);
    if ($query === '') {
        return $path;
    }

    return $path . '?' . $query;
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

$sortBy = ap_s($request['sort_by'] ?? 'price_usd');
$sortDir = strtolower(ap_s($request['sort_dir'] ?? 'asc'));
$limit = (int)($request['limit'] ?? 10);
$requestOffset = (int)($request['offset'] ?? 0);
$page = (int)($request['page'] ?? 0);
$allowedPageLimits = [10, 25, 50, 100, 200];

if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'asc';
}
if (!in_array($limit, $allowedPageLimits, true)) {
    $limit = 10;
}
if ($requestOffset < 0) {
    $requestOffset = 0;
}
if ($page <= 0) {
    $page = (int)floor($requestOffset / $limit) + 1;
}
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

$searchText = ap_s($request['search'] ?? '');

$selectedApi = ap_request_list($request['f_api'] ?? []);
$selectedCategory = ap_request_list($request['f_proxy_category'] ?? []);
$selectedCountry = ap_request_list($request['f_country'] ?? []);
$selectedStock = ap_request_list($request['f_stock_status'] ?? []);
$selectedDays = ap_request_list($request['f_days'] ?? []);
$selectedAccess = ap_request_list($request['f_access_type'] ?? []);

$priceUsdFrom = ap_parse_nullable_float($request['f_price_usd_from'] ?? null);
$priceUsdTo = ap_parse_nullable_float($request['f_price_usd_to'] ?? null);
$pricePerDayFrom = ap_parse_nullable_float($request['f_price_per_day_from'] ?? null);
$pricePerDayTo = ap_parse_nullable_float($request['f_price_per_day_to'] ?? null);
$pricePerGbFrom = ap_parse_nullable_float($request['f_price_per_gb_from'] ?? null);
$pricePerGbTo = ap_parse_nullable_float($request['f_price_per_gb_to'] ?? null);

$cache = Sogerien::Cache();
$mergedApiCacheFile = 'AllProxy_merged_api_cache_v1.json';
$maxRows = 200;
$catalogCacheTtlSeconds = 60;

/** @var array<string,mixed>|null $mergedResp */
$mergedResp = null;
$mergedUpdatedAt = $cache->get_last_update($mergedApiCacheFile);
if ($mergedUpdatedAt <= 0 || $cache->is_interval_elapsed($mergedApiCacheFile, $catalogCacheTtlSeconds)) {
    Sogerien::ProxyCatalogCache()->refresh_cyberyozh_cache(200);
    Sogerien::ProxyCatalogCache()->refresh_infatica_cache(200);
    $mergedUpdatedAt = $cache->get_last_update($mergedApiCacheFile);
}
if ($mergedUpdatedAt > 0) {
    $loaded = $cache->load($mergedApiCacheFile, $mergedUpdatedAt);
    if (is_array($loaded)) {
        $mergedResp = $loaded;
    }
}
if (!is_array($mergedResp)) {
    $mergedResp = [
        'ok' => false,
        'error' => 'Cache is empty. Run cron endpoint /cron/cache/proxies or /cron/cache/proxies/infatica_io first.',
    ];
}

$apiError = '';
$apiWarning = '';
$columns = [];
$rows = [];
$preferredColumns = [
    'API',
    'id',
    'title',
    'location_country_code',
    'price_usd',
    'price_per_day',
    'days',
    'proxy_category',
    'stock_status',
    'traffic_limitation',
    'price_per_gb',
    'is_auto_renewal_possible',
    'access_type',
];

if (($mergedResp['ok'] ?? false) === true && isset($mergedResp['data']) && is_array($mergedResp['data'])) {
    $columns = isset($mergedResp['data']['columns']) && is_array($mergedResp['data']['columns'])
        ? array_values(array_map('strval', $mergedResp['data']['columns']))
        : [];
    $rows = isset($mergedResp['data']['rows']) && is_array($mergedResp['data']['rows'])
        ? array_values(array_filter($mergedResp['data']['rows'], static fn($row): bool => is_array($row)))
        : [];
    if (count($rows) > $maxRows) {
        $rows = array_slice($rows, 0, $maxRows);
    }
    $apiWarning = ap_s($mergedResp['warning'] ?? '');
} else {
    $apiError = ap_s($mergedResp['error'] ?? ap_t('proxy.action_failed', 'Action failed'));
}

if ($columns === []) {
    $columns = ap_build_columns($rows);
} else {
    $columns = array_values(array_filter(
        $preferredColumns,
        static fn(string $col): bool => in_array($col, $columns, true)
    ));
    if ($columns === []) {
        $columns = ap_build_columns($rows);
    }
}
if (!in_array('buy_action', $columns, true)) {
    $columns[] = 'buy_action';
}

$filterApiValues = ap_collect_column_values($rows, 'API');
$filterCategoryValues = ap_collect_column_values($rows, 'proxy_category');
$filterCountryValues = ap_collect_country_facet_values($rows);
$filterStockValues = ap_collect_column_values($rows, 'stock_status');
$filterDaysValues = ap_collect_column_values($rows, 'days');
$filterAccessValues = ap_collect_column_values($rows, 'access_type');

if (!in_array($sortBy, $columns, true)) {
    $sortBy = in_array('price_usd', $columns, true) ? 'price_usd' : ($columns[0] ?? 'id');
}

$selectedApiSet = ap_lower_set($selectedApi);
$selectedCategorySet = ap_lower_set($selectedCategory);
$selectedStockSet = ap_lower_set($selectedStock);
$selectedDaysSet = ap_lower_set($selectedDays);
$selectedAccessSet = ap_lower_set($selectedAccess);
$selectedCountrySet = [];
foreach ($selectedCountry as $countryCode) {
    $selectedCountrySet[strtoupper($countryCode)] = true;
}
$searchNeedleLower = ap_lower($searchText);

$filteredRows = [];
foreach ($rows as $row) {
    if ($selectedApiSet !== []) {
        $apiValue = ap_lower(ap_s($row['API'] ?? ''));
        if ($apiValue === '' || !isset($selectedApiSet[$apiValue])) {
            continue;
        }
    }

    if ($selectedCategorySet !== []) {
        $categoryValue = ap_lower(ap_s($row['proxy_category'] ?? ''));
        if ($categoryValue === '' || !isset($selectedCategorySet[$categoryValue])) {
            continue;
        }
    }

    if ($selectedCountrySet !== []) {
        $codes = ap_country_codes($row['location_country_code'] ?? '');
        $hasCountryMatch = false;
        foreach ($codes as $code) {
            if (isset($selectedCountrySet[$code])) {
                $hasCountryMatch = true;
                break;
            }
        }
        if (!$hasCountryMatch) {
            continue;
        }
    }

    if ($selectedStockSet !== []) {
        $stockValue = ap_lower(ap_s($row['stock_status'] ?? ''));
        if ($stockValue === '' || !isset($selectedStockSet[$stockValue])) {
            continue;
        }
    }

    if ($selectedDaysSet !== []) {
        $daysValue = ap_lower(ap_s($row['days'] ?? ''));
        if ($daysValue === '' || !isset($selectedDaysSet[$daysValue])) {
            continue;
        }
    }

    if ($selectedAccessSet !== []) {
        $accessValue = ap_lower(ap_normalize_access_type($row['access_type'] ?? ''));
        if ($accessValue === '' || !isset($selectedAccessSet[$accessValue])) {
            continue;
        }
    }

    if ($priceUsdFrom !== null || $priceUsdTo !== null) {
        $value = ap_row_number($row, 'price_usd');
        if ($value === null) {
            continue;
        }
        if ($priceUsdFrom !== null && $value < $priceUsdFrom) {
            continue;
        }
        if ($priceUsdTo !== null && $value > $priceUsdTo) {
            continue;
        }
    }

    if ($pricePerDayFrom !== null || $pricePerDayTo !== null) {
        $value = ap_row_number($row, 'price_per_day');
        if ($value === null) {
            continue;
        }
        if ($pricePerDayFrom !== null && $value < $pricePerDayFrom) {
            continue;
        }
        if ($pricePerDayTo !== null && $value > $pricePerDayTo) {
            continue;
        }
    }

    if ($pricePerGbFrom !== null || $pricePerGbTo !== null) {
        $value = ap_row_number($row, 'price_per_gb');
        if ($value === null) {
            continue;
        }
        if ($pricePerGbFrom !== null && $value < $pricePerGbFrom) {
            continue;
        }
        if ($pricePerGbTo !== null && $value > $pricePerGbTo) {
            continue;
        }
    }

    if (!ap_row_contains_search($row, $searchNeedleLower, $columns)) {
        continue;
    }

    $filteredRows[] = $row;
}

if ($sortBy !== '') {
    ap_sort_rows($filteredRows, $sortBy, $sortDir);
}

$rowsTotal = count($filteredRows);
$totalPages = max(1, (int)ceil($rowsTotal / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;
$rowsPage = array_slice($filteredRows, $offset, $limit);

$viewFrom = $rowsTotal > 0 ? ($offset + 1) : 0;
$viewTo = $rowsTotal > 0 ? min($offset + count($rowsPage), $rowsTotal) : 0;

$headers = [];
foreach ($columns as $col) {
    $headers[$col] = $col;
}
if (isset($headers['location_country_code'])) {
    $headers['location_country_code'] = ap_t('common.country', 'Country');
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
    $headers['price_per_day'] = '$ per day';
}
if (isset($headers['traffic_limitation'])) {
    $headers['traffic_limitation'] = ap_t('proxy.traffic_gb', 'Traffic GB');
}
if (isset($headers['price_per_gb'])) {
    $headers['price_per_gb'] = '$ per 1 Gb';
}
if (isset($headers['is_auto_renewal_possible'])) {
    $headers['is_auto_renewal_possible'] = ap_t('proxy.auto_renewal', 'auto renewal');
}
if (isset($headers['access_type'])) {
    $headers['access_type'] = ap_t('proxy.access_short', 'access');
}
$headers['buy_action'] = 'Buy';

$currentPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/all_proxy'), PHP_URL_PATH);
if ($currentPath === '') {
    $currentPath = '/all_proxy';
}
$currentQueryParams = is_array($_GET ?? null) ? $_GET : [];
$pageWindowStart = max(1, $page - 2);
$pageWindowEnd = min($totalPages, $page + 2);

Sogerien::Page()->title = 'All Proxy';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($apiError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= ap_h($apiError) ?></div>
    <?php endif; ?>
    <?php if ($apiWarning !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= ap_h($apiWarning) ?></div>
    <?php endif; ?>

    <?php
    $tr = Sogerien::TableRenderer();
    $tr->set_params = new SetParams();
    $tr->set_params->data = $rows;
    $tr->set_params->columns = $columns;
    $tr->set_params->headers = $headers;
    $tr->set_params->gridId = 'all_proxy_grid_lazy';
    $tr->set_params->searchCols = $columns;
    $tr->set_params->perPage = max(1, $limit);
    $tr->set_params->columnsOrder = $columns;
    $tr->set_params->autoHideEmptyCols = false;
    $defaultVisibleColumns = [
        'title' => true,
        'location_country_code' => true,
        'price_usd' => true,
        'price_per_day' => true,
        'days' => true,
        'traffic_limitation' => true,
        'price_per_gb' => true,
        'buy_action' => true,
    ];
    foreach ($columns as $columnName) {
        $tr->set_params->column_view[$columnName]['visible'] = isset($defaultVisibleColumns[$columnName]);
    }
    $tr->set_params->column_view['location_country_code'] = [
        'width' => '60px',
        'ellipsis' => true,
        'visible' => true,
    ];
    $facets = [];
    if (in_array('proxy_category', $columns, true)) {
        $facets[] = ['title' => ap_t('proxy.category', 'Category'), 'column' => 'proxy_category', 'type' => 'dropdown_multi', 'slot' => 'side', 'search' => true];
    }
    if (in_array('location_country_code', $columns, true)) {
        $facets[] = [
            'title' => ap_t('common.country', 'Country'),
            'column' => 'location_country_code',
            'type' => 'dropdown_multi',
            'match' => 'csv_token',
            'values' => ap_collect_country_facet_values($rows),
            'search' => true,
            'slot' => 'side',
        ];
    }
    if (in_array('stock_status', $columns, true)) {
        $facets[] = ['title' => ap_t('proxy.stock', 'Stock'), 'column' => 'stock_status', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    $rangeNumFacet = static function (string $col, string $titleKey) use ($rows): array {
        $facet = ['title' => ap_t($titleKey), 'column' => $col, 'type' => 'range_number'];
        $values = ap_collect_numeric_facet_values($rows, $col);
        if ($values !== []) {
            $facet['values'] = $values;
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
        $facets[] = ['title' => ap_t('proxy.days', 'Days'), 'column' => 'days', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    if (in_array('access_type', $columns, true)) {
        $facets[] = ['title' => ap_t('proxy.access', 'Access'), 'column' => 'access_type', 'type' => 'dropdown_multi', 'slot' => 'side'];
    }
    if (in_array('price_per_gb', $columns, true)) {
        $facets[] = $rangeNumFacet('price_per_gb', 'proxy.price_per_gb');
    }
    $tr->set_params->facets = $facets;

    $tr->set_params->formatters['location_country_code'] = static function ($value): string {
        $codes = ap_country_codes($value);
        if ($codes === []) {
            return '';
        }

        $flat = implode(',', $codes);
        $full = implode(', ', $codes);
        $dialogBody = '';
        foreach ($codes as $code) {
            $dialogBody .= '<div class="mb-1"><strong>' . ap_h($code) . '</strong></div>';
        }

        $dialogButtons = json_encode(
            [['label' => 'Close', 'role' => 'cancel', 'kind' => 'secondary']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($dialogButtons)) {
            $dialogButtons = '[]';
        }

        return '<span class="d-none">' . ap_h($flat . ',') . '</span>'
            . '<a href="#" class="tr-action proxy-country-list-link"'
            . ' data-action-type="GET"'
            . ' data-href="#"'
            . ' data-has-dialog="1"'
            . ' data-dialog-title="' . ap_h(ap_t('proxy.countries', 'Countries')) . '"'
            . ' data-dialog-msg="' . ap_h($dialogBody) . '"'
            . ' data-dialog-buttons="' . ap_h($dialogButtons) . '">'
            . ap_h($full)
            . '</a>';
    };
    $tr->set_params->formatters['is_auto_renewal_possible'] = static function ($value): string {
        $v = ap_s($value);
        if ($v === '1') {
            return ap_h(ap_t('common.yes', 'Yes'));
        }
        if ($v === '' || $v === '0') {
            return ap_h(ap_t('common.no', 'No'));
        }
        return ap_h($v);
    };
    $tr->set_params->formatters['access_type'] = static function ($value): string {
        return ap_h(ap_normalize_access_type($value));
    };
    $tr->set_params->formatters['traffic_limitation'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ((int)$value === -1) {
            return ap_h(ap_t('proxy.unlimited', 'Unlimited'));
        }
        return ap_h(number_format((float)$value, 2) . ' GB');
    };
    $tr->set_params->formatters['price_per_day'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return ap_h(number_format((float)$value, 4));
    };
    $tr->set_params->formatters['price_per_gb'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        if ((int)$value === -1) {
            return ap_h('-1');
        }
        return ap_h(number_format((float)$value, 4));
    };
    $tr->set_params->formatters['price_usd'] = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return ap_h(number_format((float)$value, 2));
    };
    $tr->set_params->formatters['buy_action'] = static function ($value, array $row) use ($buyerUserId): string {
        $api = ap_s($row['API'] ?? '');
        $id = ap_s($row['id'] ?? '');
        $title = ap_s($row['title'] ?? '');
        $stock = strtolower(ap_s($row['stock_status'] ?? ''));
        $priceUsd = ap_s($row['price_usd'] ?? '');
        $days = ap_s($row['days'] ?? '');
        $country = ap_s($row['location_country_code'] ?? '');
        $category = ap_s($row['proxy_category'] ?? '');
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
            . ' data-id="' . ap_h($id) . '"'
            . ' data-api="' . ap_h($api) . '"'
            . ' data-title="' . ap_h($title) . '"'
            . ' data-price-usd="' . ap_h($priceUsd) . '"'
            . ' data-days="' . ap_h($days) . '"'
            . ' data-country="' . ap_h($country) . '"'
            . ' data-category="' . ap_h($category) . '"'
            . ' data-auto-renew-possible="' . ap_h($autoRenewPossible) . '"'
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
                <div class="small text-muted">User #<?= (int)$buyerUserId ?> - balance USD <?= ap_h($buyerBalanceUsd) ?></div>
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
            const storageKey = 'pm_proxy_cart_v1';
            const addedModalEl = document.getElementById('pmProxyAddedModal');
            const addedModal = addedModalEl && window.bootstrap ? new bootstrap.Modal(addedModalEl) : null;
            const cartCanvasEl = document.getElementById('pmProxyCartCanvas');
            const cartCanvas = cartCanvasEl && window.bootstrap ? bootstrap.Offcanvas.getOrCreateInstance(cartCanvasEl) : null;
            let lastAddedId = '';

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
                api: button.dataset.api || '',
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
                    lastAddedId = item.id;
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


