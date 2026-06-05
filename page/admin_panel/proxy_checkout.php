<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function pc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pc_s(mixed $value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

function pc_money(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function pc_log_file_path(): string
{
    return Sogerien::$SOGERIEN_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'logs_by.txt';
}

function pc_log_write(string $message, array $context = []): void
{
    $dir = dirname(pc_log_file_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_string($json) && $json !== '') {
            $line .= ' | ' . $json;
        }
    }
    $line .= PHP_EOL;

    @file_put_contents(pc_log_file_path(), $line, FILE_APPEND);
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = (string)(Sogerien::InputRequest()->url ?? '/client/proxy/checkout');
$isSuccessPage = str_contains($path, '/success');
$isCancelPage = str_contains($path, '/cancel');

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;
$adminViewUserIdRaw = trim((string)($_GET['user_id'] ?? ''));
$isAdminClientPreview = preg_match('/^[1-9]\d*$/', $adminViewUserIdRaw) === 1;

$stripe = Sogerien::API()->Stripe();
$stripe->debug_enabled = false;
$stripe->set_api_key(defined('STRIPE_LIVE_SECRET_KEY_LLC') ? (string)STRIPE_LIVE_SECRET_KEY_LLC : '');

$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$domain = $requestHost !== '' ? ($requestScheme . '://' . $requestHost) : rtrim((string)(Sogerien::InputRequest()->domain ?: Sogerien::InputRequest()->sogerien_domain), '/');
$successBaseUrl = $domain . '/client/proxy/checkout/success';
$cancelBaseUrl = $domain . '/client/proxy/checkout/cancel';

$errorMessage = '';
$infoMessage = '';
$redirectUrl = '';
$verified = null;
$finalized = null;
$orderId = pc_s($request['order_id'] ?? '');

pc_log_write('proxy_checkout page hit', [
    'path' => $path,
    'method' => $method,
    'is_success' => $isSuccessPage,
    'is_cancel' => $isCancelPage,
    'order_id' => $orderId,
    'user_id' => $userId,
]);

if ($userId <= 0) {
    $errorMessage = 'You need to sign in before buying proxy.';
}

if ($errorMessage === '' && $isAdminClientPreview && !$isSuccessPage && !$isCancelPage && $method === 'POST') {
    $errorMessage = 'Checkout is disabled in admin client preview. Sign in as the client to create a payment.';
    pc_log_write('checkout rejected in admin client preview', [
        'user_id' => $userId,
        'requested_user_id' => $adminViewUserIdRaw,
    ]);
}

if ($errorMessage === '' && $isCancelPage) {
    $infoMessage = 'Stripe checkout was cancelled.';
}

if ($errorMessage === '' && !$isSuccessPage && !$isCancelPage && $method === 'POST') {
    $cartPayloadRaw = $request['cart_payload'] ?? '[]';
    if (is_string($cartPayloadRaw)) {
        $decoded = json_decode($cartPayloadRaw, true);
    } elseif (is_array($cartPayloadRaw)) {
        $decoded = $cartPayloadRaw;
    } else {
        $decoded = [];
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    pc_log_write('checkout POST cart received', [
        'order_id' => $orderId,
        'user_id' => $userId,
        'items_count' => count($decoded),
    ]);

    $verified = $shop->verify_cart_items($decoded);
    if (($verified['ok'] ?? false) !== true) {
        $errorMessage = (string)($verified['error'] ?? 'Failed to verify cart.');
        pc_log_write('cart verification failed', [
            'user_id' => $userId,
            'error' => $errorMessage,
        ]);
    } else {
        pc_log_write('cart verified', [
            'user_id' => $userId,
            'items_count' => count((array)($verified['items'] ?? [])),
            'amount_usd' => (string)($verified['amount_usd'] ?? ''),
            'total_cents' => (int)($verified['total_cents'] ?? 0),
        ]);

        $draft = $shop->create_checkout_draft(
            $userId,
            $verified['items'],
            (string)$verified['amount_usd'],
            (int)$verified['total_cents']
        );
        if (($draft['ok'] ?? false) !== true) {
            $errorMessage = (string)($draft['error'] ?? 'Failed to create order.');
            pc_log_write('checkout draft create failed', [
                'user_id' => $userId,
                'error' => $errorMessage,
            ]);
        } else {
            $orderId = (string)$draft['order_id'];
            $paymentId = (string)$draft['payment_id'];
            pc_log_write('checkout draft created', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'user_id' => $userId,
            ]);

            $stripeSession = $stripe->create_checkout_session([
                'mode' => 'payment',
                'success_url' => $successBaseUrl . '?order_id=' . rawurlencode($orderId) . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelBaseUrl . '?order_id=' . rawurlencode($orderId),
                'payment_method_types' => ['card'],
                'client_reference_id' => $orderId,
                'metadata' => [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'user_id' => (string)$userId,
                    'source' => 'proxy_checkout',
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => (int)$verified['total_cents'],
                        'product_data' => [
                            'name' => 'Proxy order',
                            'description' => 'Order ID: ' . $orderId,
                        ],
                    ],
                ]],
            ], 'proxy_checkout_' . preg_replace('/[^a-zA-Z0-9_]/', '', $orderId));

            if (!is_array($stripeSession)) {
                $errorMessage = $stripe->error !== '' ? $stripe->error : 'Stripe session creation failed.';
                pc_log_write('stripe session create failed', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'error' => $errorMessage,
                ]);
            } else {
                $shop->attach_checkout_session($orderId, $paymentId, $stripeSession);
                $redirectUrl = pc_s($stripeSession['url'] ?? '');
                pc_log_write('stripe session created', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'stripe_session_id' => pc_s($stripeSession['id'] ?? ''),
                    'stripe_payment_intent' => pc_s($stripeSession['payment_intent'] ?? ''),
                    'redirect_url' => $redirectUrl,
                ]);
                if ($redirectUrl === '') {
                    $errorMessage = 'Stripe did not return redirect URL.';
                    pc_log_write('stripe redirect url missing', [
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                    ]);
                } else {
                    pc_log_write('redirecting user to stripe', [
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'redirect_url' => $redirectUrl,
                    ]);
                    header('Location: ' . $redirectUrl, true, 303);
                    Sogerien::markDone();
                    Sogerien::exit();
                }
            }
        }
    }
}

