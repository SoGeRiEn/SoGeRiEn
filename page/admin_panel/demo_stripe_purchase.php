<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

const DSP_ORDER_ID = 'f35a803e-fea8-4e26-a422-c1f3e309118a';
const DSP_SERVICE_NAME = 'proxy datacenter';
const DSP_CURRENCY = 'usd';
const DSP_DEFAULT_AMOUNT = '10.00';
const DSP_MIN_AMOUNT_CENTS = 50;

function dsp_t(string $key, string $fallback = ''): string
{
    $value = Sogerien::Lang()->get($key);
    if ($fallback !== '' && $value === $key) {
        return $fallback;
    }

    return $value;
}

function dsp_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dsp_s(mixed $value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

function dsp_amount_to_cents(string $amount): ?int
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
    if ($cents < DSP_MIN_AMOUNT_CENTS) {
        return null;
    }

    return $cents;
}

function dsp_money(int $cents, string $currency): string
{
    $amount = number_format($cents / 100, 2, '.', '');
    return strtoupper($currency) . ' ' . $amount;
}

function dsp_json(mixed $value): string
{
    $encoded = json_encode(
        $value,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    return is_string($encoded) ? $encoded : '{}';
}

function dsp_mask_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $length = strlen($value);
    if ($length <= 10) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 7) . str_repeat('*', max(0, $length - 11)) . substr($value, -4);
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$currentUrl = (string)(Sogerien::InputRequest()->url ?? '/demo/purchase');
$isSuccessPage = $currentUrl === '/demo/purchase/success';

$domain = trim((string)(Sogerien::InputRequest()->domain ?: Sogerien::InputRequest()->sogerien_domain));
$domain = rtrim($domain, '/');
$purchasePath = '/demo/purchase';
$successPath = '/demo/purchase/success';
$successUrl = $domain . $successPath . '?session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = $domain . $purchasePath . '?cancel=1';

$stripe = Sogerien::API()->Stripe();
$stripe->debug_enabled = false;
if (defined('STRIPE_TEST_SECRET_KEY')) {
    $stripe->set_api_key((string)STRIPE_TEST_SECRET_KEY);
}

$localDebug = [];
$errorMessage = '';
$infoMessage = '';
$checkoutRedirectUrl = '';
$createdSession = null;
$checkoutSession = null;
$checkoutLineItems = [];

$localDebug[] = [
    'event' => 'page_bootstrap',
    'mode' => $isSuccessPage ? 'success' : 'purchase',
    'method' => $method,
    'domain' => $domain,
    'url' => $currentUrl,
];

