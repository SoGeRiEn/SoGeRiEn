<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

const LSP_DEFAULT_SERVICE_NAME = 'proxy datacenter';
const LSP_DEFAULT_CURRENCY = 'usd';
const LSP_DEFAULT_AMOUNT = '10.00';
const LSP_MIN_AMOUNT_CENTS = 50;

/**
 * @param array<string,mixed> $profiles
 */
function lsp_detect_profile_key(string $currentUrl, array $profiles): string
{
    foreach ($profiles as $key => $profile) {
        if (!is_string($key) || !is_array($profile)) {
            continue;
        }
        $purchasePath = lsp_s($profile['purchase_path'] ?? '');
        $successPath = lsp_s($profile['success_path'] ?? '');
        if ($purchasePath !== '' && str_starts_with($currentUrl, $purchasePath)) {
            return $key;
        }
        if ($successPath !== '' && str_starts_with($currentUrl, $successPath)) {
            return $key;
        }
    }

    return 'llc';
}

function lsp_t(string $key, string $fallback = ''): string
{
    $value = Sogerien::Lang()->get($key);
    if ($fallback !== '' && $value === $key) {
        return $fallback;
    }

    return $value;
}

function lsp_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lsp_s(mixed $value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

function lsp_amount_to_cents(string $amount): ?int
{
    $normalized = str_replace(',', '.', trim($amount));
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
        return null;
    }

    $parts = explode('.', $normalized, 2);
    $whole = (int)$parts[0];
    $fraction = 0;
    if (isset($parts[1])) {
        $fraction = (int)str_pad($parts[1], 2, '0');
    }

    $cents = ($whole * 100) + $fraction;
    if ($cents < LSP_MIN_AMOUNT_CENTS) {
        return null;
    }

    return $cents;
}

function lsp_money(int $cents, string $currency): string
{
    $amount = number_format($cents / 100, 2, '.', '');
    return strtoupper($currency) . ' ' . $amount;
}

function lsp_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8)
        . '-' . substr($hex, 8, 4)
        . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4)
        . '-' . substr($hex, 20, 12);
}

/** @var array<string,mixed> $request */
$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$currentUrl = (string)(Sogerien::InputRequest()->url ?? '/demo/purchase/live/llc');

$profiles = [
    'llc' => [
        'code' => 'llc',
        'title' => 'LLC',
        'secret_key_const' => 'STRIPE_LIVE_SECRET_KEY_LLC',
        'purchase_path' => '/demo/purchase/live/llc',
        'success_path' => '/demo/purchase/live/llc/success',
        'source' => 'admin_live_llc_purchase',
    ],
    'ltd' => [
        'code' => 'ltd',
        'title' => 'LTD',
        'secret_key_const' => 'STRIPE_LIVE_SECRET_KEY_LTD',
        'purchase_path' => '/demo/purchase/live/ltd',
        'success_path' => '/demo/purchase/live/ltd/success',
        'source' => 'admin_live_ltd_purchase',
    ],
];

$profileKey = lsp_detect_profile_key($currentUrl, $profiles);
/** @var array<string,string> $profile */
$profile = $profiles[$profileKey];
$isSuccessPage = str_ends_with($currentUrl, '/success');

$domain = trim((string)(Sogerien::InputRequest()->domain ?: Sogerien::InputRequest()->sogerien_domain));
$domain = rtrim($domain, '/');
$purchasePath = $profile['purchase_path'];
$successPath = $profile['success_path'];
$successUrl = $domain . $successPath . '?session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = $domain . $purchasePath . '?cancel=1';

$stripe = Sogerien::API()->Stripe();
$stripe->debug_enabled = false;

$errorMessage = '';
$infoMessage = '';
$checkoutRedirectUrl = '';
$checkoutSession = null;
$checkoutLineItems = [];

$secretConst = $profile['secret_key_const'];
$secretKey = defined($secretConst) ? lsp_s((string)constant($secretConst)) : '';
if ($secretKey === '') {
    $errorMessage = lsp_t('stripe.live_key_not_configured', 'Stripe live key is not configured for') . ' ' . strtoupper($profile['title']) . '.';
} else {
    $stripe->set_api_key($secretKey);
}

$draftOrderId = lsp_s($request['order_id'] ?? '');
if ($draftOrderId === '') {
    $draftOrderId = lsp_uuid_v4();
}