if ($errorMessage === '' && $isSuccessPage) {
    $sessionId = pc_s($request['session_id'] ?? '');
    pc_log_write('success page entered', [
        'order_id' => $orderId,
        'session_id' => $sessionId,
        'user_id' => $userId,
    ]);
    if ($sessionId === '') {
        $errorMessage = 'Missing Stripe session_id.';
        pc_log_write('success page failed - missing session_id', [
            'order_id' => $orderId,
            'user_id' => $userId,
        ]);
    } elseif ($orderId === '') {
        $errorMessage = 'Missing order_id.';
        pc_log_write('success page failed - missing order_id', [
            'session_id' => $sessionId,
            'user_id' => $userId,
        ]);
    } else {
        $checkoutSession = $stripe->retrieve_checkout_session($sessionId, ['expand' => ['line_items', 'payment_intent']]);
        if (!is_array($checkoutSession)) {
            $errorMessage = $stripe->error !== '' ? $stripe->error : 'Failed to load Stripe session.';
            pc_log_write('stripe session retrieve failed on success page', [
                'order_id' => $orderId,
                'session_id' => $sessionId,
                'error' => $errorMessage,
            ]);
        } else {
            $paymentStatus = pc_s($checkoutSession['payment_status'] ?? '');
            pc_log_write('stripe session retrieved on success page', [
                'order_id' => $orderId,
                'session_id' => $sessionId,
                'payment_status' => $paymentStatus,
                'status' => pc_s($checkoutSession['status'] ?? ''),
                'amount_total' => (int)($checkoutSession['amount_total'] ?? 0),
                'currency' => pc_s($checkoutSession['currency'] ?? ''),
            ]);

            if ($paymentStatus !== 'paid') {
                $errorMessage = 'Payment is not in paid status yet.';
                pc_log_write('success page payment not paid', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                    'payment_status' => $paymentStatus,
                ]);
            } else {
                pc_log_write('success page starts local finalize', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                ]);
                $finalized = $shop->finalize_checkout($orderId, $checkoutSession);
                if (($finalized['ok'] ?? false) !== true) {
                    $errorMessage = (string)($finalized['error'] ?? 'Order finalization failed.');
                    pc_log_write('local finalize failed on success page', [
                        'order_id' => $orderId,
                        'session_id' => $sessionId,
                        'error' => $errorMessage,
                        'result' => $finalized,
                    ]);
                } else {
                    $alreadyProcessed = !empty($finalized['already_processed']);
                    $servicesCount = isset($finalized['services']) && is_array($finalized['services']) ? count($finalized['services']) : 0;
                    $infoMessage = $alreadyProcessed
                        ? 'Order was already processed. Data was loaded from saved state.'
                        : 'Payment accepted. Proxy order was processed and saved to your profile.';
                    pc_log_write('local finalize success on success page', [
                        'order_id' => $orderId,
                        'session_id' => $sessionId,
                        'already_processed' => $alreadyProcessed,
                        'services_count' => $servicesCount,
                        'result' => $finalized,
                    ]);
                }
            }
        }
    }
}

$balanceUsd = $userId > 0 ? $shop->get_user_balance_usd($userId) : '0.00';
$orderValue = $orderId !== '' ? $shop->get_order_value($orderId) : null;