if ($isSuccessPage) {
    $sessionId = dsp_s($request['session_id'] ?? '');
    if ($sessionId === '') {
        $errorMessage = dsp_t('stripe.session_id_missing', 'session_id is missing after payment.');
        $localDebug[] = ['event' => 'missing_session_id'];
    } else {
        $localDebug[] = [
            'event' => 'retrieve_checkout_session_start',
            'session_id' => $sessionId,
        ];

        $checkoutSession = $stripe->retrieve_checkout_session(
            $sessionId,
            ['expand' => ['line_items', 'payment_intent']]
        );

        $localDebug[] = [
            'event' => 'retrieve_checkout_session_done',
            'status' => $stripe->status,
            'error' => $stripe->error,
            'http_code' => $stripe->last_http_code,
            'request_id' => $stripe->last_request_id,
        ];

        if ($checkoutSession === null) {
            $errorMessage = dsp_t('stripe.fetch_error', 'Error getting payment data:') . ' ' . ($stripe->error !== '' ? $stripe->error : 'unknown Stripe error');
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
    $amountInput = dsp_s($request['amount'] ?? DSP_DEFAULT_AMOUNT);
    if ($amountInput === '') {
        $amountInput = DSP_DEFAULT_AMOUNT;
    }

    if (isset($request['cancel']) && (string)$request['cancel'] === '1') {
        $infoMessage = dsp_t('stripe.cancelled', 'Payment was cancelled on Stripe Checkout side.');
        $localDebug[] = ['event' => 'payment_cancelled_by_user'];
    }

    $action = dsp_s($request['action'] ?? '');
    if ($method === 'POST' && $action === 'create_checkout_session') {
        $amountInput = dsp_s($request['amount'] ?? '');
        $amountCents = dsp_amount_to_cents($amountInput);

        $localDebug[] = [
            'event' => 'create_checkout_session_validate_amount',
            'amount_input' => $amountInput,
            'amount_cents' => $amountCents,
        ];

        if ($amountCents === null) {
            $errorMessage = dsp_t('stripe.amount_validation', 'Amount must be a number >= 0.50 with up to 2 decimal places.');
        } else {
            $stripeParams = [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'payment_method_types' => ['card'],
                'client_reference_id' => DSP_ORDER_ID,
                'metadata' => [
                    'order_id' => DSP_ORDER_ID,
                    'service' => DSP_SERVICE_NAME,
                    'amount_input' => $amountInput,
                    'source' => 'admin_demo_purchase',
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => DSP_CURRENCY,
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => DSP_SERVICE_NAME,
                            'description' => 'Order ID: ' . DSP_ORDER_ID,
                        ],
                    ],
                ]],
            ];

            $idempotencyKey = str_replace('.', '', uniqid('demo_' . $amountCents . '_', true));
            $localDebug[] = [
                'event' => 'create_checkout_session_start',
                'idempotency_key' => $idempotencyKey,
                'params' => $stripeParams,
            ];

            $createdSession = $stripe->create_checkout_session($stripeParams, $idempotencyKey);

            $localDebug[] = [
                'event' => 'create_checkout_session_done',
                'status' => $stripe->status,
                'error' => $stripe->error,
                'http_code' => $stripe->last_http_code,
                'request_id' => $stripe->last_request_id,
                'session_id' => is_array($createdSession) ? (string)($createdSession['id'] ?? '') : '',
            ];

            if ($createdSession === null) {
                $errorMessage = dsp_t('stripe.create_session_failed', 'Stripe failed to create checkout session:') . ' ' . ($stripe->error !== '' ? $stripe->error : 'unknown Stripe error');
            } else {
                $checkoutRedirectUrl = dsp_s($createdSession['url'] ?? '');
                if ($checkoutRedirectUrl === '') {
                    $errorMessage = dsp_t('stripe.no_redirect_url', 'Stripe returned session without redirect URL.');
                } else {
                    $infoMessage = dsp_t('stripe.session_created', 'Checkout session created. Redirecting to Stripe page...');
                }
            }
        }
    }
}

Sogerien::Template()->title = $isSuccessPage
    ? dsp_t('stripe.page_title_success', 'Stripe: successful payment')
    : dsp_t('stripe.page_title_demo', 'Stripe: demo purchase');
Sogerien::Template()->header();
Sogerien::Template()->mainmenu();

echo '<main class="container my-4 sog-ui">';

