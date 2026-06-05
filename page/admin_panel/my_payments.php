<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function mpay_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<int,array<string,mixed>> $payments
 * @return array<int,array<string,mixed>>
 */
function mpay_paid_payments(array $payments): array
{
    $rows = [];
    foreach ($payments as $payment) {
        $status = strtolower(trim((string)($payment['payment_status'] ?? $payment['status'] ?? '')));
        if ($status === 'paid') {
            $rows[] = $payment;
        }
    }
    return $rows;
}

/**
 * @param array<int,array<string,mixed>> $charges
 * @return array<int,array<string,mixed>>
 */
function mpay_paid_charges(array $charges): array
{
    $rows = [];
    foreach ($charges as $charge) {
        $checkoutStatus = strtolower(trim((string)($charge['checkout_status'] ?? '')));
        $fulfillmentStatus = strtolower(trim((string)($charge['fulfillment_status'] ?? '')));
        if ($checkoutStatus === 'paid' || in_array($fulfillmentStatus, ['fulfilled', 'provider_failed'], true)) {
            $rows[] = $charge;
        }
    }
    return $rows;
}

$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;
$userEmail = trim((string)($users->user_data['email'] ?? ''));

if ($userId <= 0) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/client/my/payments');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '/client/my/payments');
    if (!str_starts_with($requestPath, '/') || str_starts_with($requestPath, '//')) {
        $requestUri = '/client/my/payments';
    }

    if (!isset($_GET['next']) || trim((string)$_GET['next']) === '') {
        $_GET['next'] = $requestUri;
    }

    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);

$balanceUsd = $userId > 0 ? $shop->get_user_balance_usd($userId) : '0.00';
$payments = $userId > 0 ? $shop->list_user_payments($userId) : [];
$payments = mpay_paid_payments($payments);
$charges = $userId > 0 ? $shop->list_user_charges($userId) : [];
$charges = mpay_paid_charges($charges);

Sogerien::Page()->title = 'My Payments';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($userId > 0): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-muted small">Client</div>
                        <div class="h5 mb-0">мой профиль</div>
                        <div class="small text-muted"><?= mpay_h($userEmail !== '' ? $userEmail : ('User #' . (int)$userId)) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Balance USD</div>
                        <div class="h5 mb-0"><?= mpay_h($balanceUsd) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Payments</div>
                        <div class="h5 mb-0"><?= count($payments) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Charges</div>
                        <div class="h5 mb-0"><?= count($charges) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header">Client payments</div>
            <div class="card-body p-0">
                <?php if ($payments === []): ?>
                    <div class="p-3 text-muted">No payments yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Created</th>
                                <th>Payment ID</th>
                                <th>Order ID</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Balance after</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php if (!is_array($payment)) { continue; } ?>
                                <tr>
                                    <td><?= mpay_h($payment['created_at'] ?? '-') ?></td>
                                    <td><code><?= mpay_h($payment['payment_id'] ?? '-') ?></code></td>
                                    <td><code><?= mpay_h($payment['order_id'] ?? '-') ?></code></td>
                                    <td><?= mpay_h($payment['provider'] ?? '-') ?></td>
                                    <td>
                                        <?= mpay_h($payment['payment_status'] ?? '-') ?>
                                        <?php if ((string)($payment['vendor_status'] ?? '') !== ''): ?>
                                            / <?= mpay_h($payment['vendor_status']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= mpay_h((string)($payment['amount_usd'] ?? '')) ?> <?= mpay_h(strtoupper((string)($payment['currency'] ?? 'usd'))) ?></td>
                                    <td><?= mpay_h((string)($payment['balance_after_usd'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">Charges for ordered services</div>
            <div class="card-body p-0">
                <?php if ($charges === []): ?>
                    <div class="p-3 text-muted">No charges yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Created</th>
                                <th>Order ID</th>
                                <th>Title</th>
                                <th>Checkout</th>
                                <th>Fulfillment</th>
                                <th>Items</th>
                                <th>Services</th>
                                <th>Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($charges as $charge): ?>
                                <?php if (!is_array($charge)) { continue; } ?>
                                <tr>
                                    <td><?= mpay_h($charge['created_at'] ?? '-') ?></td>
                                    <td><code><?= mpay_h($charge['order_id'] ?? '-') ?></code></td>
                                    <td><?= mpay_h($charge['title'] ?? '-') ?></td>
                                    <td><?= mpay_h($charge['checkout_status'] ?? '-') ?></td>
                                    <td><?= mpay_h($charge['fulfillment_status'] ?? '-') ?></td>
                                    <td><?= (int)($charge['items_count'] ?? 0) ?></td>
                                    <td><?= (int)($charge['services_count'] ?? 0) ?></td>
                                    <td><?= mpay_h((string)($charge['amount_usd'] ?? '')) ?> <?= mpay_h(strtoupper((string)($charge['currency'] ?? 'usd'))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
