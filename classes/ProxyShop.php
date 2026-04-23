<?php
declare(strict_types=1);

final class ProxyShop
{
    public bool $status = false;
    public string $error = '';
    public string $db_alias = 'front';
    private int $checkout_catalog_fallback_limit = 1000;

    public function init_db_alias(string $alias): void
    {
        $alias = trim($alias);
        if ($alias !== '') {
            $this->db_alias = $alias;
        }
        $this->ok();
    }

    /**
     * @param array<int,mixed> $items
     * @return array<string,mixed>
     */
    public function verify_cart_items(array $items): array
    {
        $this->fail('');

        if ($items === []) {
            return $this->fail_result('Cart is empty.');
        }

        $requiredIds = [];
        foreach ($items as $rawItem) {
            if (!is_array($rawItem)) {
                return $this->fail_result('Invalid cart item.');
            }

            $id = trim((string)($rawItem['id'] ?? ''));
            if ($id === '') {
                return $this->fail_result('Invalid cart item.');
            }

            $requiredIds[$id] = true;
        }

        $catalog = $this->load_catalog_map(array_keys($requiredIds));
        if ($catalog === []) {
            return $this->fail_result($this->error !== '' ? $this->error : 'Catalog is empty.');
        }

        $verified = [];
        $totalCents = 0;

        foreach ($items as $rawItem) {
            $id = trim((string)($rawItem['id'] ?? ''));
            if ($id === '' || !isset($catalog[$id])) {
                return $this->fail_result('Proxy is no longer available: ' . $id);
            }

            $row = $catalog[$id];
            $stock = strtolower(trim((string)($row['stock_status'] ?? '')));
            if ($stock !== 'in_stock') {
                return $this->fail_result('Proxy is out of stock: ' . (string)($row['title'] ?? $id));
            }

            $supportCheck = $this->check_vendor_support($row);
            if (($supportCheck['ok'] ?? false) !== true) {
                return $this->fail_result((string)($supportCheck['error'] ?? ('Proxy is not available for automatic checkout: ' . (string)($row['title'] ?? $id))));
            }

            $priceUsd = $this->to_money((float)($row['price_usd'] ?? 0));
            $priceCents = $this->money_to_cents($priceUsd);
            if ($priceCents <= 0) {
                return $this->fail_result('Invalid proxy price: ' . (string)($row['title'] ?? $id));
            }

            $autoRenewRequested = (bool)($rawItem['auto_renew'] ?? false);
            if (($row['is_auto_renewal_possible'] ?? false) !== true) {
                $autoRenewRequested = false;
            }

            $verified[] = [
                'catalog_id' => $id,
                'title' => (string)($row['title'] ?? ''),
                'price_usd' => $priceUsd,
                'price_cents' => $priceCents,
                'days' => (int)($row['days'] ?? 0),
                'proxy_category' => (string)($row['proxy_category'] ?? ''),
                'access_type' => (string)($row['access_type'] ?? ''),
                'location_country_code' => (string)($row['location_country_code'] ?? ''),
                'stock_status' => (string)($row['stock_status'] ?? ''),
                'is_auto_renewal_possible' => (bool)($row['is_auto_renewal_possible'] ?? false),
                'auto_renew' => $autoRenewRequested,
                'api' => (string)($supportCheck['api'] ?? ''),
                'vendor_flow' => (string)($supportCheck['vendor_flow'] ?? ''),
                'vendor_category' => (string)($supportCheck['vendor_category'] ?? ''),
                'vendor_support_label' => (string)($supportCheck['label'] ?? ''),
                'source_row' => $row,
            ];
            $totalCents += $priceCents;
        }

        $this->ok();
        return [
            'ok' => true,
            'items' => $verified,
            'total_cents' => $totalCents,
            'amount_usd' => $this->to_money($totalCents / 100),
            'currency' => 'usd',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $verifiedItems
     * @return array<string,mixed>
     */
    public function create_checkout_draft(int $userId, array $verifiedItems, string $amountUsd, int $amountCents): array
    {
        $this->fail('');

        if ($userId <= 0) {
            return $this->fail_result('User is not authorized.');
        }
        if ($verifiedItems === []) {
            return $this->fail_result('No items to checkout.');
        }
        if ($amountCents <= 0) {
            return $this->fail_result('Invalid checkout amount.');
        }

        $orderId = $this->uuid_v4();
        $paymentId = $this->uuid_v4();
        $createdAt = gmdate('c');

        $orderValue = [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'checkout_status' => 'pending_payment',
            'fulfillment_status' => 'waiting_payment',
            'items' => $verifiedItems,
            'amount_usd' => $amountUsd,
            'amount_cents' => $amountCents,
            'currency' => 'usd',
            'services' => [],
            'created_at_iso' => $createdAt,
        ];
        $paymentValue = [
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'amount_usd' => $amountUsd,
            'amount_cents' => $amountCents,
            'currency' => 'usd',
            'payment_status' => 'pending',
            'provider' => 'stripe',
            'created_at_iso' => $createdAt,
        ];

        $insertOrder = $this->insert_row(
            'proxy_order',
            $orderId,
            [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'user_id' => (string)$userId,
            ],
            $orderValue
        );
        if ($insertOrder <= 0) {
            return $this->fail_result($this->error !== '' ? $this->error : 'Failed to create order draft.');
        }

        $insertPayment = $this->insert_row(
            'payment',
            $paymentId,
            [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'user_id' => (string)$userId,
            ],
            $paymentValue
        );
        if ($insertPayment <= 0) {
            return $this->fail_result($this->error !== '' ? $this->error : 'Failed to create payment draft.');
        }

        $this->ok();
        return [
            'ok' => true,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function direct_cyberyozh_order(int $userId, array $item): array
    {
        $this->fail('');

        if ($userId <= 0) {
            return $this->fail_result('User is not authorized.');
        }

        $catalogId = trim((string)($item['catalog_id'] ?? $item['id'] ?? ''));
        if ($catalogId === '') {
            return $this->fail_result('Proxy id is empty.');
        }

        $priceUsd = $this->to_money((float)($item['price_usd'] ?? 0));
        $priceCents = $this->money_to_cents($priceUsd);
        if ($priceCents <= 0) {
            return $this->fail_result('Invalid proxy price.');
        }

        $normalizedItem = [
            'catalog_id' => $catalogId,
            'title' => (string)($item['title'] ?? ''),
            'price_usd' => $priceUsd,
            'price_cents' => $priceCents,
            'days' => (int)($item['days'] ?? 0),
            'proxy_category' => (string)($item['proxy_category'] ?? $item['category'] ?? ''),
            'access_type' => (string)($item['access_type'] ?? 'private'),
            'location_country_code' => (string)($item['location_country_code'] ?? $item['country'] ?? ''),
            'stock_status' => (string)($item['stock_status'] ?? 'in_stock'),
            'is_auto_renewal_possible' => (bool)($item['is_auto_renewal_possible'] ?? $item['auto_renew_possible'] ?? false),
            'auto_renew' => (bool)($item['auto_renew'] ?? false),
            'api' => 'cyberyozh',
            'vendor_flow' => 'cyberyozh_buy_proxies',
            'vendor_category' => (string)($item['proxy_category'] ?? $item['category'] ?? ''),
            'vendor_support_label' => 'CyberYozh',
            'source_row' => $item,
        ];

        $beforeHistory = $this->fetch_history_rows();
        $buyItem = [
            'id' => $catalogId,
            'auto_renew' => (bool)$normalizedItem['auto_renew'],
        ];
        $promoCode = trim((string)($item['promo_code'] ?? ''));
        if ($promoCode !== '') {
            $buyItem['promo_code'] = $promoCode;
        }

        $vendorResponse = Sogerien::API()->Cyberyozh()->buy_proxies([$buyItem]);
        if (!is_array($vendorResponse)) {
            return $this->fail_result(Sogerien::API()->Cyberyozh()->error !== '' ? Sogerien::API()->Cyberyozh()->error : 'CyberYozh order request failed.');
        }

        $vendorRow = null;
        if (isset($vendorResponse[0]) && is_array($vendorResponse[0])) {
            $vendorRow = $vendorResponse[0];
        } elseif (isset($vendorResponse['data'][0]) && is_array($vendorResponse['data'][0])) {
            $vendorRow = $vendorResponse['data'][0];
        }

        $vendorStatus = strtolower(trim((string)($vendorRow['status'] ?? '')));
        if (in_array($vendorStatus, ['canceled', 'failed', 'error'], true)) {
            $vendorMessage = trim((string)($vendorRow['message'] ?? $vendorRow['error'] ?? 'CyberYozh rejected the order.'));
            return $this->fail_result($vendorMessage !== '' ? $vendorMessage : 'CyberYozh rejected the order.');
        }

        $afterHistory = $this->fetch_history_rows();
        $orderId = $this->uuid_v4();
        $services = $this->extract_new_services([$normalizedItem], $beforeHistory, $afterHistory, $orderId);

        $orderValue = [
            'order_id' => $orderId,
            'payment_id' => '',
            'user_id' => $userId,
            'checkout_status' => 'direct_vendor',
            'fulfillment_status' => $services === [] ? 'pending_vendor_sync' : 'fulfilled',
            'items' => [$normalizedItem],
            'amount_usd' => $priceUsd,
            'amount_cents' => $priceCents,
            'currency' => 'usd',
            'services' => $services,
            'vendor_response' => $vendorResponse,
            'vendor_requested_at' => gmdate('c'),
            'created_at_iso' => gmdate('c'),
            'fulfilled_at' => $services === [] ? '' : gmdate('c'),
            'source' => 'cyberyozh_direct',
        ];

        $insertOrder = $this->insert_row(
            'proxy_order',
            $orderId,
            [
                'order_id' => $orderId,
                'user_id' => (string)$userId,
                'source' => 'cyberyozh_direct',
            ],
            $orderValue
        );
        if ($insertOrder <= 0) {
            return $this->fail_result($this->error !== '' ? $this->error : 'Failed to store direct CyberYozh order.');
        }

        $this->ok();
        return [
            'ok' => true,
            'order_id' => $orderId,
            'services' => $services,
            'vendor_response' => $vendorResponse,
        ];
    }

    /**
     * @param array<string,mixed> $session
     */
    public function attach_checkout_session(string $orderId, string $paymentId, array $session): bool
    {
        $this->fail('');

        $sessionId = trim((string)($session['id'] ?? ''));
        if ($sessionId === '') {
            return false;
        }

        $sessionUrl = trim((string)($session['url'] ?? ''));
        $paymentIntent = trim((string)($session['payment_intent'] ?? ''));

        $order = $this->get_order_row($orderId);
        $payment = $this->get_payment_row($paymentId);
        if ($order === null || $payment === null) {
            return false;
        }

        $orderValue = $this->row_value($order);
        $paymentValue = $this->row_value($payment);

        $orderValue['checkout_status'] = 'session_created';
        $orderValue['stripe_session_id'] = $sessionId;
        $orderValue['stripe_checkout_url'] = $sessionUrl;
        $orderValue['stripe_payment_intent'] = $paymentIntent;
        $orderValue['updated_at_iso'] = gmdate('c');

        $paymentValue['payment_status'] = 'session_created';
        $paymentValue['stripe_session_id'] = $sessionId;
        $paymentValue['stripe_checkout_url'] = $sessionUrl;
        $paymentValue['stripe_payment_intent'] = $paymentIntent;
        $paymentValue['updated_at_iso'] = gmdate('c');

        $okOrder = $this->update_row_value((int)$order['id'], $orderValue);
        $okPayment = $this->update_row_value((int)$payment['id'], $paymentValue);
        if (!$okOrder || !$okPayment) {
            return false;
        }

        $this->ok();
        return true;
    }

    /**
     * @param array<string,mixed> $checkoutSession
     * @return array<string,mixed>
     */
    public function finalize_checkout(string $orderId, array $checkoutSession): array
    {
        $this->fail('');

        $order = $this->get_order_row($orderId);
        if ($order === null) {
            return $this->fail_result('Order not found.');
        }

        $orderValue = $this->row_value($order);
        $paymentId = trim((string)($orderValue['payment_id'] ?? ''));
        $userId = (int)($orderValue['user_id'] ?? 0);
        if ($paymentId === '' || $userId <= 0) {
            return $this->fail_result('Order is broken.');
        }

        $payment = $this->get_payment_row($paymentId);
        if ($payment === null) {
            return $this->fail_result('Payment not found.');
        }

        $paymentValue = $this->row_value($payment);
        $sessionId = trim((string)($checkoutSession['id'] ?? ''));
        $paymentStatus = trim((string)($checkoutSession['payment_status'] ?? ''));
        $amountTotal = (int)($checkoutSession['amount_total'] ?? 0);
        $amountUsd = $this->to_money($amountTotal / 100);

        $orderValue['stripe_session_id'] = $sessionId;
        $orderValue['checkout_status'] = $paymentStatus === 'paid' ? 'paid' : 'not_paid';
        $orderValue['updated_at_iso'] = gmdate('c');
        $paymentValue['stripe_session_id'] = $sessionId;
        $paymentValue['payment_status'] = $paymentStatus;
        $paymentValue['updated_at_iso'] = gmdate('c');
        $paymentValue['stripe_session'] = $checkoutSession;

        if ($paymentStatus !== 'paid') {
            $this->update_row_value((int)$order['id'], $orderValue);
            $this->update_row_value((int)$payment['id'], $paymentValue);
            return $this->fail_result('Payment is not in paid status.');
        }

        if (!isset($paymentValue['balance_credited_at'])) {
            $newBalance = Sogerien::Users()->increase_balance_amount($userId, $amountTotal / 100, 'USD');
            if ($newBalance === null) {
                return $this->fail_result(Sogerien::Users()->error !== '' ? Sogerien::Users()->error : 'Failed to update balance.');
            }

            $paymentValue['balance_credited_at'] = gmdate('c');
            $paymentValue['balance_after_usd'] = $newBalance;
            $orderValue['balance_after_usd'] = $newBalance;
        }

        $existingServices = $orderValue['services'] ?? [];
        if (is_array($existingServices) && $existingServices !== []) {
            $this->update_row_value((int)$order['id'], $orderValue);
            $this->update_row_value((int)$payment['id'], $paymentValue);
            $this->ok();
            return [
                'ok' => true,
                'order' => $orderValue,
                'payment' => $paymentValue,
                'services' => $existingServices,
                'already_processed' => true,
            ];
        }

        if (isset($orderValue['vendor_response'])) {
            $this->update_row_value((int)$order['id'], $orderValue);
            $this->update_row_value((int)$payment['id'], $paymentValue);
            return $this->fail_result('Payment is captured, but vendor order needs manual check. Duplicate buy is blocked.');
        }

        $items = $orderValue['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $this->fail_result('Order items are missing.');
        }

        $beforeHistory = $this->fetch_history_rows();
        $buyItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $buyItems[] = [
                'id' => (string)($item['catalog_id'] ?? ''),
                'auto_renew' => (bool)($item['auto_renew'] ?? false),
            ];
        }
        if ($buyItems === []) {
            return $this->fail_result('Nothing to send to vendor.');
        }

        $provisionResult = $this->provision_vendor_services($items, $buyItems, $beforeHistory, $orderId);
        $orderValue['vendor_response'] = $provisionResult['vendor_response'] ?? [];
        $orderValue['vendor_requested_at'] = gmdate('c');

        if (($provisionResult['ok'] ?? false) !== true) {
            $orderValue['fulfillment_status'] = 'vendor_failed';
            $orderValue['vendor_error'] = (string)($provisionResult['error'] ?? 'Vendor purchase failed.');
            $paymentValue['vendor_status'] = 'failed';
            $this->update_row_value((int)$order['id'], $orderValue);
            $this->update_row_value((int)$payment['id'], $paymentValue);
            return $this->fail_result($orderValue['vendor_error'] !== '' ? (string)$orderValue['vendor_error'] : 'Vendor purchase failed.');
        }

        $services = [];
        if (isset($provisionResult['services']) && is_array($provisionResult['services'])) {
            $services = $provisionResult['services'];
        }

        $orderValue['services'] = $services;
        $orderValue['fulfillment_status'] = $services === [] ? 'vendor_paid_missing_credentials' : 'fulfilled';
        $orderValue['fulfilled_at'] = gmdate('c');
        $paymentValue['vendor_status'] = $orderValue['fulfillment_status'];

        $this->update_row_value((int)$order['id'], $orderValue);
        $this->update_row_value((int)$payment['id'], $paymentValue);

        $this->ok();
        return [
            'ok' => true,
            'order' => $orderValue,
            'payment' => $paymentValue,
            'services' => $services,
            'already_processed' => false,
        ];
    }

    /**
     * @param array<string,mixed> $stripeEvent
     * @return array<string,mixed>
     */
    public function finalize_checkout_by_event(array $stripeEvent): array
    {
        $this->fail('');

        $eventId = trim((string)($stripeEvent['id'] ?? ''));
        $eventType = trim((string)($stripeEvent['type'] ?? ''));
        $eventObject = $stripeEvent['data']['object'] ?? null;
        if ($eventId === '' || !is_array($eventObject)) {
            return $this->fail_result('Stripe event payload is invalid.');
        }
        if ($eventType !== 'checkout.session.completed') {
            return $this->fail_result('Unsupported Stripe event type: ' . $eventType);
        }

        $sessionId = trim((string)($eventObject['id'] ?? ''));
        if ($sessionId === '') {
            return $this->fail_result('Stripe session id is missing in event.');
        }

        $order = $this->find_order_by_session_id($sessionId);
        if ($order === null) {
            $metadata = $eventObject['metadata'] ?? [];
            $orderId = is_array($metadata) ? trim((string)($metadata['order_id'] ?? '')) : '';
            if ($orderId !== '') {
                $order = $this->get_order_row($orderId);
            }
        }
        if ($order === null) {
            return $this->fail_result('Order for Stripe session was not found.');
        }

        $orderValue = $this->row_value($order);
        $handledEventId = trim((string)($orderValue['stripe_last_webhook_event_id'] ?? ''));
        if ($handledEventId === $eventId && isset($orderValue['fulfilled_at'])) {
            $this->ok();
            return [
                'ok' => true,
                'already_processed' => true,
                'order' => $orderValue,
            ];
        }

        $orderId = trim((string)($orderValue['order_id'] ?? $order['name'] ?? ''));
        if ($orderId === '') {
            return $this->fail_result('Order id is missing.');
        }

        $finalized = $this->finalize_checkout($orderId, $eventObject);
        $freshOrder = $this->get_order_row($orderId);
        if ($freshOrder !== null) {
            $freshOrderValue = $this->row_value($freshOrder);
            $freshOrderValue['stripe_last_webhook_event_id'] = $eventId;
            $freshOrderValue['stripe_last_webhook_event_type'] = $eventType;
            $freshOrderValue['stripe_last_webhook_at'] = gmdate('c');
            $freshOrderValue['stripe_last_webhook'] = $stripeEvent;
            $this->update_row_value((int)$freshOrder['id'], $freshOrderValue);
        }

        return $finalized;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_services(int $userId): array
    {
        $this->fail('');

        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT id, name, table_index, table_value, status, created_at, updated_at
            FROM sogerien
            WHERE table_name = 'proxy_order'
              AND status <> 'delete'
              AND table_index->>'user_id' = :user_id
            ORDER BY created_at DESC;
        ";
        $res = $this->db_query($sql, ['user_id' => (string)$userId]);
        if (($res['result'] ?? false) !== true) {
            return [];
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $services = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $orderValue = $this->row_value($row);
            $orderId = (string)($orderValue['order_id'] ?? $row['name'] ?? '');
            $items = $orderValue['items'] ?? [];
            $orderServices = $orderValue['services'] ?? [];

            if (is_array($orderServices) && $orderServices !== []) {
                foreach ($orderServices as $service) {
                    if (!is_array($service)) {
                        continue;
                    }
                    $service['order_id'] = $orderId;
                    $service['order_status'] = (string)($orderValue['fulfillment_status'] ?? '');
                    $services[] = $service;
                }
                continue;
            }

            if (!is_array($items)) {
                continue;
            }
            $fulfillmentStatus = strtolower(trim((string)($orderValue['fulfillment_status'] ?? 'waiting_payment')));
            if (in_array($fulfillmentStatus, ['waiting_payment', 'pending_payment'], true)) {
                continue;
            }
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $services[] = [
                    'service_id' => $orderId . '-pending-' . (string)$index,
                    'order_id' => $orderId,
                    'title' => (string)($item['title'] ?? ''),
                    'country' => (string)($item['location_country_code'] ?? ''),
                    'price_usd' => (string)($item['price_usd'] ?? ''),
                    'expires_at' => '',
                    'status' => (string)($orderValue['fulfillment_status'] ?? 'waiting_payment'),
                    'connection_host' => '',
                    'connection_port' => '',
                    'connection_login' => '',
                    'connection_password' => '',
                    'auto_renew_request' => (bool)($item['auto_renew'] ?? false),
                    'vendor_history_id' => '',
                    'proxy_category' => (string)($item['proxy_category'] ?? ''),
                    'access_type' => (string)($item['access_type'] ?? ''),
                    'system_status' => (string)($orderValue['fulfillment_status'] ?? ''),
                    'raw' => [],
                ];
            }
        }

        $this->ok();
        return $services;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_payments(int $userId): array
    {
        $this->fail('');

        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT id, name, table_index, table_value, status, created_at, updated_at
            FROM sogerien
            WHERE table_name = 'payment'
              AND status <> 'delete'
              AND table_index->>'user_id' = :user_id
            ORDER BY created_at DESC;
        ";
        $res = $this->db_query($sql, ['user_id' => (string)$userId]);
        if (($res['result'] ?? false) !== true) {
            return [];
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = $this->row_value($row);
            $out[] = [
                'payment_id' => (string)($value['payment_id'] ?? $row['name'] ?? ''),
                'order_id' => (string)($value['order_id'] ?? ''),
                'provider' => (string)($value['provider'] ?? ''),
                'currency' => (string)($value['currency'] ?? 'usd'),
                'amount_usd' => (string)($value['amount_usd'] ?? ''),
                'amount_cents' => (int)($value['amount_cents'] ?? 0),
                'payment_status' => (string)($value['payment_status'] ?? ''),
                'vendor_status' => (string)($value['vendor_status'] ?? ''),
                'stripe_session_id' => (string)($value['stripe_session_id'] ?? ''),
                'balance_after_usd' => (string)($value['balance_after_usd'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'raw' => $value,
            ];
        }

        $this->ok();
        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_charges(int $userId): array
    {
        $this->fail('');

        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT id, name, table_index, table_value, status, created_at, updated_at
            FROM sogerien
            WHERE table_name = 'proxy_order'
              AND status <> 'delete'
              AND table_index->>'user_id' = :user_id
            ORDER BY created_at DESC;
        ";
        $res = $this->db_query($sql, ['user_id' => (string)$userId]);
        if (($res['result'] ?? false) !== true) {
            return [];
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = $this->row_value($row);
            $items = $value['items'] ?? [];
            $services = $value['services'] ?? [];
            $firstItem = (is_array($items) && isset($items[0]) && is_array($items[0])) ? $items[0] : [];
            $out[] = [
                'order_id' => (string)($value['order_id'] ?? $row['name'] ?? ''),
                'source' => (string)($value['source'] ?? ''),
                'checkout_status' => (string)($value['checkout_status'] ?? ''),
                'fulfillment_status' => (string)($value['fulfillment_status'] ?? ''),
                'amount_usd' => (string)($value['amount_usd'] ?? ''),
                'amount_cents' => (int)($value['amount_cents'] ?? 0),
                'currency' => (string)($value['currency'] ?? 'usd'),
                'items_count' => is_array($items) ? count($items) : 0,
                'services_count' => is_array($services) ? count($services) : 0,
                'title' => (string)($firstItem['title'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'raw' => $value,
            ];
        }

        $this->ok();
        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function service_action(int $userId, string $serviceId, string $action, array $payload = []): array
    {
        $this->fail('');

        $service = $this->find_service($userId, $serviceId);
        if ($service === null) {
            return $this->fail_result('Service not found.');
        }

        $vendorHistoryId = trim((string)($service['service']['vendor_history_id'] ?? ''));
        if ($vendorHistoryId === '') {
            return $this->fail_result('Vendor service id is missing.');
        }

        $result = ['ok' => false, 'error' => 'Unsupported action'];
        if ($action === 'restart') {
            $raw = Sogerien::API()->Cyberyozh()->refresh_ip($vendorHistoryId);
            $result = $raw !== null ? ['ok' => true, 'data' => $raw] : ['ok' => false, 'error' => Sogerien::API()->Cyberyozh()->error];
        } elseif ($action === 'reboot_modem') {
            $raw = Sogerien::API()->Cyberyozh()->reboot_modem($vendorHistoryId);
            $result = $raw !== null ? ['ok' => true, 'data' => $raw] : ['ok' => false, 'error' => Sogerien::API()->Cyberyozh()->error];
        } elseif ($action === 'auto_renew_on') {
            $raw = Sogerien::API()->Cyberyozh()->update_auto_renewal($vendorHistoryId, true);
            $result = $raw !== null ? ['ok' => true, 'data' => $raw] : ['ok' => false, 'error' => Sogerien::API()->Cyberyozh()->error];
            if (($result['ok'] ?? false) === true) {
                $service['service']['auto_renew_request'] = true;
            }
        } elseif ($action === 'auto_renew_off') {
            $raw = Sogerien::API()->Cyberyozh()->update_auto_renewal($vendorHistoryId, false);
            $result = $raw !== null ? ['ok' => true, 'data' => $raw] : ['ok' => false, 'error' => Sogerien::API()->Cyberyozh()->error];
            if (($result['ok'] ?? false) === true) {
                $service['service']['auto_renew_request'] = false;
            }
        }

        $service['service']['last_action'] = $action;
        $service['service']['last_action_at'] = gmdate('c');
        $service['service']['last_action_result'] = $result;

        $services = $service['order_value']['services'] ?? [];
        if (is_array($services)) {
            $services[$service['service_index']] = $service['service'];
            $service['order_value']['services'] = array_values($services);
            $service['order_value']['updated_at_iso'] = gmdate('c');
            $this->update_row_value((int)$service['order_row']['id'], $service['order_value']);
        }

        if (($result['ok'] ?? false) !== true) {
            return $this->fail_result((string)($result['error'] ?? 'Action failed.'));
        }

        $this->ok();
        return [
            'ok' => true,
            'result' => $result,
            'service' => $service['service'],
        ];
    }

    public function get_order_value(string $orderId): ?array
    {
        $row = $this->get_order_row($orderId);
        if ($row === null) {
            return null;
        }
        return $this->row_value($row);
    }

    public function get_user_balance_usd(int $userId): string
    {
        $balance = Sogerien::Users()->get_balance_amount($userId, 'USD');
        if ($balance === null) {
            return '0.00';
        }
        return $this->to_money($balance);
    }

    private function get_order_row(string $orderId): ?array
    {
        return $this->get_single_row('proxy_order', 'order_id', $orderId);
    }

    private function get_payment_row(string $paymentId): ?array
    {
        return $this->get_single_row('payment', 'payment_id', $paymentId);
    }

    private function find_order_by_session_id(string $sessionId): ?array
    {
        $sql = "
            SELECT id, name, table_index, table_value, status, created_at, updated_at
            FROM sogerien
            WHERE table_name = 'proxy_order'
              AND status <> 'delete'
              AND table_value->>'stripe_session_id' = :session_id
            ORDER BY id DESC
            LIMIT 1;
        ";
        $res = $this->db_query($sql, ['session_id' => $sessionId]);
        if (($res['result'] ?? false) !== true) {
            return null;
        }
        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
            return null;
        }
        return $rows[0];
    }

    private function get_single_row(string $tableName, string $indexKey, string $indexValue): ?array
    {
        $sql = "
            SELECT id, name, table_index, table_value, status, created_at, updated_at
            FROM sogerien
            WHERE table_name = :table_name
              AND status <> 'delete'
              AND jsonb_extract_path_text(table_index, :index_key) = :index_value
            ORDER BY id DESC
            LIMIT 1;
        ";
        $res = $this->db_query($sql, [
            'table_name' => $tableName,
            'index_key' => $indexKey,
            'index_value' => $indexValue,
        ]);
        if (($res['result'] ?? false) !== true) {
            return null;
        }
        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
            return null;
        }
        return $rows[0];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function row_value(array $row): array
    {
        $value = $row['table_value'] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string,mixed> $tableIndex
     * @param array<string,mixed> $tableValue
     */
    private function insert_row(string $tableName, string $name, array $tableIndex, array $tableValue): int
    {
        $sql = "
            INSERT INTO sogerien (table_name, name, table_index, table_value, status, created_at, updated_at)
            VALUES (:table_name, :name, :table_index::jsonb, :table_value::jsonb, 'actual', now(), now())
            RETURNING id;
        ";
        $res = $this->db_query($sql, [
            'table_name' => $tableName,
            'name' => $name,
            'table_index' => $this->encode_json($tableIndex),
            'table_value' => $this->encode_json($tableValue),
        ]);
        if (($res['result'] ?? false) !== true) {
            return 0;
        }
        $rows = $res['rows'] ?? [];
        if (!is_array($rows) || !isset($rows[0]['id'])) {
            return 0;
        }
        return (int)$rows[0]['id'];
    }

    /**
     * @param array<string,mixed> $tableValue
     */
    private function update_row_value(int $rowId, array $tableValue): bool
    {
        $sql = "
            UPDATE sogerien
            SET table_value = :table_value::jsonb,
                updated_at = now()
            WHERE id = :id;
        ";
        $res = $this->db_query($sql, [
            'id' => $rowId,
            'table_value' => $this->encode_json($tableValue),
        ]);
        return ($res['result'] ?? false) === true;
    }

    /**
     * @param array<int,string> $requiredIds
     * @return array<string,array<string,mixed>>
     */
    private function load_catalog_map(array $requiredIds = []): array
    {
        Sogerien::ProxyCatalogCache()->refresh_cyberyozh_cache(200);
        Sogerien::ProxyCatalogCache()->refresh_infatica_cache(200);
        $map = $this->read_merged_catalog_map();
        if ($map === []) {
            return [];
        }

        if ($requiredIds !== [] && $this->catalog_has_missing_ids($map, $requiredIds)) {
            Sogerien::ProxyCatalogCache()->refresh_cyberyozh_cache($this->checkout_catalog_fallback_limit);
            Sogerien::ProxyCatalogCache()->refresh_infatica_cache($this->checkout_catalog_fallback_limit);
            $map = $this->read_merged_catalog_map();
            if ($map === []) {
                return [];
            }
        }

        $this->ok();
        return $map;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function read_merged_catalog_map(): array
    {
        $cacheFile = Sogerien::ProxyCatalogCache()->merged_cache_file;
        $updatedAt = Sogerien::Cache()->get_last_update($cacheFile);
        if ($updatedAt <= 0) {
            $this->fail('Merged proxy cache timestamp is missing.');
            return [];
        }

        $payload = Sogerien::Cache()->load($cacheFile, $updatedAt);
        if (!is_array($payload) || ($payload['ok'] ?? false) !== true) {
            $this->fail('Merged proxy cache is invalid.');
            return [];
        }

        $rows = $payload['data']['rows'] ?? [];
        if (!is_array($rows)) {
            $this->fail('Merged proxy rows are missing.');
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $map[$id] = $row;
        }

        return $map;
    }

    /**
     * @param array<string,array<string,mixed>> $catalog
     * @param array<int,string> $requiredIds
     */
    private function catalog_has_missing_ids(array $catalog, array $requiredIds): bool
    {
        foreach ($requiredIds as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            if (!isset($catalog[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function check_vendor_support(array $row): array
    {
        $api = strtolower(trim((string)($row['API'] ?? '')));
        $category = strtolower(trim((string)($row['proxy_category'] ?? $row['proxy_api_type'] ?? '')));
        if ($api === 'cyberyozh') {
            return [
                'ok' => true,
                'api' => 'cyberyozh',
                'vendor_flow' => 'cyberyozh_buy_proxies',
                'vendor_category' => $category,
                'label' => 'CyberYozh',
            ];
        }

        if ($api === 'infaticaio' || $api === 'infatica_io') {
            if ($category === 'dc') {
                $category = 'isp';
            }
            if ($category === 'isp') {
                return [
                    'ok' => true,
                    'api' => 'infatica_io',
                    'vendor_flow' => 'infatica_isp_package_create',
                    'vendor_category' => 'isp',
                    'label' => 'Infatica ISP',
                ];
            }

            return [
                'ok' => false,
                'error' => 'Automatic checkout is not connected yet for Infatica ' . ($category !== '' ? strtoupper($category) : 'package') . '.',
            ];
        }

        return [
            'ok' => false,
            'error' => 'Automatic checkout is not connected for provider ' . ($api !== '' ? $api : 'unknown') . '.',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<int,array<string,mixed>> $buyItems
     * @param array<int,array<string,mixed>> $beforeHistory
     * @return array<string,mixed>
     */
    private function provision_vendor_services(array $items, array $buyItems, array $beforeHistory, string $orderId): array
    {
        $provider = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemProvider = trim((string)($item['api'] ?? ''));
            if ($itemProvider === '') {
                continue;
            }
            if ($provider === '') {
                $provider = $itemProvider;
                continue;
            }
            if ($provider !== $itemProvider) {
                return [
                    'ok' => false,
                    'error' => 'Mixed vendors in one order are not supported yet. Split cart by provider.',
                ];
            }
        }

        if ($provider === 'cyberyozh') {
            $buyResp = Sogerien::API()->Cyberyozh()->buy_proxies($buyItems);
            if (($buyResp['ok'] ?? false) !== true) {
                $vendorError = trim((string)($buyResp['error'] ?? ''));
                if ($vendorError === '') {
                    $vendorError = trim(Sogerien::API()->Cyberyozh()->error);
                }
                if ($vendorError === '') {
                    $vendorError = 'CyberYozh purchase failed.';
                }
                return [
                    'ok' => false,
                    'error' => $vendorError,
                    'vendor_response' => $buyResp,
                ];
            }

            $afterHistory = $this->fetch_history_rows();
            return [
                'ok' => true,
                'vendor_response' => $buyResp,
                'services' => $this->extract_new_services($items, $beforeHistory, $afterHistory, $orderId),
            ];
        }

        if ($provider === 'infatica_io') {
            return $this->provision_infatica_services($items, $orderId);
        }

        return [
            'ok' => false,
            'error' => 'Unsupported provider in order.',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function provision_infatica_services(array $items, string $orderId): array
    {
        $responses = [];
        $services = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $country = strtoupper(trim((string)($item['location_country_code'] ?? '')));
            $category = strtolower(trim((string)($item['vendor_category'] ?? $item['proxy_category'] ?? '')));
            if ($category === 'dc') {
                $category = 'isp';
            }
            if ($category !== 'isp') {
                return [
                    'ok' => false,
                    'error' => 'Infatica automatic provisioning is connected only for ISP/DC rows.',
                    'vendor_response' => $responses,
                ];
            }
            if ($country === '' || strlen($country) !== 2) {
                return [
                    'ok' => false,
                    'error' => 'Infatica ISP row does not have valid country code.',
                    'vendor_response' => $responses,
                ];
            }

            $createResp = Sogerien::API()->InfaticaIo()->isp_package_create($country, 1);
            $responses[] = [
                'step' => 'isp_package_create',
                'item_index' => $index,
                'response' => $createResp,
            ];
            if (!is_array($createResp)) {
                return [
                    'ok' => false,
                    'error' => Sogerien::API()->InfaticaIo()->error !== '' ? Sogerien::API()->InfaticaIo()->error : 'Infatica ISP package create failed.',
                    'vendor_response' => $responses,
                ];
            }

            $packageKey = $this->extract_first_string($createResp, ['package_key', 'key', 'id', 'package', 'pid']);
            $infoResp = null;
            if ($packageKey !== '') {
                $infoResp = Sogerien::API()->InfaticaIo()->isp_package_info($packageKey);
                $responses[] = [
                    'step' => 'isp_package_info',
                    'item_index' => $index,
                    'package_key' => $packageKey,
                    'response' => $infoResp,
                ];
            }

            $serviceSource = is_array($infoResp) ? $infoResp : $createResp;
            $host = $this->extract_first_string($serviceSource, ['connection_host', 'host', 'ip', 'proxy_host', 'server', 'hostname']);
            $port = $this->extract_first_string($serviceSource, ['connection_port', 'port', 'proxy_port']);
            $login = $this->extract_first_string($serviceSource, ['connection_login', 'login', 'username', 'user']);
            $password = $this->extract_first_string($serviceSource, ['connection_password', 'password', 'pass']);
            $expiresAt = $this->extract_first_string($serviceSource, ['access_expires_at', 'expired_at', 'expires_at']);

            $services[] = [
                'service_id' => $orderId . '-infatica-' . (string)$index,
                'order_id' => $orderId,
                'title' => (string)($item['title'] ?? 'Infatica ISP'),
                'country' => $country,
                'price_usd' => (string)($item['price_usd'] ?? '0.00'),
                'expires_at' => $expiresAt,
                'status' => 'active',
                'connection_host' => $host,
                'connection_port' => $port,
                'connection_login' => $login,
                'connection_password' => $password,
                'auto_renew_request' => (bool)($item['auto_renew'] ?? false),
                'vendor_history_id' => $packageKey,
                'vendor_package_key' => $packageKey,
                'proxy_category' => $category,
                'access_type' => (string)($item['access_type'] ?? 'private'),
                'system_status' => ($host !== '' || $login !== '') ? 'fulfilled' : 'vendor_paid_missing_credentials',
                'raw' => $serviceSource,
            ];
        }

        return [
            'ok' => true,
            'vendor_response' => $responses,
            'services' => $services,
        ];
    }

    private function extract_first_string(mixed $source, array $keys): string
    {
        if (!is_array($source)) {
            return '';
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && !is_array($source[$key]) && !is_object($source[$key])) {
                $value = trim((string)$source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach ($source as $value) {
            $found = $this->extract_first_string($value, $keys);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetch_history_rows(): array
    {
        $resp = Sogerien::API()->Cyberyozh()->get_proxy_history(1, 100);
        $rows = $resp['data']['results'] ?? $resp['results'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<int,array<string,mixed>> $beforeHistory
     * @param array<int,array<string,mixed>> $afterHistory
     * @return array<int,array<string,mixed>>
     */
    private function extract_new_services(array $items, array $beforeHistory, array $afterHistory, string $orderId): array
    {
        $beforeIds = [];
        foreach ($beforeHistory as $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id !== '') {
                $beforeIds[$id] = true;
            }
        }

        $candidateRows = [];
        foreach ($afterHistory as $row) {
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '' || isset($beforeIds[$id])) {
                continue;
            }
            $candidateRows[] = $row;
        }

        usort($candidateRows, static function (array $a, array $b): int {
            return strcmp((string)($b['access_starts_at'] ?? ''), (string)($a['access_starts_at'] ?? ''));
        });

        $services = [];
        foreach (array_values($items) as $index => $item) {
            $history = $candidateRows[$index] ?? [];
            if (!is_array($history)) {
                $history = [];
            }
            $services[] = [
                'service_id' => $orderId . '-svc-' . (string)$index,
                'vendor_history_id' => (string)($history['id'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'country' => (string)($history['geoip']['countryCode2'] ?? ($item['location_country_code'] ?? '')),
                'price_usd' => (string)($item['price_usd'] ?? ''),
                'expires_at' => (string)($history['access_expires_at'] ?? ''),
                'status' => (string)($history['system_status'] ?? 'pending_vendor_sync'),
                'system_status' => (string)($history['system_status'] ?? 'pending_vendor_sync'),
                'connection_url' => (string)($history['url'] ?? ''),
                'connection_host' => (string)($history['connection_host'] ?? ''),
                'connection_port' => (string)($history['connection_port'] ?? ''),
                'connection_login' => (string)($history['connection_login'] ?? ''),
                'connection_password' => (string)($history['connection_password'] ?? ''),
                'auto_renew_request' => (bool)($history['auto_renew_request'] ?? ($item['auto_renew'] ?? false)),
                'proxy_category' => (string)($history['proxy_category'] ?? ($item['proxy_category'] ?? '')),
                'access_type' => (string)($history['access_type'] ?? ($item['access_type'] ?? '')),
                'public_ipaddress' => (string)($history['public_ipaddress'] ?? ''),
                'ovpn_config_link' => (string)($history['ovpn_config_link'] ?? ''),
                'xray_settings_str' => (string)($history['xray_settings_str'] ?? ''),
                'traffic_remains' => $history['traffic_remains'] ?? '',
                'raw' => $history,
            ];
        }

        return $services;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_service(int $userId, string $serviceId): ?array
    {
        $sql = "
            SELECT id, name, table_index, table_value, status, created_at, updated_at
            FROM sogerien
            WHERE table_name = 'proxy_order'
              AND status <> 'delete'
              AND table_index->>'user_id' = :user_id
            ORDER BY created_at DESC;
        ";
        $res = $this->db_query($sql, ['user_id' => (string)$userId]);
        if (($res['result'] ?? false) !== true) {
            return null;
        }

        $rows = $res['rows'] ?? [];
        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $orderValue = $this->row_value($row);
            $services = $orderValue['services'] ?? [];
            if (!is_array($services)) {
                continue;
            }
            foreach ($services as $index => $service) {
                if (!is_array($service)) {
                    continue;
                }
                if ((string)($service['service_id'] ?? '') !== $serviceId) {
                    continue;
                }
                return [
                    'order_row' => $row,
                    'order_value' => $orderValue,
                    'service_index' => (int)$index,
                    'service' => $service,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function db_query(string $sql, array $params = []): array
    {
        $json = Sogerien::DbController()->sql_request($this->db_alias, [
            'sql' => $sql,
            'params' => $params,
        ]);

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->fail('Invalid DB response.');
            return ['result' => false];
        }
        if (($data['result'] ?? false) !== true) {
            $message = (string)($data['error']['message'] ?? 'DB error.');
            $this->fail($message);
        } else {
            $this->ok();
        }
        return $data;
    }

    /**
     * @param array<string,mixed> $value
     */
    private function encode_json(array $value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (!is_string($json)) {
            throw new RuntimeException('Failed to encode JSON.');
        }
        return $json;
    }

    private function to_money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function money_to_cents(string $amount): int
    {
        $normalized = str_replace(',', '.', trim($amount));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0;
        }
        return (int)round(((float)$normalized) * 100);
    }

    private function uuid_v4(): string
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

    /**
     * @return array<string,mixed>
     */
    private function fail_result(string $message): array
    {
        $this->fail($message);
        return [
            'ok' => false,
            'error' => $message,
        ];
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $message): void
    {
        $this->status = $message === '';
        $this->error = $message;
    }
}