if ($isSuccessPage) {
    echo '<h1 class="mb-3">' . dsp_h(dsp_t('stripe.success_title', 'Payment successful')) . '</h1>';
    echo '<p class="text-muted">' . dsp_h(dsp_t('stripe.order_id', 'Order ID')) . ': <code>' . dsp_h(DSP_ORDER_ID) . '</code></p>';

    if ($errorMessage !== '') {
        echo '<div class="alert alert-danger" role="alert">' . dsp_h($errorMessage) . '</div>';
    } else {
        $amountTotal = (int)($checkoutSession['amount_total'] ?? 0);
        $currency = dsp_s($checkoutSession['currency'] ?? DSP_CURRENCY);
        $paymentStatus = dsp_s($checkoutSession['payment_status'] ?? '');
        $sessionStatus = dsp_s($checkoutSession['status'] ?? '');
        $sessionId = dsp_s($checkoutSession['id'] ?? '');
        $customerEmail = dsp_s($checkoutSession['customer_details']['email'] ?? ($checkoutSession['customer_email'] ?? ''));
        $service = dsp_s($checkoutSession['metadata']['service'] ?? DSP_SERVICE_NAME);
        $orderId = dsp_s($checkoutSession['metadata']['order_id'] ?? ($checkoutSession['client_reference_id'] ?? DSP_ORDER_ID));

        $isPaid = $paymentStatus === 'paid';
        $alertClass = $isPaid ? 'alert-success' : 'alert-warning';
        $statusText = $isPaid
            ? dsp_t('stripe.payment_confirmed', 'Payment confirmed.')
            : dsp_t('stripe.payment_not_paid', 'Payment status is not paid.');
        echo '<div class="alert ' . dsp_h($alertClass) . '" role="alert">' . dsp_h($statusText) . '</div>';

        echo '<div class="card shadow-sm mb-3"><div class="card-body">';
        echo '<h5 class="card-title mb-3">' . dsp_h(dsp_t('stripe.payment_info', 'Payment information')) . '</h5>';
        echo '<dl class="row mb-0">';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.order_id', 'Order ID')) . '</dt><dd class="col-sm-8"><code>' . dsp_h($orderId) . '</code></dd>';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.service', 'Service')) . '</dt><dd class="col-sm-8">' . dsp_h($service) . '</dd>';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.amount', 'Amount')) . '</dt><dd class="col-sm-8"><strong>' . dsp_h(dsp_money($amountTotal, $currency)) . '</strong></dd>';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.checkout_session', 'Checkout Session')) . '</dt><dd class="col-sm-8"><code>' . dsp_h($sessionId) . '</code></dd>';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.payment_status', 'Payment status')) . '</dt><dd class="col-sm-8">' . dsp_h($paymentStatus) . '</dd>';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.session_status', 'Session status')) . '</dt><dd class="col-sm-8">' . dsp_h($sessionStatus) . '</dd>';
        echo '<dt class="col-sm-4">' . dsp_h(dsp_t('stripe.email', 'Email')) . '</dt><dd class="col-sm-8">' . dsp_h($customerEmail !== '' ? $customerEmail : '-') . '</dd>';
        echo '</dl>';
        echo '</div></div>';

        if ($checkoutLineItems !== []) {
            echo '<div class="card shadow-sm mb-3"><div class="card-body">';
            echo '<h5 class="card-title mb-3">' . dsp_h(dsp_t('stripe.line_items', 'Payment line items')) . '</h5>';
            echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">';
            echo '<thead><tr><th>' . dsp_h(dsp_t('stripe.item_name', 'Name')) . '</th><th>' . dsp_h(dsp_t('stripe.item_qty', 'Qty')) . '</th><th>' . dsp_h(dsp_t('stripe.amount', 'Amount')) . '</th></tr></thead><tbody>';
            foreach ($checkoutLineItems as $lineItem) {
                if (!is_array($lineItem)) {
                    continue;
                }
                $lineName = dsp_s($lineItem['description'] ?? ($lineItem['price']['product'] ?? 'item'));
                $lineQty = (int)($lineItem['quantity'] ?? 0);
                $lineAmount = (int)($lineItem['amount_total'] ?? 0);
                $lineCurrency = dsp_s($lineItem['currency'] ?? $currency);
                echo '<tr>';
                echo '<td>' . dsp_h($lineName) . '</td>';
                echo '<td>' . dsp_h((string)$lineQty) . '</td>';
                echo '<td>' . dsp_h(dsp_money($lineAmount, $lineCurrency)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            echo '</div></div>';
        }
    }

    echo '<a class="btn btn-primary" href="' . dsp_h($purchasePath) . '">' . dsp_h(dsp_t('stripe.new_demo', 'New demo purchase')) . '</a>';
} else {
    $amountInput = dsp_s($request['amount'] ?? DSP_DEFAULT_AMOUNT);
    if ($amountInput === '') {
        $amountInput = DSP_DEFAULT_AMOUNT;
    }

    echo '<h1 class="mb-3">' . dsp_h(dsp_t('stripe.demo_purchase', 'Stripe demo purchase')) . '</h1>';
    echo '<div class="card shadow-sm mb-3"><div class="card-body">';
    echo '<h5 class="card-title mb-3">' . dsp_h(dsp_t('stripe.order_params', 'Order parameters')) . '</h5>';
    echo '<dl class="row mb-0">';
    echo '<dt class="col-sm-3">' . dsp_h(dsp_t('stripe.order_id', 'Order ID')) . '</dt><dd class="col-sm-9"><code>' . dsp_h(DSP_ORDER_ID) . '</code></dd>';
    echo '<dt class="col-sm-3">' . dsp_h(dsp_t('stripe.service', 'Service')) . '</dt><dd class="col-sm-9">' . dsp_h(DSP_SERVICE_NAME) . '</dd>';
    echo '<dt class="col-sm-3">' . dsp_h(dsp_t('stripe.currency', 'Currency')) . '</dt><dd class="col-sm-9">' . dsp_h(strtoupper(DSP_CURRENCY)) . '</dd>';
    echo '</dl>';
    echo '</div></div>';

    if ($errorMessage !== '') {
        echo '<div class="alert alert-danger" role="alert">' . dsp_h($errorMessage) . '</div>';
    }
    if ($infoMessage !== '') {
        echo '<div class="alert alert-info" role="alert">' . dsp_h($infoMessage) . '</div>';
    }

    echo '<form method="post" action="' . dsp_h($purchasePath) . '" class="card shadow-sm mb-3">';
    echo '<div class="card-body">';
    echo '<h5 class="card-title mb-3">' . dsp_h(dsp_t('stripe.pay', 'Pay')) . '</h5>';
    echo '<input type="hidden" name="action" value="create_checkout_session">';
    echo '<div class="mb-3">';
    echo '<label class="form-label" for="amount">' . dsp_h(dsp_t('stripe.amount', 'Amount')) . ' (USD)</label>';
    echo '<input class="form-control" id="amount" name="amount" type="text" value="' . dsp_h($amountInput) . '" placeholder="10.00">';
    echo '<div class="form-text">' . dsp_h(dsp_t('stripe.min_amount', 'Minimum: 0.50 USD')) . '</div>';
    echo '</div>';
    echo '<button type="submit" class="btn btn-primary">' . dsp_h(dsp_t('stripe.pay', 'Pay')) . '</button>';
    echo '</div></form>';

    echo '<div class="alert alert-secondary" role="alert">';
    echo dsp_h(dsp_t('stripe.card_hint', 'Stripe test card: 4242 4242 4242 4242, any future date, any CVC.'));
    echo '</div>';

    if ($checkoutRedirectUrl !== '') {
        $redirectUrlJson = dsp_json($checkoutRedirectUrl);
        echo '<div class="card border-info mb-3"><div class="card-body">';
        echo '<div class="mb-2">' . dsp_h(dsp_t('stripe.redirecting', 'Redirecting to Stripe Checkout...')) . '</div>';
        echo '<a class="btn btn-outline-primary" href="' . dsp_h($checkoutRedirectUrl) . '">' . dsp_h(dsp_t('stripe.go_now', 'Go now')) . '</a>';
        echo '</div></div>';
        echo '<script>';
        echo 'setTimeout(function(){window.location.href=' . $redirectUrlJson . ';}, 1000);';
        echo '</script>';
    }
}

