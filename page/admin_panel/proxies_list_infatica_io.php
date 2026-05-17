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

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$buyerUserId = (int)$users->user_id;
$infaticaApi = Sogerien::API()->InfaticaIo()->Catalog();
$pricing = $infaticaApi->retail_pricing();
$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$usedTrialCategories = [];
foreach ($shop->list_user_services($buyerUserId) as $service) {
    if (!is_array($service) || pli_s($service['is_trial'] ?? '') !== '1') {
        continue;
    }
    $trialCategory = strtolower(pli_s($service['provider_pool_category'] ?? ''));
    if ($trialCategory !== '') {
        $usedTrialCategories[$trialCategory] = true;
    }
}
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
$trialPlans = $infaticaApi->trial_retail_pricing();
$visibleCategories = ['residential', 'residential_ipv6', 'mobile', 'isp', 'dc', 'dc_shared'];
if ($onlyCategory !== '') {
    $visibleCategories = [$onlyCategory];
}
$planGroups = [];
foreach ($visibleCategories as $category) {
    if (isset($trialPlans[$category]) && !isset($usedTrialCategories[$category])) {
        $trial = $trialPlans[$category];
        $planGroups[] = [
            'category' => $category,
            'traffic' => (string)((float)$trial['traffic']),
            'days' => (string)((int)$trial['days']),
            'price' => number_format((float)$trial['price'], 2, '.', ''),
            'price_per_gb' => number_format((float)$trial['price'] / max(1.0, (float)$trial['traffic']), 2, '.', ''),
            'is_trial' => true,
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
            'traffic' => (string)$trafficFloat,
            'days' => '364',
            'price' => number_format($trafficFloat * $pricePerGbFloat, 2, '.', ''),
            'price_per_gb' => number_format($pricePerGbFloat, 2, '.', ''),
            'is_trial' => false,
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
            $trafficLabel = ((float)$group['traffic'] === 1.0 ? '1 GB' : rtrim(rtrim(number_format((float)$group['traffic'], 2, '.', ''), '0'), '.') . ' GB');
            $badge = $group['is_trial'] ? 'Trial' : ($group['days'] === '7' ? '7 days' : '12 месяцев');
            ?>
            <article class="pm-plan-card">
                <div class="pm-plan-badge"><?= pli_h($badge) ?></div>
                <div class="pm-plan-price">$<?= pli_h(number_format((float)$group['price'], 0)) ?></div>
                <div class="pm-plan-meta"><?= pli_h($trafficLabel) ?> traffic</div>
                <?php if (pli_s($group['price_per_gb']) !== ''): ?>
                    <div class="text-muted small">$<?= pli_h(number_format((float)$group['price_per_gb'], 2)) ?> per GB</div>
                <?php endif; ?>
                <button type="button" class="btn btn-primary w-100 mt-3 pm-plan-select-btn"
                    data-category="<?= pli_h($category) ?>"
                    data-price-usd="<?= pli_h((string)$group['price']) ?>"
                    data-days="<?= pli_h((string)$group['days']) ?>"
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
                    return Array.isArray(parsed) ? parsed : [];
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
                const suffix = pendingPlan.days === '7' && traffic === '1'
                    ? 'trial-gb1'
                    : `gb${traffic}`;
                saveSingleItem({
                    id: `${pendingPlan.category}-${country}-${suffix}`,
                    title,
                    api: 'InfaticaIo',
                    price_usd: pendingPlan.price_usd,
                    days: pendingPlan.days,
                    country,
                    category: pendingPlan.category,
                    traffic,
                    auto_renew: false,
                    auto_renew_possible: pendingPlan.days !== '7'
                });
                document.querySelectorAll('.pm-plan-card.is-selected').forEach((card) => card.classList.remove('is-selected'));
                document.querySelector(`.pm-plan-select-btn[data-category="${CSS.escape(pendingPlan.category)}"][data-traffic="${CSS.escape(pendingPlan.traffic)}"]`)?.closest('.pm-plan-card')?.classList.add('is-selected');
                return true;
            };

            const renderCart = () => {
                const cart = loadCart();
                const summaryName = document.getElementById('pmPlanSummaryName');
                const summaryTotal = document.getElementById('pmPlanSummaryTotal');
                if (summaryName && summaryTotal) {
                    const first = cart[0] || null;
                    const price = Number.parseFloat(first?.price_usd || '0');
                    summaryName.textContent = first ? `${first.category || 'proxy'} - ${first.country || '-'} - ${first.traffic || ''} GB` : 'Nothing selected';
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