if ($isSuccessPage) {
    $sessionId = lsp_s($request['session_id'] ?? '');
    if ($errorMessage === '' && $sessionId === '') {
        $errorMessage = lsp_t('stripe.session_id_missing', 'session_id is missing after payment.');
    }

    if ($errorMessage === '' && $sessionId !== '') {
        $checkoutSession = $stripe->retrieve_checkout_session(
            $sessionId,
            ['expand' => ['line_items', 'payment_intent']]
        );

        if ($checkoutSession === null) {
            $errorMessage = lsp_t('stripe.fetch_error', 'Error getting payment data:') . ' ' . ($stripe->error !== '' ? $stripe->error : 'unknown Stripe error');
        } else {
            $lineItemsNode = $checkoutSession['line_items']['data'] ?? [];
            if (is_array($lineItemsNode)) {
                $checkoutLineItems = $lineItemsNode;
            }
            if ($checkoutLineItems === []) {
                $lineItemsResp = $stripe->list_checkout_session_line_items($sessionId, ['limit' => 10]);
                if (is_array($lineItemsResp) && isset($lineItemsResp['data']) && is_array($lineItemsResp['data'])) {
                    $checkoutLineItems = $lineItemsResp['data'];
                }
            }
        }
    }
} else {
    if (isset($request['cancel']) && (string)$request['cancel'] === '1') {
        $infoMessage = lsp_t('stripe.cancelled', 'Payment was cancelled on Stripe Checkout side.');
    }

    $action = lsp_s($request['action'] ?? '');
    if ($errorMessage === '' && $method === 'POST' && $action === 'create_checkout_session') {
        $amountInput = lsp_s($request['amount'] ?? '');
        $serviceName = lsp_s($request['service'] ?? '');
        $orderId = lsp_s($request['order_id'] ?? '');

        if ($serviceName === '') {
            $serviceName = LSP_DEFAULT_SERVICE_NAME;
        }
        if ($orderId === '') {
            $orderId = $draftOrderId;
        }

        $amountCents = lsp_amount_to_cents($amountInput);
        if ($amountCents === null) {
            $errorMessage = lsp_t('stripe.amount_validation', 'Amount must be a number >= 0.50 with up to 2 decimal places.');
        } else {
            $stripeParams = [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'payment_method_types' => ['card'],
                'client_reference_id' => $orderId,
                'metadata' => [
                    'order_id' => $orderId,
                    'service' => $serviceName,
                    'amount_input' => $amountInput,
                    'source' => $profile['source'],
                    'profile' => $profile['code'],
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => LSP_DEFAULT_CURRENCY,
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => $serviceName,
                            'description' => 'Order ID: ' . $orderId,
                        ],
                    ],
                ]],
            ];

            $idempotencyKey = str_replace('.', '', uniqid('live_' . $profile['code'] . '_' . $amountCents . '_', true));
            $createdSession = $stripe->create_checkout_session($stripeParams, $idempotencyKey);
            if ($createdSession === null) {
                $errorMessage = lsp_t('stripe.create_session_failed', 'Stripe failed to create checkout session:') . ' ' . ($stripe->error !== '' ? $stripe->error : 'unknown Stripe error');
            } else {
                $checkoutRedirectUrl = lsp_s($createdSession['url'] ?? '');
                if ($checkoutRedirectUrl === '') {
                    $errorMessage = lsp_t('stripe.no_redirect_url', 'Stripe returned session without redirect URL.');
                } else {
                    $infoMessage = lsp_t('stripe.session_created', 'Checkout session created. Redirecting to Stripe page...');
                }
            }
        }
    }
}

$titleSuffix = strtoupper($profile['title']) . ' LIVE';
Sogerien::Template()->title = $isSuccessPage
    ? $titleSuffix . ' - ' . lsp_t('stripe.page_title_success', 'Stripe: successful payment')
    : $titleSuffix . ' - ' . lsp_t('stripe.page_title_demo', 'Stripe: demo purchase');
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();

echo '<main class="container my-4 sog-ui">';

echo '<div class="d-flex flex-wrap gap-2 mb-3">';
echo '<a class="btn btn-outline-primary' . ($profileKey === 'llc' ? ' active' : '') . '" href="/demo/purchase/live/llc">' . lsp_h(lsp_t('stripe.live_profile_llc', 'LLC LIVE')) . '</a>';
echo '<a class="btn btn-outline-primary' . ($profileKey === 'ltd' ? ' active' : '') . '" href="/demo/purchase/live/ltd">' . lsp_h(lsp_t('stripe.live_profile_ltd', 'LTD LIVE')) . '</a>';
echo '</div>';

