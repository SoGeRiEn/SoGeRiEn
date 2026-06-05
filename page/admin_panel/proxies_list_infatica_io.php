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

/**
 * @return array<mixed>|null
 */
function pli_safe_api(callable $callback): ?array
{
    try {
        $value = $callback();
        return is_array($value) ? $value : null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<mixed> $source
 * @param array<int,string> $needles
 */
function pli_first_number(array $source, array $needles): int
{
    $flat = [];
    pli_flatten_numbers($source, '', $flat);
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
function pli_flatten_numbers(array $source, string $prefix, array &$flat): void
{
    foreach ($source as $key => $value) {
        $name = strtolower(trim($prefix . '_' . (string)$key, '_'));
        if (is_array($value)) {
            pli_flatten_numbers($value, $name, $flat);
            continue;
        }
        if (is_numeric((string)$value)) {
            $flat[$name] = (float)$value;
        }
    }
}

function pli_country_code(mixed $value): string
{
    $raw = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z]{2}$/', $raw) === 1 ? $raw : '';
}

/**
 * @param array<mixed>|null $source
 * @param array<string,string> $fallbackCountries
 * @return array<int,array{country:string,name:string,nodes:int,online:int,unique:int}>
 */
function pli_country_rows(?array $source, array $fallbackCountries): array
{
    $indexed = [];
    if (is_array($source)) {
        pli_collect_country_rows($source, $indexed, $fallbackCountries);
    }
    if ($indexed === []) {
        foreach ($fallbackCountries as $code => $name) {
            $indexed[$code] = ['country' => $code, 'name' => $name, 'nodes' => 0, 'online' => 0, 'unique' => 0];
        }
    }
    ksort($indexed);
    return array_values($indexed);
}

/**
 * @param array<mixed> $source
 * @param array<string,array{country:string,name:string,nodes:int,online:int,unique:int}> $indexed
 * @param array<string,string> $names
 */
function pli_collect_country_rows(array $source, array &$indexed, array $names): void
{
    foreach ($source as $key => $item) {
        if (is_array($item)) {
            $code = pli_country_code($item['country'] ?? $item['country_code'] ?? $item['code'] ?? $item['iso'] ?? $key);
            if ($code !== '') {
                $indexed[$code] = [
                    'country' => $code,
                    'name' => pli_s($item['name'] ?? $item['title'] ?? $item['country_name'] ?? ($names[$code] ?? $code)),
                    'nodes' => pli_first_number($item, ['nodes', 'node_count', 'count', 'total']),
                    'online' => pli_first_number($item, ['online', 'online_nodes']),
                    'unique' => pli_first_number($item, ['unique', 'unique_nodes']),
                ];
            }
            pli_collect_country_rows($item, $indexed, $names);
            continue;
        }
        $code = pli_country_code(is_string($key) ? $key : $item);
        if ($code !== '' && !isset($indexed[$code])) {
            $indexed[$code] = ['country' => $code, 'name' => $names[$code] ?? $code, 'nodes' => is_numeric((string)$item) ? (int)$item : 1, 'online' => 0, 'unique' => 0];
        }
    }
}

/**
 * @param array<mixed>|null $stats
 * @param array{country:string,name:string,nodes:int,online:int,unique:int} $countryRow
 * @return array{nodes:int,online:int,unique:int}
 */
function pli_live_country_stats(?array $stats, array $countryRow): array
{
    $nodes = (int)$countryRow['nodes'];
    $online = (int)$countryRow['online'];
    $unique = (int)$countryRow['unique'];
    if (is_array($stats)) {
        $onlineFromApi = pli_first_number($stats, ['online_nodes', 'online', 'active']);
        $uniqueFromApi = pli_first_number($stats, ['unique_nodes', 'unique']);
        $nodesFromApi = pli_first_number($stats, ['nodes', 'node_count', 'total']);
        $online = $onlineFromApi > 0 ? $onlineFromApi : $online;
        $unique = $uniqueFromApi > 0 ? $uniqueFromApi : $unique;
        $nodes = $nodesFromApi > 0 ? $nodesFromApi : $nodes;
    }
    return ['nodes' => $nodes, 'online' => $online, 'unique' => $unique];
}

/**
 * @param array<int,array{country:string,name:string,nodes:int,online:int,unique:int}> $countryRows
 * @return array{country:string,name:string,nodes:int,online:int,unique:int}
 */
function pli_pick_country_row(array $countryRows, string $preferredCountry): array
{
    foreach ($countryRows as $row) {
        if ($row['country'] === $preferredCountry) {
            return $row;
        }
    }
    return $countryRows[0] ?? ['country' => $preferredCountry, 'name' => $preferredCountry, 'nodes' => 0, 'online' => 0, 'unique' => 0];
}

/**
 * @param array<string,array<string,string>> $planCountryOptions
 * @return array<int,array{category:string,label:string,country:string,country_name:string,nodes:int,online:int,unique:int,note:string}>
 */
function pli_proxy_availability_rows(array $planCountryOptions): array
{
    $rows = [];
    $api = Sogerien::API()->InfaticaIo();
    $configs = [
        'residential' => [
            'label' => 'Residential proxies',
            'countries' => static fn(): ?array => $api->Residential()->geos(),
            'stats' => static fn(string $country): ?array => $api->Residential()->online_statistics($country),
            'preferred_country' => 'US',
            'note' => '',
        ],
        'residential_ipv6' => [
            'label' => 'Residential IPv6 proxies',
            'countries' => static fn(): ?array => $api->Residential()->ipv6_detailed_geos(),
            'stats' => static fn(string $country): ?array => $api->Residential()->online_statistics($country),
            'preferred_country' => 'US',
            'note' => '',
        ],
        'mobile' => [
            'label' => 'Mobile proxies',
            'countries' => static fn(): ?array => $api->Mobile()->geos(),
            'stats' => static fn(string $country): ?array => $api->Mobile()->online_statistics($country),
            'preferred_country' => 'US',
            'note' => '',
        ],
        'isp' => [
            'label' => 'ISP proxies',
            'countries' => static fn(): ?array => $api->Isp()->countries(),
            'stats' => static fn(string $country): ?array => null,
            'preferred_country' => 'CA',
            'note' => 'ISP API returns countries here, not live node counters.',
        ],
        'dc' => [
            'label' => 'Dedicated DC proxies',
            'countries' => static fn(): ?array => $api->Dc()->detailed_geos(),
            'stats' => static fn(string $country): ?array => $api->Dc()->online_nodes(),
            'preferred_country' => 'US',
            'note' => '',
        ],
        'dc_shared' => [
            'label' => 'Shared DC proxies',
            'countries' => static fn(): ?array => $api->Dc()->detailed_geos(),
            'stats' => static fn(string $country): ?array => $api->Dc()->online_nodes(),
            'preferred_country' => 'US',
            'note' => 'Shared DC uses the same DC availability API.',
        ],
    ];

    foreach ($configs as $category => $config) {
        $countryRows = pli_country_rows(pli_safe_api($config['countries']), $planCountryOptions[$category] ?? []);
        $countryRow = pli_pick_country_row($countryRows, (string)$config['preferred_country']);
        $liveStats = pli_live_country_stats(pli_safe_api(static fn(): ?array => $config['stats']((string)$countryRow['country'])), $countryRow);
        $rows[] = [
            'category' => $category,
            'label' => (string)$config['label'],
            'country' => (string)$countryRow['country'],
            'country_name' => (string)$countryRow['name'],
            'nodes' => $liveStats['nodes'],
            'online' => $liveStats['online'],
            'unique' => $liveStats['unique'],
            'note' => (string)$config['note'],
        ];
    }

    return $rows;
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

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$buyerUserId = (int)$users->user_id;
$infaticaApi = Sogerien::API()->InfaticaIo()->Catalog();
$pricing = $infaticaApi->retail_pricing();
$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$hasUsedTrial = $shop->has_used_trial($buyerUserId);
$requestPath = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
$onlyCategory = $requestPath === 'proxies/mobile_proxy' ? 'mobile' : '';
$planCountryOptions = [
    'residential' => ['BR' => 'Brazil', 'CA' => 'Canada', 'CO' => 'Colombia', 'ES' => 'Spain', 'FR' => 'France', 'GB' => 'United Kingdom', 'RU' => 'Russia', 'UA' => 'Ukraine', 'US' => 'United States'],
    'residential_ipv6' => ['BR' => 'Brazil', 'CA' => 'Canada', 'CO' => 'Colombia', 'ES' => 'Spain', 'FR' => 'France', 'GB' => 'United Kingdom', 'RU' => 'Russia', 'UA' => 'Ukraine', 'US' => 'United States'],
    'mobile' => ['CN' => 'China', 'IN' => 'India', 'IT' => 'Italy', 'KZ' => 'Kazakhstan', 'MY' => 'Malaysia', 'PL' => 'Poland', 'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'US' => 'United States'],
    'isp' => ['AT' => 'Austria', 'BR' => 'Brazil', 'CA' => 'Canada', 'FR' => 'France', 'JP' => 'Japan', 'LV' => 'Latvia', 'RO' => 'Romania', 'UA' => 'Ukraine'],
    'dc' => ['BR' => 'Brazil', 'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France', 'GB' => 'United Kingdom', 'NL' => 'Netherlands', 'US' => 'United States'],
    'dc_shared' => ['BR' => 'Brazil', 'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France', 'GB' => 'United Kingdom', 'NL' => 'Netherlands', 'US' => 'United States'],
];
$availabilityRows = pli_proxy_availability_rows($planCountryOptions);
$trialPlans = $infaticaApi->trial_retail_pricing();
$visibleCategories = ['residential', 'residential_ipv6', 'mobile', 'isp', 'dc', 'dc_shared'];
if ($onlyCategory !== '') {
    $visibleCategories = [$onlyCategory];
}
$planGroups = [];
foreach ($visibleCategories as $category) {
    $isStatic = in_array($category, ['isp', 'dc'], true);
    if (isset($trialPlans[$category]) && !$hasUsedTrial) {
        $trial = $trialPlans[$category];
        $planGroups[] = [
            'category' => $category,
            'traffic' => $isStatic ? '' : (string)((float)$trial['traffic']),
            'ip_count' => $isStatic ? (string)((int)$trial['traffic']) : '',
            'days' => (string)((int)$trial['days']),
            'price' => number_format((float)$trial['price'], 2, '.', ''),
            'price_per_gb' => number_format((float)$trial['price'] / max(1.0, (float)$trial['traffic']), 2, '.', ''),
            'is_trial' => true,
            'is_static' => $isStatic,
        ];
    }
    $categoryPricing = isset($pricing[$category]) && is_array($pricing[$category]) ? $pricing[$category] : [];
    ksort($categoryPricing, SORT_NUMERIC);
    foreach ($categoryPricing as $traffic => $pricePerGb) {
        $trafficFloat = (float)$traffic;
        if ($trafficFloat <= 0.0) {
            continue;
        }
        $pricePerGbFloat = (float)$pricePerGb;
        $planGroups[] = [
            'category' => $category,
            'traffic' => $isStatic ? '' : (string)$trafficFloat,
            'ip_count' => $isStatic ? (string)((int)$trafficFloat) : '',
            'days' => $isStatic ? '30' : '364',
            'price' => number_format($trafficFloat * $pricePerGbFloat, 2, '.', ''),
            'price_per_gb' => number_format($pricePerGbFloat, 2, '.', ''),
            'is_trial' => false,
            'is_static' => $isStatic,
        ];
    }
}

$categoryLabels = [
    'residential' => 'Residential proxies',
    'residential_ipv6' => 'Residential IPv6 proxies',
    'mobile' => 'Mobile proxies',
    'isp' => 'ISP proxies',
    'dc' => 'Dedicated DC proxies',
    'dc_shared' => 'Shared DC proxies',
];

Sogerien::Page()->title = $onlyCategory === 'mobile' ? 'Mobile proxies' : pli_t('proxy.catalog_title', 'Proxy Catalog');
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <section class="pm-infatica-shop mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h1 class="h3 mb-1"><?= $onlyCategory === 'mobile' ? 'Mobile proxy traffic packages' : 'Proxy traffic packages' ?></h1>
                <div class="text-muted">Choose traffic first. Proxy access lists are generated after the service is active.</div>
            </div>
            <div class="pm-order-summary">
                <div class="small text-muted">Selected package</div>
                <div class="fw-semibold" id="pmPlanSummaryName">Nothing selected</div>
                <div class="h5 mb-0">$<span id="pmPlanSummaryTotal">0.00</span></div>
                <button type="button" class="btn btn-primary w-100 mt-2" id="pmPlanSummaryCheckoutBtn">Next step</button>
            </div>
        </div>

        <section class="pm-live-nodes mb-4" aria-label="Proxy live availability">
            <div class="pm-live-head">
                <div class="small text-muted">Proxy availability from API</div>
                <div class="fw-semibold">Nodes count, online nodes, unique nodes</div>
            </div>
            <?php foreach ($availabilityRows as $availability): ?>
                <article class="pm-live-row">
                    <div class="pm-live-title">
                        <strong><?= pli_h($availability['label']) ?></strong>
                        <span><?= pli_h($availability['country'] . ' - ' . $availability['country_name']) ?></span>
                        <?php if ($availability['note'] !== ''): ?>
                            <em><?= pli_h($availability['note']) ?></em>
                        <?php endif; ?>
                    </div>
                    <div class="pm-live-kpi">
                        <span>Nodes count</span>
                        <strong><?= pli_h((string)$availability['nodes']) ?></strong>
                    </div>
                    <div class="pm-live-kpi">
                        <span>Online nodes</span>
                        <strong><?= pli_h((string)$availability['online']) ?></strong>
                    </div>
                    <div class="pm-live-kpi">
                        <span>Unique nodes</span>
                        <strong><?= pli_h((string)$availability['unique']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php $lastCategory = ''; ?>
        <?php foreach ($planGroups as $group): ?>
            <?php
            $category = (string)$group['category'];
            if ($category !== $lastCategory):
                if ($lastCategory !== '') {
                    echo '</div>';
                }
                $lastCategory = $category;
            ?>
                <h2 class="h5 mt-4 mb-3"><?= pli_h($categoryLabels[$category] ?? $category) ?></h2>
                <div class="pm-plan-grid">
            <?php endif; ?>
            <?php
            $amountLabel = $group['is_static']
                ? ((int)$group['ip_count'] === 1 ? '1 IP' : (string)((int)$group['ip_count']) . ' IPs')
                : (((float)$group['traffic'] === 1.0 ? '1 GB' : rtrim(rtrim(number_format((float)$group['traffic'], 2, '.', ''), '0'), '.') . ' GB') . ' traffic');
            $badge = $group['is_trial'] ? 'Trial - 1 month' : ($group['is_static'] ? '1 month' : pli_t('proxy.duration_12_months', '12 months'));
            ?>
            <article class="pm-plan-card">
                <div class="pm-plan-badge"><?= pli_h($badge) ?></div>
                <div class="pm-plan-price">$<?= pli_h(number_format((float)$group['price'], 0)) ?></div>
                <div class="pm-plan-meta"><?= pli_h($amountLabel) ?></div>
                <?php if (pli_s($group['price_per_gb']) !== ''): ?>
                    <div class="text-muted small">$<?= pli_h(number_format((float)$group['price_per_gb'], 2)) ?> per <?= $group['is_static'] ? 'IP' : 'GB' ?></div>
                <?php endif; ?>
                <button type="button" class="btn btn-primary w-100 mt-3 pm-plan-select-btn"
                    data-category="<?= pli_h($category) ?>"
                    data-price-usd="<?= pli_h((string)$group['price']) ?>"
                    data-days="<?= pli_h((string)$group['days']) ?>"
                    data-is-trial="<?= $group['is_trial'] ? '1' : '0' ?>"
                    data-is-static="<?= $group['is_static'] ? '1' : '0' ?>"
                    data-ip-count="<?= pli_h((string)$group['ip_count']) ?>"
                    data-traffic="<?= pli_h((string)$group['traffic']) ?>">Select package</button>
            </article>
        <?php endforeach; ?>
        <?php if ($lastCategory !== ''): ?>
            </div>
        <?php endif; ?>
    </section>

    <script type="application/json" id="pmPlanCountryOptions"><?= (string)json_encode($planCountryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <div class="modal fade" id="pmPlanSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Package settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="pmPlanCountry">Country</label>
                        <select class="form-select" id="pmPlanCountry"></select>
                    </div>
                    <div class="small text-muted">After payment open the service and generate proxy access lists there.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pmPlanConfirmBtn">Use this package</button>
                </div>
            </div>
        </div>
    </div>
    <form id="pmProxyCartCheckoutForm" method="post" action="/client/proxy/checkout" class="d-none">
        <input type="hidden" name="cart_payload" id="pmProxyCartPayload" value="[]">
    </form>

    <style>
        .pm-infatica-shop { border: 1px solid rgba(148,163,184,.28); border-radius: 8px; padding: 18px; }
        .pm-plan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; }
        .pm-plan-card { border: 1px solid rgba(148,163,184,.35); border-radius: 8px; padding: 16px; box-shadow: 0 8px 20px rgba(15,23,42,.06); }
        .pm-plan-card.is-selected { border-color: rgb(13,110,253); box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
        .pm-plan-badge { display: inline-flex; border: 1px solid rgba(109,40,217,.25); border-radius: 6px; padding: 2px 8px; font-size: 12px; font-weight: 700; color: rgb(109,40,217); text-transform: uppercase; }
        .pm-plan-price { margin-top: 12px; font-size: 34px; line-height: 1; font-weight: 800; color: rgb(24,58,117); }
        .pm-plan-meta { margin-top: 10px; font-weight: 700; }
        .pm-order-summary { width: min(280px, 100%); border: 1px solid rgba(148,163,184,.35); border-radius: 8px; padding: 14px; }
        .pm-live-nodes { display: grid; gap: 10px; }
        .pm-live-head { border: 1px solid rgba(148,163,184,.35); border-radius: 8px; padding: 12px; background: rgb(255,255,255); }
        .pm-live-row { display: grid; grid-template-columns: minmax(190px, 1fr) repeat(3, minmax(120px, 160px)); gap: 10px; align-items: stretch; }
        .pm-live-row > div { border: 1px solid rgba(148,163,184,.35); border-radius: 8px; padding: 12px; background: rgb(255,255,255); }
        .pm-live-title strong,.pm-live-title span,.pm-live-title em { display: block; }
        .pm-live-title span { color: rgb(100,116,139); font-size: 13px; margin-top: 3px; }
        .pm-live-title em { color: rgb(100,116,139); font-size: 12px; font-style: normal; margin-top: 5px; }
        .pm-live-kpi span { display: block; color: rgb(100,116,139); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .pm-live-kpi strong { display: block; margin-top: 4px; color: rgb(24,58,117); font-size: 24px; line-height: 1; }
        @media (max-width: 900px) { .pm-live-row { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .pm-live-row { grid-template-columns: 1fr; } }
    </style>

    <script>
        (() => {
            const storageKey = 'pm_proxy_cart_infatica_v1';
            let pendingPlan = null;
            const planModalEl = document.getElementById('pmPlanSelectModal');
            const planModal = planModalEl && window.bootstrap ? new bootstrap.Modal(planModalEl) : null;
            let countryOptions = {};
            try {
                countryOptions = JSON.parse(document.getElementById('pmPlanCountryOptions')?.textContent || '{}');
            } catch (_err) {
                countryOptions = {};
            }

            const loadCart = () => {
                try {
                    const raw = localStorage.getItem(storageKey);
                    const parsed = raw ? JSON.parse(raw) : [];
                    if (!Array.isArray(parsed)) {
                        return [];
                    }
                    return parsed.map((item) => {
                        if (String(item?.id || '').includes('-trial-gb') && item.days === '7') {
                            return {...item, days: '30', auto_renew: false, auto_renew_possible: false};
                        }
                        return item;
                    });
                } catch (_err) {
                    return [];
                }
            };

            const saveCart = (cart) => {
                localStorage.setItem(storageKey, JSON.stringify(cart));
                renderCart();
            };

            const saveSingleItem = (item) => {
                saveCart([item]);
            };

            const applyPendingPlan = (country) => {
                const countries = countryOptions[pendingPlan?.category || ''] || {};
                if (!pendingPlan || !country || !countries[country]) {
                    return false;
                }
                const title = countries[country] || country;
                const traffic = String(Number.parseFloat(pendingPlan.traffic || '0')).replace(/\.0$/, '');
                const isTrial = pendingPlan.is_trial === '1';
                const isStatic = pendingPlan.is_static === '1';
                const amount = isStatic ? pendingPlan.ip_count : traffic;
                const suffix = isTrial
                    ? (isStatic ? `trial-ip${amount}` : `trial-gb${amount}`)
                    : (isStatic ? `ip${amount}` : `gb${amount}`);
                const item = {
                    id: `${pendingPlan.category}-${country}-${suffix}`,
                    title,
                    api: 'InfaticaIo',
                    price_usd: pendingPlan.price_usd,
                    days: pendingPlan.days,
                    country,
                    category: pendingPlan.category,
                    auto_renew: false,
                    auto_renew_possible: !isTrial
                };
                if (isStatic) {
                    item.ip_count = amount;
                } else {
                    item.traffic = amount;
                }
                saveSingleItem(item);
                document.querySelectorAll('.pm-plan-card.is-selected').forEach((card) => card.classList.remove('is-selected'));
                const planKey = isStatic ? 'data-ip-count' : 'data-traffic';
                document.querySelector(`.pm-plan-select-btn[data-category="${CSS.escape(pendingPlan.category)}"][${planKey}="${CSS.escape(amount)}"]`)?.closest('.pm-plan-card')?.classList.add('is-selected');
                return true;
            };

            const renderCart = () => {
                const cart = loadCart();
                const summaryName = document.getElementById('pmPlanSummaryName');
                const summaryTotal = document.getElementById('pmPlanSummaryTotal');
                if (summaryName && summaryTotal) {
                    const first = cart[0] || null;
                    const price = Number.parseFloat(first?.price_usd || '0');
                    summaryName.textContent = first ? `${first.category || 'proxy'} - ${first.country || '-'} - ${first.ip_count ? `${first.ip_count} IP` : `${first.traffic || ''} GB`}` : 'Nothing selected';
                    summaryTotal.textContent = (Number.isFinite(price) ? price : 0).toFixed(2);
                }
            };

            const submitCheckout = () => {
                const cart = loadCart();
                if (!cart.length) {
                    window.alert('Select package first.');
                    return;
                }
                const payloadInput = document.getElementById('pmProxyCartPayload');
                const form = document.getElementById('pmProxyCartCheckoutForm');
                if (!payloadInput || !form) {
                    return;
                }
                payloadInput.value = JSON.stringify([cart[0]]);
                form.submit();
            };

            document.addEventListener('click', (event) => {
                const planButton = event.target.closest('.pm-plan-select-btn');
                if (planButton) {
                    pendingPlan = {
                        category: planButton.dataset.category || '',
                        price_usd: planButton.dataset.priceUsd || '0.00',
                        days: planButton.dataset.days || '',
                        is_trial: planButton.dataset.isTrial || '0',
                        is_static: planButton.dataset.isStatic || '0',
                        ip_count: planButton.dataset.ipCount || '',
                        traffic: planButton.dataset.traffic || ''
                    };
                    const options = countryOptions[pendingPlan.category] || {};
                    const select = document.getElementById('pmPlanCountry');
                    if (select) {
                        select.innerHTML = '';
                        Object.keys(options).sort().forEach((code) => {
                            const option = document.createElement('option');
                            option.value = code;
                            option.textContent = `${code} - ${options[code] || code}`;
                            select.appendChild(option);
                        });
                    }
                    if (planModal) {
                        planModal.show();
                    } else {
                        const firstCountry = Object.keys(options).sort()[0] || '';
                        applyPendingPlan(firstCountry);
                    }
                    return;
                }
            });

            document.getElementById('pmPlanConfirmBtn')?.addEventListener('click', () => {
                const select = document.getElementById('pmPlanCountry');
                const country = select ? select.value : '';
                const countries = countryOptions[pendingPlan?.category || ''] || {};
                if (!pendingPlan || !country || !countries[country]) {
                    return;
                }
                applyPendingPlan(country);
                if (planModal) {
                    planModal.hide();
                }
            });

            document.getElementById('pmPlanSummaryCheckoutBtn')?.addEventListener('click', () => {
                submitCheckout();
            });

            renderCart();
        })();
    </script>
</main>

<?php
Sogerien::Page()->footer();
