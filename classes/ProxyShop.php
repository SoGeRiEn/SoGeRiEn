<?php
declare(strict_types=1);

final class ProxyShop
{
    use SogerienClassHelp;

    private string $db_alias = 'front';

    public function init_db_alias(string $db_alias): void
    {
        $db_alias = trim($db_alias);
        $this->db_alias = $db_alias !== '' ? $db_alias : 'front';
        $this->ensure_storage();
    }

    /**
     * @param array<int,mixed> $cart
     * @return array<string,mixed>
     */
    public function verify_cart_items(array $cart): array
    {
        $items = [];
        $total = 0.0;
        foreach ($cart as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }
            $staticItem = $this->verify_infatica_tariff_item($rawItem);
            if (is_array($staticItem)) {
                $price = $this->money_float($staticItem['price_usd'] ?? 0);
                $staticItem['auto_renew'] = !empty($rawItem['auto_renew']);
                $items[] = $staticItem;
                $total += $price;
                continue;
            }

            $staticIpItem = $this->verify_static_ip_item($rawItem);
            if (is_array($staticIpItem)) {
                $price = $this->money_float($staticIpItem['price_usd'] ?? 0);
                $staticIpItem['auto_renew'] = !empty($rawItem['auto_renew']);
                $items[] = $staticIpItem;
                $total += $price;
                continue;
            }

            $scraperItem = $this->verify_scraper_item($rawItem);
            if (is_array($scraperItem)) {
                $price = $this->money_float($scraperItem['price_usd'] ?? 0);
                $scraperItem['auto_renew'] = !empty($rawItem['auto_renew']);
                $items[] = $scraperItem;
                $total += $price;
                continue;
            }

            $id = $this->str($rawItem['id'] ?? '');
            if ($id === '') {
                return ['ok' => false, 'error' => 'Catalog item is not available: ' . $id];
            }
            return ['ok' => false, 'error' => 'Legacy catalog checkout is disabled: ' . $id];
        }

        if ($items === []) {
            return ['ok' => false, 'error' => 'Cart is empty.'];
        }

