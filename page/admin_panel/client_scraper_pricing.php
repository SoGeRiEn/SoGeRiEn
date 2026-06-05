<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function csp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csp_s(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
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

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$plans = $shop->scraper_pricing_plans();

$tableRows = [];
foreach ($plans as $plan) {
    $tableRows[] = [
        'plan' => csp_s($plan['title'] ?? ''),
        'mode' => csp_s($plan['plan'] ?? ''),
        'requests_included' => number_format((int)($plan['requests_limit'] ?? 0)),
        'render_quota' => (int)($plan['render_requests_limit'] ?? 0) > 0 ? number_format((int)$plan['render_requests_limit']) : '-',
        'price_per_1000' => '$' . csp_s($plan['price_per_1000'] ?? ''),
        'total_price' => '$' . csp_s($plan['price_usd'] ?? ''),
        'overage_policy' => csp_s($plan['overage_policy'] ?? ''),
    ];
}

$planOptions = [];
foreach ($plans as $plan) {
    $planOptions[] = [
        'id' => csp_s($plan['id'] ?? ''),
        'title' => csp_s($plan['title'] ?? '') . ' - ' . number_format((int)($plan['requests_limit'] ?? 0)) . ' requests - $' . csp_s($plan['price_usd'] ?? ''),
    ];
}

Sogerien::Page()->title = 'Scraper API Pricing';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui pm-scraper-pricing-page">
    <style>
        .pm-scraper-pricing-page{--pm-border:rgba(148,163,184,.32);--pm-muted:rgb(203,213,225);--pm-ink:rgb(241,245,249);--pm-accent:rgb(56,189,248);--pm-panel:rgba(15,23,42,.42);--pm-panel-strong:rgba(15,23,42,.62)}
        .pm-scraper-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
        .pm-scraper-head h1{font-size:32px;line-height:1.1;margin:0 0 8px;color:rgb(224,242,254);letter-spacing:0}
        .pm-scraper-head p{margin:0;color:rgb(203,213,225);max-width:760px}
        .pm-scraper-note{border:1px solid var(--pm-border);border-radius:8px;padding:14px;background:var(--pm-panel);color:var(--pm-ink);max-width:320px}
        .pm-section{border:1px solid var(--pm-border);border-radius:8px;padding:16px;margin-bottom:16px;color:var(--pm-ink)}
        .pm-section h2{font-size:20px;margin:0 0 12px;color:var(--pm-ink)}
        .pm-plan-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
        .pm-plan-card{border:1px solid var(--pm-border);border-radius:8px;padding:14px;min-height:170px;display:flex;flex-direction:column;gap:8px;color:var(--pm-ink)}
        .pm-plan-card.is-selected{border-color:var(--pm-accent);background:var(--pm-panel-strong);box-shadow:0 0 0 3px rgba(56,189,248,.18)}
        .pm-plan-card button{margin-top:auto}
        .pm-plan-card strong{color:var(--pm-ink)}
        .pm-plan-badge{font-size:12px;font-weight:800;text-transform:uppercase;color:var(--pm-accent)}
        .pm-plan-price{font-size:28px;font-weight:800;color:var(--pm-ink);line-height:1}
        .pm-scraper-order .sog-ui.container-fluid{padding:0}
        .pm-scraper-order .sog-form{margin:0}
        .pm-scraper-order .row{row-gap:12px}
        .pm-scraper-order label,.pm-scraper-order .form-label{display:block;margin-bottom:6px;color:var(--pm-ink)!important;font-weight:700}
        .pm-scraper-pricing-page .text-muted,.pm-scraper-pricing-page .small{color:var(--pm-muted)!important}
        .pm-scraper-pricing-page select,.pm-scraper-pricing-page input,.pm-scraper-pricing-page textarea{background:rgb(255,255,255)!important;color:rgb(15,23,42)!important;border:1px solid rgb(203,213,225)!important;border-radius:8px;min-height:42px}
        .pm-scraper-pricing-page input[type=checkbox]{min-height:auto;width:16px;height:16px;accent-color:var(--pm-accent)}
        .pm-scraper-order .sog-form button[type=submit]{background:var(--pm-accent);color:rgb(255,255,255);border:0;border-radius:8px;min-height:42px;font-weight:800;padding:9px 14px}
        .pm-order-total{border:1px solid var(--pm-border);border-radius:8px;padding:14px;background:var(--pm-panel)}
        .pm-order-total strong{display:block;font-size:24px;color:var(--pm-ink)}
        @media(max-width:760px){.pm-scraper-head{display:block}.pm-scraper-note{margin-top:12px;max-width:none}.pm-plan-grid{grid-template-columns:1fr}.pm-scraper-head h1{font-size:28px}}
    </style>

    <div class="pm-scraper-head">
        <div>
            <h1>Scraper API Pricing</h1>
            <p>Request quota, optional render quota and success-only billing through the ProxyMint scraper gateway.</p>
        </div>
        <div class="pm-scraper-note">
            <strong>Gateway model</strong>
            <div class="text-muted small mt-1">Client - ProxyMint scraper gateway - provider API. Provider credentials are never shown in UI.</div>
        </div>
    </div>

    <section class="pm-section">
        <h2>Pricing table</h2>
        <?php
        $tr = Sogerien::TableRenderer();
        $tr->set_params = new SetParams();
        $tr->set_params->data = $tableRows;
        $tr->set_params->columns = ['plan', 'mode', 'requests_included', 'render_quota', 'price_per_1000', 'total_price', 'overage_policy'];
        $tr->set_params->headers = [
            'plan' => 'Plan',
            'mode' => 'Mode',
            'requests_included' => 'Included requests',
            'render_quota' => 'Render quota',
            'price_per_1000' => 'Price per 1k',
            'total_price' => 'Total price',
            'overage_policy' => 'Overage policy',
        ];
        $tr->set_params->gridId = 'scraper_pricing_grid';
        $tr->set_params->searchCols = ['plan', 'mode', 'requests_included'];
        $tr->set_params->perPage = 100;
        $tr->render();
        ?>
    </section>

    <section class="pm-section">
        <h2>Plan cards</h2>
        <div class="pm-plan-grid">
            <?php foreach ($plans as $index => $plan): ?>
                <article class="pm-plan-card <?= $index === 0 ? 'is-selected' : '' ?>" data-plan-card="<?= csp_h(csp_s($plan['id'] ?? '')) ?>">
                    <span class="pm-plan-badge"><?= csp_h(csp_s($plan['plan'] ?? '')) ?></span>
                    <span class="pm-plan-price">$<?= csp_h(csp_s($plan['price_usd'] ?? '0.00')) ?></span>
                    <strong><?= csp_h(csp_s($plan['title'] ?? '')) ?></strong>
                    <span class="text-muted small"><?= csp_h(number_format((int)($plan['requests_limit'] ?? 0))) ?> requests included</span>
                    <span class="text-muted small">$<?= csp_h(csp_s($plan['price_per_1000'] ?? '')) ?> per 1k</span>
                    <button type="button" class="btn btn-outline-primary" data-plan-id="<?= csp_h(csp_s($plan['id'] ?? '')) ?>">Select</button>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="pm-section pm-scraper-order">
        <h2>Order form</h2>
        <?php
        $form = new Forms(['id' => 'pmScraperOrderForm', 'action' => '/client/proxy/checkout', 'method' => 'POST', 'ajax' => false]);
        $form->addHidden('cart_payload', '[]');
        $form->addSelect('plan_id', 'Plan', $planOptions, ['required' => 'required', 'data-empty' => 'off'], csp_s($plans[0]['id'] ?? ''))->col(12, 6, 5);
        $form->addInput('request_amount', 'Request amount', 'number', ['min' => '1', 'step' => '1', 'readonly' => 'readonly'], (string)((int)($plans[0]['requests_limit'] ?? 0)))->col(12, 6, 3);
        $form->addCheckbox('gateway_key_generation', 'Generate gateway API key', [], true)->col(12, 6, 4);
        $form->addCheckbox('auto_renew', 'Auto-renew', [], false)->col(12, 6, 4);
        $form->addHTML('<div class="pm-order-total"><span class="text-muted small">Estimated total</span><strong id="pmScraperTotal">$0.00</strong><span id="pmScraperSummary" class="small text-muted">Select a plan.</span></div>', [], 'order_total')->col(12, 12, 8);
        $form->addSubmit('Add to cart')->col(12, 12, 4);
        $form->render();
        ?>
    </section>

    <script type="application/json" id="pmScraperPlans"><?= (string)json_encode($plans, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let plans = [];
            const plansEl = document.getElementById('pmScraperPlans');
            try { plans = JSON.parse(plansEl ? plansEl.textContent : '[]'); } catch (_err) {}
            const byId = (name) => document.getElementById('pmScraperOrderForm__' + name);
            const planById = new Map(plans.map((plan) => [String(plan.id || ''), plan]));
            const totalEl = document.getElementById('pmScraperTotal');
            const summaryEl = document.getElementById('pmScraperSummary');
            const form = document.getElementById('pmScraperOrderForm');
            const currentPlan = () => {
                const field = byId('plan_id');
                return planById.get(String(field ? field.value : '')) || plans[0] || {};
            };
            const updateCartPayload = () => {
                const plan = currentPlan();
                const payload = byId('cart_payload');
                const gatewayField = byId('gateway_key_generation');
                const autoRenewField = byId('auto_renew');
                if (payload) {
                    payload.value = JSON.stringify([{
                        id: String(plan.id || ''),
                        plan_id: String(plan.id || ''),
                        category: 'scraper',
                        proxy_category: 'scraper',
                        requests_limit: String(plan.requests_limit || ''),
                        gateway_key_generation: (gatewayField ? gatewayField.checked : false) || false,
                        auto_renew: (autoRenewField ? autoRenewField.checked : false) || false
                    }]);
                }
            };
            const updatePlan = () => {
                const plan = currentPlan();
                if (byId('request_amount')) byId('request_amount').value = String(plan.requests_limit || '');
                if (totalEl) totalEl.textContent = '$' + Number.parseFloat(plan.price_usd || '0').toFixed(2);
                if (summaryEl) summaryEl.textContent = Number(plan.requests_limit || 0).toLocaleString() + ' requests - ' + String(plan.overage_policy || 'Stop at quota');
                document.querySelectorAll('[data-plan-card]').forEach((card) => card.classList.toggle('is-selected', String(card.dataset.planCard || '') === String(plan.id || '')));
                updateCartPayload();
            };
            document.querySelectorAll('[data-plan-id]').forEach((button) => {
                button.addEventListener('click', () => {
                    const field = byId('plan_id');
                    if (field) {
                        field.value = String(button.dataset.planId || '');
                        field.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                    updatePlan();
                });
            });
            const planField = byId('plan_id');
            if (planField) planField.addEventListener('change', updatePlan);
            ['gateway_key_generation', 'auto_renew'].forEach((name) => {
                const field = byId(name);
                if (field) field.addEventListener('change', updateCartPayload);
            });
            if (form) form.addEventListener('submit', updateCartPayload);
            updatePlan();
        });
    </script>
</main>
<?php
Sogerien::Page()->footer();