if ($isSuccessPage) {
    echo '<h1 class="mb-3">' . lsp_h(lsp_t('stripe.success_title', 'Payment successful')) . '</h1>';

    if ($errorMessage !== '') {
        echo '<div class="alert alert-danger" role="alert">' . lsp_h($errorMessage) . '</div>';
    } else {
        $amountTotal = (int)($checkoutSession['amount_total'] ?? 0);
        $currency = lsp_s($checkoutSession['currency'] ?? LSP_DEFAULT_CURRENCY);
        $paymentStatus = lsp_s($checkoutSession['payment_status'] ?? '');
        $sessionStatus = lsp_s($checkoutSession['status'] ?? '');
        $sessionId = lsp_s($checkoutSession['id'] ?? '');
        $customerEmail = lsp_s($checkoutSession['customer_details']['email'] ?? ($checkoutSession['customer_email'] ?? ''));
        $service = lsp_s($checkoutSession['metadata']['service'] ?? LSP_DEFAULT_SERVICE_NAME);
        $orderId = lsp_s($checkoutSession['metadata']['order_id'] ?? ($checkoutSession['client_reference_id'] ?? ''));

        $isPaid = $paymentStatus === 'paid';
        $alertClass = $isPaid ? 'alert-success' : 'alert-warning';
        $statusText = $isPaid
            ? lsp_t('stripe.payment_confirmed', 'Payment confirmed.')
            : lsp_t('stripe.payment_not_paid', 'Payment status is not paid.');
        echo '<div class="alert ' . lsp_h($alertClass) . '" role="alert">' . lsp_h($statusText) . '</div>';

        echo '<div class="card shadow-sm mb-3"><div class="card-body">';
        echo '<h5 class="card-title mb-3">' . lsp_h(lsp_t('stripe.payment_info', 'Payment information')) . '</h5>';
        echo '<dl class="row mb-0">';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.account_label', 'Stripe Account')) . '</dt><dd class="col-sm-8"><strong>' . lsp_h(strtoupper($profile['title'])) . ' ' . lsp_h(lsp_t('stripe.mode_live', 'LIVE')) . '</strong></dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.order_id', 'Order ID')) . '</dt><dd class="col-sm-8"><code>' . lsp_h($orderId) . '</code></dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.service', 'Service')) . '</dt><dd class="col-sm-8">' . lsp_h($service) . '</dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.amount', 'Amount')) . '</dt><dd class="col-sm-8"><strong>' . lsp_h(lsp_money($amountTotal, $currency)) . '</strong></dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.checkout_session', 'Checkout Session')) . '</dt><dd class="col-sm-8"><code>' . lsp_h($sessionId) . '</code></dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.payment_status', 'Payment status')) . '</dt><dd class="col-sm-8">' . lsp_h($paymentStatus) . '</dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.session_status', 'Session status')) . '</dt><dd class="col-sm-8">' . lsp_h($sessionStatus) . '</dd>';
        echo '<dt class="col-sm-4">' . lsp_h(lsp_t('stripe.email', 'Email')) . '</dt><dd class="col-sm-8">' . lsp_h($customerEmail !== '' ? $customerEmail : '-') . '</dd>';
        echo '</dl>';
        echo '</div></div>';

        if ($checkoutLineItems !== []) {
            echo '<div class="card shadow-sm mb-3"><div class="card-body">';
            echo '<h5 class="card-title mb-3">' . lsp_h(lsp_t('stripe.line_items', 'Payment line items')) . '</h5>';
            echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">';
            echo '<thead><tr><th>' . lsp_h(lsp_t('stripe.item_name', 'Name')) . '</th><th>' . lsp_h(lsp_t('stripe.item_qty', 'Qty')) . '</th><th>' . lsp_h(lsp_t('stripe.amount', 'Amount')) . '</th></tr></thead><tbody>';
            foreach ($checkoutLineItems as $lineItem) {
                if (!is_array($lineItem)) {
                    continue;
                }
                $lineName = lsp_s($lineItem['description'] ?? ($lineItem['price']['product'] ?? 'item'));
                $lineQty = (int)($lineItem['quantity'] ?? 0);
                $lineAmount = (int)($lineItem['amount_total'] ?? 0);
                $lineCurrency = lsp_s($lineItem['currency'] ?? $currency);
                echo '<tr>';
                echo '<td>' . lsp_h($lineName) . '</td>';
                echo '<td>' . lsp_h((string)$lineQty) . '</td>';
                echo '<td>' . lsp_h(lsp_money($lineAmount, $lineCurrency)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            echo '</div></div>';
        }
    }

    echo '<a class="btn btn-primary" href="' . lsp_h($purchasePath) . '">' . lsp_h(lsp_t('stripe.new_demo', 'New purchase')) . '</a>';
} else {
    $amountInput = lsp_s($request['amount'] ?? LSP_DEFAULT_AMOUNT);
    if ($amountInput === '') {
        $amountInput = LSP_DEFAULT_AMOUNT;
    }

    $serviceName = lsp_s($request['service'] ?? LSP_DEFAULT_SERVICE_NAME);
    if ($serviceName === '') {
        $serviceName = LSP_DEFAULT_SERVICE_NAME;
    }

    echo '<h1 class="mb-3">' . lsp_h($titleSuffix . ' - ' . lsp_t('stripe.demo_purchase', 'Stripe payment')) . '</h1>';
    echo '<div class="alert alert-warning" role="alert"><strong>' . lsp_h(lsp_t('stripe.live_mode', 'LIVE mode:')) . '</strong> ' . lsp_h(lsp_t('stripe.live_mode_warning', 'real card charges are enabled for this page.')) . '</div>';

    echo '<div class="card shadow-sm mb-3"><div class="card-body">';
    echo '<h5 class="card-title mb-3">' . lsp_h(lsp_t('stripe.order_params', 'Order parameters')) . '</h5>';
    echo '<dl class="row mb-0">';
    echo '<dt class="col-sm-3">' . lsp_h(lsp_t('stripe.account_label', 'Stripe Account')) . '</dt><dd class="col-sm-9"><strong>' . lsp_h(strtoupper($profile['title'])) . ' ' . lsp_h(lsp_t('stripe.mode_live', 'LIVE')) . '</strong></dd>';
    echo '<dt class="col-sm-3">' . lsp_h(lsp_t('stripe.order_id', 'Order ID')) . '</dt><dd class="col-sm-9"><code>' . lsp_h($draftOrderId) . '</code></dd>';
    echo '<dt class="col-sm-3">' . lsp_h(lsp_t('stripe.service', 'Service')) . '</dt><dd class="col-sm-9">' . lsp_h($serviceName) . '</dd>';
    echo '<dt class="col-sm-3">' . lsp_h(lsp_t('stripe.currency', 'Currency')) . '</dt><dd class="col-sm-9">' . lsp_h(strtoupper(LSP_DEFAULT_CURRENCY)) . '</dd>';
    echo '</dl>';
    echo '</div></div>';

    if ($errorMessage !== '') {
        echo '<div class="alert alert-danger" role="alert">' . lsp_h($errorMessage) . '</div>';
    }
    if ($infoMessage !== '') {
        echo '<div class="alert alert-info" role="alert">' . lsp_h($infoMessage) . '</div>';
    }

    echo '<form method="post" action="' . lsp_h($purchasePath) . '" class="card shadow-sm mb-3">';
    echo '<div class="card-body">';
    echo '<h5 class="card-title mb-3">' . lsp_h(lsp_t('stripe.pay', 'Pay')) . '</h5>';
    echo '<input type="hidden" name="action" value="create_checkout_session">';
    echo '<input type="hidden" name="order_id" value="' . lsp_h($draftOrderId) . '">';
    echo '<input type="hidden" name="service" value="' . lsp_h($serviceName) . '">';
    echo '<div class="mb-3">';
    echo '<label class="form-label" for="amount">' . lsp_h(lsp_t('stripe.amount', 'Amount')) . ' (USD)</label>';
    echo '<input class="form-control" id="amount" name="amount" type="text" value="' . lsp_h($amountInput) . '" placeholder="10.00">';
    echo '<div class="form-text">' . lsp_h(lsp_t('stripe.min_amount', 'Minimum: 0.50 USD')) . '</div>';
    echo '</div>';
    echo '<button type="submit" class="btn btn-danger">' . lsp_h(lsp_t('stripe.pay', 'Pay')) . '</button>';
    echo '</div></form>';
}

echo '</main>';

Sogerien::Template()->footer();
Sogerien::markDone();
?>