        $amount = number_format($total, 2, '.', '');
        return [
            'ok' => true,
            'items' => $items,
            'amount_usd' => $amount,
            'total_cents' => (int)round($total * 100),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function proxy_product_plans(string $category): array
    {
        $category = strtolower($this->str($category));
        $plans = [];
        if (in_array($category, ['mobile', 'residential', 'residential_ipv6', 'dc_shared'], true)) {
            $api = Sogerien::API()->InfaticaIo()->Catalog();
            $trial = $api->trial_retail_pricing();
            $trialCost = $api->trial_cost_pricing();
            if (isset($trial[$category]) && is_array($trial[$category])) {
                $traffic = (float)($trial[$category]['traffic'] ?? 0);
                $price = (float)($trial[$category]['price'] ?? 0);
                if ($traffic > 0.0 && $price > 0.0) {
                    $costOffer = isset($trialCost[$category]) && is_array($trialCost[$category]) ? $trialCost[$category] : [];
                    $cost = (float)($costOffer['price'] ?? 0);
                    $plans[] = [
                        'id' => $category . '-trial-gb' . $this->compact_number($traffic),
                        'category' => $category,
                        'traffic_gb' => $this->compact_number($traffic),
                        'days' => (string)((int)($trial[$category]['days'] ?? 30)),
                        'price_usd' => number_format($price, 2, '.', ''),
                        'price_per_gb' => number_format($price / max(1.0, $traffic), 2, '.', ''),
                        'provider_cost_usd' => $cost > 0.0 ? number_format($cost, 2, '.', '') : '',
                        'is_trial' => true,
                    ];
                }
            }

            $pricing = $api->retail_pricing();
            $costPricing = $api->cost_pricing();
            $categoryPricing = isset($pricing[$category]) && is_array($pricing[$category]) ? $pricing[$category] : [];
            ksort($categoryPricing, SORT_NUMERIC);
            foreach ($categoryPricing as $traffic => $pricePerGb) {
                $trafficFloat = (float)$traffic;
                $pricePerGbFloat = (float)$pricePerGb;
                if ($trafficFloat <= 0.0 || $pricePerGbFloat <= 0.0) {
                    continue;
                }
                $costPerGb = isset($costPricing[$category][(string)(int)$trafficFloat]) ? (float)$costPricing[$category][(string)(int)$trafficFloat] : 0.0;
                $price = $trafficFloat * $pricePerGbFloat;
                $cost = $costPerGb > 0.0 ? $trafficFloat * $costPerGb : 0.0;
                $plans[] = [
                    'id' => $category . '-gb' . $this->compact_number($trafficFloat),
                    'category' => $category,
                    'traffic_gb' => $this->compact_number($trafficFloat),
                    'days' => '364',
                    'price_usd' => number_format($price, 2, '.', ''),
                    'price_per_gb' => number_format($pricePerGbFloat, 2, '.', ''),
                    'provider_cost_usd' => $cost > 0.0 ? number_format($cost, 2, '.', '') : '',
                    'is_trial' => false,
                ];
            }
            return $plans;
        }

        if (in_array($category, ['isp', 'dc'], true)) {
            $api = Sogerien::API()->InfaticaIo()->Catalog();
            $trial = $api->trial_retail_pricing();
            if (isset($trial[$category]) && is_array($trial[$category])) {
                $ipCount = (int)($trial[$category]['traffic'] ?? 0);
                $price = (float)($trial[$category]['price'] ?? 0);
                if ($ipCount > 0 && $price > 0.0) {
                    $plans[] = [
                        'id' => $category . '-trial-ip' . (string)$ipCount,
                        'category' => $category,
                        'ip_count' => (string)$ipCount,
                        'days' => (string)((int)($trial[$category]['days'] ?? 30)),
                        'price_per_ip' => number_format($price / max(1, $ipCount), 2, '.', ''),
                        'price_usd' => number_format($price, 2, '.', ''),
                        'is_trial' => true,
                    ];
                }
            }
            $pricing = $api->retail_pricing();
            $tiers = isset($pricing[$category]) && is_array($pricing[$category]) ? $pricing[$category] : [];
            ksort($tiers, SORT_NUMERIC);
            foreach ($tiers as $ipCount => $pricePerIp) {
                $count = (int)$ipCount;
                $price = (float)$pricePerIp;
                if ($count <= 0 || $price <= 0.0) {
                    continue;
                }
                $plans[] = [
                    'id' => $category . '-ip' . (string)$count,
                    'category' => $category,
                    'ip_count' => (string)$count,
                    'days' => '30',
                    'price_per_ip' => number_format($price, 2, '.', ''),
                    'price_usd' => number_format($count * $price, 2, '.', ''),
                ];
            }
        }

        return $plans;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function scraper_pricing_plans(): array
    {
        return [
            [
                'id' => 'basic_67000',
                'plan' => 'basic',
                'title' => 'Basic scrape',
                'requests_limit' => 67000,
                'render_requests_limit' => 0,
                'price_per_1000' => '0.28',
                'price_usd' => '19.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'basic_500000',
                'plan' => 'basic',
                'title' => 'Basic scrape',
                'requests_limit' => 500000,
                'render_requests_limit' => 0,
                'price_per_1000' => '0.13',
                'price_usd' => '65.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'basic_1500000',
                'plan' => 'basic',
                'title' => 'Basic scrape',
                'requests_limit' => 1500000,
                'render_requests_limit' => 0,
                'price_per_1000' => '0.11',
                'price_usd' => '165.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'basic_4000000',
                'plan' => 'basic',
                'title' => 'Basic scrape',
                'requests_limit' => 4000000,
                'render_requests_limit' => 0,
                'price_per_1000' => '0.10',
                'price_usd' => '400.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'js_render_23000',
                'plan' => 'js_render',
                'title' => 'JS render',
                'requests_limit' => 23000,
                'render_requests_limit' => 23000,
                'price_per_1000' => '0.79',
                'price_usd' => '18.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'js_render_82000',
                'plan' => 'js_render',
                'title' => 'JS render',
                'requests_limit' => 82000,
                'render_requests_limit' => 82000,
                'price_per_1000' => '0.75',
                'price_usd' => '62.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'serp_100000',
                'plan' => 'serp',
                'title' => 'SERP',
                'requests_limit' => 100000,
                'render_requests_limit' => 0,
                'price_per_1000' => '0.45',
                'price_usd' => '45.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
            [
                'id' => 'ai_search_50000',
                'plan' => 'ai_search',
                'title' => 'AI/search scraping',
                'requests_limit' => 50000,
                'render_requests_limit' => 0,
                'price_per_1000' => '0.90',
                'price_usd' => '45.00',
                'overage_policy' => 'Stop at quota',
                'success_only_billing' => true,
                'days' => 364,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $rawItem
     * @return array<string,mixed>|null
     */
    private function verify_infatica_tariff_item(array $rawItem): ?array
    {
        $category = strtolower($this->str($rawItem['category'] ?? $rawItem['proxy_category'] ?? ''));
        if (!in_array($category, ['mobile', 'residential', 'residential_ipv6', 'dc_shared'], true)) {
            return null;
        }
        $traffic = $this->money_float($rawItem['traffic'] ?? $rawItem['traffic_limitation'] ?? 0);
        $days = (int)$this->money_float($rawItem['days'] ?? 0);
        $country = $this->first_country($rawItem['country'] ?? $rawItem['location_country_code'] ?? '');
        if ($category === '' || $traffic <= 0.0 || $days <= 0 || $country === '') {
            return null;
        }

        $api = Sogerien::API()->InfaticaIo()->Catalog();
        $trial = $api->trial_retail_pricing();
        $trialCost = $api->trial_cost_pricing();
        $trialOffer = isset($trial[$category]) && is_array($trial[$category]) ? $trial[$category] : null;
        $isTrial = is_array($trialOffer)
            && $days === (int)($trialOffer['days'] ?? 0)
            && abs($traffic - (float)($trialOffer['traffic'] ?? 0)) < 0.001;
        $price = 0.0;
        $pricePerGb = 0.0;
        $cost = 0.0;
        $costPerGb = 0.0;
        if ($isTrial) {
            $price = (float)$trialOffer['price'];
            $pricePerGb = $price / max(1.0, (float)$trialOffer['traffic']);
            $costOffer = $trialCost[$category] ?? null;
            if (is_array($costOffer) && abs($traffic - (float)$costOffer['traffic']) < 0.001) {
                $cost = (float)$costOffer['price'];
                $costPerGb = $cost / max(1.0, (float)$costOffer['traffic']);
            }
        } elseif ($days === 364) {
            $pricing = $api->retail_pricing();
            $costPricing = $api->cost_pricing();
            $categoryPricing = isset($pricing[$category]) && is_array($pricing[$category]) ? $pricing[$category] : [];
            $trafficKey = (string)(int)$traffic;
            if (!isset($categoryPricing[$trafficKey])) {
                return null;
            }
            $pricePerGb = (float)$categoryPricing[$trafficKey];
            $price = $traffic * $pricePerGb;
            if (isset($costPricing[$category][$trafficKey])) {
                $costPerGb = (float)$costPricing[$category][$trafficKey];
                $cost = $traffic * $costPerGb;
            }
        } else {
            return null;
        }

        if ($price <= 0.0) {
            return null;
        }

        $countryTitle = $this->str($rawItem['title'] ?? '');
        if ($countryTitle === '') {
            $countryTitle = $country;
        }
        $trafficText = rtrim(rtrim(number_format($traffic, 2, '.', ''), '0'), '.');
        $suffix = $isTrial ? 'trial-gb' . $trafficText : 'gb' . $trafficText;

        return [
            'id' => $category . '-' . $country . '-' . $suffix,
            'API' => 'InfaticaIo',
            'title' => $countryTitle,
            'location_country_code' => $country,
            'price_usd' => number_format($price, 2, '.', ''),
            'price_per_day' => number_format($price / max(1, $days), 4, '.', ''),
            'days' => (string)$days,
            'proxy_category' => $category,
            'stock_status' => 'in_stock',
            'traffic_limitation' => $trafficText,
            'price_per_gb' => number_format($pricePerGb, 2, '.', ''),
            'provider_unit_price_usd' => $costPerGb > 0.0 ? number_format($costPerGb, 4, '.', '') : '',
            'provider_cost_usd' => $cost > 0.0 ? number_format($cost, 2, '.', '') : '',
            'profit_usd' => $cost > 0.0 ? number_format($price - $cost, 2, '.', '') : '',
            'is_auto_renewal_possible' => $isTrial ? '0' : '1',
            'is_trial' => $isTrial ? '1' : '0',
        ];
    }

    /**
     * @param array<string,mixed> $rawItem
     * @return array<string,mixed>|null
     */
    private function verify_static_ip_item(array $rawItem): ?array
    {
        $category = strtolower($this->str($rawItem['category'] ?? $rawItem['proxy_category'] ?? ''));
        if (!in_array($category, ['isp', 'dc'], true)) {
            return null;
        }

        $country = $this->first_country($rawItem['country'] ?? $rawItem['location_country_code'] ?? '');
        $ipCount = (int)$this->money_float($rawItem['ip_count'] ?? $rawItem['count'] ?? 0);
        $days = (int)$this->money_float($rawItem['days'] ?? 30);
        if ($country === '' || $ipCount <= 0 || !in_array($days, [30, 90, 180, 364], true)) {
            return null;
        }

        $api = Sogerien::API()->InfaticaIo()->Catalog();
        $trial = $api->trial_retail_pricing();
        $trialCost = $api->trial_cost_pricing();
        $trialOffer = isset($trial[$category]) && is_array($trial[$category]) ? $trial[$category] : null;
        $isTrial = is_array($trialOffer)
            && $days === (int)($trialOffer['days'] ?? 0)
            && $ipCount === (int)($trialOffer['traffic'] ?? 0);
        if ($isTrial) {
            $trialPrice = (float)$trialOffer['price'];
            $cost = isset($trialCost[$category]) && is_array($trialCost[$category])
                ? (float)($trialCost[$category]['price'] ?? 0)
                : 0.0;
            $price = [
                'price_per_ip' => number_format($trialPrice / max(1, $ipCount), 2, '.', ''),
                'price_usd' => number_format($trialPrice, 2, '.', ''),
                'provider_unit_price_usd' => $cost > 0.0 ? number_format($cost / max(1, $ipCount), 2, '.', '') : '',
                'provider_cost_usd' => $cost > 0.0 ? number_format($cost, 2, '.', '') : '',
                'profit_usd' => $cost > 0.0 ? number_format($trialPrice - $cost, 2, '.', '') : '',
            ];
        } else {
            $price = $this->price_static_ip_item($category, $ipCount, $days);
            if ($price === null) {
                return null;
            }
        }
        $title = $category === 'dc' ? 'Dedicated DC proxy ' : 'ISP proxy ';

        return [
            'id' => $category . '-' . $country . '-ip' . (string)$ipCount . '-d' . (string)$days,
            'API' => 'InfaticaIo',
            'title' => $title . $country,
            'location_country_code' => $country,
            'proxy_category' => $category,
            'provider_pool_category' => $category,
            'stock_status' => 'in_stock',
            'ip_count' => (string)$ipCount,
            'days' => (string)$days,
            'price_per_ip' => $price['price_per_ip'],
            'price_usd' => $price['price_usd'],
            'provider_unit_price_usd' => $price['provider_unit_price_usd'],
            'provider_cost_usd' => $price['provider_cost_usd'],
            'profit_usd' => $price['profit_usd'],
            'is_auto_renewal_possible' => $isTrial ? '0' : '1',
            'is_trial' => $isTrial ? '1' : '0',
        ];
    }

    /**
     * @param array<string,mixed> $rawItem
     * @return array<string,mixed>|null
     */
    private function verify_scraper_item(array $rawItem): ?array
    {
        $category = strtolower($this->str($rawItem['category'] ?? $rawItem['proxy_category'] ?? ''));
        if ($category !== 'scraper') {
            return null;
        }

        $planId = $this->str($rawItem['plan_id'] ?? $rawItem['id'] ?? '');
        if ($planId === '') {
            return null;
        }

        $plan = null;
        foreach ($this->scraper_pricing_plans() as $candidate) {
            if ($this->str($candidate['id'] ?? '') === $planId) {
                $plan = $candidate;
                break;
            }
        }
        if (!is_array($plan)) {
            return null;
        }

        return [
            'id' => 'scraper-' . $this->str($plan['id'] ?? ''),
            'API' => 'InfaticaIo',
            'title' => $this->str($plan['title'] ?? 'Scraper API'),
            'proxy_category' => 'scraper',
            'provider_pool_category' => 'scraper',
            'plan_id' => $this->str($plan['id'] ?? ''),
            'plan' => $this->str($plan['plan'] ?? ''),
            'requests_limit' => (string)((int)($plan['requests_limit'] ?? 0)),
            'render_requests_limit' => (string)((int)($plan['render_requests_limit'] ?? 0)),
            'requests_used' => '0',
            'requests_left' => (string)((int)($plan['requests_limit'] ?? 0)),
            'success_only_billing' => !empty($plan['success_only_billing']) ? '1' : '0',
            'gateway_key_generation' => !empty($rawItem['gateway_key_generation']) ? '1' : '0',
            'days' => (string)((int)($plan['days'] ?? 364)),
            'price_per_1000' => $this->str($plan['price_per_1000'] ?? ''),
            'price_usd' => $this->str($plan['price_usd'] ?? ''),
            'overage_policy' => $this->str($plan['overage_policy'] ?? 'Stop at quota'),
            'is_auto_renewal_possible' => '1',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    public function create_checkout_draft(int $user_id, array $items, string $amount_usd, int $total_cents): array
    {
        if ($user_id <= 0) {
            return ['ok' => false, 'error' => 'User is required.'];
        }
        $trialItems = 0;
        foreach ($items as $item) {
            if (is_array($item) && $this->str($item['is_trial'] ?? '') === '1') {
                $trialItems++;
            }
        }
        if ($trialItems > 1 || ($trialItems === 1 && $this->has_used_trial($user_id))) {
            return ['ok' => false, 'error' => 'Trial package can be ordered only once per account.'];
        }

        $orderId = 'pm_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $paymentId = 'pay_' . bin2hex(random_bytes(8));
        $value = [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'user_id' => $user_id,
            'items' => $items,
            'amount_usd' => $amount_usd,
            'total_cents' => $total_cents,
            'currency' => 'USD',
            'checkout_status' => 'draft',
            'fulfillment_status' => 'pending_payment',
            'created_at' => date('c'),
        ];

        $orderSaved = $this->insert_row('proxy_order', $orderId, 'Proxy order ' . $orderId, $value);
        $paymentSaved = $this->insert_row('payment', $paymentId, 'Payment ' . $paymentId, [
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'user_id' => $user_id,
            'amount_usd' => $amount_usd,
            'total_cents' => $total_cents,
            'currency' => 'USD',
            'status' => 'draft',
            'created_at' => date('c'),
        ]);

        if (!$orderSaved || !$paymentSaved) {
            return ['ok' => false, 'error' => 'Failed to save order before payment.'];
        }

        return ['ok' => true, 'order_id' => $orderId, 'payment_id' => $paymentId];
    }

    public function has_used_trial(int $user_id): bool
    {
        foreach ($this->list_user_services($user_id) as $service) {
            if (is_array($service) && $this->str($service['is_trial'] ?? '') === '1') {
                return true;
            }
        }
        foreach ($this->list_all_orders() as $order) {
            if (!is_array($order) || (int)($order['user_id'] ?? 0) !== $user_id) {
                continue;
            }
            foreach (($order['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemId = $this->str($item['id'] ?? '');
                if ($this->str($item['is_trial'] ?? '') === '1' || str_contains($itemId, '-trial-')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $stripe_session
     */
    public function attach_checkout_session(string $order_id, string $payment_id, array $stripe_session): void
    {
        $order = $this->get_order_value($order_id);
        if (is_array($order)) {
            $order['checkout_status'] = 'stripe_created';
            $order['stripe_session'] = $stripe_session;
            $this->update_json_row('proxy_order', $order_id, $order);
        }
        $payment = $this->load_one('payment', $payment_id);
        if (is_array($payment)) {
            $payment['status'] = 'stripe_created';
            $payment['stripe_session'] = $stripe_session;
            $this->update_json_row('payment', $payment_id, $payment);
        }
    }

    /**
     * @param array<string,mixed> $stripe_session
     * @return array<string,mixed>
     */
    public function finalize_checkout(string $order_id, array $stripe_session): array
    {
        $order = $this->get_order_value($order_id);
        if (!is_array($order)) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if (($order['fulfillment_status'] ?? '') === 'fulfilled' && isset($order['services']) && is_array($order['services'])) {
            return ['ok' => true, 'already_processed' => true, 'services' => $order['services']];
        }

        $items = isset($order['items']) && is_array($order['items']) ? $order['items'] : [];
        $services = [];
        $providerFailed = false;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $service = $this->fulfill_infatica_item((int)($order['user_id'] ?? 0), $order_id, $item);
            if (($service['status'] ?? '') === 'provider_failed') {
                $providerFailed = true;
            }
            $services[] = $service;
        }

        $order['checkout_status'] = 'paid';
        $order['fulfillment_status'] = $providerFailed ? 'provider_failed' : 'fulfilled';
        $order['paid_at'] = date('c');
        $order['stripe_session'] = $stripe_session;
        $order['services'] = $services;
        $this->update_json_row('proxy_order', $order_id, $order);

        foreach ($services as &$service) {
            if ($this->merge_traffic_service_if_possible((int)($order['user_id'] ?? 0), $service)) {
                continue;
            }
            $this->insert_row('proxy_service', (string)$service['service_id'], (string)$service['title'], $service);
        }
        unset($service);

        return ['ok' => true, 'services' => $services];
    }

    /**
     * Charge saved Stripe default card without bringing the client back to Checkout.
     *
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    public function charge_user_default_method(
        int $user_id,
        int $amount_cents,
        string $currency = 'usd',
        string $reason = 'auto_renewal',
        array $metadata = []
    ): array {
        if ($user_id <= 0) {
            return ['ok' => false, 'error' => 'user_id is required'];
        }
        if ($amount_cents <= 0) {
            return ['ok' => false, 'error' => 'amount_cents must be > 0'];
        }

        $currency = strtolower(trim($currency));
        if (preg_match('/^[a-z]{3}$/', $currency) !== 1) {
            return ['ok' => false, 'error' => 'currency must be 3-letter ISO code'];
        }

        $billing = $this->user_billing_profile($user_id);
        if (($billing['ok'] ?? false) !== true) {
            return $billing;
        }

        $attemptId = 'pat_' . date('YmdHis') . '_' . bin2hex(random_bytes(5));
        $idempotencyKey = $this->str($metadata['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            $idempotencyKey = 'charge_' . $attemptId;
        }

        $baseAttempt = [
            'attempt_id' => $attemptId,
            'user_id' => $user_id,
            'stripe_customer_id' => $billing['stripe_customer_id'],
            'payment_method_id' => $billing['payment_method_id'],
            'amount_cents' => $amount_cents,
            'amount_usd' => number_format($amount_cents / 100, 2, '.', ''),
            'currency' => strtoupper($currency),
            'reason' => $reason,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $this->insert_row('payment_attempt', $attemptId, 'Payment attempt ' . $attemptId, $baseAttempt);

        $stripe = Sogerien::API()->Stripe();
        $stripe->debug_enabled = false;
        $stripe->set_api_key(defined('STRIPE_LIVE_SECRET_KEY_LLC') ? (string)STRIPE_LIVE_SECRET_KEY_LLC : '');

        $stripeMetadata = array_merge($metadata, [
            'attempt_id' => $attemptId,
            'user_id' => (string)$user_id,
            'reason' => $reason,
            'source' => 'proxymint_off_session',
        ]);

        $paymentIntent = $stripe->create_payment_intent($amount_cents, $currency, [
            'customer' => $billing['stripe_customer_id'],
            'payment_method' => $billing['payment_method_id'],
            'off_session' => 'true',
            'confirm' => 'true',
            'description' => 'ProxyMint ' . $reason,
            'metadata' => $stripeMetadata,
        ], $idempotencyKey);

        if (!is_array($paymentIntent)) {
            $failedAttempt = $baseAttempt;
            $failedAttempt['status'] = 'failed';
            $failedAttempt['failure_category'] = $this->stripe_failure_category($stripe->last_error_code, $stripe->last_error_decline_code);
            $failedAttempt['stripe_error'] = $this->stripe_error_snapshot($stripe);
            $failedAttempt['updated_at'] = date('c');

            $errorIntentId = $this->payment_intent_id_from_stripe_error($stripe->last_response_raw);
            if ($errorIntentId !== '') {
                $failedAttempt['payment_intent_id'] = $errorIntentId;
            }

            $this->update_json_row('payment_attempt', $attemptId, $failedAttempt);
            return [
                'ok' => false,
                'attempt_id' => $attemptId,
                'payment_intent_id' => $errorIntentId,
                'status' => 'failed',
                'failure_category' => $failedAttempt['failure_category'],
                'error' => $stripe->last_error_message !== '' ? $stripe->last_error_message : ($stripe->error !== '' ? $stripe->error : 'Stripe charge failed'),
                'stripe_error' => $failedAttempt['stripe_error'],
            ];
        }

        $status = $this->str($paymentIntent['status'] ?? '');
        $paymentIntentId = $this->str($paymentIntent['id'] ?? '');
        $attempt = $baseAttempt;
        $attempt['payment_intent_id'] = $paymentIntentId;
        $attempt['status'] = $this->payment_intent_attempt_status($status);
        $attempt['stripe_status'] = $status;
        $attempt['stripe_payment_intent'] = $paymentIntent;
        $attempt['updated_at'] = date('c');
        $this->update_json_row('payment_attempt', $attemptId, $attempt);

        return [
            'ok' => $attempt['status'] === 'succeeded',
            'attempt_id' => $attemptId,
            'payment_intent_id' => $paymentIntentId,
            'status' => $attempt['status'],
            'stripe_status' => $status,
            'payment_intent' => $paymentIntent,
        ];
    }

    /**
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    public function handle_payment_intent_event(array $event): array
    {
        $eventType = $this->str($event['type'] ?? '');
        $object = $event['data']['object'] ?? null;
        if (!is_array($object)) {
            return ['ok' => false, 'error' => 'Stripe event object is missing'];
        }

        $paymentIntentId = $this->str($object['id'] ?? '');
        if ($paymentIntentId === '') {
            return ['ok' => false, 'error' => 'payment_intent_id is missing'];
        }

        $attempt = $this->load_one('payment_attempt', $paymentIntentId);
        if (!is_array($attempt)) {
            return ['ok' => true, 'ignored' => true, 'error' => 'Payment attempt not found'];
        }

        $attempt['payment_intent_id'] = $paymentIntentId;
        $attempt['stripe_status'] = $this->str($object['status'] ?? '');
        $attempt['stripe_payment_intent'] = $object;
        $attempt['stripe_event_id'] = $this->str($event['id'] ?? '');
        $attempt['stripe_event_type'] = $eventType;
        $attempt['updated_at'] = date('c');

        if ($eventType === 'payment_intent.succeeded') {
            $attempt['status'] = 'succeeded';
            $attempt['paid_at'] = date('c');
        } elseif ($eventType === 'payment_intent.payment_failed') {
            $attempt['status'] = 'failed';
            $error = isset($object['last_payment_error']) && is_array($object['last_payment_error']) ? $object['last_payment_error'] : [];
            $attempt['failure_category'] = $this->stripe_failure_category(
                $this->str($error['code'] ?? ''),
                $this->str($error['decline_code'] ?? '')
            );
            $attempt['stripe_error'] = $error;
        } else {
            $attempt['status'] = $this->payment_intent_attempt_status($this->str($object['status'] ?? ''));
        }

        $this->update_json_row('payment_attempt', $paymentIntentId, $attempt);
        return ['ok' => true, 'attempt_id' => $this->str($attempt['attempt_id'] ?? ''), 'status' => $this->str($attempt['status'] ?? '')];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_services(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }
        $resp = $this->sql("
            SELECT table_index, table_value, created_at, updated_at
            FROM sogerien
            WHERE table_name = 'proxy_service'
              AND status <> 'delete'
              AND table_value->>'user_id' = :user_id
            ORDER BY created_at DESC
            LIMIT 1000
        ", ['user_id' => (string)$user_id]);

        $rows = [];
        foreach (($resp['rows'] ?? []) as $row) {
            if (isset($row['table_value']) && is_array($row['table_value'])) {
                $value = $row['table_value'];
                if ($this->str($value['service_id'] ?? '') === '') {
                    $value['service_id'] = $this->normalize_table_index($row['table_index'] ?? '');
                }
                $this->hydrate_provider_traffic_fields($value);
                $rows[] = $value;
            }
        }
        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_all_services(): array
    {
        $resp = $this->sql("
            SELECT table_index, table_value
            FROM sogerien
            WHERE table_name = 'proxy_service'
              AND status <> 'delete'
            ORDER BY updated_at DESC
            LIMIT 5000
        ", []);
        $rows = [];
        foreach (($resp['rows'] ?? []) as $row) {
            if (!isset($row['table_value']) || !is_array($row['table_value'])) {
                continue;
            }
            $value = $row['table_value'];
            if ($this->str($value['service_id'] ?? '') === '') {
                $value['service_id'] = $this->normalize_table_index($row['table_index'] ?? '');
            }
            if ($this->str($value['created_at'] ?? '') === '') {
                $value['created_at'] = $this->str($row['created_at'] ?? '');
            }
            if ($this->str($value['updated_at'] ?? '') === '') {
                $value['updated_at'] = $this->str($row['updated_at'] ?? '');
            }
            $this->hydrate_provider_traffic_fields($value);
            $rows[] = $value;
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $service
     */
    private function hydrate_provider_traffic_fields(array &$service): void
    {
        $info = isset($service['provider_info_response']) && is_array($service['provider_info_response'])
            ? $service['provider_info_response']
            : null;
        if ($info === null) {
            return;
        }

        $snapshot = $this->extract_provider_traffic_snapshot($info, null);
        if ($snapshot['total_gb'] <= 0.0 && $snapshot['used_gb'] <= 0.0 && $snapshot['remaining_gb'] <= 0.0) {
            return;
        }

        $storedUsed = $this->money_float($service['traffic_used_gb'] ?? 0);
        $storedTotal = $this->money_float($service['traffic_total_gb'] ?? 0);
        $storedLeft = $this->money_float($service['traffic_remaining_gb'] ?? 0);
        if ($storedUsed > 0.0 && abs($storedUsed - $snapshot['used_gb']) < 0.01) {
            return;
        }

        if ($storedUsed <= 0.0 || $storedTotal <= 0.0 || abs($storedLeft - $snapshot['remaining_gb']) >= 0.01) {
            $service['traffic_total_gb'] = number_format($snapshot['total_gb'], 2, '.', '');
            $service['traffic_used_gb'] = number_format($snapshot['used_gb'], 2, '.', '');
            $service['traffic_remaining_gb'] = number_format($snapshot['remaining_gb'], 2, '.', '');
            $service['traffic_remains'] = $service['traffic_remaining_gb'] . ' GB';
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_all_orders(): array
    {
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'proxy_order'
              AND status <> 'delete'
            ORDER BY created_at DESC
            LIMIT 5000
        ", []);
        return $this->extract_value_rows($resp);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_all_payments(): array
    {
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'payment'
              AND status <> 'delete'
            ORDER BY created_at DESC
            LIMIT 5000
        ", []);
        return $this->extract_value_rows($resp);
    }

    /**
     * @return array{revenue:float,cost:float,profit:float,sold_gb:float,used_gb:float,left_gb:float,services:int}
     */
    public function reseller_totals(): array
    {
        $services = $this->list_all_services();
        $totals = [
            'revenue' => 0.0,
            'cost' => 0.0,
            'profit' => 0.0,
            'sold_gb' => 0.0,
            'used_gb' => 0.0,
            'left_gb' => 0.0,
            'services' => count($services),
        ];
        foreach ($services as $service) {
            $totals['revenue'] += $this->money_float($service['price_usd'] ?? 0);
            $totals['cost'] += $this->money_float($service['provider_cost_usd'] ?? 0);
            $totals['profit'] += $this->money_float($service['profit_usd'] ?? 0);
            $totals['sold_gb'] += $this->money_float($service['traffic_total_gb'] ?? 0);
            $totals['used_gb'] += $this->money_float($service['traffic_used_gb'] ?? 0);
            $totals['left_gb'] += $this->money_float($service['traffic_remaining_gb'] ?? 0);
        }
        if ($totals['profit'] === 0.0 && $totals['revenue'] > 0.0 && $totals['cost'] > 0.0) {
            $totals['profit'] = $totals['revenue'] - $totals['cost'];
        }
        return $totals;
    }

    /**
     * @param array<int,array<string,mixed>> $services
     * @return array<int,array<string,mixed>>
     */
    public function reseller_provider_inventory(array $services = [], bool $refreshProvider = false): array
    {
        if ($services === []) {
            $services = $this->list_all_services();
        }

        $client = [];
        foreach (['mobile', 'residential', 'residential_ipv6', 'isp', 'dc', 'scraper'] as $category) {
            $client[$category] = ['sold_gb' => 0.0, 'used_gb' => 0.0, 'left_gb' => 0.0, 'services' => 0];
        }

        foreach ($services as $service) {
            $category = $this->service_category($service);
            if (!isset($client[$category])) {
                $client[$category] = ['sold_gb' => 0.0, 'used_gb' => 0.0, 'left_gb' => 0.0, 'services' => 0];
            }
            $sold = $this->money_float($service['traffic_total_gb'] ?? 0);
            $used = $this->money_float($service['traffic_used_gb'] ?? 0);
            $left = $this->money_float($service['traffic_remaining_gb'] ?? '');
            if ($left <= 0.0 && $sold > 0.0) {
                $left = max(0.0, $sold - $used);
            }

            $client[$category]['sold_gb'] += $sold;
            $client[$category]['used_gb'] += $used;
            $client[$category]['left_gb'] += $left;
            $client[$category]['services']++;
        }

        $labels = [
            'mobile' => 'Mobile',
            'residential' => 'Residential',
            'residential_ipv6' => 'Residential IPv6',
            'isp' => 'ISP',
            'dc' => 'Dedicated DC',
            'scraper' => 'Scraper',
        ];

        $rows = [];
        foreach ($labels as $category => $label) {
            $provider = $this->provider_inventory_for_category($category, $refreshProvider);
            $clientSold = (float)($client[$category]['sold_gb'] ?? 0.0);
            $clientLeft = (float)($client[$category]['left_gb'] ?? 0.0);
            $providerLimit = (float)($provider['limit_gb'] ?? 0.0);
            $providerLeft = (float)($provider['left_gb'] ?? 0.0);
            $available = null;
            if (($provider['has_traffic'] ?? false) === true) {
                $availableByRemaining = $providerLeft - $clientLeft;
                $availableBySold = $providerLimit > 0.0 ? $providerLimit - $clientSold : $availableByRemaining;
                $available = min($availableByRemaining, $availableBySold);
            }

            $alert = 'OK';
            if (($provider['ok'] ?? false) !== true) {
                $alert = (string)($provider['message'] ?? 'Provider sync failed');
            } elseif ($available !== null && $available < 0.0) {
                $alert = 'Oversold';
            } elseif (($provider['is_suspended'] ?? false) === true) {
                $alert = 'Suspended';
            } elseif ($available !== null && $available <= 0.05 && ($providerLimit > 0.0 || $clientSold > 0.0)) {
                $alert = 'Low reserve';
            }

            $rows[] = [
                'category' => $category,
                'product' => $label,
                'provider_limit_gb' => round($providerLimit, 4),
                'provider_used_gb' => round((float)($provider['used_gb'] ?? 0.0), 4),
                'provider_left_gb' => round($providerLeft, 4),
                'client_sold_gb' => round($clientSold, 4),
                'client_used_gb' => round((float)($client[$category]['used_gb'] ?? 0.0), 4),
                'client_left_gb' => round($clientLeft, 4),
                'available_to_sell_gb' => $available === null ? null : round($available, 4),
                'provider_packages' => (int)($provider['packages'] ?? 0),
                'provider_state' => (string)($provider['state'] ?? ''),
                'provider_keys' => $provider['package_keys'] ?? [],
                'alert' => $alert,
            ];
        }

        return $rows;
    }

    /**
     * @return array{checked:int,suspended:int,errors:int,rows:array<int,array<string,string>>}
     */
    public function run_usage_guard(): array
    {
        $result = ['checked' => 0, 'suspended' => 0, 'errors' => 0, 'rows' => []];
        foreach ($this->list_all_services() as $service) {
            $serviceId = $this->str($service['service_id'] ?? '');
            if ($serviceId === '') {
                continue;
            }
            $category = $this->service_category($service);
            if (!in_array($category, ['mobile', 'residential', 'residential_ipv6'], true)) {
                continue;
            }

            $result['checked']++;
            $limit = $this->money_float($service['traffic_total_gb'] ?? 0);
            $used = $this->money_float($service['traffic_used_gb'] ?? 0);
            if ($limit <= 0.0 || $used < $limit || $this->str($service['status'] ?? '') === 'suspended') {
                $result['rows'][] = ['service_id' => $serviceId, 'status' => 'ok', 'message' => 'No suspend needed'];
                continue;
            }

            $response = $this->provider_suspend($service);
            $ok = !is_array($response) || (($response['ok'] ?? true) !== false);
            if ($ok) {
                $service['status'] = 'suspended';
                $service['suspended_at'] = date('c');
                $service['disable_reason'] = 'Usage guard: used traffic reached service limit.';
                $service['guard_response'] = $response;
                $this->update_json_row('proxy_service', $serviceId, $service);
                $result['suspended']++;
                $result['rows'][] = ['service_id' => $serviceId, 'status' => 'suspended', 'message' => 'Traffic exhausted'];
            } else {
                $result['errors']++;
                $result['rows'][] = ['service_id' => $serviceId, 'status' => 'error', 'message' => $this->str($response['error'] ?? 'Provider suspend failed')];
            }
        }
        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_payments(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'payment'
              AND status <> 'delete'
              AND table_value->>'user_id' = :user_id
            ORDER BY created_at DESC
            LIMIT 1000
        ", ['user_id' => (string)$user_id]);

        $rows = [];
        foreach (($resp['rows'] ?? []) as $row) {
            if (!isset($row['table_value']) || !is_array($row['table_value'])) {
                continue;
            }
            $payment = $row['table_value'];
            $session = isset($payment['stripe_session']) && is_array($payment['stripe_session']) ? $payment['stripe_session'] : [];
            $payment['provider'] = $this->str($payment['provider'] ?? 'stripe');
            $payment['payment_status'] = $this->str($payment['payment_status'] ?? $session['payment_status'] ?? $payment['status'] ?? '');
            $payment['vendor_status'] = $this->str($payment['vendor_status'] ?? $session['status'] ?? '');
            $payment['currency'] = $this->str($payment['currency'] ?? $session['currency'] ?? 'USD');
            $payment['amount_usd'] = $this->normalize_amount_usd($payment['amount_usd'] ?? '', $payment['total_cents'] ?? $session['amount_total'] ?? 0);
            $rows[] = $payment;
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_charges(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'proxy_order'
              AND status <> 'delete'
              AND table_value->>'user_id' = :user_id
            ORDER BY created_at DESC
            LIMIT 1000
        ", ['user_id' => (string)$user_id]);

        $rows = [];
        foreach (($resp['rows'] ?? []) as $row) {
            if (!isset($row['table_value']) || !is_array($row['table_value'])) {
                continue;
            }
            $order = $row['table_value'];
            $items = isset($order['items']) && is_array($order['items']) ? $order['items'] : [];
            $services = isset($order['services']) && is_array($order['services']) ? $order['services'] : [];
            $order['title'] = $this->charge_title($items);
            $order['items_count'] = count($items);
            $order['services_count'] = count($services);
            $order['currency'] = $this->str($order['currency'] ?? 'USD');
            $order['amount_usd'] = $this->normalize_amount_usd($order['amount_usd'] ?? '', $order['total_cents'] ?? 0);
            $rows[] = $order;
        }

        return $rows;
    }

    public function get_user_balance_usd(int $user_id): string
    {
        $users = Sogerien::Users();
        $users->init_db_alias($this->db_alias);
        $amount = $users->get_balance_amount($user_id, 'USD');
        return number_format((float)($amount ?? 0), 2, '.', '');
    }

    /**
     * @return array{countries:array<string,string>,regions:array<string,string>,cities:array<string,string>}
     */
    public function infatica_access_geo_options(string $category): array
    {
        $category = strtolower($this->str($category));
        if (!in_array($category, ['mobile', 'residential', 'residential_ipv6', 'isp', 'dc'], true)) {
            $category = 'residential';
        }

        $cacheFile = 'infatica/access_geo_' . $category . '.json';
        $cache = Sogerien::Cache();
        $updatedAt = 0;
        $cachedOptions = null;
        $cached = $cache->load($cacheFile, $updatedAt);
        if (is_array($cached)) {
            $normalizedCache = $this->normalize_access_geo_options($cached, $category);
            if (count($normalizedCache['countries']) > 1) {
                $cachedOptions = $normalizedCache;
            }
        }
        if (!$cache->is_interval_elapsed($cacheFile, 86400) && is_array($cachedOptions)) {
            return $cachedOptions;
        }

        $raw = null;
        try {
            $api = $this->traffic_provider_api(['provider_pool_category' => $category]);
            $raw = match ($category) {
                'mobile', 'residential', 'residential_ipv6' => is_object($api) && method_exists($api, 'countries') ? ['countries' => $api->countries()] : null,
                default => null,
            };
        } catch (Throwable) {
            $raw = null;
        }

        $options = $this->normalize_access_geo_options(is_array($raw) ? $raw : [], $category);
        if (count($options['countries']) <= 1) {
            return is_array($cachedOptions) ? $cachedOptions : $this->fallback_access_geo_options($category);
        }
        $cache->save($options, $cacheFile, time());

        return $options;
    }

    /** @return array<int,string> */
    public function infatica_access_regions(string $category, string $country): array
    {
        return $this->infatica_access_location_values($category, 'regions', strtoupper($this->str($country)), '');
    }

    /** @return array<int,string> */
    public function infatica_access_cities(string $category, string $country, string $region): array
    {
        return $this->infatica_access_location_values($category, 'cities', strtoupper($this->str($country)), $this->str($region));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_order_value(string $order_id): ?array
    {
        return $this->load_one('proxy_order', $order_id);
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function service_action(int $user_id, string $service_id, string $action, array $request = []): array
    {
        $service = $this->load_one('proxy_service', $service_id);
        if (!is_array($service) || (int)($service['user_id'] ?? 0) !== $user_id) {
            return ['ok' => false, 'error' => 'Service not found.'];
        }
        $packageKey = $this->str($service['vendor_package_key'] ?? '');
        $apiResult = null;
        $providerActions = [
            'refresh_traffic' => true,
            'generate_proxy_list' => true,
            'disable_proxy_list' => true,
            'suspend' => true,
            'resume' => true,
            'deactivate' => true,
            'add_traffic' => true,
            'set_traffic_limit' => true,
            'prolongate' => true,
            'update_proxy_list' => true,
            'regenerate_proxy_password' => true,
            'view_proxy_list' => true,
            'api_tool_access' => true,
            'cancel' => true,
            'uncancel' => true,
        ];
        if ($packageKey === '' && isset($providerActions[$action])) {
            return ['ok' => false, 'error' => 'Provider package is not active yet.'];
        }
        if ($packageKey !== '') {
            if ($action === 'auto_renew_on' || $action === 'auto_renew_off') {
                $service['auto_renew_request'] = $action === 'auto_renew_on';
                $apiResult = ['local' => true];
            } elseif ($action === 'suspend') {
                $apiResult = $this->provider_suspend($service);
                $service['status'] = 'suspended';
                $service['suspended_at'] = date('c');
            } elseif ($action === 'resume') {
                $apiResult = $this->provider_resume($service);
                $service['status'] = 'active';
                $service['resumed_at'] = date('c');
            } elseif ($action === 'deactivate') {
                $apiResult = $this->provider_deactivate($service);
                $service['status'] = 'deactivated';
                $service['deactivated_at'] = date('c');
            } elseif ($action === 'cancel') {
                $apiResult = $this->provider_cancel($service);
                $service['status'] = 'cancel_requested';
                $service['cancel_requested_at'] = date('c');
            } elseif ($action === 'uncancel') {
                $apiResult = $this->provider_uncancel($service);
                $service['status'] = 'active';
                $service['uncancelled_at'] = date('c');
            } elseif ($action === 'add_traffic') {
                $addGb = $this->money_float($request['add_gb'] ?? 0);
                if ($addGb <= 0.0) {
                    return ['ok' => false, 'error' => 'Traffic amount must be greater than zero.'];
                }
                $apiResult = $this->provider_add_traffic_gb($service, $addGb, !empty($request['resume_after_topup']));
                $refreshResult = $this->refresh_service_traffic($service);
                if (!($refreshResult['ok'] ?? false)) {
                    $service['traffic_total_gb'] = number_format($this->money_float($service['traffic_total_gb'] ?? 0) + $addGb, 2, '.', '');
                    $service['traffic_remaining_gb'] = number_format($this->money_float($service['traffic_remaining_gb'] ?? 0) + $addGb, 2, '.', '');
                    $service['traffic_remains'] = $service['traffic_remaining_gb'] . ' GB';
                    $apiResult = ['topup' => $apiResult, 'refresh' => $refreshResult];
                } else {
                    $apiResult = ['topup' => $apiResult, 'refresh' => $refreshResult];
                }
                if (!empty($request['resume_after_topup'])) {
                    $service['status'] = 'active';
                }
            } elseif ($action === 'set_traffic_limit') {
                $limitGb = $this->money_float($request['limit_gb'] ?? 0);
                if ($limitGb <= 0.0) {
                    return ['ok' => false, 'error' => 'Traffic limit must be greater than zero.'];
                }
                $expiresAt = $this->str($request['expires_at'] ?? $service['expires_at'] ?? '');
                $apiResult = $this->provider_set_traffic_limit_gb($service, $limitGb, $expiresAt);
                $usedGb = $this->money_float($service['traffic_used_gb'] ?? 0);
                $service['traffic_total_gb'] = number_format($limitGb, 2, '.', '');
                $service['traffic_remaining_gb'] = number_format(max(0.0, $limitGb - $usedGb), 2, '.', '');
                $service['traffic_remains'] = $service['traffic_remaining_gb'] . ' GB';
                if ($expiresAt !== '') {
                    $service['expires_at'] = $expiresAt;
                }
            } elseif ($action === 'prolongate') {
                $expiresAt = $this->str($request['expires_at'] ?? '');
                if ($expiresAt === '') {
                    return ['ok' => false, 'error' => 'Expiration date is required.'];
                }
                $api = $this->traffic_provider_api($service);
                if (!is_object($api) || !method_exists($api, 'prolongate')) {
                    return ['ok' => false, 'error' => 'Prolongation is not supported for this service type.'];
                }
                $apiResult = $api->prolongate($packageKey, $expiresAt);
                $service['expires_at'] = $expiresAt;
            } elseif ($action === 'refresh_traffic') {
                $apiResult = $this->refresh_service_traffic($service);
            } elseif ($action === 'generate_proxy_list') {
                if (($service['status'] ?? '') === 'traffic_exhausted') {
                    return ['ok' => false, 'error' => 'Traffic limit exhausted.'];
                }
                $accessOptions = $this->proxy_access_options_from_request($request, $service);
                $listName = (string)$user_id . '-ProxyMint-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
                $accessOptions['list_name'] = $listName;
                $authMode = $this->str($accessOptions['auth_mode'] ?? 'login_password');
                $login = '';
                $password = '';
                if ($authMode !== 'ip_whitelist') {
                    $login = 'pm' . substr(bin2hex(random_bytes(5)), 0, 10);
                    $password = bin2hex(random_bytes(8));
                    $accessOptions['login'] = $login;
                    $accessOptions['password'] = $password;
                }
                $country = $this->first_country($accessOptions['countries'] ?? $accessOptions['country'] ?? ($service['country'] ?? ''));
                $apiResult = $this->provider_generate_access_from_options(
                    $service,
                    $packageKey,
                    $accessOptions
                );
                if (!is_array($apiResult)) {
                    return ['ok' => false, 'error' => 'Provider access list generation failed.'];
                }
                $listId = $this->extract_proxy_list_id(is_array($apiResult) ? $apiResult : []);
                $generated = [
                    'id' => $listId,
                    'vendor_list_id' => $listId,
                    'package_key' => $packageKey,
                    'pid' => $this->str($service['vendor_package_pid'] ?? $packageKey),
                    'name' => $listName,
                    'login' => $login,
                    'password' => $password,
                    'auth_mode' => $accessOptions['auth_mode'] ?? 'login_password',
                    'network' => $accessOptions['network'] ?? '',
                    'country' => $country,
                    'countries' => $accessOptions['countries'] ?? $country,
                    'region' => $accessOptions['region'] ?? '',
                    'city' => $accessOptions['city'] ?? '',
                    'isp' => $accessOptions['isp'] ?? '',
                    'isp_id' => $accessOptions['isp_id'] ?? '',
                    'zip' => $accessOptions['zip'] ?? '',
                    'port_count' => 1000,
                    'rotation_period' => $accessOptions['rotation_period'] ?? 0,
                    'rotation_mode' => $accessOptions['rotation_mode'] ?? '',
                    'format' => $accessOptions['format'] ?? '',
                    'protocol' => $this->proxy_access_protocol_from_format($accessOptions['format'] ?? null, $accessOptions['protocol'] ?? ''),
                    'status' => 'active',
                    'traffic_used_gb' => '0.0000',
                    'created_at' => date('c'),
                    'options' => $accessOptions,
                    'response' => $apiResult,
                ];
                if (!isset($service['proxy_lists']) || !is_array($service['proxy_lists'])) {
                    $service['proxy_lists'] = [];
                }
                $service['proxy_lists'][] = $generated;
                $providerApi = $this->traffic_provider_api($service);
                if (is_object($providerApi) && method_exists($providerApi, 'core')) {
                    $providerCore = $providerApi->core();
                    $service['connection_host'] = $this->str($providerCore->shared_proxy_host ?? '');
                    $service['connection_port'] = $this->str($providerCore->shared_proxy_port ?? '');
                }
                $service['connection_login'] = $login;
                $service['connection_password'] = $password;
            } elseif ($action === 'disable_proxy_list') {
                $listId = $this->str($request['list_id'] ?? '');
                $listName = $this->str($request['list_name'] ?? '');
                $apiResult = ['ok' => false, 'error' => 'Proxy list not found.'];
                if (isset($service['proxy_lists']) && is_array($service['proxy_lists'])) {
                    foreach ($service['proxy_lists'] as $idx => $list) {
                        if (!is_array($list)) {
                            continue;
                        }
                        $currentId = $this->str($list['vendor_list_id'] ?? $list['id'] ?? '');
                        $currentName = $this->str($list['name'] ?? '');
                        if (($listId !== '' && $currentId === $listId) || ($listName !== '' && $currentName === $listName)) {
                            $apiResult = $this->provider_remove_access($service, $packageKey, $currentId, $currentName);
                            unset($service['proxy_lists'][$idx]);
                            break;
                        }
                    }
                    $service['proxy_lists'] = array_values($service['proxy_lists']);
                }
            } elseif ($action === 'update_proxy_list') {
                $api = $this->traffic_provider_api($service);
                if (!is_object($api) || !method_exists($api, 'update_access')) {
                    return ['ok' => false, 'error' => 'Access list update is not supported for this service type.'];
                }
                $listId = $this->str($request['list_id'] ?? '');
                $listName = $this->str($request['list_name'] ?? $request['name'] ?? '');
                if ($listId === '' && $listName === '') {
                    return ['ok' => false, 'error' => 'Access list id or name is required.'];
                }
                $accessOptions = $this->proxy_access_options_from_request($request, $service);
                if ($listName === '') {
                    $listName = $this->str($accessOptions['list_name'] ?? '');
                }
                $apiResult = $api->update_access($packageKey, $listId, $listName, $accessOptions);
                if (isset($service['proxy_lists']) && is_array($service['proxy_lists'])) {
                    foreach ($service['proxy_lists'] as &$list) {
                        if (!is_array($list)) {
                            continue;
                        }
                        $currentId = $this->str($list['vendor_list_id'] ?? $list['id'] ?? '');
                        $currentName = $this->str($list['name'] ?? '');
                        if (($listId !== '' && $currentId === $listId) || ($listName !== '' && $currentName === $listName)) {
                            $list = array_merge($list, $accessOptions);
                            $list['updated_at'] = date('c');
                            $list['update_response'] = $apiResult;
                            break;
                        }
                    }
                    unset($list);
                }
            } elseif ($action === 'regenerate_proxy_password') {
                $api = $this->traffic_provider_api($service);
                if (!is_object($api) || !method_exists($api, 'regenerate_access_password')) {
                    return ['ok' => false, 'error' => 'Password regeneration is not supported for this service type.'];
                }
                $listId = $this->str($request['list_id'] ?? '');
                $listName = $this->str($request['list_name'] ?? '');
                $apiResult = $api->regenerate_access_password($packageKey, $listId, $listName);
            } elseif ($action === 'view_proxy_list') {
                $api = $this->traffic_provider_api($service);
                if (!is_object($api) || !method_exists($api, 'view_access')) {
                    return ['ok' => false, 'error' => 'Access list view is not supported for this service type.'];
                }
                $listId = $this->str($request['list_id'] ?? '');
                $listName = $this->str($request['list_name'] ?? '');
                if (($listId === '' || $listName === '') && isset($service['proxy_lists']) && is_array($service['proxy_lists'])) {
                    foreach ($service['proxy_lists'] as $list) {
                        if (!is_array($list)) {
                            continue;
                        }
                        $currentId = $this->str($list['vendor_list_id'] ?? $list['id'] ?? '');
                        $currentName = $this->str($list['name'] ?? '');
                        if (($listId !== '' && $currentId === $listId) || ($listName !== '' && $currentName === $listName)) {
                            $listId = $currentId;
                            $listName = $currentName;
                            break;
                        }
                    }
                }
                if ($listId === '' || $listName === '') {
                    return ['ok' => false, 'error' => 'Access list id or name is missing. Regenerate the access list.'];
                }
                $apiResult = $api->view_access($packageKey, $listId, $listName);
            } elseif ($action === 'api_tool_access') {
                $api = $this->traffic_provider_api($service);
                if (!is_object($api) || !method_exists($api, 'api_tool_access')) {
                    return ['ok' => false, 'error' => 'API tool access is not supported for this service type.'];
                }
                $apiResult = $api->api_tool_access($packageKey, $this->proxy_access_options_from_request($request, $service));
            } else {
                $apiResult = ['local' => true, 'message' => 'Action is stored but provider endpoint is not mapped.'];
            }
        }
        $service['last_action'] = ['action' => $action, 'at' => date('c'), 'response' => $apiResult];
        $this->update_json_row('proxy_service', $service_id, $service);
        return ['ok' => true, 'response' => $apiResult];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function admin_provider_action(int $adminUserId, array $request): array
    {
        $action = $this->str($request['action'] ?? '');
        $result = ['ok' => false, 'error' => 'Unknown admin action.'];

        if ($action === 'admin_service_action') {
            $serviceId = $this->str($request['service_id'] ?? '');
            $service = $this->load_one('proxy_service', $serviceId);
            if (!is_array($service)) {
                $result = ['ok' => false, 'error' => 'Service not found.'];
            } else {
                $result = $this->service_action((int)($service['user_id'] ?? 0), $serviceId, $this->str($request['provider_action'] ?? ''), $request);
            }
        } elseif ($action === 'create_traffic_package') {
            $result = $this->admin_create_traffic_package($request);
        } elseif ($action === 'create_static_package') {
            $result = $this->admin_create_static_package($request);
        } elseif ($action === 'provider_balance') {
            $result = $this->admin_provider_balance($this->str($request['category'] ?? 'mobile'));
        } elseif ($action === 'provider_catalog') {
            $result = $this->admin_provider_catalog($this->str($request['category'] ?? 'mobile'), $this->str($request['catalog_method'] ?? 'geos'), $request);
        } elseif ($action === 'ip_block_check' || $action === 'ip_unblock') {
            $ipAction = $this->str($request['block_action'] ?? $action);
            $result = $this->admin_ip_block_action($ipAction === 'ip_unblock' ? 'ip_unblock' : 'ip_block_check', $request);
        } elseif ($action === 'scraper_test') {
            $result = $this->admin_scraper_test($request);
        } elseif ($action === 'proxy_test') {
            $result = $this->admin_proxy_test($request);
        } elseif ($action === 'integration_snippet') {
            $result = $this->admin_integration_snippet($request);
        } elseif ($action === 'client_api_diagnostics') {
            $result = $this->admin_client_api_diagnostics($request);
        } elseif ($action === 'raw_api') {
            $result = $this->admin_raw_api($request);
        }

        $this->write_provider_audit($adminUserId, $action, $request, $result);
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    public function provider_audit_log(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'provider_api_audit' AND status <> 'delete'
            ORDER BY id DESC
            LIMIT " . $limit, []);
        return $this->extract_value_rows($resp);
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_create_traffic_package(array $request): array
    {
        $category = $this->service_category($request);
        if (!in_array($category, ['mobile', 'residential', 'residential_ipv6'], true)) {
            return ['ok' => false, 'error' => 'Traffic packages support only mobile/residential/residential_ipv6.'];
        }
        $gb = $this->money_float($request['traffic_gb'] ?? $request['limit_gb'] ?? 0);
        $expiresAt = $this->str($request['expires_at'] ?? '');
        if ($gb <= 0.0 || $expiresAt === '') {
            return ['ok' => false, 'error' => 'Traffic GB and expiration date are required.'];
        }

        $api = $this->traffic_provider_api(['provider_pool_category' => $category]);
        if (!is_object($api) || !method_exists($api, 'create_package_gib')) {
            return ['ok' => false, 'error' => 'Provider create package is not available.'];
        }

        $raw = $api->create_package_gib($gb, $expiresAt);
        $packageKey = is_array($raw) ? $this->extract_package_key($raw) : '';
        $serviceId = $this->str($request['service_id'] ?? '');
        $userId = (int)$this->str($request['client_user_id'] ?? $request['user_id'] ?? 0);
        if ($serviceId !== '') {
            $service = $this->load_one('proxy_service', $serviceId);
            if (is_array($service)) {
                $service['vendor_package_key'] = $packageKey;
                $service['vendor_package_pid'] = $packageKey;
                $service['provider_pool_category'] = $category;
                $service['traffic_total_gb'] = number_format($gb, 2, '.', '');
                $service['traffic_remaining_gb'] = number_format($gb, 2, '.', '');
                $service['expires_at'] = $expiresAt;
                $service['status'] = $packageKey !== '' ? 'active' : 'provider_failed';
                $service['provider_raw_json'] = $raw;
                $service['updated_at'] = date('c');
                $this->update_json_row('proxy_service', $serviceId, $service);
            }
        } elseif ($userId > 0) {
            $serviceId = 'svc_' . bin2hex(random_bytes(8));
            $service = [
                'service_id' => $serviceId,
                'order_id' => 'admin_' . bin2hex(random_bytes(6)),
                'user_id' => $userId,
                'provider' => 'infatica_io',
                'provider_pool_category' => $category,
                'vendor_package_key' => $packageKey,
                'vendor_package_pid' => $packageKey,
                'title' => $this->str($request['title'] ?? 'Admin issued ' . $category . ' package'),
                'country' => strtoupper($this->first_country($request['country'] ?? '')),
                'status' => $packageKey !== '' ? 'active' : 'provider_failed',
                'traffic_total_gb' => number_format($gb, 2, '.', ''),
                'traffic_used_gb' => '0.00',
                'traffic_remaining_gb' => number_format($gb, 2, '.', ''),
                'traffic_remains' => number_format($gb, 2, '.', '') . ' GB',
                'expires_at' => $expiresAt,
                'provider_raw_json' => $raw,
                'created_at' => date('c'),
            ];
            $this->insert_row('proxy_service', $serviceId, $service['title'], $service);
        }

        return ['ok' => $packageKey !== '', 'package_key' => $packageKey, 'service_id' => $serviceId, 'response' => $raw];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_create_static_package(array $request): array
    {
        $category = $this->service_category($request);
        if (!in_array($category, ['isp', 'dc'], true)) {
            return ['ok' => false, 'error' => 'Static package supports only ISP/DC.'];
        }
        $country = strtoupper($this->first_country($request['country'] ?? ''));
        $count = max(1, (int)$this->money_float($request['ip_count'] ?? $request['count'] ?? 1));
        if ($country === '') {
            return ['ok' => false, 'error' => 'Country is required.'];
        }
        $raw = $category === 'dc'
            ? Sogerien::API()->InfaticaIo()->Dc()->create_package($country, $count)
            : Sogerien::API()->InfaticaIo()->Isp()->create_package($country, $count);
        return ['ok' => is_array($raw), 'package_key' => is_array($raw) ? $this->extract_package_key($raw) : '', 'response' => $raw];
    }

    /** @return array<string,mixed> */
    private function admin_provider_balance(string $category): array
    {
        $category = strtolower($category);
        $api = Sogerien::API()->InfaticaIo();
        $raw = match ($category) {
            'isp' => $api->Isp()->balance(),
            'dc', 'dc_shared' => $api->Dc()->balance(),
            'mobile' => $api->Mobile()->reseller_stats(),
            'residential', 'residential_ipv6' => $api->Residential()->reseller_stats(),
            default => null,
        };
        $keys = in_array($category, ['mobile'], true)
            ? $api->Mobile()->keys()
            : (in_array($category, ['residential', 'residential_ipv6'], true) ? $api->Residential()->keys() : null);
        return ['ok' => $raw !== null, 'category' => $category, 'response' => $raw, 'keys' => $keys];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_provider_catalog(string $category, string $method, array $request): array
    {
        $category = strtolower($category);
        $method = strtolower($method);
        $api = match ($category) {
            'mobile' => Sogerien::API()->InfaticaIo()->Mobile(),
            'residential', 'residential_ipv6' => Sogerien::API()->InfaticaIo()->Residential(),
            'isp' => Sogerien::API()->InfaticaIo()->Isp(),
            'dc', 'dc_shared' => Sogerien::API()->InfaticaIo()->Dc(),
            default => null,
        };
        if (!is_object($api)) {
            return ['ok' => false, 'error' => 'Unknown provider category.'];
        }
        $allowed = ['geos', 'detailed_geos', 'ipv6_detailed_geos', 'subdivision_codes', 'isp_codes', 'zip_codes', 'geo_db', 'online_statistics', 'countries', 'online_nodes'];
        if (!in_array($method, $allowed, true) || !method_exists($api, $method)) {
            return ['ok' => false, 'error' => 'Catalog method is not allowed for this provider.'];
        }
        $country = strtoupper($this->first_country($request['country'] ?? ''));
        $raw = $method === 'zip_codes' ? $api->{$method}($country) : $api->{$method}();
        return ['ok' => $raw !== null, 'category' => $category, 'method' => $method, 'response' => $raw];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_ip_block_action(string $action, array $request): array
    {
        $ip = $this->str($request['ip'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'error' => 'Valid IP is required.'];
        }
        $category = $this->service_category($request);
        $api = $category === 'mobile' ? Sogerien::API()->InfaticaIo()->Mobile() : Sogerien::API()->InfaticaIo()->Residential();
        $raw = $action === 'ip_unblock' ? $api->unblock_ip($ip) : $api->check_ip_block($ip);
        return ['ok' => $raw !== null, 'ip' => $ip, 'response' => $raw];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_scraper_test(array $request): array
    {
        $method = strtolower($this->str($request['scraper_method'] ?? 'scrape'));
        $api = Sogerien::API()->InfaticaIo()->Scraper();
        $rawPayload = $this->str($request['payload_json'] ?? '');
        $payload = $rawPayload !== '' ? json_decode($rawPayload, true) : ['url' => $this->str($request['url'] ?? '')];
        if (!is_array($payload)) {
            return ['ok' => false, 'error' => 'Payload JSON is invalid.'];
        }
        $raw = match ($method) {
            'render' => $api->render($payload),
            'serp' => $api->serp($payload),
            'chatgpt' => $api->chatgpt($this->str($request['query'] ?? ''), !empty($request['return_html'])),
            'gemini' => $api->gemini($this->str($request['query'] ?? ''), !empty($request['return_html'])),
            'perplexity' => $api->perplexity($this->str($request['query'] ?? ''), !empty($request['return_html'])),
            default => $api->scrape($payload),
        };
        $core = $api->core();
        return ['ok' => $raw !== null, 'http_code' => $core->last_http_code, 'error' => $core->error, 'response' => $raw];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_proxy_test(array $request): array
    {
        $proxyUrl = $this->str($request['proxy_url'] ?? '');
        $targetUrl = $this->str($request['target_url'] ?? 'http://ip-api.com/json');
        if ($proxyUrl === '') {
            $urls = Sogerien::API()->InfaticaIo()->Catalog()->shared_proxy_urls(
                $this->str($request['login'] ?? ''),
                $this->str($request['password'] ?? ''),
                $request
            );
            $proxyUrl = is_array($urls) ? (string)($urls['http'] ?? '') : '';
        }
        if ($proxyUrl === '') {
            return ['ok' => false, 'error' => 'Proxy URL or login/password is required.'];
        }
        $core = Sogerien::API()->InfaticaIo()->Transport();
        $raw = $core->proxy_check_ip_api($proxyUrl, $targetUrl !== '' ? $targetUrl : 'http://ip-api.com/json');
        return ['ok' => $raw !== null, 'http_code' => $core->last_http_code, 'err_code' => $core->last_err_code, 'err_msg' => $core->last_err_msg, 'response' => $raw];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_integration_snippet(array $request): array
    {
        $core = Sogerien::API()->InfaticaIo()->Transport();
        $login = $this->str($request['login'] ?? '');
        $password = $this->str($request['password'] ?? '');
        $urls = $core->shared_proxy_urls_from_options($login, $password, $request);
        $curl = $core->shared_proxy_curl_command($this->str($request['target_url'] ?? 'https://example.com'), $login, $password, $request);
        return ['ok' => is_array($urls), 'urls' => $urls, 'curl' => $curl, 'guidelines' => $core->shared_proxy_guidelines()];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_client_api_diagnostics(array $request): array
    {
        $pid = $this->str($request['pid'] ?? $request['package_key'] ?? '');
        $login = $this->str($request['login'] ?? '');
        $category = $this->service_category($request);
        $core = Sogerien::API()->InfaticaIo()->Transport();
        return [
            'ok' => true,
            'traffic' => $pid !== '' ? $core->client_get_traffic($pid, $login) : null,
            'remaining_traffic' => $pid !== '' ? $core->client_remaining_traffic($pid) : null,
            'balance' => $core->client_get_balance(),
            'count_nodes' => $core->client_count_nodes($category === 'mobile', in_array($category, ['dc', 'dc_shared'], true)),
            'geo_nodes' => $core->client_geo_nodes($category === 'mobile', in_array($category, ['dc', 'dc_shared'], true), $category === 'residential_ipv6'),
            'day_online' => $core->client_day_online($category === 'mobile', in_array($category, ['dc', 'dc_shared'], true)),
            'isp_codes' => $core->client_isp_codes(),
            'zip_codes' => $core->client_zip_codes($this->first_country($request['country'] ?? '')),
            'subdivision_codes' => $core->client_subdivision_codes(),
            'http_code' => $core->last_http_code,
            'error' => $core->error,
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function admin_raw_api(array $request): array
    {
        $scope = strtolower($this->str($request['scope'] ?? 'transport'));
        $method = $this->str($request['method_name'] ?? '');
        $allowed = [
            'mobile' => ['reseller_stats', 'packages', 'package_info', 'package_usage', 'traffic_details', 'lists', 'keys', 'geos', 'detailed_geos', 'subdivision_codes', 'isp_codes', 'zip_codes', 'geo_db'],
            'residential' => ['reseller_stats', 'packages', 'package_info', 'package_usage', 'traffic_details', 'lists', 'keys', 'geos', 'detailed_geos', 'ipv6_detailed_geos', 'subdivision_codes', 'isp_codes', 'zip_codes', 'geo_db'],
            'isp' => ['balance', 'countries', 'package_info'],
            'dc' => ['balance', 'countries', 'online_nodes', 'detailed_geos', 'package_info'],
        ];
        $api = match ($scope) {
            'mobile' => Sogerien::API()->InfaticaIo()->Mobile(),
            'residential', 'residential_ipv6' => Sogerien::API()->InfaticaIo()->Residential(),
            'isp' => Sogerien::API()->InfaticaIo()->Isp(),
            'dc', 'dc_shared' => Sogerien::API()->InfaticaIo()->Dc(),
            default => null,
        };
        $allowKey = $scope === 'residential_ipv6' ? 'residential' : ($scope === 'dc_shared' ? 'dc' : $scope);
        if (!is_object($api) || !isset($allowed[$allowKey]) || !in_array($method, $allowed[$allowKey], true) || !method_exists($api, $method)) {
            return ['ok' => false, 'error' => 'Raw API method is not allowed.'];
        }
        $arg1 = $this->str($request['arg1'] ?? '');
        $arg2 = $this->str($request['arg2'] ?? '');
        $raw = $arg2 !== '' ? $api->{$method}($arg1, $arg2) : ($arg1 !== '' ? $api->{$method}($arg1) : $api->{$method}());
        return ['ok' => $raw !== null, 'scope' => $scope, 'method' => $method, 'response' => $raw];
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $result
     */
    private function write_provider_audit(int $adminUserId, string $action, array $request, array $result): void
    {
        $id = 'audit_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $safeRequest = $request;
        foreach (['password', 'new_password', 'proxy-list-password'] as $secretKey) {
            if (isset($safeRequest[$secretKey])) {
                $safeRequest[$secretKey] = '***';
            }
        }
        $this->insert_row('provider_api_audit', $id, $action, [
            'audit_id' => $id,
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'request' => $safeRequest,
            'result' => $result,
            'created_at' => date('c'),
        ]);
    }

    /**
     * @param array<string,mixed>|null $info
     * @param array<string,mixed>|null $details
     * @return array{total_gb:float,used_gb:float,remaining_gb:float}
     */
    private function extract_traffic_usage(?array $info, ?array $details, float $fallbackTotalGb): array
    {
        $snapshot = $this->extract_provider_traffic_snapshot($info, $details);
        if ($snapshot['total_gb'] > 0.0 || $snapshot['used_gb'] > 0.0 || $snapshot['remaining_gb'] > 0.0) {
            return $snapshot;
        }

        $flat = [];
        $this->flatten_assoc($info ?? [], '', $flat);
        $this->flatten_assoc($details ?? [], '', $flat);

        $totalNeedles     = ['traffic_limit', 'traffic_total', 'total_traffic', 'limit_gb', 'traffic_gb'];
        $usedNeedles      = ['traffic_used', 'used_traffic', 'used_gb', 'spent_traffic', 'traffic_spent'];
        $remainingNeedles = ['traffic_remaining', 'remaining_traffic', 'remain_traffic', 'traffic_remains', 'left_traffic', 'available_traffic'];

        $total = $this->first_flat_number_gb($flat, $totalNeedles);
        $used = $this->first_flat_number_gb($flat, $usedNeedles);
        $remaining = $this->first_flat_number_gb($flat, $remainingNeedles);

        if ($total <= 0.0) {
            $total = $fallbackTotalGb;
        }
        if ($remaining <= 0.0 && $total > 0.0 && $used > 0.0) {
            $remaining = max(0.0, $total - $used);
        }
        if ($used <= 0.0 && $total > 0.0 && $remaining >= 0.0) {
            $used = max(0.0, $total - $remaining);
        }

        return [
            'total_gb' => round($total, 4),
            'used_gb' => round($used, 4),
            'remaining_gb' => round(max(0.0, $remaining), 4),
        ];
    }

    /**
     * @param array<string,mixed>|null $info
     * @param array<string,mixed>|null $details
     * @return array{total_gb:float,used_gb:float,remaining_gb:float}
     */
    private function extract_provider_traffic_snapshot(?array $info, ?array $details): array
    {
        $row = is_array($info) && isset($info['results']) && is_array($info['results']) ? $info['results'] : ($info ?? []);
        $limitBytes = $this->nested_number($row, ['traffic_limits', 'common']);
        $usedBytes = $this->nested_number($row, ['traffic_usage', 'common']);

        if ($usedBytes <= 0.0 && is_array($details)) {
            $detailsRow = isset($details['results']) && is_array($details['results']) ? $details['results'] : $details;
            $usedBytes = $this->nested_number($detailsRow, ['common']);
        }

        if ($limitBytes <= 0.0 && $usedBytes <= 0.0) {
            return ['total_gb' => 0.0, 'used_gb' => 0.0, 'remaining_gb' => 0.0];
        }

        $totalGb = $this->bytes_to_gb($limitBytes);
        $usedGb = $this->bytes_to_gb($usedBytes);

        return [
            'total_gb' => $totalGb,
            'used_gb' => $usedGb,
            'remaining_gb' => round(max(0.0, $totalGb - $usedGb), 4),
        ];
    }

    /**
     * Find first matching key in flattened map, returning GB.
     * Auto-converts bytes/megabytes/kilobytes keys to GB. GB keys returned as-is.
     *
     * @param array<string,float> $flat
     * @param array<int,string> $needles
     */
    private function first_flat_number_gb(array $flat, array $needles): float
    {
        // First pass: prefer keys NOT in bytes/kb/mb (likely already GB)
        foreach ($flat as $key => $value) {
            $unit = $this->key_unit_factor_to_gb($key);
            if ($unit === null) {
                continue;
            }
            // Skip bytes-like keys in first pass; they'll be picked up below if no GB key found.
            if ($unit !== 1.0) {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($key, $needle)) {
                    return (float)$value;
                }
            }
        }
        // Second pass: accept bytes/kb/mb keys and convert to GB
        foreach ($flat as $key => $value) {
            $unit = $this->key_unit_factor_to_gb($key);
            if ($unit === null) {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($key, $needle)) {
                    return (float)$value * $unit;
                }
            }
        }
        return 0.0;
    }

    /**
     * Return factor that converts a raw value with this key into GB.
     * Returns null if the key looks unrelated (e.g. price, timestamp).
     * 1.0 for plain or _gb keys (already GB), smaller fractions for _mb, _kb, _bytes.
     */
    private function key_unit_factor_to_gb(string $key): ?float
    {
        if (str_ends_with($key, '_bytes') || str_contains($key, '_bytes_')) {
            return 1.0 / (1024.0 * 1024.0 * 1024.0);
        }
        if (str_ends_with($key, '_kb') || str_contains($key, '_kb_')) {
            return 1.0 / (1024.0 * 1024.0);
        }
        if (str_ends_with($key, '_mb') || str_contains($key, '_mb_')) {
            return 1.0 / 1024.0;
        }
        // _gb keys or plain keys (assumed GB)
        return 1.0;
    }

    /**
     * @param array<mixed> $value
     * @param array<string,float> $flat
     */
    private function flatten_assoc(array $value, string $prefix, array &$flat): void
    {
        foreach ($value as $key => $item) {
            $name = strtolower(trim($prefix . '_' . (string)$key, '_'));
            if (is_array($item)) {
                $this->flatten_assoc($item, $name, $flat);
                continue;
            }
            if (is_scalar($item)) {
                $raw = str_replace(',', '.', (string)$item);
                if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $m) === 1) {
                    $flat[$name] = (float)$m[0];
                }
            }
        }
    }

    /**
     * @param array<string,float> $flat
     * @param array<int,string> $needles
     */
    private function first_flat_number(array $flat, array $needles): float
    {
        foreach ($flat as $key => $value) {
            foreach ($needles as $needle) {
                if (str_contains($key, $needle)) {
                    return (float)$value;
                }
            }
        }
        return 0.0;
    }

    /**
     * @param array<string,mixed> $service
     * @return array<string,mixed>
     */
    private function refresh_service_traffic(array &$service): array
    {
        $packageKey = $this->str($service['vendor_package_key'] ?? '');
        if ($packageKey === '') {
            return ['ok' => false, 'error' => 'Provider package key is empty.'];
        }

        $api = $this->traffic_provider_api($service);
        if (!is_object($api) || !method_exists($api, 'package_info')) {
            return ['ok' => false, 'error' => 'Traffic refresh is not supported for this service type.'];
        }
        $info = $api->package_info($packageKey);
        $details = method_exists($api, 'package_usage')
            ? $api->package_usage($packageKey)
            : (method_exists($api, 'traffic_details') ? $api->traffic_details($packageKey, 'all') : null);
        $usage = $this->extract_traffic_usage($info, $details, (float)$this->money_float($service['traffic_limit_gb'] ?? $service['traffic_total_gb'] ?? 0));
        $trafficDetails = method_exists($api, 'traffic_details')
            ? [
                'daily' => $api->traffic_details($packageKey, 'daily'),
                'weekly' => $api->traffic_details($packageKey, 'weekly'),
                'monthly' => $api->traffic_details($packageKey, 'monthly'),
                'all' => $api->traffic_details($packageKey, 'all'),
            ]
            : [];

        $service['provider_info_response'] = $info;
        $service['provider_traffic_response'] = $details;
        $service['provider_traffic_details'] = $trafficDetails;
        if (method_exists($api, 'lists')) {
            $this->sync_provider_proxy_lists($service, $packageKey, $api->lists($packageKey), $api, $trafficDetails['all'] ?? null);
        }
        $service['traffic_total_gb'] = number_format($usage['total_gb'], 2, '.', '');
        $service['traffic_used_gb'] = number_format($usage['used_gb'], 2, '.', '');
        $service['traffic_remaining_gb'] = number_format($usage['remaining_gb'], 2, '.', '');
        $service['traffic_remains'] = number_format($usage['remaining_gb'], 2, '.', '') . ' GB';
        $service['traffic_updated_at'] = date('c');
        $this->append_traffic_history_snapshot($service);

        if ($usage['total_gb'] > 0.0 && $usage['remaining_gb'] <= 0.0) {
            $service['status'] = 'traffic_exhausted';
            $service['disable_reason'] = 'Traffic limit exhausted.';
        } elseif (($service['status'] ?? '') === 'traffic_exhausted' && $usage['remaining_gb'] > 0.0) {
            $service['status'] = 'active';
            unset($service['disable_reason']);
        }

        return ['ok' => true, 'usage' => $usage, 'info' => $info, 'traffic_details' => $details];
    }

    /**
     * @param array<string,mixed> $service
     */
    private function append_traffic_history_snapshot(array &$service): void
    {
        $history = isset($service['traffic_history']) && is_array($service['traffic_history'])
            ? array_values($service['traffic_history'])
            : [];
        $history[] = [
            'at' => $this->str($service['traffic_updated_at'] ?? date('c')),
            'used_gb' => number_format($this->money_float($service['traffic_used_gb'] ?? 0), 4, '.', ''),
            'remaining_gb' => number_format($this->money_float($service['traffic_remaining_gb'] ?? 0), 4, '.', ''),
            'total_gb' => number_format($this->money_float($service['traffic_total_gb'] ?? 0), 4, '.', ''),
        ];
        $service['traffic_history'] = array_slice($history, -400);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function fulfill_infatica_item(int $user_id, string $order_id, array $item): array
    {
        $category = strtolower($this->str($item['proxy_category'] ?? $item['proxy_api_type'] ?? 'residential'));
        if ($category === 'isp' || $category === 'dc') {
            return $this->fulfill_static_ip_item($user_id, $order_id, $item, $category);
        }
        if ($category === 'scraper') {
            return $this->fulfill_scraper_item($user_id, $order_id, $item);
        }

        $serviceId = 'svc_' . bin2hex(random_bytes(8));
        $country = $this->first_country($item['location_country_code'] ?? '');
        $traffic = (int)$this->money_float($item['traffic_limitation'] ?? $item['traffic_gb'] ?? 0);
        $days = max(1, (int)$this->money_float($item['days'] ?? 364));
        $expires = date('c', time() + ($days * 86400));

        $api = Sogerien::API()->InfaticaIo()->Catalog();
        $pool = $this->provider_pool_for_category($category);
        $packageKey = $this->str($pool['package_key'] ?? '');
        $packagePid = $this->str($pool['pid'] ?? $packageKey);

        $login = 'pm' . substr($serviceId, 4, 10);
        $password = bin2hex(random_bytes(6));
        $urls = $api->shared_proxy_urls($login, $password, ['country' => $country]);

        return [
            'service_id' => $serviceId,
            'order_id' => $order_id,
            'user_id' => $user_id,
            'provider' => 'infatica_io',
            'vendor_package_key' => $packageKey,
            'vendor_package_pid' => $packagePid,
            'provider_pool_category' => $category,
            'title' => $this->str($item['title'] ?? 'Infatica proxy'),
            'country' => $country,
            'status' => $packageKey !== '' ? 'active' : 'pending_provider_pool',
            'price_usd' => $this->str($item['price_usd'] ?? ''),
            'provider_cost_usd' => $this->str($item['provider_cost_usd'] ?? ''),
            'profit_usd' => $this->str($item['profit_usd'] ?? ''),
            'days' => (string)$days,
            'billing_period' => $days <= 31 ? 'trial' : 'year',
            'is_trial' => $days <= 31 ? '1' : '0',
            'connection_host' => $api->shared_proxy_host(),
            'connection_port' => (string)$api->shared_proxy_port(),
            'connection_login' => is_array($urls) ? (string)($urls['login'] ?? $login) : $login,
            'connection_password' => $password,
            'http_url' => is_array($urls) ? (string)($urls['http'] ?? '') : '',
            'socks5_url' => is_array($urls) ? (string)($urls['socks5'] ?? '') : '',
            'expires_at' => $expires,
            'traffic_total_gb' => $traffic > 0 ? (string)$traffic : '',
            'traffic_used_gb' => '0.00',
            'traffic_remaining_gb' => $traffic > 0 ? number_format((float)$traffic, 2, '.', '') : '',
            'traffic_remains' => $traffic > 0 ? ((string)$traffic . ' GB') : '-',
            'auto_renew_request' => !empty($item['auto_renew']),
            'created_at' => date('c'),
        ];
    }

    /**
     * @param array<string,mixed> $service
     */
    private function merge_traffic_service_if_possible(int $user_id, array &$service): bool
    {
        if ($user_id <= 0 || $this->str($service['is_trial'] ?? '') === '1') {
            return false;
        }

        $category = $this->service_category($service);
        if (!in_array($category, ['mobile', 'residential', 'residential_ipv6'], true)) {
            return false;
        }

        $country = $this->first_country($service['country'] ?? '');
        $addGb = $this->money_float($service['traffic_total_gb'] ?? 0);
        if ($country === '' || $addGb <= 0.0) {
            return false;
        }

        foreach ($this->list_user_services($user_id) as $existing) {
            if (!is_array($existing) || $this->str($existing['is_trial'] ?? '') === '1') {
                continue;
            }
            if ($this->service_category($existing) !== $category || $this->first_country($existing['country'] ?? '') !== $country) {
                continue;
            }

            $existingId = $this->str($existing['service_id'] ?? '');
            if ($existingId === '') {
                continue;
            }

            $oldTotal = $this->money_float($existing['traffic_total_gb'] ?? 0);
            $oldRemaining = $this->money_float($existing['traffic_remaining_gb'] ?? 0);
            $existing['traffic_total_gb'] = number_format($oldTotal + $addGb, 2, '.', '');
            $existing['traffic_remaining_gb'] = number_format($oldRemaining + $addGb, 2, '.', '');
            $existing['traffic_remains'] = $existing['traffic_remaining_gb'] . ' GB';
            $existing['status'] = $this->str($existing['status'] ?? '') === 'traffic_exhausted' ? 'active' : ($existing['status'] ?? 'active');
            $existing['last_topup_order_id'] = $this->str($service['order_id'] ?? '');
            $existing['updated_at'] = date('c');
            $this->update_json_row('proxy_service', $existingId, $existing);
            $service = $existing;
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function fulfill_static_ip_item(int $user_id, string $order_id, array $item, string $category): array
    {
        $serviceId = 'svc_' . bin2hex(random_bytes(8));
        $country = $this->first_country($item['location_country_code'] ?? '');
        $ipCount = max(1, (int)$this->money_float($item['ip_count'] ?? 1));
        $days = max(1, (int)$this->money_float($item['days'] ?? 30));
        $expires = date('c', time() + ($days * 86400));
        $category = $category === 'dc' ? 'dc' : 'isp';

        $providerRaw = $category === 'dc'
            ? Sogerien::API()->InfaticaIo()->Dc()->create_package($country, $ipCount)
            : Sogerien::API()->InfaticaIo()->Isp()->create_package($country, $ipCount);
        $packageKey = is_array($providerRaw) ? $this->extract_package_key($providerRaw) : '';
        $status = $packageKey !== '' ? 'active' : 'provider_failed';
        $issuedIps = is_array($providerRaw) ? $this->extract_issued_ips($providerRaw) : [];
        $title = $category === 'dc' ? 'Dedicated DC proxy ' : 'ISP proxy ';

        return [
            'service_id' => $serviceId,
            'order_id' => $order_id,
            'user_id' => $user_id,
            'provider' => 'infatica_io',
            'provider_pool_category' => $category,
            'vendor_package_key' => $packageKey,
            'vendor_package_pid' => $packageKey,
            'title' => $title . $country,
            'country' => $country,
            'ip_count' => (string)$ipCount,
            'issued_ips' => $issuedIps,
            'status' => $status,
            'price_usd' => $this->str($item['price_usd'] ?? ''),
            'price_per_ip' => $this->str($item['price_per_ip'] ?? ''),
            'provider_cost_usd' => $this->str($item['provider_cost_usd'] ?? ''),
            'profit_usd' => $this->str($item['profit_usd'] ?? ''),
            'days' => (string)$days,
            'billing_period' => $this->str($item['is_trial'] ?? '') === '1' ? 'trial' : 'month',
            'is_trial' => $this->str($item['is_trial'] ?? '') === '1' ? '1' : '0',
            'expires_at' => $expires,
            'auto_renew_request' => !empty($item['auto_renew']),
            'provider_raw_json' => $providerRaw,
            'created_at' => date('c'),
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function fulfill_scraper_item(int $user_id, string $order_id, array $item): array
    {
        $serviceId = 'svc_' . bin2hex(random_bytes(8));
        $days = max(1, (int)$this->money_float($item['days'] ?? 364));
        $requestsLimit = max(1, (int)$this->money_float($item['requests_limit'] ?? 0));
        $clientApiKey = 'pm_scr_' . bin2hex(random_bytes(18));

        return [
            'service_id' => $serviceId,
            'order_id' => $order_id,
            'user_id' => $user_id,
            'provider' => 'proxymint_gateway',
            'provider_pool_category' => 'scraper',
            'vendor_package_key' => '',
            'title' => $this->str($item['title'] ?? 'Scraper API'),
            'status' => 'active',
            'plan_id' => $this->str($item['plan_id'] ?? ''),
            'plan' => $this->str($item['plan'] ?? ''),
            'client_api_key' => $clientApiKey,
            'requests_limit' => (string)$requestsLimit,
            'requests_used' => '0',
            'requests_left' => (string)$requestsLimit,
            'render_requests_limit' => $this->str($item['render_requests_limit'] ?? '0'),
            'success_only_billing' => $this->str($item['success_only_billing'] ?? '1'),
            'price_usd' => $this->str($item['price_usd'] ?? ''),
            'expires_at' => date('c', time() + ($days * 86400)),
            'auto_renew_request' => !empty($item['auto_renew']),
            'created_at' => date('c'),
        ];
    }

    /**
     * @param array<string,mixed> $service
     */
    private function service_category(array $service): string
    {
        return strtolower($this->str($service['provider_pool_category'] ?? $service['proxy_category'] ?? $service['category'] ?? 'mobile'));
    }

    /**
     * @param array<string,mixed> $service
     */
    private function traffic_provider_api(array $service): ?object
    {
        $category = $this->service_category($service);
        return match ($category) {
            'mobile' => Sogerien::API()->InfaticaIo()->Mobile(),
            'residential', 'residential_ipv6' => Sogerien::API()->InfaticaIo()->Residential(),
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_suspend(array $service): ?array
    {
        $packageKey = $this->str($service['vendor_package_key'] ?? '');
        $category = $this->service_category($service);
        if ($category === 'isp') {
            return Sogerien::API()->InfaticaIo()->Isp()->suspend($packageKey);
        }
        if ($category === 'dc') {
            return Sogerien::API()->InfaticaIo()->Dc()->suspend($packageKey);
        }
        $api = $this->traffic_provider_api($service);
        return is_object($api) && method_exists($api, 'suspend') ? $api->suspend($packageKey) : ['ok' => false, 'error' => 'Suspend is not supported for this service type.'];
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_resume(array $service): ?array
    {
        $packageKey = $this->str($service['vendor_package_key'] ?? '');
        $category = $this->service_category($service);
        if ($category === 'isp') {
            return Sogerien::API()->InfaticaIo()->Isp()->resume($packageKey);
        }
        if ($category === 'dc') {
            return Sogerien::API()->InfaticaIo()->Dc()->resume($packageKey);
        }
        $api = $this->traffic_provider_api($service);
        return is_object($api) && method_exists($api, 'resume') ? $api->resume($packageKey) : ['ok' => false, 'error' => 'Resume is not supported for this service type.'];
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_deactivate(array $service): ?array
    {
        $packageKey = $this->str($service['vendor_package_key'] ?? '');
        $category = $this->service_category($service);
        if ($category === 'isp') {
            return Sogerien::API()->InfaticaIo()->Isp()->deactivate($packageKey);
        }
        if ($category === 'dc') {
            return Sogerien::API()->InfaticaIo()->Dc()->deactivate($packageKey);
        }
        $api = $this->traffic_provider_api($service);
        return is_object($api) && method_exists($api, 'deactivate') ? $api->deactivate($packageKey) : ['ok' => false, 'error' => 'Deactivate is not supported for this service type.'];
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_cancel(array $service): ?array
    {
        $category = $this->service_category($service);
        if ($category === 'dc') {
            return Sogerien::API()->InfaticaIo()->Dc()->cancel($this->str($service['vendor_package_key'] ?? ''));
        }
        if ($category !== 'isp') {
            return ['ok' => false, 'error' => 'Cancel is supported only for ISP/DC services.'];
        }
        return Sogerien::API()->InfaticaIo()->Isp()->cancel($this->str($service['vendor_package_key'] ?? ''));
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_uncancel(array $service): ?array
    {
        if ($this->service_category($service) !== 'isp') {
            return ['ok' => false, 'error' => 'Uncancel is supported only for ISP services.'];
        }
        return Sogerien::API()->InfaticaIo()->Isp()->uncancel($this->str($service['vendor_package_key'] ?? ''));
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_add_traffic_gb(array $service, float $addGb, bool $resume): ?array
    {
        $api = $this->traffic_provider_api($service);
        if (!is_object($api) || !method_exists($api, 'add_traffic_bytes')) {
            return ['ok' => false, 'error' => 'Traffic topup is not supported for this service type.'];
        }
        return $api->add_traffic_bytes($this->str($service['vendor_package_key'] ?? ''), $this->gb_to_bytes($addGb), $resume);
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_set_traffic_limit_gb(array $service, float $limitGb, string $expiresAt): ?array
    {
        $api = $this->traffic_provider_api($service);
        if (!is_object($api) || !method_exists($api, 'set_traffic_limit_bytes')) {
            return ['ok' => false, 'error' => 'Traffic limit is not supported for this service type.'];
        }
        return $api->set_traffic_limit_bytes($this->str($service['vendor_package_key'] ?? ''), $this->gb_to_bytes($limitGb), $expiresAt);
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_generate_access(array $service, string $packageKey, string $name, string $login, string $password, string $country, int $rotation, int $format): ?array
    {
        $api = $this->traffic_provider_api($service);
        if (!is_object($api) || !method_exists($api, 'generate_access')) {
            return ['ok' => false, 'error' => 'Access list generation is not supported for this service type.'];
        }
        return $api->generate_access($packageKey, $name, $login, $password, $country, $rotation, $format);
    }

    /**
     * @param array<string,mixed> $service
     * @param array<string,mixed> $options
     * @return array<mixed>|null
     */
    private function provider_generate_access_from_options(array $service, string $packageKey, array $options): ?array
    {
        $api = $this->traffic_provider_api($service);
        if (!is_object($api)) {
            return ['ok' => false, 'error' => 'Access list generation is not supported for this service type.'];
        }
        if (method_exists($api, 'generate_access_from_options')) {
            return $api->generate_access_from_options($packageKey, $options);
        }
        if (!method_exists($api, 'generate_access')) {
            return ['ok' => false, 'error' => 'Access list generation is not supported for this service type.'];
        }
        return $api->generate_access(
            $packageKey,
            $this->str($options['list_name'] ?? ''),
            $this->str($options['login'] ?? ''),
            $this->str($options['password'] ?? ''),
            $this->first_country($options['countries'] ?? $options['country'] ?? ''),
            (int)($options['rotation_period'] ?? 0),
            (int)($options['format'] ?? 3)
        );
    }

    /**
     * @param array<string,mixed> $service
     * @return array<mixed>|null
     */
    private function provider_remove_access(array $service, string $packageKey, string $listId, string $listName): ?array
    {
        $api = $this->traffic_provider_api($service);
        if (!is_object($api) || !method_exists($api, 'remove_access')) {
            return ['ok' => false, 'error' => 'Access list removal is not supported for this service type.'];
        }
        return $api->remove_access($packageKey, $listId, $listName);
    }

    private function gb_to_bytes(float $gb): int
    {
        return (int)round($gb * 1024 * 1024 * 1024);
    }

    private function ensure_storage(): void
    {
        $this->sql("
            CREATE TABLE IF NOT EXISTS sogerien (
                id bigserial PRIMARY KEY,
                table_name text NOT NULL DEFAULT '',
                table_index text NOT NULL DEFAULT '',
                name text NOT NULL DEFAULT '',
                status text NOT NULL DEFAULT 'active',
                table_value jsonb NOT NULL DEFAULT '{}'::jsonb,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        ", []);
        $this->sql("CREATE INDEX IF NOT EXISTS sogerien_table_lookup_idx ON sogerien (table_name, table_index)", []);
    }

    /**
     * @return array<string,mixed>
     */
    private function user_billing_profile(int $user_id): array
    {
        $users = Sogerien::Users();
        $users->init_db_alias($this->db_alias);
        $row = $users->get_user_by_id($user_id);
        $value = is_array($row) ? ($row['table_value'] ?? []) : [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }

        $customerId = $this->str($value['stripe_customer_id'] ?? '');
        $paymentMethodId = $this->str($value['billing_default_payment_method_id'] ?? '');
        $methods = is_array($value['payment_methods'] ?? null) ? $value['payment_methods'] : [];

        if ($paymentMethodId === '') {
            $first = '';
            foreach ($methods as $method) {
                if (!is_array($method)) {
                    continue;
                }
                $id = $this->str($method['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                if ($first === '') {
                    $first = $id;
                }
                if ($this->str($method['is_default'] ?? '') === '1') {
                    $paymentMethodId = $id;
                    break;
                }
            }
            if ($paymentMethodId === '') {
                $paymentMethodId = $first;
            }
        }

        $autopay = $this->str($value['billing_autopay_enabled'] ?? '');
        $enabled = $autopay === '' || $autopay === '1' || strtolower($autopay) === 'true';

        if (!$enabled) {
            return ['ok' => false, 'error' => 'Autopay is disabled for this client.'];
        }
        if ($customerId === '') {
            return ['ok' => false, 'error' => 'Stripe customer is missing.'];
        }
        if ($paymentMethodId === '') {
            return ['ok' => false, 'error' => 'Default Stripe payment method is missing.'];
        }

        return [
            'ok' => true,
            'stripe_customer_id' => $customerId,
            'payment_method_id' => $paymentMethodId,
            'user' => $value,
        ];
    }

    private function payment_intent_attempt_status(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded' => 'succeeded',
            'processing', 'requires_capture' => 'processing',
            'requires_action', 'requires_payment_method', 'requires_confirmation' => 'action_required',
            'canceled' => 'canceled',
            default => $stripeStatus !== '' ? $stripeStatus : 'unknown',
        };
    }

    private function stripe_failure_category(string $code, string $declineCode): string
    {
        $code = strtolower(trim($code));
        $declineCode = strtolower(trim($declineCode));
        if ($code === 'authentication_required') {
            return 'authentication_required';
        }
        if ($declineCode === 'insufficient_funds') {
            return 'insufficient_funds';
        }
        if ($code === 'card_declined' || $declineCode !== '') {
            return 'card_declined';
        }
        return $code !== '' ? $code : 'stripe_error';
    }

    /**
     * @return array<string,string|int>
     */
    private function stripe_error_snapshot(APIStripe $stripe): array
    {
        return [
            'http_code' => $stripe->last_http_code,
            'type' => $stripe->last_error_type,
            'code' => $stripe->last_error_code,
            'decline_code' => $stripe->last_error_decline_code,
            'message' => $stripe->last_error_message,
            'param' => $stripe->last_error_param,
            'advice_code' => $stripe->last_error_advice_code,
            'network_advice_code' => $stripe->last_error_network_advice_code,
            'network_decline_code' => $stripe->last_error_network_decline_code,
            'doc_url' => $stripe->last_error_doc_url,
            'request_log_url' => $stripe->last_error_request_log_url,
            'request_id' => $stripe->last_request_id,
        ];
    }

    private function payment_intent_id_from_stripe_error(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }
        $error = $decoded['error'] ?? null;
        if (!is_array($error)) {
            return '';
        }
        $paymentIntent = $error['payment_intent'] ?? null;
        if (is_array($paymentIntent)) {
            return $this->str($paymentIntent['id'] ?? '');
        }
        return '';
    }

    /**
     * @param array<string,mixed> $value
     */
    private function insert_row(string $table_name, string $table_index, string $name, array $value): bool
    {
        $resp = $this->sql("
            INSERT INTO sogerien (table_name, table_index, name, status, table_value, created_at, updated_at)
            VALUES (:table_name, to_jsonb(:table_index::text), :name, 'active', :table_value::jsonb, now(), now())
        ", [
            'table_name' => $table_name,
            'table_index' => $table_index,
            'name' => $name,
            'table_value' => $value,
        ]);
        return ($resp['result'] ?? false) === true && (int)($resp['rowCount'] ?? 0) > 0;
    }

    /**
     * @param array<string,mixed> $value
     */
    private function update_json_row(string $table_name, string $table_index, array $value): void
    {
        $this->sql("
            UPDATE sogerien
            SET table_value = :table_value::jsonb, updated_at = now()
            WHERE id = (
                SELECT id FROM sogerien
                WHERE table_name = :table_name
                  AND status <> 'delete'
                  AND (
                      table_index::text = :table_index
                      OR table_index::text = to_jsonb(:table_index::text)::text
                      OR name = :table_index
                      OR table_value->>'service_id' = :table_index
                      OR table_value->>'vendor_package_key' = :table_index
                      OR table_value->>'order_id' = :table_index
                      OR table_value->>'payment_id' = :table_index
                      OR table_value->>'payment_intent_id' = :table_index
                  )
                ORDER BY id DESC
                LIMIT 1
            )
        ", [
            'table_name' => $table_name,
            'table_index' => $table_index,
            'table_value' => $value,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function load_one(string $table_name, string $table_index): ?array
    {
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = :table_name
              AND status <> 'delete'
              AND (
                  table_index::text = :table_index
                  OR table_index::text = to_jsonb(:table_index::text)::text
                  OR name = :table_index
                  OR table_value->>'service_id' = :table_index
                  OR table_value->>'vendor_package_key' = :table_index
                  OR table_value->>'order_id' = :table_index
                  OR table_value->>'payment_id' = :table_index
                  OR table_value->>'payment_intent_id' = :table_index
              )
            ORDER BY id DESC
            LIMIT 1
        ", ['table_name' => $table_name, 'table_index' => $table_index]);
        $row = ($resp['rows'] ?? [])[0] ?? null;
        return is_array($row) && isset($row['table_value']) && is_array($row['table_value']) ? $row['table_value'] : null;
    }

    /**
     * @param array<string,mixed> $resp
     * @return array<int,array<string,mixed>>
     */
    private function extract_value_rows(array $resp): array
    {
        $rows = [];
        foreach (($resp['rows'] ?? []) as $row) {
            if (isset($row['table_value']) && is_array($row['table_value'])) {
                $rows[] = $row['table_value'];
            }
        }
        return $rows;
    }

    private function normalize_table_index(mixed $value): string
    {
        $index = $this->str($value);
        if (strlen($index) >= 2 && $index[0] === '"' && substr($index, -1) === '"') {
            $decoded = json_decode($index, true);
            if (is_string($decoded)) {
                return trim($decoded);
            }
        }
        return $index;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function sql(string $sql, array $params): array
    {
        $json = Sogerien::DbController()->sql_request($this->db_alias, ['sql' => $sql, 'params' => $params]);
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : ['result' => false, 'rows' => []];
    }

    private function str(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return trim((string)$value);
        }
        return '';
    }

    private function money_float(mixed $value): float
    {
        $raw = str_replace(',', '.', $this->str($value));
        return is_numeric($raw) ? (float)$raw : 0.0;
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $service
     * @return array<string,mixed>
     */
    private function proxy_access_options_from_request(array $request, array $service): array
    {
        $options = [];
        $copyString = [
            'list_name' => ['list_name', 'proxy-list-name', 'name'],
            'login' => ['login', 'proxy-list-login'],
            'password' => ['password', 'proxy-list-password'],
            'network' => ['network', 'whitelist', 'proxy-list-ip', 'proxy-list-network'],
            'region' => ['region', 'proxy-list-region'],
            'city' => ['city', 'proxy-list-city'],
            'isp' => ['isp', 'proxy-list-isp'],
            'isp_id' => ['isp_id'],
            'zip' => ['zip', 'proxy-zip-code', 'proxy-list-zip'],
            'rotation_period' => ['rotation_period', 'proxy-list-rotation-period'],
            'rotation_mode' => ['rotation_mode', 'proxy-list-rotation-mode'],
            'format' => ['format', 'proxy-list-format'],
            'protocol' => ['protocol'],
        ];

        foreach ($copyString as $target => $keys) {
            foreach ($keys as $key) {
                $value = $this->str($request[$key] ?? '');
                if ($value !== '' && strtolower($value) !== 'all') {
                    $options[$target] = $value;
                    break;
                }
            }
        }

        $authMode = strtolower(str_replace([' ', '-', '/'], '_', $this->str($request['auth_mode'] ?? $request['proxy-list-preset-auth'] ?? $request['proxy-list-auth'] ?? 'login_password')));
        if ($authMode === 'ip_whitelist' || $authMode === 'ip_whitelist_only' || $authMode === 'ip') {
            $options['auth_mode'] = 'ip_whitelist';
        } else {
            $options['auth_mode'] = 'login_password';
        }

        $countryRaw = $request['countries'] ?? $request['country'] ?? $request['proxy-list-country'] ?? $request['proxy-list-country[]'] ?? ($service['country'] ?? '');
        $countries = $this->proxy_access_countries($countryRaw);
        if ($countries !== []) {
            $options['countries'] = $countries;
            $options['country'] = count($countries) === 1 ? $countries[0] : $countries;
        }

        if (($options['auth_mode'] ?? 'login_password') === 'ip_whitelist') {
            unset($options['login'], $options['password']);
        }

        return $options;
    }

    /**
     * @return array<int,string>
     */
    private function proxy_access_countries(mixed $value): array
    {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    continue;
                }
                $items[] = $this->str($item);
            }
        } else {
            $items = preg_split('/[\s,;]+/', strtoupper($this->str($value))) ?: [];
        }

        $countries = [];
        foreach ($items as $item) {
            $country = strtoupper($this->str($item));
            if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
                $countries[] = $country;
            }
        }
        return array_values(array_unique($countries));
    }

    private function proxy_access_protocol_from_format(mixed $format, string $protocol): string
    {
        $protocol = strtolower($this->str($protocol));
        if ($protocol !== '') {
            return $protocol;
        }
        $format = $this->str($format);
        if ($format === '3') {
            return 'http';
        }
        if ($format === '4') {
            return 'socks5';
        }
        return 'mixed';
    }

    /**
     * @return array<int,string>
     */
    private function infatica_access_location_values(string $category, string $method, string $country, string $region): array
    {
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            return [];
        }
        $api = $this->traffic_provider_api(['provider_pool_category' => $category]);
        if (!is_object($api) || !method_exists($api, $method)) {
            return [];
        }
        try {
            $raw = $method === 'cities' ? $api->cities($country, $region) : $api->regions($country);
        } catch (Throwable) {
            return [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $values = [];
        foreach ($raw as $key => $value) {
            $label = $this->str(is_string($value) || is_numeric($value) ? $value : $key);
            if ($label !== '') {
                $values[] = $label;
            }
        }
        $values = array_values(array_unique($values));
        sort($values);
        return $values;
    }

    /**
     * @param array<string,mixed> $service
     * @param array<mixed>|null $response
     * @param array<mixed>|null $trafficDetails
     */
    private function sync_provider_proxy_lists(array &$service, string $packageKey, ?array $response, object $api, ?array $trafficDetails = null): void
    {
        $providerLists = isset($response['results']) && is_array($response['results']) ? $response['results'] : [];
        if ($providerLists === []) {
            return;
        }
        $stored = isset($service['proxy_lists']) && is_array($service['proxy_lists']) ? $service['proxy_lists'] : [];
        $storedById = [];
        foreach ($stored as $list) {
            if (is_array($list)) {
                $storedById[$this->str($list['vendor_list_id'] ?? $list['id'] ?? '')] = $list;
            }
        }
        $synced = [];
        foreach ($providerLists as $providerList) {
            if (!is_array($providerList)) {
                continue;
            }
            $id = $this->str($providerList['id'] ?? '');
            $geo = isset($providerList['geo'][0]) && is_array($providerList['geo'][0]) ? $providerList['geo'][0] : [];
            $synced[] = array_merge($storedById[$id] ?? [], [
                'id' => $id,
                'vendor_list_id' => $id,
                'package_key' => $packageKey,
                'name' => $this->str($providerList['name'] ?? ''),
                'login' => $this->str($providerList['login'] ?? ''),
                'password' => $this->str($providerList['password'] ?? ''),
                'country' => $this->str($geo['country'] ?? ''),
                'region' => $this->str($geo['region'] ?? ''),
                'city' => $this->str($geo['city'] ?? ''),
                'format' => $this->str($providerList['format'] ?? ''),
                'traffic_used_gb' => number_format($this->proxy_list_traffic_used_gb($providerList, $trafficDetails), 4, '.', ''),
                'status' => 'active',
                'provider_synced_at' => date('c'),
            ]);
        }
        if ($synced === []) {
            return;
        }
        $service['proxy_lists'] = $synced;
        $primary = $synced[0];
        if (method_exists($api, 'core')) {
            $core = $api->core();
            $service['connection_host'] = $this->str($core->shared_proxy_host ?? '');
            $service['connection_port'] = $this->str($core->shared_proxy_port ?? '');
        }
        $service['connection_login'] = $this->str($primary['login'] ?? '');
        $service['connection_password'] = $this->str($primary['password'] ?? '');
    }

    /**
     * @param array<mixed> $providerList
     * @param array<mixed>|null $trafficDetails
     */
    private function proxy_list_traffic_used_gb(array $providerList, ?array $trafficDetails): float
    {
        $trafficUsageBytes = $this->nested_number($providerList, ['traffic_usage', 'common']);
        if ($trafficUsageBytes > 0.0) {
            return round($this->bytes_to_gb($trafficUsageBytes), 4);
        }
        $flat = [];
        $this->flatten_assoc($providerList, '', $flat);
        $used = $this->first_flat_number_gb($flat, ['traffic_used', 'used_traffic', 'used_gb']);
        if ($used > 0.0) {
            return round($used, 4);
        }

        $login = $this->str($providerList['login'] ?? '');
        $rows = is_array($trafficDetails) && isset($trafficDetails['results']) && is_array($trafficDetails['results'])
            ? $trafficDetails['results']
            : ($trafficDetails ?? []);
        if ($login === '' || !isset($rows[$login]) || !is_array($rows[$login])) {
            return 0.0;
        }
        $bytes = 0.0;
        foreach ($rows[$login] as $rawBytes) {
            if (is_numeric($rawBytes)) {
                $bytes += (float)$rawBytes;
            }
        }
        return round($this->bytes_to_gb($bytes), 4);
    }

    /**
     * @param array<mixed> $raw
     * @return array{countries:array<string,string>,regions:array<string,string>,cities:array<string,string>}
     */
    private function normalize_access_geo_options(array $raw, string $category): array
    {
        $countries = [];
        $regions = [];
        $cities = [];
        $this->collect_infatica_geo_options($raw, $countries, $regions, $cities);
        $this->collect_access_geo_options($raw, $countries, $regions, $cities);
        ksort($countries);
        asort($regions);
        asort($cities);

        if ($countries === [] && isset($raw['countries']) && is_array($raw['countries'])) {
            foreach ($raw['countries'] as $code => $label) {
                $country = is_string($code) && preg_match('/^[A-Za-z]{2}$/', $code) === 1 ? strtoupper($code) : $this->first_country($label);
                if ($country !== '') {
                    $countries[$country] = is_string($label) && trim($label) !== '' ? trim($label) : $country;
                }
            }
        }

        if ($countries === []) {
            return $this->fallback_access_geo_options($category);
        }

        return ['countries' => $countries, 'regions' => $regions, 'cities' => $cities];
    }

    /**
     * @param array<mixed> $raw
     * @param array<string,string> $countries
     * @param array<string,string> $regions
     * @param array<string,string> $cities
     */
    private function collect_infatica_geo_options(array $raw, array &$countries, array &$regions, array &$cities): void
    {
        foreach ($raw as $countryNode) {
            if (!is_array($countryNode)) {
                continue;
            }
            $country = $this->first_country($countryNode['code'] ?? $countryNode['country'] ?? '');
            if ($country !== '') {
                $label = $this->str($countryNode['name'] ?? $countryNode['country_name'] ?? $country);
                $countries[$country] = $label !== '' && preg_match('/^[A-Z]{2}$/', $label) !== 1 ? $label : $country;
            }
            foreach (($countryNode['regions'] ?? []) as $regionNode) {
                if (!is_array($regionNode)) {
                    continue;
                }
                $region = $this->str($regionNode['name'] ?? $regionNode['region'] ?? '');
                if ($region !== '') {
                    $regions[$region] = $region;
                }
                foreach (($regionNode['cities'] ?? []) as $cityNode) {
                    if (!is_array($cityNode)) {
                        continue;
                    }
                    $city = $this->str($cityNode['name'] ?? $cityNode['city'] ?? '');
                    if ($city !== '') {
                        $cities[$city] = $city;
                    }
                }
            }
        }
    }

    /**
     * @param array<mixed> $node
     * @param array<string,string> $countries
     * @param array<string,string> $regions
     * @param array<string,string> $cities
     */
    private function collect_access_geo_options(array $node, array &$countries, array &$regions, array &$cities): void
    {
        $country = $this->first_country($node['country'] ?? $node['country_code'] ?? $node['code'] ?? $node['iso'] ?? $node['location_country_code'] ?? '');
        if ($country !== '') {
            $label = $this->str($node['country_name'] ?? $node['name'] ?? $node['title'] ?? $country);
            $countries[$country] = $label !== '' && preg_match('/^[A-Z]{2}$/', $label) !== 1 ? $label : $country;
        }

        $region = $this->str($node['region'] ?? $node['region_name'] ?? $node['state'] ?? $node['subdivision'] ?? '');
        if ($region !== '') {
            $regions[$region] = $region;
        }

        $city = $this->str($node['city'] ?? $node['city_name'] ?? '');
        if ($city !== '') {
            $cities[$city] = $city;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collect_access_geo_options($value, $countries, $regions, $cities);
            }
        }
    }

    /**
     * @return array{countries:array<string,string>,regions:array<string,string>,cities:array<string,string>}
     */
    private function fallback_access_geo_options(string $category): array
    {
        $map = [
            'residential' => ['BR' => 'Brazil', 'CA' => 'Canada', 'CO' => 'Colombia', 'ES' => 'Spain', 'FR' => 'France', 'GB' => 'United Kingdom', 'RU' => 'Russia', 'UA' => 'Ukraine', 'US' => 'United States'],
            'residential_ipv6' => ['BR' => 'Brazil', 'CA' => 'Canada', 'CO' => 'Colombia', 'ES' => 'Spain', 'FR' => 'France', 'GB' => 'United Kingdom', 'RU' => 'Russia', 'UA' => 'Ukraine', 'US' => 'United States'],
            'mobile' => ['CN' => 'China', 'IN' => 'India', 'IT' => 'Italy', 'KZ' => 'Kazakhstan', 'MY' => 'Malaysia', 'PL' => 'Poland', 'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'US' => 'United States'],
            'isp' => ['AT' => 'Austria', 'BR' => 'Brazil', 'CA' => 'Canada', 'FR' => 'France', 'JP' => 'Japan', 'LV' => 'Latvia', 'RO' => 'Romania', 'UA' => 'Ukraine'],
            'dc' => ['BR' => 'Brazil', 'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France', 'GB' => 'United Kingdom', 'NL' => 'Netherlands', 'US' => 'United States'],
        ];
        return ['countries' => $map[$category] ?? $map['residential'], 'regions' => [], 'cities' => []];
    }

    private function normalize_amount_usd(mixed $amount, mixed $total_cents): string
    {
        $money = $this->money_float($amount);
        if ($money <= 0.0) {
            $cents = (int)$this->money_float($total_cents);
            if ($cents > 0) {
                $money = $cents / 100;
            }
        }
        return number_format($money, 2, '.', '');
    }

    private function compact_number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array{price_per_ip:string,price_usd:string,provider_unit_price_usd:string,provider_cost_usd:string,profit_usd:string}|null
     */
    private function price_isp_item(int $ipCount, int $days): ?array
    {
        return $this->price_static_ip_item('isp', $ipCount, $days);
    }

    /**
     * @return array{price_per_ip:string,price_usd:string,provider_unit_price_usd:string,provider_cost_usd:string,profit_usd:string}|null
     */
    private function price_static_ip_item(string $category, int $ipCount, int $days): ?array
    {
        $catalog = Sogerien::API()->InfaticaIo()->Catalog();
        $retail = $catalog->retail_pricing();
        $cost = $catalog->cost_pricing();
        $category = in_array($category, ['isp', 'dc'], true) ? $category : 'isp';
        $retailTiers = isset($retail[$category]) && is_array($retail[$category]) ? $retail[$category] : [];
        if ($retailTiers === []) {
            return null;
        }
        $costTiers = isset($cost[$category]) && is_array($cost[$category]) ? $cost[$category] : [];
        $pricePerIp = $this->tier_price_for_count($retailTiers, $ipCount);
        if ($pricePerIp <= 0.0) {
            return null;
        }
        $costPerIp = $this->tier_price_for_count($costTiers, $ipCount);
        $termMultiplier = match ($days) {
            90 => 3.0,
            180 => 6.0,
            364 => 12.0,
            default => 1.0,
        };
        $price = $ipCount * $pricePerIp * $termMultiplier;
        $providerCost = $costPerIp > 0.0 ? $ipCount * $costPerIp * $termMultiplier : 0.0;
        return [
            'price_per_ip' => number_format($pricePerIp, 2, '.', ''),
            'price_usd' => number_format($price, 2, '.', ''),
            'provider_unit_price_usd' => $costPerIp > 0.0 ? number_format($costPerIp, 4, '.', '') : '',
            'provider_cost_usd' => $providerCost > 0.0 ? number_format($providerCost, 2, '.', '') : '',
            'profit_usd' => $providerCost > 0.0 ? number_format($price - $providerCost, 2, '.', '') : '',
        ];
    }

    /**
     * @param array<int|string,float|int|string> $tiers
     */
    private function tier_price_for_count(array $tiers, int $count): float
    {
        if ($tiers === [] || $count <= 0) {
            return 0.0;
        }
        ksort($tiers, SORT_NUMERIC);
        $selected = 0.0;
        foreach ($tiers as $threshold => $price) {
            if (!is_numeric((string)$threshold) || !is_numeric((string)$price)) {
                continue;
            }
            if ((int)$threshold <= $count) {
                $selected = (float)$price;
            }
        }
        if ($selected <= 0.0) {
            $first = reset($tiers);
            $selected = is_numeric((string)$first) ? (float)$first : 0.0;
        }
        return $selected;
    }

    /**
     * @param array<int,mixed> $items
     */
    private function charge_title(array $items): string
    {
        foreach ($items as $item) {
            if (is_array($item)) {
                $title = $this->str($item['title'] ?? $item['name'] ?? '');
                if ($title !== '') {
                    return $title;
                }
            }
        }
        return 'Proxy order';
    }

    private function first_country(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_array($item)) {
                    $country = strtoupper($this->str($item));
                    if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
                        return $country;
                    }
                }
            }
        }
        $raw = strtoupper($this->str($value));
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        foreach ($parts as $part) {
            if (preg_match('/^[A-Z]{2}$/', $part) === 1) {
                return $part;
            }
        }
        return 'US';
    }

    /**
     * @return array<string,mixed>
     */
    private function provider_inventory_for_category(string $category, bool $refresh = false): array
    {
        $category = strtolower($this->str($category));
        $cacheFile = 'proxy_shop/provider_inventory_' . $category . '.json';
        $cache = Sogerien::Cache();
        $updatedAt = null;
        $cached = $cache->load($cacheFile, $updatedAt);

        if (!$refresh && is_array($cached) && !$cache->is_interval_elapsed($cacheFile, 300)) {
            return $cached;
        }

        $provider = $this->load_provider_inventory_for_category($category);
        if (($provider['ok'] ?? false) === true) {
            $cache->save($provider, $cacheFile, time());
            return $provider;
        }

        if (is_array($cached)) {
            $cached['state'] = trim((string)($cached['state'] ?? '') . '; stale cache', '; ');
            $cached['message'] = (string)($provider['message'] ?? 'Provider refresh failed');
            return $cached;
        }

        return $provider;
    }

    /**
     * @return array<string,mixed>
     */
    private function load_provider_inventory_for_category(string $category): array
    {
        $category = strtolower($this->str($category));
        if ($category === 'mobile') {
            $api = Sogerien::API()->InfaticaIo()->Mobile();
            return $this->provider_traffic_inventory_from_stats($api->reseller_stats(), $api->packages());
        }
        if ($category === 'residential') {
            $api = Sogerien::API()->InfaticaIo()->Residential();
            return $this->provider_traffic_inventory_from_stats($api->reseller_stats(), $api->packages());
        }
        if ($category === 'residential_ipv6') {
            return [
                'ok' => true,
                'has_traffic' => false,
                'message' => 'No separate provider package sync',
                'state' => 'No separate package',
            ];
        }
        if ($category === 'isp') {
            $balance = Sogerien::API()->InfaticaIo()->Isp()->balance();
            if (!is_array($balance)) {
                return ['ok' => false, 'has_traffic' => false, 'message' => 'ISP balance sync failed', 'state' => 'API error'];
            }
            $left = $this->first_number_from_array($balance, ['balance']);
            return [
                'ok' => true,
                'has_traffic' => false,
                'limit_gb' => 0.0,
                'used_gb' => 0.0,
                'left_gb' => 0.0,
                'packages' => 0,
                'state' => 'Available IPs: ' . rtrim(rtrim(number_format($left, 2, '.', ''), '0'), '.'),
                'raw' => $balance,
            ];
        }
        if ($category === 'dc') {
            $balance = Sogerien::API()->InfaticaIo()->Dc()->balance();
            if (!is_array($balance)) {
                return ['ok' => false, 'has_traffic' => false, 'message' => 'DC balance sync failed', 'state' => 'API error'];
            }
            $left = $this->first_number_from_array($balance, ['balance']);
            return [
                'ok' => true,
                'has_traffic' => false,
                'limit_gb' => 0.0,
                'used_gb' => 0.0,
                'left_gb' => 0.0,
                'packages' => 0,
                'state' => 'DC balance: $' . rtrim(rtrim(number_format($left, 2, '.', ''), '0'), '.'),
                'raw' => $balance,
            ];
        }
        if ($category === 'scraper') {
            return [
                'ok' => true,
                'has_traffic' => false,
                'message' => 'Requests-based product',
                'state' => 'Requests',
            ];
        }

        return ['ok' => false, 'has_traffic' => false, 'message' => 'Unknown provider category', 'state' => 'Unknown'];
    }

    /**
     * @param array<mixed>|null $source
     * @return array<string,mixed>
     */
    private function provider_traffic_inventory_from_packages(?array $source): array
    {
        if (!is_array($source)) {
            return ['ok' => false, 'has_traffic' => true, 'message' => 'Provider package sync failed', 'state' => 'API error'];
        }

        $items = $this->extract_provider_package_items($source);
        if ($items === []) {
            return [
                'ok' => true,
                'has_traffic' => true,
                'limit_gb' => 0.0,
                'used_gb' => 0.0,
                'left_gb' => 0.0,
                'packages' => 0,
                'package_keys' => [],
                'state' => 'No packages',
            ];
        }

        $limitBytes = 0.0;
        $usedBytes = 0.0;
        $packageKeys = [];
        $suspended = 0;
        $active = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $limitBytes += $this->nested_number($item, ['traffic_limits', 'common']);
            $usedBytes += $this->nested_number($item, ['traffic_usage', 'common']);
            $key = $this->first_string_from_array($item, ['package_key', 'key', 'id']);
            if ($key !== '') {
                $packageKeys[] = $key;
            }
            if (!empty($item['is_suspended'])) {
                $suspended++;
            }
            if (!empty($item['is_active'])) {
                $active++;
            }
        }

        $limitGb = $this->bytes_to_gb($limitBytes);
        $usedGb = $this->bytes_to_gb($usedBytes);
        $leftGb = max(0.0, $limitGb - $usedGb);
        $state = 'Packages: ' . count($items);
        if ($active > 0 || $suspended > 0) {
            $state .= ', active: ' . $active . ', suspended: ' . $suspended;
        }

        return [
            'ok' => true,
            'has_traffic' => true,
            'limit_gb' => $limitGb,
            'used_gb' => $usedGb,
            'left_gb' => $leftGb,
            'packages' => count($items),
            'package_keys' => array_values(array_unique($packageKeys)),
            'is_suspended' => $suspended > 0 && $active === 0,
            'state' => $state,
        ];
    }

    /**
     * @param array<mixed>|null $stats
     * @param array<mixed>|null $packages
     * @return array<string,mixed>
     */
    private function provider_traffic_inventory_from_stats(?array $stats, ?array $packages = null): array
    {
        $packageInventory = $this->provider_traffic_inventory_from_packages($packages);
        if (!is_array($stats)) {
            if (($packageInventory['ok'] ?? false) === true) {
                $packageInventory['state'] = trim('Package fallback; ' . (string)($packageInventory['state'] ?? ''), '; ');
                return $packageInventory;
            }
            return ['ok' => false, 'has_traffic' => true, 'message' => 'Provider stats sync failed', 'state' => 'API /stats error'];
        }

        $row = isset($stats['results']) && is_array($stats['results']) ? $stats['results'] : $stats;
        $totalRaw = $this->first_number_from_array($row, ['traffic_total', 'traffic_limit', 'total_traffic', 'limit']);
        $usedRaw = $this->first_number_from_array($row, ['traffic_used', 'used_traffic', 'traffic_spent', 'spent_traffic']);
        $leftRaw = $this->first_number_from_array($row, ['traffic_left', 'traffic_remaining', 'left_traffic', 'available_traffic']);

        $totalGb = $this->traffic_amount_to_gb($totalRaw);
        $usedGb = $this->traffic_amount_to_gb($usedRaw);
        $leftGb = $this->traffic_amount_to_gb($leftRaw);
        if ($leftGb <= 0.0 && $totalGb > 0.0) {
            $leftGb = max(0.0, $totalGb - $usedGb);
        }
        if ($usedGb <= 0.0 && $totalGb > 0.0 && $leftGb > 0.0 && $leftGb < $totalGb) {
            $usedGb = max(0.0, $totalGb - $leftGb);
        }

        $packageState = (string)($packageInventory['state'] ?? '');
        $state = '/stats';
        if ($packageState !== '') {
            $state .= '; ' . $packageState;
        }

        return [
            'ok' => true,
            'has_traffic' => true,
            'limit_gb' => $totalGb,
            'used_gb' => $usedGb,
            'left_gb' => $leftGb,
            'packages' => (int)($packageInventory['packages'] ?? 0),
            'package_keys' => $packageInventory['package_keys'] ?? [],
            'is_suspended' => (bool)($packageInventory['is_suspended'] ?? false),
            'state' => $state,
            'raw' => $stats,
        ];
    }

    /**
     * @param array<mixed> $source
     * @return array<int,array<mixed>>
     */
    private function extract_provider_package_items(array $source): array
    {
        foreach ([['results'], ['data', 'packages'], ['packages'], ['data']] as $path) {
            $value = $source;
            foreach ($path as $key) {
                if (!is_array($value) || !isset($value[$key])) {
                    $value = null;
                    break;
                }
                $value = $value[$key];
            }
            if (!is_array($value)) {
                continue;
            }
            if ($this->is_list_array($value)) {
                return array_values(array_filter($value, 'is_array'));
            }
            if ($this->looks_like_provider_package($value)) {
                return [$value];
            }
        }

        if ($this->is_list_array($source)) {
            return array_values(array_filter($source, 'is_array'));
        }
        return $this->looks_like_provider_package($source) ? [$source] : [];
    }

    /**
     * @param array<mixed> $value
     */
    private function looks_like_provider_package(array $value): bool
    {
        return isset($value['package_key'])
            || isset($value['traffic_limits'])
            || isset($value['traffic_usage'])
            || isset($value['expired_at']);
    }

    /**
     * @param array<mixed> $value
     */
    private function is_list_array(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param array<mixed> $source
     * @param array<int,string> $path
     */
    private function nested_number(array $source, array $path): float
    {
        $value = $source;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return 0.0;
            }
            $value = $value[$key];
        }
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }
        if (is_string($value)) {
            $raw = str_replace(',', '.', trim($value));
            return is_numeric($raw) ? (float)$raw : 0.0;
        }
        return 0.0;
    }

    /**
     * @param array<mixed> $source
     * @param array<int,string> $keys
     */
    private function first_number_from_array(array $source, array $keys): float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if (is_int($value) || is_float($value)) {
                return (float)$value;
            }
            if (is_string($value)) {
                $raw = str_replace(',', '.', trim($value));
                if (is_numeric($raw)) {
                    return (float)$raw;
                }
            }
        }
        return 0.0;
    }

    /**
     * @param array<mixed> $source
     * @param array<int,string> $keys
     */
    private function first_string_from_array(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? '';
            if (is_scalar($value)) {
                $out = trim((string)$value);
                if ($out !== '') {
                    return $out;
                }
            }
        }
        return '';
    }

    private function bytes_to_gb(float $bytes): float
    {
        return round($bytes / 1024 / 1024 / 1024, 4);
    }

    private function traffic_amount_to_gb(float $value): float
    {
        if ($value <= 0.0) {
            return 0.0;
        }
        if ($value >= 1024 * 1024) {
            return $this->bytes_to_gb($value);
        }
        return round($value, 4);
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extract_package_key(array $response): string
    {
        foreach (['key', 'package_key', 'service_key', 'package_id', 'id'] as $key) {
            $value = $this->str($response[$key] ?? '');
            if ($value !== '') {
                return $value;
            }
        }
        if (isset($response['data']) && is_array($response['data'])) {
            foreach (['key', 'package_key', 'service_key', 'package_id', 'id'] as $key) {
                $value = $this->str($response['data'][$key] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }
        if (isset($response['results']) && is_array($response['results'])) {
            foreach (['key', 'package_key', 'service_key', 'package_id', 'id'] as $key) {
                $value = $this->str($response['results'][$key] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    /**
     * @param array<string,mixed> $response
     * @return array<int,string>
     */
    private function extract_issued_ips(array $response): array
    {
        $ips = [];
        $this->collect_ips($response, $ips);
        return array_values(array_unique($ips));
    }

    /**
     * @param array<mixed> $value
     * @param array<int,string> $ips
     */
    private function collect_ips(array $value, array &$ips): void
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                $this->collect_ips($item, $ips);
                continue;
            }
            if (!is_scalar($item)) {
                continue;
            }
            $raw = trim((string)$item);
            if (filter_var($raw, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $raw;
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function provider_pool_for_category(string $category): array
    {
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'config' AND name = 'infatica_provider_pools' AND status <> 'delete'
            ORDER BY id DESC
            LIMIT 1
        ", []);
        $row = ($resp['rows'] ?? [])[0] ?? null;
        $config = is_array($row) && isset($row['table_value']) && is_array($row['table_value']) ? $row['table_value'] : null;
        $pools = is_array($config) && isset($config['pools']) && is_array($config['pools']) ? $config['pools'] : [];
        $pool = $pools[$category] ?? null;
        return is_array($pool) ? $pool : [];
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extract_proxy_list_id(array $response): string
    {
        foreach (['id', 'list_id', 'proxy_list_id'] as $key) {
            $value = $this->str($response[$key] ?? '');
            if ($value !== '') {
                return $value;
            }
        }
        if (isset($response['results']) && is_array($response['results'])) {
            foreach (['id', 'list_id', 'proxy_list_id'] as $key) {
                $value = $this->str($response['results'][$key] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }
        if (isset($response['data']) && is_array($response['data'])) {
            foreach (['id', 'list_id', 'proxy_list_id'] as $key) {
                $value = $this->str($response['data'][$key] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }
}