echo '</main>';

/*
$stripeSecret = defined('STRIPE_TEST_SECRET_KEY') ? (string)STRIPE_TEST_SECRET_KEY : '';
$stripePublishable = defined('STRIPE_TEST_PUBLISHABLE_KEY') ? (string)STRIPE_TEST_PUBLISHABLE_KEY : '';

$debugPayload = [
    'page_mode' => $isSuccessPage ? 'success' : 'purchase',
    'request' => [
        'method' => $method,
        'url' => $currentUrl,
        'request_uri' => (string)(Sogerien::InputRequest()->REQUEST_URI ?? ''),
        'params' => $request,
    ],
    'stripe_config' => [
        'publishable_key' => dsp_mask_key($stripePublishable),
        'secret_key' => dsp_mask_key($stripeSecret),
        'base_url' => $stripe->base_url,
        'debug_enabled' => $stripe->debug_enabled,
    ],
    'stripe_state' => [
        'status' => $stripe->status,
        'error' => $stripe->error,
        'last_http_code' => $stripe->last_http_code,
        'last_url' => $stripe->last_url,
        'last_request_id' => $stripe->last_request_id,
        'last_error_type' => $stripe->last_error_type,
        'last_error_code' => $stripe->last_error_code,
        'last_error_decline_code' => $stripe->last_error_decline_code,
        'last_error_message' => $stripe->last_error_message,
        'last_error_param' => $stripe->last_error_param,
        'last_error_advice_code' => $stripe->last_error_advice_code,
        'last_error_network_advice_code' => $stripe->last_error_network_advice_code,
        'last_error_network_decline_code' => $stripe->last_error_network_decline_code,
        'last_error_doc_url' => $stripe->last_error_doc_url,
        'last_error_request_log_url' => $stripe->last_error_request_log_url,
    ],
    'local_events' => $localDebug,
    'created_session' => $createdSession,
    'checkout_session' => $checkoutSession,
    'checkout_line_items' => $checkoutLineItems,
    'stripe_debug_history' => $stripe->debug_history,
];

echo '<section class="container mb-4">';
echo '<div class="card border-secondary">';
echo '<div class="card-header">' . dsp_h(dsp_t('stripe.debug', 'Stripe debug')) . '</div>';
echo '<div class="card-body">';
echo '<pre style="margin:0;white-space:pre-wrap;word-break:break-word;">' . dsp_h(dsp_json($debugPayload)) . '</pre>';
echo '</div></div></section>';
*/

Sogerien::Template()->footer();
Sogerien::markDone();
?>
