<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function ppp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ppp_s(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

/**
 * @return array<string,array<string,string>>
 */
function ppp_page_configs(): array
{
    return [
        'mobile' => [
            'title' => 'Mobile Proxy',
            'subtitle' => 'Traffic packages, yearly usage window, country access.',
            'category_label' => 'Mobile proxy',
            'route' => '/client/proxies/mobile_proxy',
            'hero_note' => 'Best fit for mobile-first targets, app checks and country-specific sessions.',
        ],
        'residential' => [
            'title' => 'Residential IPv4',
            'subtitle' => 'Residential IPv4 traffic packages with country access and longer rotation.',
            'category_label' => 'Residential IPv4',
            'route' => '/client/proxies/residential',
            'hero_note' => 'Traffic model is GB-based. This page does not use Mobile API credentials.',
        ],
        'residential_ipv6' => [
            'title' => 'Residential IPv6',
            'subtitle' => 'Residential IPv6 traffic packages for IPv6-compatible targets.',
            'category_label' => 'Residential IPv6',
            'route' => '/client/proxies/residential-ipv6',
            'hero_note' => 'Use only if the target supports IPv6.',
        ],
        'isp' => [
            'title' => 'ISP Proxy',
            'subtitle' => 'Dedicated ISP IP packages by country, IP count and term.',
            'category_label' => 'ISP proxy',
            'route' => '/client/proxies/isp',
            'hero_note' => 'ISP is an IP-count product, not a GB traffic package.',
        ],
    ];
}

function ppp_category_from_path(string $path): string
{
    $path = trim($path, '/');
    if (str_starts_with($path, 'client/')) {
        $path = substr($path, 7);
    }
    return match ($path) {
        'proxies/residential' => 'residential',
        'proxies/residential-ipv6' => 'residential_ipv6',
        'proxies/isp' => 'isp',
        default => 'mobile',
    };
}

/**
 * @return array<string,string>
 */
function ppp_default_countries(string $category): array
{
    return match ($category) {
        'mobile' => ['CN' => 'China', 'IN' => 'India', 'IT' => 'Italy', 'KZ' => 'Kazakhstan', 'MY' => 'Malaysia', 'PL' => 'Poland', 'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'US' => 'United States'],
        'isp' => ['AT' => 'Austria', 'BR' => 'Brazil', 'CA' => 'Canada', 'FR' => 'France', 'JP' => 'Japan', 'LV' => 'Latvia', 'RO' => 'Romania', 'UA' => 'Ukraine', 'US' => 'United States'],
        default => ['BR' => 'Brazil', 'CA' => 'Canada', 'CO' => 'Colombia', 'ES' => 'Spain', 'FR' => 'France', 'GB' => 'United Kingdom', 'RU' => 'Russia', 'UA' => 'Ukraine', 'US' => 'United States'],
    };
}

/**
 * @return array<string,string>
 */
function ppp_country_names(): array
{
    $names = [];
    foreach (['mobile', 'residential', 'isp'] as $category) {
        foreach (ppp_default_countries($category) as $code => $name) {
            $names[$code] = $name;
        }
    }
    return $names;
}

/**
 * @return array<mixed>|null
 */
function ppp_safe_api(callable $callback): ?array
{
    try {
        $value = $callback();
        return is_array($value) ? $value : null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<mixed>|null $source
 * @return array<int,array<string,mixed>>
 */
function ppp_country_rows(string $category, ?array $source, bool &$fallback): array
{
    $names = ppp_country_names();
    $rows = [];
    if (is_array($source)) {
        if ($category === 'isp') {
            ppp_collect_isp_countries($source, $rows, $names);
        } else {
            ppp_collect_geo_countries($source, $rows, $names);
        }
    }

    $indexed = [];
    foreach ($rows as $row) {
        $code = ppp_country_code($row['country'] ?? '');
        if ($code === '') {
            continue;
        }
        $row['country'] = $code;
        $row['name'] = ppp_s($row['name'] ?? ($names[$code] ?? $code));
        $row['nodes'] = (int)($row['nodes'] ?? 0);
        $row['online'] = (int)($row['online'] ?? 0);
        $row['unique'] = (int)($row['unique'] ?? 0);
        $row['available'] = $category === 'isp' || $row['nodes'] > 0 || $row['online'] > 0 || $row['unique'] > 0;
        $indexed[$code] = $row;
    }

    if ($indexed === []) {
        $fallback = true;
        foreach (ppp_default_countries($category) as $code => $name) {
            $indexed[$code] = [
                'country' => $code,
                'name' => $name,
                'nodes' => 0,
                'online' => 0,
                'unique' => 0,
                'available' => true,
                'fallback' => true,
            ];
        }
    }

    ksort($indexed);
    return array_values($indexed);
}

/**
 * @param array<mixed> $source
 * @param array<int,array<string,mixed>> $rows
 * @param array<string,string> $names
 */
function ppp_collect_isp_countries(array $source, array &$rows, array $names): void
{
    foreach (ppp_extract_list_candidates($source) as $item) {
        if (is_scalar($item)) {
            $raw = trim((string)$item);
            $code = ppp_country_code($raw);
            if ($code === '') {
                $code = ppp_country_code(array_search($raw, $names, true));
            }
            if ($code !== '') {
                $rows[] = ['country' => $code, 'name' => $names[$code] ?? $raw, 'nodes' => 1, 'available' => true];
            }
        } elseif (is_array($item)) {
            $code = ppp_country_code(ppp_first_string($item, ['country', 'country_code', 'code', 'iso', 'iso2', 'location_country_code']));
            if ($code !== '') {
                $rows[] = ['country' => $code, 'name' => ppp_first_string($item, ['name', 'title', 'country_name']) ?: ($names[$code] ?? $code), 'nodes' => 1, 'available' => true];
            }
        }
    }
}

/**
 * @param array<mixed> $source
 * @param array<int,array<string,mixed>> $rows
 * @param array<string,string> $names
 */
function ppp_collect_geo_countries(array $source, array &$rows, array $names): void
{
    foreach ($source as $key => $item) {
        if (is_array($item)) {
            $code = ppp_country_code(ppp_first_string($item, ['country', 'country_code', 'code', 'iso', 'iso2', 'location_country_code']));
            if ($code === '') {
                $code = ppp_country_code((string)$key);
            }
            if ($code !== '') {
                $rows[] = [
                    'country' => $code,
                    'name' => ppp_first_string($item, ['name', 'title', 'country_name']) ?: ($names[$code] ?? $code),
                    'nodes' => ppp_first_number($item, ['nodes', 'node_count', 'count', 'total']),
                    'online' => ppp_first_number($item, ['online', 'online_nodes']),
                    'unique' => ppp_first_number($item, ['unique', 'unique_nodes']),
                ];
            }
            ppp_collect_geo_countries($item, $rows, $names);
            continue;
        }
        $code = ppp_country_code(is_string($key) ? $key : (string)$item);
        if ($code !== '') {
            $rows[] = ['country' => $code, 'name' => $names[$code] ?? $code, 'nodes' => is_numeric((string)$item) ? (int)$item : 0];
        }
    }
}

/**
 * @param array<mixed> $source
 * @return array<int,mixed>
 */
function ppp_extract_list_candidates(array $source): array
{
    foreach ([[], ['data'], ['results'], ['items'], ['data', 'countries'], ['countries']] as $path) {
        $value = $source;
        foreach ($path as $segment) {
            if (!is_array($value) || !isset($value[$segment]) || !is_array($value[$segment])) {
                $value = null;
                break;
            }
            $value = $value[$segment];
        }
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }
    }
    return [];
}

/**
 * @param array<mixed> $source
 * @param array<int,string> $keys
 */
function ppp_first_string(array $source, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && is_scalar($source[$key])) {
            $value = trim((string)$source[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

/**
 * @param array<mixed> $source
 * @param array<int,string> $needles
 */
function ppp_first_number(array $source, array $needles): int
{
    $flat = [];
    ppp_flatten_numbers($source, '', $flat);
    foreach ($flat as $key => $value) {
        foreach ($needles as $needle) {
            if (str_contains($key, $needle)) {
                return (int)round($value);
            }
        }
    }
    return 0;
}

/**
 * @param array<mixed> $source
 * @param array<string,float> $flat
 */
function ppp_flatten_numbers(array $source, string $prefix, array &$flat): void
{
    foreach ($source as $key => $value) {
        $name = strtolower(trim($prefix . '_' . (string)$key, '_'));
        if (is_array($value)) {
            ppp_flatten_numbers($value, $name, $flat);
            continue;
        }
        if (is_numeric((string)$value)) {
            $flat[$name] = (float)$value;
        }
    }
}

function ppp_country_code(mixed $value): string
{
    $raw = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z]{2}$/', $raw) === 1 ? $raw : '';
}

/**
 * @param array<mixed>|null $stats
 * @param array<string,mixed> $countryRow
 * @return array{nodes:int,online:int,unique:int,warning:string}
 */
function ppp_country_stats(?array $stats, array $countryRow): array
{
    $nodes = (int)($countryRow['nodes'] ?? 0);
    $online = (int)($countryRow['online'] ?? 0);
    $unique = (int)($countryRow['unique'] ?? 0);
    if (is_array($stats)) {
        $onlineFromApi = ppp_first_number($stats, ['online', 'online_nodes', 'active']);
        $uniqueFromApi = ppp_first_number($stats, ['unique', 'unique_nodes']);
        $nodesFromApi = ppp_first_number($stats, ['nodes', 'node_count', 'total']);
        $online = $onlineFromApi > 0 ? $onlineFromApi : $online;
        $unique = $uniqueFromApi > 0 ? $uniqueFromApi : $unique;
        $nodes = $nodesFromApi > 0 ? $nodesFromApi : $nodes;
    }
    $warning = '';
    if ($nodes > 0 && ($online < 5 || $unique < 5)) {
        $warning = 'Low node availability for this country.';
    }
    return ['nodes' => $nodes, 'online' => $online, 'unique' => $unique, 'warning' => $warning];
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}
Sogerien::AccessCheck()->init_db_alias($dbAlias);
$accessOk = Sogerien::AccessCheck()->check_access_or_show_login_form('proxies_list', 0, []);
if (!$accessOk) {
    Sogerien::Page()->title = 'Access denied';
    Sogerien::Page()->header();
    Sogerien::Page()->mainmenu();
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">Access denied.</div></main>';
    Sogerien::Page()->footer();
    Sogerien::markDone();
    Sogerien::exit();
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();

$path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
$category = ppp_category_from_path($path);
$configs = ppp_page_configs();
$config = $configs[$category];
$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$plans = $shop->proxy_product_plans($category);

$geoSource = null;
if ($category === 'mobile') {
    $geoSource = ppp_safe_api(static fn(): ?array => Sogerien::API()->InfaticaIo()->Mobile()->geos());
} elseif ($category === 'isp') {
    $geoSource = ppp_safe_api(static fn(): ?array => Sogerien::API()->InfaticaIo()->Isp()->countries());
} else {
    $geoSource = ppp_safe_api(static fn(): ?array => Sogerien::API()->InfaticaIo()->Residential()->geos());
}

$fallbackCatalog = false;
$countryRows = ppp_country_rows($category, $geoSource, $fallbackCatalog);
$availableCountryRows = array_values(array_filter($countryRows, static fn(array $row): bool => !empty($row['available'])));
if ($availableCountryRows === []) {
    $availableCountryRows = $countryRows;
}

$requestedCountry = ppp_country_code((string)($_GET['country'] ?? ''));
$selectedCountry = $requestedCountry !== '' ? $requestedCountry : ppp_s($availableCountryRows[0]['country'] ?? 'US');
$selectedRow = $availableCountryRows[0] ?? ['country' => $selectedCountry, 'name' => $selectedCountry];
foreach ($availableCountryRows as $row) {
    if (ppp_s($row['country'] ?? '') === $selectedCountry) {
        $selectedRow = $row;
        break;
    }
}

$onlineStatsSource = null;
if (in_array($category, ['mobile', 'residential', 'residential_ipv6'], true)) {
    $onlineStatsSource = ppp_safe_api(static function () use ($category, $selectedCountry): ?array {
        return $category === 'mobile'
            ? Sogerien::API()->InfaticaIo()->Mobile()->online_statistics($selectedCountry)
            : Sogerien::API()->InfaticaIo()->Residential()->online_statistics($selectedCountry);
    });
}
$selectedStats = ppp_country_stats($onlineStatsSource, $selectedRow);

$countryOptions = [];
foreach ($availableCountryRows as $row) {
    $countryOptions[] = [
        'id' => ppp_s($row['country'] ?? ''),
        'title' => ppp_s($row['country'] ?? '') . ' - ' . ppp_s($row['name'] ?? $row['country'] ?? ''),
    ];
}

$trafficPlanOptions = [];
foreach ($plans as $plan) {
    if (!isset($plan['traffic_gb'])) {
        continue;
    }
    $traffic = ppp_s($plan['traffic_gb']);
    $label = $traffic . ' GB';
    if (!empty($plan['is_trial'])) {
        $label .= ' trial';
    }
    $label .= ' - $' . ppp_s($plan['price_usd']);
    $trafficPlanOptions[] = ['id' => ppp_s($plan['id']), 'title' => $label];
}

$termOptions = [
    ['id' => '30', 'title' => '30 days'],
    ['id' => '90', 'title' => '90 days'],
    ['id' => '180', 'title' => '180 days'],
    ['id' => '364', 'title' => '364 days'],
];

$rotationDefault = $category === 'residential' || $category === 'residential_ipv6' ? '3600' : '0';

Sogerien::Page()->title = $config['title'];
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui pm-product-page">
    <style>
        .pm-product-page{--pm-border:rgba(148,163,184,.32);--pm-muted:rgb(100,116,139);--pm-ink:rgb(16,35,63);--pm-accent:rgb(15,123,143);--pm-good:rgb(15,138,95);--pm-warn:rgb(166,95,0)}
        .pm-product-head{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:16px;align-items:start;margin-bottom:16px}
        .pm-product-head h1{font-size:32px;line-height:1.1;margin:0 0 8px;color:rgb(224,242,254);letter-spacing:0}
        .pm-product-head p{margin:0;color:rgb(203,213,225);max-width:720px}
        .pm-product-note{border:1px solid var(--pm-border);border-radius:8px;padding:14px;background:rgb(255,255,255);color:var(--pm-ink)}
        .pm-product-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .pm-kpi{border:1px solid var(--pm-border);border-radius:8px;background:rgb(255,255,255);padding:14px;min-height:92px}
        .pm-kpi .pm-kpi-label{font-size:12px;color:var(--pm-muted);text-transform:uppercase;font-weight:700}
        .pm-kpi .pm-kpi-value{font-size:26px;font-weight:800;color:var(--pm-ink);margin-top:6px}
        .pm-section{border:1px solid var(--pm-border);border-radius:8px;background:rgb(255,255,255);padding:16px;margin-bottom:16px;color:var(--pm-ink)}
        .pm-section h2{font-size:20px;margin:0 0 12px;color:var(--pm-ink)}
        .pm-country-line{display:grid;grid-template-columns:minmax(220px,340px) minmax(0,1fr);gap:14px;align-items:end}
        .pm-country-line select{min-height:42px}
        .pm-country-line label,.pm-country-line .form-label{display:block;margin-bottom:6px;color:rgb(51,65,85)!important;font-weight:700}
        .pm-country-warning{color:var(--pm-warn);font-weight:700}
        .pm-plan-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}
        .pm-plan-card{border:1px solid var(--pm-border);border-radius:8px;padding:14px;background:rgb(255,255,255);min-height:156px;display:flex;flex-direction:column;gap:8px}
        .pm-plan-card.is-selected{border-color:var(--pm-accent);box-shadow:0 0 0 3px rgba(15,123,143,.14)}
        .pm-plan-badge{font-size:12px;font-weight:800;text-transform:uppercase;color:var(--pm-accent)}
        .pm-plan-price{font-size:28px;font-weight:800;color:var(--pm-ink);line-height:1}
        .pm-plan-meta{color:var(--pm-muted);font-size:13px}
        .pm-product-order .sog-ui.container-fluid{padding:0}
        .pm-product-order .sog-form{margin:0}
        .pm-product-order .row{row-gap:12px}
        .pm-product-order label,.pm-product-order .form-label{display:block;margin-bottom:6px;color:rgb(51,65,85)!important;font-weight:700}
        .pm-product-page .text-muted,.pm-product-page .small{color:rgb(71,85,105)!important}
        .pm-product-page select,.pm-product-page input,.pm-product-page textarea{background:rgb(255,255,255)!important;color:rgb(15,23,42)!important;border:1px solid rgb(203,213,225)!important;border-radius:8px;min-height:42px}
        .pm-product-page input[type=checkbox]{min-height:auto;width:16px;height:16px;accent-color:var(--pm-accent)}
        .pm-product-order .sog-form button[type=submit]{background:var(--pm-accent);color:rgb(255,255,255);border:0;border-radius:8px;min-height:42px;font-weight:800;padding:9px 14px}
        .pm-order-total{border:1px solid var(--pm-border);border-radius:8px;padding:14px;background:rgb(248,250,252)}
        .pm-order-total strong{display:block;font-size:24px;color:var(--pm-ink)}
        .pm-quick-list{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .pm-quick-list div{border:1px solid var(--pm-border);border-radius:8px;padding:12px;min-height:88px}
        .pm-quick-list strong{display:block;margin-bottom:4px;color:var(--pm-ink)}
        @media(max-width:900px){.pm-product-head,.pm-country-line{grid-template-columns:1fr}.pm-product-kpis,.pm-quick-list{grid-template-columns:1fr 1fr}}
        @media(max-width:560px){.pm-product-kpis,.pm-quick-list,.pm-plan-grid{grid-template-columns:1fr}.pm-product-head h1{font-size:28px}}
    </style>

    <div class="pm-product-head">
        <div>
            <h1><?= ppp_h($config['title']) ?></h1>
            <p><?= ppp_h($config['subtitle']) ?></p>
        </div>
        <div class="pm-product-note">
            <strong><?= ppp_h($config['category_label']) ?></strong>
            <div class="text-muted small mt-1"><?= ppp_h($config['hero_note']) ?></div>
        </div>
    </div>

    <?php if ($category === 'residential_ipv6'): ?>
        <div class="alert alert-warning" role="alert">Use only if target supports IPv6.</div>
    <?php endif; ?>
    <?php if ($fallbackCatalog): ?>
        <div class="alert alert-warning" role="alert">Provider geos are unavailable. Fallback catalog is shown; checkout still validates item contracts.</div>
    <?php endif; ?>

    <section class="pm-section">
        <h2><?= $category === 'isp' ? 'Country availability' : 'Country availability' ?></h2>
        <div class="pm-country-line">
            <form method="get" action="<?= ppp_h($config['route']) ?>">
                <label class="form-label" for="pmProductCountry">Country</label>
                <select class="form-select" id="pmProductCountry" name="country">
                    <?php foreach ($availableCountryRows as $row): ?>
                        <?php $code = ppp_s($row['country'] ?? ''); ?>
                        <option value="<?= ppp_h($code) ?>" <?= $code === $selectedCountry ? 'selected' : '' ?>>
                            <?= ppp_h($code . ' - ' . ppp_s($row['name'] ?? $code)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div>
                <div class="pm-product-kpis mb-0">
                    <div class="pm-kpi"><div class="pm-kpi-label">Nodes count</div><div class="pm-kpi-value"><?= ppp_h((string)$selectedStats['nodes']) ?></div></div>
                    <div class="pm-kpi"><div class="pm-kpi-label">Online nodes</div><div class="pm-kpi-value"><?= ppp_h((string)$selectedStats['online']) ?></div></div>
                    <div class="pm-kpi"><div class="pm-kpi-label">Unique nodes</div><div class="pm-kpi-value"><?= ppp_h((string)$selectedStats['unique']) ?></div></div>
                </div>
                <?php if ($selectedStats['warning'] !== ''): ?>
                    <div class="pm-country-warning mt-2"><?= ppp_h($selectedStats['warning']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="pm-section">
        <h2><?= $category === 'isp' ? 'IP package pricing' : 'Pricing' ?></h2>
        <div class="pm-plan-grid" id="pmProductPlans">
            <?php foreach ($plans as $index => $plan): ?>
                <?php
                $isTraffic = isset($plan['traffic_gb']);
                $isSelected = $index === 0;
                $badge = $isTraffic ? (!empty($plan['is_trial']) ? 'Trial' : 'Yearly window') : 'Monthly tier';
                $main = $isTraffic ? ppp_s($plan['traffic_gb']) . ' GB' : ppp_s($plan['ip_count'] ?? '') . ' IPs';
                $price = '$' . ppp_s($plan['price_usd'] ?? '0.00');
                $meta = $isTraffic
                    ? '$' . ppp_s($plan['price_per_gb'] ?? '-') . ' per GB'
                    : '$' . ppp_s($plan['price_per_ip'] ?? '-') . ' per IP';
                ?>
                <button type="button" class="pm-plan-card text-start <?= $isSelected ? 'is-selected' : '' ?>" data-plan-id="<?= ppp_h(ppp_s($plan['id'] ?? '')) ?>">
                    <span class="pm-plan-badge"><?= ppp_h($badge) ?></span>
                    <span class="pm-plan-price"><?= ppp_h($price) ?></span>
                    <strong><?= ppp_h($main) ?></strong>
                    <span class="pm-plan-meta"><?= ppp_h($meta) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="pm-section pm-product-order">
        <h2>Order form</h2>
        <?php
        $form = new Forms(['id' => 'pmProxyProductOrderForm', 'action' => '/client/proxy/checkout', 'method' => 'POST', 'ajax' => false]);
        $form->addHidden('cart_payload', '[]');
        if ($category === 'isp') {
            $form->addSelect('country', 'Country', $countryOptions, ['required' => 'required', 'data-empty' => 'off'], $selectedCountry)->col(12, 6, 4);
            $form->addInput('ip_count', 'IP count', 'number', ['min' => '1', 'step' => '1', 'required' => 'required'], ppp_s($plans[0]['ip_count'] ?? '5'))->col(12, 6, 4);
            $form->addSelect('days', 'Term', $termOptions, ['required' => 'required', 'data-empty' => 'off'], '30')->col(12, 6, 4);
            $form->addCheckbox('auto_renew', 'Auto-renew', [], false)->col(12, 6, 4);
        } else {
            $form->addSelect('traffic_plan', 'Traffic amount', $trafficPlanOptions, ['required' => 'required', 'data-empty' => 'off'], ppp_s($plans[0]['id'] ?? ''))->col(12, 6, 4);
            $form->addSelect('country', 'Country', $countryOptions, ['required' => 'required', 'data-empty' => 'off'], $selectedCountry)->col(12, 6, 4);
            $form->addSelect('rotation_preference', 'Rotation preference', [
                ['id' => '0', 'title' => 'Sticky until changed'],
                ['id' => '300', 'title' => 'Rotate every 5 minutes'],
                ['id' => '900', 'title' => 'Rotate every 15 minutes'],
                ['id' => '3600', 'title' => 'Rotate every hour'],
            ], ['data-empty' => 'off'], $rotationDefault)->col(12, 6, 4);
            $form->addInput('login', 'Login optional', 'text', ['autocomplete' => 'off'], '')->col(12, 6, 4);
            $form->addInput('password', 'Password optional', 'text', ['autocomplete' => 'off'], '')->col(12, 6, 4);
            $form->addCheckbox('auto_renew', 'Auto-renew', [], false)->col(12, 6, 4);
        }
        $form->addHTML('<div class="pm-order-total"><span class="text-muted small">Estimated total</span><strong id="pmProductTotal">$0.00</strong><span id="pmProductSummary" class="small text-muted">Select package settings.</span></div>', [], 'order_total')->col(12, 12, 8);
        $form->addSubmit('Add to cart')->col(12, 12, 4);
        $form->render();
        ?>
    </section>

    <section class="pm-section">
        <h2><?= $category === 'residential' || $category === 'residential_ipv6' ? 'Integration example' : 'FAQ / manual quick block' ?></h2>
        <?php if ($category === 'isp'): ?>
            <div class="pm-quick-list">
                <div><strong>IP model</strong><span class="text-muted small">ISP is sold by IP count. It does not appear in GB traffic topup.</span></div>
                <div><strong>Fulfillment</strong><span class="text-muted small">Package is created after payment and saved as a service.</span></div>
                <div><strong>Actions</strong><span class="text-muted small">Cancel, uncancel, suspend, resume and deactivate are service actions.</span></div>
                <div><strong>Traffic</strong><span class="text-muted small">ISP is not counted in `/client/traffic` GB reports.</span></div>
            </div>
        <?php else: ?>
            <div class="pm-quick-list">
                <div><strong>HTTP/SOCKS</strong><span class="text-muted small">Access lists can produce HTTP and SOCKS5 connection URLs after activation.</span></div>
                <div><strong>Rotation</strong><span class="text-muted small">Rotation is configured per access list and can be changed later.</span></div>
                <div><strong>Yearly traffic</strong><span class="text-muted small">Paid traffic uses a yearly usage window unless the plan is a trial.</span></div>
                <div><strong>Exhausted traffic</strong><span class="text-muted small">Services are suspended when the traffic limit is exhausted.</span></div>
            </div>
        <?php endif; ?>
    </section>

    <script type="application/json" id="pmProductPageData"><?= (string)json_encode([
        'category' => $category,
        'plans' => $plans,
        'selectedCountry' => $selectedCountry,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dataEl = document.getElementById('pmProductPageData');
            let pageData = {category: '', plans: [], selectedCountry: ''};
            try { pageData = JSON.parse(dataEl ? dataEl.textContent : '{}'); } catch (_err) {}
            const byId = (name) => document.getElementById('pmProxyProductOrderForm__' + name);
            const form = document.getElementById('pmProxyProductOrderForm');
            const countryPicker = document.getElementById('pmProductCountry');
            const totalEl = document.getElementById('pmProductTotal');
            const summaryEl = document.getElementById('pmProductSummary');
            const plans = Array.isArray(pageData.plans) ? pageData.plans : [];
            const planById = new Map(plans.map((plan) => [String(plan.id || ''), plan]));
            const selectedClass = 'is-selected';

            if (countryPicker) {
                countryPicker.addEventListener('change', () => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('country', countryPicker.value || pageData.selectedCountry || 'US');
                    window.location.href = url.toString();
                });
            }

            const termMultiplier = (days) => {
                if (String(days) === '90') return 3;
                if (String(days) === '180') return 6;
                if (String(days) === '364') return 12;
                return 1;
            };

            const priceForIsp = (ipCount, days) => {
                let selected = null;
                const sorted = plans
                    .filter((plan) => Number.parseInt(plan.ip_count || '0', 10) > 0)
                    .sort((a, b) => Number.parseInt(a.ip_count || '0', 10) - Number.parseInt(b.ip_count || '0', 10));
                for (const plan of sorted) {
                    if (Number.parseInt(plan.ip_count || '0', 10) <= ipCount) selected = plan;
                }
                if (!selected && sorted.length) selected = sorted[0];
                const pricePerIp = Number.parseFloat(selected ? (selected.price_per_ip || '0') : '0');
                return {
                    pricePerIp,
                    total: Math.max(1, ipCount) * pricePerIp * termMultiplier(days)
                };
            };

            const currentTrafficPlan = () => {
                const field = byId('traffic_plan');
                return planById.get(String(field ? field.value : '')) || plans[0] || {};
            };
            const buildCartItem = () => {
                const countryField = byId('country');
                const autoRenewField = byId('auto_renew');
                const country = (countryField ? countryField.value : '') || pageData.selectedCountry || 'US';
                const autoRenew = (autoRenewField ? autoRenewField.checked : false) || false;
                let item = {
                    api: 'InfaticaIo',
                    category: pageData.category,
                    proxy_category: pageData.category,
                    country,
                    location_country_code: country,
                    auto_renew: autoRenew
                };
                if (pageData.category === 'isp') {
                    const ipField = byId('ip_count');
                    const daysField = byId('days');
                    const ipCount = Math.max(1, Number.parseInt(ipField ? ipField.value : '1', 10));
                    const days = daysField ? daysField.value : '30';
                    const price = priceForIsp(ipCount, days);
                    return Object.assign({}, item, {id: 'isp-' + country + '-ip' + String(ipCount) + '-d' + String(days), ip_count: String(ipCount), days: days, price_per_ip: price.pricePerIp.toFixed(2), price_usd: price.total.toFixed(2)});
                }

                const plan = currentTrafficPlan();
                const rotationField = byId('rotation_preference');
                const loginField = byId('login');
                const passwordField = byId('password');
                return Object.assign({}, item, {
                    id: String(pageData.category) + '-' + country + '-' + String(plan.id || '').replace(pageData.category + '-', ''),
                    traffic: String(plan.traffic_gb || ''),
                    traffic_limitation: String(plan.traffic_gb || ''),
                    days: String(plan.days || ''),
                    price_usd: String(plan.price_usd || ''),
                    rotation_preference: rotationField ? rotationField.value : '',
                    login: loginField ? loginField.value : '',
                    password: passwordField ? passwordField.value : ''
                });
            };

            const updateCartPayload = () => {
                const payload = byId('cart_payload');
                if (payload) payload.value = JSON.stringify([buildCartItem()]);
            };

            const updateTotal = () => {
                if (pageData.category === 'isp') {
                    const ipFieldCurrent = byId('ip_count');
                    const daysFieldCurrent = byId('days');
                    const ipCount = Math.max(1, Number.parseInt(ipFieldCurrent ? ipFieldCurrent.value : '1', 10));
                    const days = daysFieldCurrent ? daysFieldCurrent.value : '30';
                    const price = priceForIsp(ipCount, days);
                    if (totalEl) totalEl.textContent = '$' + price.total.toFixed(2);
                    if (summaryEl) summaryEl.textContent = String(ipCount) + ' IPs - ' + String(days) + ' days - $' + price.pricePerIp.toFixed(2) + ' per IP';
                    updateCartPayload();
                    return;
                }
                const plan = currentTrafficPlan();
                const price = Number.parseFloat(plan.price_usd || '0');
                if (totalEl) totalEl.textContent = '$' + price.toFixed(2);
                if (summaryEl) summaryEl.textContent = String(plan.traffic_gb || '-') + ' GB - ' + String(plan.days || '-') + ' days';
                updateCartPayload();
            };

            document.querySelectorAll('[data-plan-id]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('[data-plan-id]').forEach((item) => item.classList.remove(selectedClass));
                    button.classList.add(selectedClass);
                    const plan = planById.get(String(button.dataset.planId || ''));
                    if (!plan) return;
                    const trafficField = byId('traffic_plan');
                    const ipField = byId('ip_count');
                    if (trafficField && plan.id) {
                        trafficField.value = String(plan.id);
                        trafficField.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                    if (ipField && plan.ip_count) {
                        ipField.value = String(plan.ip_count);
                        ipField.dispatchEvent(new Event('input', {bubbles: true}));
                    }
                    updateTotal();
                });
            });

            ['traffic_plan', 'country', 'rotation_preference', 'ip_count', 'days'].forEach((name) => {
                const field = byId(name);
                if (field) {
                    field.addEventListener('change', updateTotal);
                    field.addEventListener('input', updateTotal);
                }
            });

            if (form) form.addEventListener('submit', updateCartPayload);

            updateTotal();
        });
    </script>
</main>
<?php
Sogerien::Page()->footer();