Sogerien::Page()->title = $isSuccessPage ? 'Proxy Checkout Success' : 'Proxy Checkout';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= pc_h($errorMessage) ?></div>
    <?php endif; ?>
    <?php if ($infoMessage !== ''): ?>
        <div class="alert alert-success" role="alert"><?= pc_h($infoMessage) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h1 class="h4 mb-3"><?= $isSuccessPage ? 'Order summary' : 'Checkout summary' ?></h1>
            <dl class="row mb-0">
                <dt class="col-sm-3">User ID</dt>
                <dd class="col-sm-9"><?= (int)$userId ?></dd>
                <dt class="col-sm-3">Order ID</dt>
                <dd class="col-sm-9"><code><?= pc_h($orderId !== '' ? $orderId : '-') ?></code></dd>
                <dt class="col-sm-3">Balance USD</dt>
                <dd class="col-sm-9"><strong><?= pc_h($balanceUsd) ?></strong></dd>
                <?php if (is_array($verified) && ($verified['ok'] ?? false) === true): ?>
                    <dt class="col-sm-3">Amount USD</dt>
                    <dd class="col-sm-9"><strong><?= pc_h((string)$verified['amount_usd']) ?></strong></dd>
                <?php elseif (is_array($orderValue)): ?>
                    <dt class="col-sm-3">Amount USD</dt>
                    <dd class="col-sm-9"><strong><?= pc_h((string)($orderValue['amount_usd'] ?? '0.00')) ?></strong></dd>
                <?php endif; ?>
                <?php if (is_array($orderValue)): ?>
                    <dt class="col-sm-3">Checkout status</dt>
                    <dd class="col-sm-9"><?= pc_h((string)($orderValue['checkout_status'] ?? '-')) ?></dd>
                    <dt class="col-sm-3">Fulfillment</dt>
                    <dd class="col-sm-9"><?= pc_h((string)($orderValue['fulfillment_status'] ?? '-')) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <?php
    $itemsToShow = [];
    if (is_array($verified) && ($verified['ok'] ?? false) === true && isset($verified['items']) && is_array($verified['items'])) {
        $itemsToShow = $verified['items'];
    } elseif (is_array($orderValue) && isset($orderValue['items']) && is_array($orderValue['items'])) {
        $itemsToShow = $orderValue['items'];
    }
    ?>
    <?php if ($itemsToShow !== []): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5 mb-3">Items</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Country</th>
                            <th>Category</th>
                            <th>Details</th>
                            <th>Days</th>
                            <th>Price USD</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($itemsToShow as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <?php
                            $details = '-';
                            $category = pc_s($item['proxy_category'] ?? '');
                            if ($category === 'isp') {
                                $details = pc_s($item['ip_count'] ?? '-') . ' IPs';
                            } elseif ($category === 'scraper') {
                                $details = pc_s($item['requests_limit'] ?? '-') . ' requests';
                            } elseif (pc_s($item['traffic_limitation'] ?? '') !== '') {
                                $details = pc_s($item['traffic_limitation'] ?? '') . ' GB';
                            }
                            ?>
                            <tr>
                                <td><?= pc_h((string)($item['title'] ?? '')) ?></td>
                                <td><?= pc_h((string)($item['location_country_code'] ?? '')) ?></td>
                                <td><?= pc_h((string)($item['proxy_category'] ?? '')) ?></td>
                                <td><?= pc_h($details) ?></td>
                                <td><?= pc_h((string)($item['days'] ?? '')) ?></td>
                                <td><?= pc_h((string)($item['price_usd'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $servicesToShow = [];
    if (is_array($finalized) && isset($finalized['services']) && is_array($finalized['services'])) {
        $servicesToShow = $finalized['services'];
    } elseif (is_array($orderValue) && isset($orderValue['services']) && is_array($orderValue['services'])) {
        $servicesToShow = $orderValue['services'];
    }
    ?>
    <?php if ($servicesToShow !== []): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5 mb-3">Purchased Services</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Host</th>
                            <th>Port</th>
                            <th>Login</th>
                            <th>Password</th>
                            <th>Units</th>
                            <th>Expires</th>
                            <th>Manage</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($servicesToShow as $service): ?>
                            <?php if (!is_array($service)) { continue; } ?>
                            <?php
                            $units = '-';
                            $serviceType = pc_s($service['provider_pool_category'] ?? '');
                            if ($serviceType === 'isp') {
                                $units = pc_s($service['ip_count'] ?? '-') . ' IPs';
                            } elseif ($serviceType === 'scraper') {
                                $units = pc_s($service['requests_limit'] ?? '-') . ' requests';
                            } elseif (pc_s($service['traffic_total_gb'] ?? '') !== '') {
                                $units = pc_s($service['traffic_total_gb'] ?? '') . ' GB';
                            }
                            ?>
                            <tr>
                                <td><?= pc_h((string)($service['title'] ?? '')) ?></td>
                                <td><?= pc_h((string)($service['connection_host'] ?? '')) ?></td>
                                <td><?= pc_h((string)($service['connection_port'] ?? '')) ?></td>
                                <td><?= pc_h((string)($service['connection_login'] ?? '')) ?></td>
                                <td><?= pc_h((string)($service['connection_password'] ?? '')) ?></td>
                                <td><?= pc_h($units) ?></td>
                                <td><?= pc_h((string)($service['expires_at'] ?? '')) ?></td>
                                <td>
                                    <?php if (pc_s($service['service_id'] ?? '') !== ''): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="/client/proxy/manage?service_id=<?= rawurlencode((string)$service['service_id']) ?>">Manage</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/client/my/proxies">My Proxies</a>
        <a class="btn btn-outline-secondary" href="/client/all_proxy">Back to catalog</a>
    </div>
</main>
<?php
Sogerien::Page()->footer();
