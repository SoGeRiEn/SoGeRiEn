<?php
declare(strict_types=1);

final class APIStripe
{
    public bool $status = false;
    public string $error = '';

    public string $api_key = '';
    public string $base_url = 'https://api.stripe.com';
    public string $stripe_account = '';
    public string $stripe_version = '';

    public bool $debug_enabled = true;
    public array $debug = [];
    public array $debug_history = [];

    public int $last_http_code = 0;
    public string $last_url = '';
    public string $last_request_id = '';
    public string $last_response_raw = '';
    public string $last_error_type = '';
    public string $last_error_code = '';
    public string $last_error_decline_code = '';
    public string $last_error_message = '';
    public string $last_error_param = '';
    public string $last_error_advice_code = '';
    public string $last_error_network_advice_code = '';
    public string $last_error_network_decline_code = '';
    public string $last_error_doc_url = '';
    public string $last_error_request_log_url = '';

    public int $request_connect_timeout_seconds = 10;
    public int $request_timeout_seconds = 45;

    /** @var array<string,bool> */
    private const ACCOUNT_LINK_TYPES = [
        'account_onboarding' => true,
        'account_update' => true,
    ];

    /** @var array<string,bool> */
    private const ACCOUNT_REJECT_REASONS = [
        'fraud' => true,
        'terms_of_service' => true,
        'other' => true,
    ];

    /** @var array<string,bool> */
    private const V2_ACCOUNT_LINK_USE_CASE_TYPES = [
        'account_onboarding' => true,
        'account_update' => true,
    ];

    /** @var array<string,bool> */
    private const V2_ACCOUNT_LINK_CONFIGURATIONS = [
        'customer' => true,
        'merchant' => true,
        'recipient' => true,
    ];

    /** @var array<string,bool> */
    private const V2_ACCOUNT_LINK_COLLECTION_FIELDS = [
        'currently_due' => true,
        'eventually_due' => true,
    ];

    /** @var array<string,bool> */
    private const V2_ACCOUNT_LINK_FUTURE_REQUIREMENTS = [
        'include' => true,
        'omit' => true,
    ];

    private const STRIPE_V2_BILLING_PREVIEW_VERSION = '2026-02-25.preview';
    private const STRIPE_METER_EVENTS_STREAM_URL = 'https://meter-events.stripe.com/v2/billing/meter_event_stream';

    /**
     * Map of human section aliases to Stripe API collection paths.
     * For preview/beta features you can pass raw path directly to stripe_section_* methods.
     *
     * @var array<string,string>
     */
    private const STRIPE_SECTION_PATHS = [
        'fraud' => '/v1/radar/reviews',
        'early fraud warning' => '/v1/radar/early_fraud_warnings',
        'reviews' => '/v1/radar/reviews',
        'value lists' => '/v1/radar/value_lists',
        'value list items' => '/v1/radar/value_list_items',
        'issuing' => '/v1/issuing/cards',
        'authorizations' => '/v1/issuing/authorizations',
        'cardholders' => '/v1/issuing/cardholders',
        'cards' => '/v1/issuing/cards',
        'disputes (issuing)' => '/v1/issuing/disputes',
        'funding instructions' => '/v1/issuing/funding_instructions',
        'personalization designs' => '/v1/issuing/personalization_designs',
        'physical bundles' => '/v1/issuing/physical_bundles',
        'tokens (issuing)' => '/v1/issuing/tokens',
        'transactions (issuing)' => '/v1/issuing/transactions',
        'terminal' => '/v1/terminal/readers',
        'connection token' => '/v1/terminal/connection_tokens',
        'location' => '/v1/terminal/locations',
        'reader' => '/v1/terminal/readers',
        'terminal hardware order' => '/v1/terminal/hardware_orders',
        'terminal hardware product' => '/v1/terminal/hardware_products',
        'terminal hardware sku' => '/v1/terminal/hardware_skus',
        'terminal hardware shipping method' => '/v1/terminal/hardware_shipping_methods',
        'configuration' => '/v1/terminal/configurations',
        'treasury' => '/v1/treasury/financial_accounts',
        'financial accounts' => '/v1/treasury/financial_accounts',
        'financial account features' => '/v1/treasury/financial_accounts/{financial_account}/features',
        'transactions (treasury)' => '/v1/treasury/transactions',
        'transaction entries' => '/v1/treasury/transaction_entries',
        'outbound transfers' => '/v1/treasury/outbound_transfers',
        'outbound payments' => '/v1/treasury/outbound_payments',
        'inbound transfers' => '/v1/treasury/inbound_transfers',
        'received credits' => '/v1/treasury/received_credits',
        'received debits' => '/v1/treasury/received_debits',
        'credit reversals' => '/v1/treasury/credit_reversals',
        'debit reversals' => '/v1/treasury/debit_reversals',
        'payment records' => '/v1/payment_records',
        'payment attempt records' => '/v1/payment_attempt_records',
        'entitlements' => '/v1/entitlements/active_entitlements',
        'feature' => '/v1/entitlements/features',
        'product feature' => '/v1/products/{product}/features',
        'active entitlement' => '/v1/entitlements/active_entitlements',
        'sigma' => '/v1/sigma/scheduled_query_runs',
        'scheduled queries' => '/v1/sigma/scheduled_query_runs',
        'reporting' => '/v1/reporting/report_runs',
        'report runs' => '/v1/reporting/report_runs',
        'report types' => '/v1/reporting/report_types',
        'report runsv2' => '/v2/reporting/report_runs',
        'reportsv2' => '/v2/reporting/reports',
        'financial connections' => '/v1/financial_connections/accounts',
        'accounts (financial connections)' => '/v1/financial_connections/accounts',
        'account owner' => '/v1/financial_connections/accounts/{account}/owners',
        'authorization (financial connections)' => '/v1/financial_connections/authorizations',
        'session (financial connections)' => '/v1/financial_connections/sessions',
        'transactions (financial connections)' => '/v1/financial_connections/transactions',
        'tax' => '/v1/tax/calculations',
        'tax calculations' => '/v1/tax/calculations',
        'tax registrations' => '/v1/tax/registrations',
        'tax transactions' => '/v1/tax/transactions',
        'tax settings' => '/v1/tax/settings',
        'tax association' => '/v1/tax/associations/find',
        'identity' => '/v1/identity/verification_sessions',
        'verification session' => '/v1/identity/verification_sessions',
        'verification report' => '/v1/identity/verification_reports',
        'crypto' => '/v1/crypto/onramp_sessions',
        'crypto onramp session' => '/v1/crypto/onramp_sessions',
        'crypto onramp quotes' => '/v1/crypto/onramp_quotes',
        'webhooks' => '/v1/webhook_endpoints',
        'webhook endpoints' => '/v1/webhook_endpoints',
        'privacy' => '/v1/privacy/redaction_jobs',
        'redaction job' => '/v1/privacy/redaction_jobs',
        'redaction job validation error' => '/v1/privacy/redaction_job_validation_errors',
    ];

    public function set_api_key(string $api_key): void
    {
        $this->api_key = trim($api_key);
        $this->ok();
    }

    public function set_base_url(string $base_url): void
    {
        $base_url = trim($base_url);
        if ($base_url === '') {
            $this->fail('base_url is empty');
            return;
        }

        $this->base_url = rtrim($base_url, '/');
        $this->ok();
    }

    public function set_stripe_account(string $account_id): void
    {
        $this->stripe_account = trim($account_id);
        $this->ok();
    }

    public function set_stripe_version(string $stripe_version): void
    {
        $this->stripe_version = trim($stripe_version);
        $this->ok();
    }

    public function set_request_timeouts(int $connect_timeout_seconds, int $timeout_seconds): void
    {
        if ($connect_timeout_seconds <= 0 || $timeout_seconds <= 0) {
            $this->fail('timeouts must be > 0');
            return;
        }

        $this->request_connect_timeout_seconds = $connect_timeout_seconds;
        $this->request_timeout_seconds = $timeout_seconds;
        $this->ok();
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        return $this->request_json('GET', $path, $query);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<mixed>|null
     */
    public function post_form(string $path, array $body = [], string $idempotency_key = ''): ?array
    {
        return $this->request_json('POST', $path, [], $body, 'form', $idempotency_key);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<mixed>|null
     */
    public function post_json(string $path, array $body = [], string $idempotency_key = ''): ?array
    {
        return $this->request_json('POST', $path, [], $body, 'json', $idempotency_key);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function delete_request(string $path, array $query = []): ?array
    {
        return $this->request_json('DELETE', $path, $query);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @param array<string> $extra_headers
     * @return array<mixed>|null
     */
    public function request_json(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        string $body_type = 'none',
        string $idempotency_key = '',
        array $extra_headers = []
    ): ?array {
        $resp = $this->request($method, $path, $query, $body, $body_type, $idempotency_key, $extra_headers);
        if (($resp['ok'] ?? false) !== true) {
            return null;
        }

        $json = $resp['json'] ?? null;
        if (!is_array($json)) {
            $this->fail('Invalid JSON response');
            return null;
        }

        $this->ok();
        return $json;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @param array<string> $extra_headers
     */
    public function request_raw(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        string $body_type = 'none',
        string $idempotency_key = '',
        array $extra_headers = []
    ): ?string {
        $resp = $this->request(
            $method,
            $path,
            $query,
            $body,
            $body_type,
            $idempotency_key,
            $extra_headers,
            false
        );
        if (($resp['ok'] ?? false) !== true) {
            return null;
        }

        $raw = $resp['raw'] ?? '';
        $this->ok();
        return is_string($raw) ? $raw : '';
    }

    /**
     * Request JSON with per-request connected account and Stripe-Version headers.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @param array<string> $extra_headers
     * @return array<mixed>|null
     */
    public function request_json_as(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        string $body_type = 'none',
        string $idempotency_key = '',
        string $connected_account_id = '',
        string $stripe_version = '',
        array $extra_headers = []
    ): ?array {
        $headers = array_merge(
            $this->connected_account_headers($connected_account_id, $stripe_version),
            $extra_headers
        );

        return $this->request_json($method, $path, $query, $body, $body_type, $idempotency_key, $headers);
    }

    /**
     * Request raw response with per-request connected account and Stripe-Version headers.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @param array<string> $extra_headers
     */
    public function request_raw_as(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        string $body_type = 'none',
        string $idempotency_key = '',
        string $connected_account_id = '',
        string $stripe_version = '',
        array $extra_headers = []
    ): ?string {
        $headers = array_merge(
            $this->connected_account_headers($connected_account_id, $stripe_version),
            $extra_headers
        );

        return $this->request_raw($method, $path, $query, $body, $body_type, $idempotency_key, $headers);
    }

    /**
     * Auto-paginate v1 list endpoint and return flattened data.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    public function paginate_v1_list(string $path, array $query = [], int $max_pages = 100): ?array
    {
        if ($max_pages < 1 || $max_pages > 10000) {
            $this->fail('max_pages must be in range 1..10000');
            return null;
        }

        $all = [];
        $url = '';
        $object = 'list';
        $has_more = false;
        $starting_after = '';

        for ($i = 0; $i < $max_pages; $i++) {
            $request_query = $query;
            if ($starting_after !== '') {
                $request_query['starting_after'] = $starting_after;
                unset($request_query['ending_before']);
            }

            $resp = $this->get($path, $request_query);
            if ($resp === null) {
                return null;
            }

            $data = $resp['data'] ?? null;
            if (!is_array($data)) {
                $this->fail('Invalid list response: data must be array');
                return null;
            }

            foreach ($data as $item) {
                $all[] = $item;
            }

            $url = (string)($resp['url'] ?? $url);
            $object = (string)($resp['object'] ?? $object);
            $has_more = (($resp['has_more'] ?? false) === true);

            if (!$has_more) {
                break;
            }

            if ($data === []) {
                $this->fail('Invalid list response: has_more=true with empty data');
                return null;
            }

            $last_item = end($data);
            $last_id = '';
            if (is_array($last_item)) {
                $last_id = trim((string)($last_item['id'] ?? ''));
            }
            if ($last_id === '') {
                $this->fail('Cannot paginate list: last item id is missing');
                return null;
            }
            $starting_after = $last_id;
        }

        return [
            'object' => $object,
            'url' => $url,
            'has_more' => $has_more,
            'data' => $all,
        ];
    }

    /**
     * Auto-paginate v1 search endpoint and return flattened data.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    public function paginate_v1_search(string $path, string $search_query, array $query = [], int $max_pages = 100): ?array
    {
        $search_query = trim($search_query);
        if ($search_query === '') {
            $this->fail('query is empty');
            return null;
        }

        if ($max_pages < 1 || $max_pages > 10000) {
            $this->fail('max_pages must be in range 1..10000');
            return null;
        }

        $query['query'] = $search_query;

        $all = [];
        $url = '';
        $object = 'search_result';
        $has_more = false;
        $next_page = '';

        for ($i = 0; $i < $max_pages; $i++) {
            $request_query = $query;
            if ($next_page !== '') {
                $request_query['page'] = $next_page;
            } else {
                unset($request_query['page']);
            }

            $resp = $this->get($path, $request_query);
            if ($resp === null) {
                return null;
            }

            $data = $resp['data'] ?? null;
            if (!is_array($data)) {
                $this->fail('Invalid search response: data must be array');
                return null;
            }

            foreach ($data as $item) {
                $all[] = $item;
            }

            $url = (string)($resp['url'] ?? $url);
            $object = (string)($resp['object'] ?? $object);
            $has_more = (($resp['has_more'] ?? false) === true);
            $next_page = trim((string)($resp['next_page'] ?? ''));

            if (!$has_more) {
                break;
            }

            if ($next_page === '') {
                $this->fail('Cannot paginate search: next_page is missing');
                return null;
            }
        }

        return [
            'object' => $object,
            'url' => $url,
            'has_more' => $has_more,
            'next_page' => $next_page,
            'data' => $all,
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_payment_intent(int $amount, string $currency, array $params = [], string $idempotency_key = ''): ?array
    {
        if ($amount <= 0) {
            $this->fail('amount must be > 0');
            return null;
        }

        $currency = strtolower(trim($currency));
        if (!$this->is_currency($currency)) {
            $this->fail('currency must be 3-letter ISO code');
            return null;
        }

        $payload = $params;
        $payload['amount'] = $amount;
        $payload['currency'] = $currency;

        return $this->post_form('/v1/payment_intents', $payload, $idempotency_key);
    }

    /**
     * @param array<string> $expand
     * @return array<mixed>|null
     */
    public function retrieve_payment_intent(string $payment_intent_id, array $expand = []): ?array
    {
        $payment_intent_id = $this->normalize_required_id($payment_intent_id, 'payment_intent_id');
        if ($payment_intent_id === null) {
            return null;
        }

        $query = [];
        if ($expand !== []) {
            $query['expand'] = $expand;
        }

        return $this->get('/v1/payment_intents/' . rawurlencode($payment_intent_id), $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function confirm_payment_intent(string $payment_intent_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $payment_intent_id = $this->normalize_required_id($payment_intent_id, 'payment_intent_id');
        if ($payment_intent_id === null) {
            return null;
        }
        return $this->post_form('/v1/payment_intents/' . rawurlencode($payment_intent_id) . '/confirm', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function capture_payment_intent(string $payment_intent_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $payment_intent_id = $this->normalize_required_id($payment_intent_id, 'payment_intent_id');
        if ($payment_intent_id === null) {
            return null;
        }
        return $this->post_form('/v1/payment_intents/' . rawurlencode($payment_intent_id) . '/capture', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function cancel_payment_intent(string $payment_intent_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $payment_intent_id = $this->normalize_required_id($payment_intent_id, 'payment_intent_id');
        if ($payment_intent_id === null) {
            return null;
        }
        return $this->post_form('/v1/payment_intents/' . rawurlencode($payment_intent_id) . '/cancel', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_refund(array $params, string $idempotency_key = ''): ?array
    {
        if (!isset($params['charge']) && !isset($params['payment_intent']) && !isset($params['origin'])) {
            $this->fail('refund requires charge or payment_intent or origin');
            return null;
        }
        return $this->post_form('/v1/refunds', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_customer(array $params = [], string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/customers', $params, $idempotency_key);
    }

    /**
     * @param array<string> $expand
     * @return array<mixed>|null
     */
    public function retrieve_customer(string $customer_id, array $expand = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        $query = [];
        if ($expand !== []) {
            $query['expand'] = $expand;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id), $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_customer(string $customer_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }
        return $this->post_form('/v1/customers/' . rawurlencode($customer_id), $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customers(array $query = []): ?array
    {
        return $this->get('/v1/customers', $query);
    }

    /**
     * @return array<mixed>|null
     */
    public function delete_customer(string $customer_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->delete_request('/v1/customers/' . rawurlencode($customer_id));
    }

    /**
     * @param array<string> $expand
     * @return array<mixed>|null
     */
    public function search_customers(string $query_string, int $limit = 10, string $page = '', array $expand = []): ?array
    {
        $query_string = trim($query_string);
        if ($query_string === '') {
            $this->fail('query is empty');
            return null;
        }

        if ($limit < 1 || $limit > 100) {
            $this->fail('limit must be in range 1..100');
            return null;
        }

        $query = [
            'query' => $query_string,
            'limit' => $limit,
        ];
        if (trim($page) !== '') {
            $query['page'] = trim($page);
        }
        if ($expand !== []) {
            $query['expand'] = $expand;
        }

        return $this->get('/v1/customers/search', $query);
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_event(string $event_id): ?array
    {
        $event_id = $this->normalize_required_id($event_id, 'event_id');
        if ($event_id === null) {
            return null;
        }

        return $this->get('/v1/events/' . rawurlencode($event_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_events(array $query = []): ?array
    {
        return $this->get('/v1/events', $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_dispute(string $dispute_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $dispute_id = $this->normalize_required_id($dispute_id, 'dispute_id');
        if ($dispute_id === null) {
            return null;
        }

        return $this->post_form('/v1/disputes/' . rawurlencode($dispute_id), $params, $idempotency_key);
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_dispute(string $dispute_id): ?array
    {
        $dispute_id = $this->normalize_required_id($dispute_id, 'dispute_id');
        if ($dispute_id === null) {
            return null;
        }

        return $this->get('/v1/disputes/' . rawurlencode($dispute_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_disputes(array $query = []): ?array
    {
        return $this->get('/v1/disputes', $query);
    }

    /**
     * @return array<mixed>|null
     */
    public function close_dispute(string $dispute_id, string $idempotency_key = ''): ?array
    {
        $dispute_id = $this->normalize_required_id($dispute_id, 'dispute_id');
        if ($dispute_id === null) {
            return null;
        }

        return $this->post_form('/v1/disputes/' . rawurlencode($dispute_id) . '/close', [], $idempotency_key);
    }

    /**
     * Create customer session.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_customer_session(array $params, string $idempotency_key = ''): ?array
    {
        if ($params === []) {
            $this->fail('params is empty');
            return null;
        }

        return $this->post_form('/v1/customer_sessions', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_transfer_reversal(string $transfer_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $transfer_id = $this->normalize_required_id($transfer_id, 'transfer_id');
        if ($transfer_id === null) {
            return null;
        }
        return $this->post_form('/v1/transfers/' . rawurlencode($transfer_id) . '/reversals', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_transfer_reversal(string $transfer_id, string $reversal_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $transfer_id = $this->normalize_required_id($transfer_id, 'transfer_id');
        $reversal_id = $this->normalize_required_id($reversal_id, 'reversal_id');
        if ($transfer_id === null || $reversal_id === null) {
            return null;
        }
        return $this->post_form(
            '/v1/transfers/' . rawurlencode($transfer_id) . '/reversals/' . rawurlencode($reversal_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_transfer_reversal(string $transfer_id, string $reversal_id): ?array
    {
        $transfer_id = $this->normalize_required_id($transfer_id, 'transfer_id');
        $reversal_id = $this->normalize_required_id($reversal_id, 'reversal_id');
        if ($transfer_id === null || $reversal_id === null) {
            return null;
        }
        return $this->get('/v1/transfers/' . rawurlencode($transfer_id) . '/reversals/' . rawurlencode($reversal_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_transfer_reversals(string $transfer_id, array $query = []): ?array
    {
        $transfer_id = $this->normalize_required_id($transfer_id, 'transfer_id');
        if ($transfer_id === null) {
            return null;
        }
        return $this->get('/v1/transfers/' . rawurlencode($transfer_id) . '/reversals', $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_topup(int $amount, string $currency, array $params = [], string $idempotency_key = ''): ?array
    {
        if ($amount <= 0) {
            $this->fail('amount must be > 0');
            return null;
        }

        $currency = strtolower(trim($currency));
        if (!$this->is_currency($currency)) {
            $this->fail('currency must be 3-letter ISO code');
            return null;
        }

        $payload = $params;
        $payload['amount'] = $amount;
        $payload['currency'] = $currency;

        return $this->post_form('/v1/topups', $payload, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_topup(string $topup_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $topup_id = $this->normalize_required_id($topup_id, 'topup_id');
        if ($topup_id === null) {
            return null;
        }
        return $this->post_form('/v1/topups/' . rawurlencode($topup_id), $params, $idempotency_key);
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_topup(string $topup_id): ?array
    {
        $topup_id = $this->normalize_required_id($topup_id, 'topup_id');
        if ($topup_id === null) {
            return null;
        }
        return $this->get('/v1/topups/' . rawurlencode($topup_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_topups(array $query = []): ?array
    {
        return $this->get('/v1/topups', $query);
    }

    /**
     * @return array<mixed>|null
     */
    public function cancel_topup(string $topup_id, string $idempotency_key = ''): ?array
    {
        $topup_id = $this->normalize_required_id($topup_id, 'topup_id');
        if ($topup_id === null) {
            return null;
        }
        return $this->post_form('/v1/topups/' . rawurlencode($topup_id) . '/cancel', [], $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_person(string $account_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }
        return $this->post_form('/v1/accounts/' . rawurlencode($account_id) . '/persons', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_person(
        string $account_id,
        string $person_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_id = $this->normalize_required_id($person_id, 'person_id');
        if ($account_id === null || $person_id === null) {
            return null;
        }
        return $this->post_form(
            '/v1/accounts/' . rawurlencode($account_id) . '/persons/' . rawurlencode($person_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_person(string $account_id, string $person_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_id = $this->normalize_required_id($person_id, 'person_id');
        if ($account_id === null || $person_id === null) {
            return null;
        }
        return $this->get('/v1/accounts/' . rawurlencode($account_id) . '/persons/' . rawurlencode($person_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_persons(string $account_id, array $query = []): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }
        return $this->get('/v1/accounts/' . rawurlencode($account_id) . '/persons', $query);
    }

    /**
     * @return array<mixed>|null
     */
    public function delete_person(string $account_id, string $person_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_id = $this->normalize_required_id($person_id, 'person_id');
        if ($account_id === null || $person_id === null) {
            return null;
        }
        return $this->delete_request('/v1/accounts/' . rawurlencode($account_id) . '/persons/' . rawurlencode($person_id));
    }

    /**
     * Create setup intent.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_setup_intent(array $params = [], string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/setup_intents', $params, $idempotency_key);
    }

    /**
     * Update setup intent.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_setup_intent(
        string $setup_intent_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $setup_intent_id = $this->normalize_required_id($setup_intent_id, 'setup_intent_id');
        if ($setup_intent_id === null) {
            return null;
        }

        return $this->post_form('/v1/setup_intents/' . rawurlencode($setup_intent_id), $params, $idempotency_key);
    }

    /**
     * Retrieve setup intent.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_setup_intent(string $setup_intent_id, array $query = []): ?array
    {
        $setup_intent_id = $this->normalize_required_id($setup_intent_id, 'setup_intent_id');
        if ($setup_intent_id === null) {
            return null;
        }

        return $this->get('/v1/setup_intents/' . rawurlencode($setup_intent_id), $query);
    }

    /**
     * List setup intents.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_setup_intents(array $query = []): ?array
    {
        return $this->get('/v1/setup_intents', $query);
    }

    /**
     * Cancel setup intent.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function cancel_setup_intent(
        string $setup_intent_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $setup_intent_id = $this->normalize_required_id($setup_intent_id, 'setup_intent_id');
        if ($setup_intent_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/setup_intents/' . rawurlencode($setup_intent_id) . '/cancel',
            $params,
            $idempotency_key
        );
    }

    /**
     * Confirm setup intent.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function confirm_setup_intent(
        string $setup_intent_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $setup_intent_id = $this->normalize_required_id($setup_intent_id, 'setup_intent_id');
        if ($setup_intent_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/setup_intents/' . rawurlencode($setup_intent_id) . '/confirm',
            $params,
            $idempotency_key
        );
    }

    /**
     * Verify setup intent microdeposits.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function verify_setup_intent_microdeposits(
        string $setup_intent_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $setup_intent_id = $this->normalize_required_id($setup_intent_id, 'setup_intent_id');
        if ($setup_intent_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/setup_intents/' . rawurlencode($setup_intent_id) . '/verify_microdeposits',
            $params,
            $idempotency_key
        );
    }

    /**
     * List setup attempts for setup intent.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_setup_attempts(string $setup_intent_id, array $query = []): ?array
    {
        $setup_intent_id = $this->normalize_required_id($setup_intent_id, 'setup_intent_id');
        if ($setup_intent_id === null) {
            return null;
        }

        $query['setup_intent'] = $setup_intent_id;
        return $this->get('/v1/setup_attempts', $query);
    }

    /**
     * Retrieve mandate.
     *
     * @return array<mixed>|null
     */
    public function retrieve_mandate(string $mandate_id): ?array
    {
        $mandate_id = $this->normalize_required_id($mandate_id, 'mandate_id');
        if ($mandate_id === null) {
            return null;
        }

        return $this->get('/v1/mandates/' . rawurlencode($mandate_id));
    }

    /**
     * Create file link.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_file_link(
        string $file_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $file_id = $this->normalize_required_id($file_id, 'file_id');
        if ($file_id === null) {
            return null;
        }

        $body = $params;
        $body['file'] = $file_id;
        return $this->post_form('/v1/file_links', $body, $idempotency_key);
    }

    /**
     * Update file link.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_file_link(
        string $file_link_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $file_link_id = $this->normalize_required_id($file_link_id, 'file_link_id');
        if ($file_link_id === null) {
            return null;
        }

        return $this->post_form('/v1/file_links/' . rawurlencode($file_link_id), $params, $idempotency_key);
    }

    /**
     * Retrieve file link.
     *
     * @return array<mixed>|null
     */
    public function retrieve_file_link(string $file_link_id): ?array
    {
        $file_link_id = $this->normalize_required_id($file_link_id, 'file_link_id');
        if ($file_link_id === null) {
            return null;
        }

        return $this->get('/v1/file_links/' . rawurlencode($file_link_id));
    }

    /**
     * List file links.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_file_links(array $query = []): ?array
    {
        return $this->get('/v1/file_links', $query);
    }

    /**
     * Upload file to Stripe Files API.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_file(
        string $file_path,
        string $purpose,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $file_path = trim($file_path);
        if ($file_path === '') {
            $this->fail('file_path is empty');
            return null;
        }
        if (!is_file($file_path) || !is_readable($file_path)) {
            $this->fail('file_path is not readable file');
            return null;
        }

        $purpose = trim($purpose);
        if ($purpose === '') {
            $this->fail('purpose is empty');
            return null;
        }

        if (!class_exists('CURLFile')) {
            $this->fail('CURLFile class not available');
            return null;
        }

        $mime = $this->detect_mime_type($file_path);
        $filename = basename($file_path);
        $curl_file = new CURLFile($file_path, $mime, $filename);

        $body = $params;
        $body['purpose'] = $purpose;
        $body['file'] = $curl_file;

        return $this->request_json(
            'POST',
            'https://files.stripe.com/v1/files',
            [],
            $body,
            'multipart',
            $idempotency_key
        );
    }

    /**
     * Retrieve file.
     *
     * @return array<mixed>|null
     */
    public function retrieve_file(string $file_id): ?array
    {
        $file_id = $this->normalize_required_id($file_id, 'file_id');
        if ($file_id === null) {
            return null;
        }

        return $this->get('/v1/files/' . rawurlencode($file_id));
    }

    /**
     * List files.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_files(array $query = []): ?array
    {
        return $this->get('/v1/files', $query);
    }

    /**
     * Download file contents as raw bytes.
     *
     * @return string|null
     */
    public function retrieve_file_contents(string $file_id): ?string
    {
        $file_id = $this->normalize_required_id($file_id, 'file_id');
        if ($file_id === null) {
            return null;
        }

        return $this->request_raw('GET', 'https://files.stripe.com/v1/files/' . rawurlencode($file_id) . '/contents');
    }

    /**
     * @param array<string> $expand
     * @return array<mixed>|null
     */
    public function retrieve_account(string $account_id, array $expand = []): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        $query = [];
        if ($expand !== []) {
            $query['expand'] = $expand;
        }

        return $this->get('/v1/accounts/' . rawurlencode($account_id), $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_account(string $account_id, array $params, string $idempotency_key = ''): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->post_form('/v1/accounts/' . rawurlencode($account_id), $params, $idempotency_key);
    }

    /**
     * Create connected account.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_account(array $params = [], string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/accounts', $params, $idempotency_key);
    }

    /**
     * List connected accounts.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_accounts(array $query = []): ?array
    {
        return $this->get('/v1/accounts', $query);
    }

    /**
     * Delete connected account.
     *
     * @return array<mixed>|null
     */
    public function delete_account(string $account_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->delete_request('/v1/accounts/' . rawurlencode($account_id));
    }

    /**
     * Reject connected account.
     *
     * @return array<mixed>|null
     */
    public function reject_account(string $account_id, string $reason, string $idempotency_key = ''): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        $reason = strtolower(trim($reason));
        if (!isset(self::ACCOUNT_REJECT_REASONS[$reason])) {
            $this->fail('reason must be fraud, terms_of_service, or other');
            return null;
        }

        return $this->post_form('/v1/accounts/' . rawurlencode($account_id) . '/reject', [
            'reason' => $reason,
        ], $idempotency_key);
    }

    /**
     * Retrieve financing summary for a connected account.
     *
     * @return array<mixed>|null
     */
    public function retrieve_financing_summary(string $connected_account_id = ''): ?array
    {
        return $this->request_json(
            'GET',
            '/v1/capital/financing_summary',
            [],
            null,
            'none',
            '',
            $this->connected_account_headers($connected_account_id, '')
        );
    }

    /**
     * Retrieve financing offer by ID.
     *
     * @return array<mixed>|null
     */
    public function retrieve_financing_offer(string $financing_offer_id): ?array
    {
        $financing_offer_id = $this->normalize_required_id($financing_offer_id, 'financing_offer_id');
        if ($financing_offer_id === null) {
            return null;
        }

        return $this->get('/v1/capital/financing_offers/' . rawurlencode($financing_offer_id));
    }

    /**
     * List financing offers.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_financing_offers(array $query = []): ?array
    {
        return $this->get('/v1/capital/financing_offers', $query);
    }

    /**
     * Acknowledge that financing offer has been delivered.
     *
     * @return array<mixed>|null
     */
    public function mark_financing_offer_delivered(string $financing_offer_id, string $idempotency_key = ''): ?array
    {
        $financing_offer_id = $this->normalize_required_id($financing_offer_id, 'financing_offer_id');
        if ($financing_offer_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v1/capital/financing_offers/' . rawurlencode($financing_offer_id) . '/mark_delivered',
            [],
            null,
            'none',
            $idempotency_key
        );
    }

    /**
     * Create test clock.
     *
     * @return array<mixed>|null
     */
    public function create_test_clock(int $frozen_time, string $name = '', string $idempotency_key = ''): ?array
    {
        if ($frozen_time <= 0) {
            $this->fail('frozen_time must be > 0');
            return null;
        }

        $body = ['frozen_time' => $frozen_time];
        $name = trim($name);
        if ($name !== '') {
            $body['name'] = $name;
        }

        return $this->post_form('/v1/test_helpers/test_clocks', $body, $idempotency_key);
    }

    /**
     * Retrieve test clock.
     *
     * @return array<mixed>|null
     */
    public function retrieve_test_clock(string $test_clock_id): ?array
    {
        $test_clock_id = $this->normalize_required_id($test_clock_id, 'test_clock_id');
        if ($test_clock_id === null) {
            return null;
        }

        return $this->get('/v1/test_helpers/test_clocks/' . rawurlencode($test_clock_id));
    }

    /**
     * List test clocks.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_test_clocks(array $query = []): ?array
    {
        return $this->get('/v1/test_helpers/test_clocks', $query);
    }

    /**
     * Delete test clock.
     *
     * @return array<mixed>|null
     */
    public function delete_test_clock(string $test_clock_id): ?array
    {
        $test_clock_id = $this->normalize_required_id($test_clock_id, 'test_clock_id');
        if ($test_clock_id === null) {
            return null;
        }

        return $this->delete_request('/v1/test_helpers/test_clocks/' . rawurlencode($test_clock_id));
    }

    /**
     * Advance test clock.
     *
     * @return array<mixed>|null
     */
    public function advance_test_clock(string $test_clock_id, int $frozen_time, string $idempotency_key = ''): ?array
    {
        $test_clock_id = $this->normalize_required_id($test_clock_id, 'test_clock_id');
        if ($test_clock_id === null) {
            return null;
        }

        if ($frozen_time <= 0) {
            $this->fail('frozen_time must be > 0');
            return null;
        }

        return $this->post_form(
            '/v1/test_helpers/test_clocks/' . rawurlencode($test_clock_id) . '/advance',
            ['frozen_time' => $frozen_time],
            $idempotency_key
        );
    }

    /**
     * Create customer tax ID.
     *
     * @return array<mixed>|null
     */
    public function create_customer_tax_id(
        string $customer_id,
        string $type,
        string $value,
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        $type = trim($type);
        $value = trim($value);
        if ($type === '') {
            $this->fail('type is empty');
            return null;
        }
        if ($value === '') {
            $this->fail('value is empty');
            return null;
        }

        return $this->post_form('/v1/customers/' . rawurlencode($customer_id) . '/tax_ids', [
            'type' => $type,
            'value' => $value,
        ], $idempotency_key);
    }

    /**
     * Retrieve customer tax ID.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_tax_id(string $customer_id, string $tax_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $tax_id = $this->normalize_required_id($tax_id, 'tax_id');
        if ($customer_id === null || $tax_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/tax_ids/' . rawurlencode($tax_id));
    }

    /**
     * List customer tax IDs.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customer_tax_ids(string $customer_id, array $query = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/tax_ids', $query);
    }

    /**
     * Delete customer tax ID.
     *
     * @return array<mixed>|null
     */
    public function delete_customer_tax_id(string $customer_id, string $tax_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $tax_id = $this->normalize_required_id($tax_id, 'tax_id');
        if ($customer_id === null || $tax_id === null) {
            return null;
        }

        return $this->delete_request('/v1/customers/' . rawurlencode($customer_id) . '/tax_ids/' . rawurlencode($tax_id));
    }

    /**
     * Create tax ID.
     *
     * @param array<string,mixed> $owner
     * @return array<mixed>|null
     */
    public function create_tax_id(
        string $type,
        string $value,
        array $owner = [],
        string $idempotency_key = ''
    ): ?array {
        $type = trim($type);
        $value = trim($value);
        if ($type === '') {
            $this->fail('type is empty');
            return null;
        }
        if ($value === '') {
            $this->fail('value is empty');
            return null;
        }

        $body = [
            'type' => $type,
            'value' => $value,
        ];
        if ($owner !== []) {
            $body['owner'] = $owner;
        }

        return $this->post_form('/v1/tax_ids', $body, $idempotency_key);
    }

    /**
     * Retrieve tax ID.
     *
     * @return array<mixed>|null
     */
    public function retrieve_tax_id(string $tax_id): ?array
    {
        $tax_id = $this->normalize_required_id($tax_id, 'tax_id');
        if ($tax_id === null) {
            return null;
        }

        return $this->get('/v1/tax_ids/' . rawurlencode($tax_id));
    }

    /**
     * List tax IDs.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_tax_ids(array $query = []): ?array
    {
        return $this->get('/v1/tax_ids', $query);
    }

    /**
     * Delete tax ID.
     *
     * @return array<mixed>|null
     */
    public function delete_tax_id(string $tax_id): ?array
    {
        $tax_id = $this->normalize_required_id($tax_id, 'tax_id');
        if ($tax_id === null) {
            return null;
        }

        return $this->delete_request('/v1/tax_ids/' . rawurlencode($tax_id));
    }

    /**
     * Create subscription.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_subscription(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/subscriptions', $params, $idempotency_key);
    }

    /**
     * Update subscription.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_subscription(
        string $subscription_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        return $this->post_form('/v1/subscriptions/' . rawurlencode($subscription_id), $params, $idempotency_key);
    }

    /**
     * Retrieve subscription.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_subscription(string $subscription_id, array $query = []): ?array
    {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        return $this->get('/v1/subscriptions/' . rawurlencode($subscription_id), $query);
    }

    /**
     * List subscriptions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_subscriptions(array $query = []): ?array
    {
        return $this->get('/v1/subscriptions', $query);
    }

    /**
     * Cancel subscription.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function cancel_subscription(string $subscription_id, array $params = []): ?array
    {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        if ($params === []) {
            return $this->delete_request('/v1/subscriptions/' . rawurlencode($subscription_id));
        }

        return $this->request_json(
            'DELETE',
            '/v1/subscriptions/' . rawurlencode($subscription_id),
            [],
            $params,
            'form'
        );
    }

    /**
     * Migrate subscription billing mode.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function migrate_subscription(
        string $subscription_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        return $this->post_form('/v1/subscriptions/' . rawurlencode($subscription_id) . '/migrate', $params, $idempotency_key);
    }

    /**
     * Resume paused subscription.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function resume_subscription(
        string $subscription_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        return $this->post_form('/v1/subscriptions/' . rawurlencode($subscription_id) . '/resume', $params, $idempotency_key);
    }

    /**
     * Search subscriptions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function search_subscriptions(string $search_query, array $query = []): ?array
    {
        $search_query = trim($search_query);
        if ($search_query === '') {
            $this->fail('query is empty');
            return null;
        }

        $query['query'] = $search_query;
        return $this->get('/v1/subscriptions/search', $query);
    }

    /**
     * Create quote.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_quote(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/quotes', $params, $idempotency_key);
    }

    /**
     * Update quote.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_quote(string $quote_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->post_form('/v1/quotes/' . rawurlencode($quote_id), $params, $idempotency_key);
    }

    /**
     * Retrieve quote.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_quote(string $quote_id, array $query = []): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->get('/v1/quotes/' . rawurlencode($quote_id), $query);
    }

    /**
     * Retrieve quote line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_quote_line_items(string $quote_id, array $query = []): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->get('/v1/quotes/' . rawurlencode($quote_id) . '/line_items', $query);
    }

    /**
     * Retrieve quote computed upfront line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_quote_computed_upfront_line_items(string $quote_id, array $query = []): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->get('/v1/quotes/' . rawurlencode($quote_id) . '/computed_upfront_line_items', $query);
    }

    /**
     * List quotes.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_quotes(array $query = []): ?array
    {
        return $this->get('/v1/quotes', $query);
    }

    /**
     * Accept quote.
     *
     * @return array<mixed>|null
     */
    public function accept_quote(string $quote_id, string $idempotency_key = ''): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v1/quotes/' . rawurlencode($quote_id) . '/accept',
            [],
            null,
            'none',
            $idempotency_key
        );
    }

    /**
     * Cancel quote.
     *
     * @return array<mixed>|null
     */
    public function cancel_quote(string $quote_id, string $idempotency_key = ''): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v1/quotes/' . rawurlencode($quote_id) . '/cancel',
            [],
            null,
            'none',
            $idempotency_key
        );
    }

    /**
     * Download quote PDF as raw bytes.
     *
     * @return string|null
     */
    public function retrieve_quote_pdf(string $quote_id, bool $use_files_api = true): ?string
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        $path = '/v1/quotes/' . rawurlencode($quote_id) . '/pdf';
        if ($use_files_api) {
            $path = 'https://files.stripe.com/v1/quotes/' . rawurlencode($quote_id) . '/pdf';
        }

        return $this->request_raw('GET', $path, [], null, 'none', '', ['Accept: application/pdf']);
    }

    /**
     * Finalize quote.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function finalize_quote(string $quote_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $quote_id = $this->normalize_required_id($quote_id, 'quote_id');
        if ($quote_id === null) {
            return null;
        }

        return $this->post_form('/v1/quotes/' . rawurlencode($quote_id) . '/finalize', $params, $idempotency_key);
    }

    /**
     * Create plan.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_plan(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/plans', $params, $idempotency_key);
    }

    /**
     * Update plan.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_plan(string $plan_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $plan_id = $this->normalize_required_id($plan_id, 'plan_id');
        if ($plan_id === null) {
            return null;
        }

        return $this->post_form('/v1/plans/' . rawurlencode($plan_id), $params, $idempotency_key);
    }

    /**
     * Retrieve plan.
     *
     * @return array<mixed>|null
     */
    public function retrieve_plan(string $plan_id): ?array
    {
        $plan_id = $this->normalize_required_id($plan_id, 'plan_id');
        if ($plan_id === null) {
            return null;
        }

        return $this->get('/v1/plans/' . rawurlencode($plan_id));
    }

    /**
     * List plans.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_plans(array $query = []): ?array
    {
        return $this->get('/v1/plans', $query);
    }

    /**
     * Delete plan.
     *
     * @return array<mixed>|null
     */
    public function delete_plan(string $plan_id): ?array
    {
        $plan_id = $this->normalize_required_id($plan_id, 'plan_id');
        if ($plan_id === null) {
            return null;
        }

        return $this->delete_request('/v1/plans/' . rawurlencode($plan_id));
    }

    /**
     * Create v2 billing meter event (synchronous validation).
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_meter_event_v2(
        string $event_name,
        array $payload,
        string $identifier = '',
        string $timestamp = '',
        string $stripe_version = self::STRIPE_V2_BILLING_PREVIEW_VERSION,
        string $idempotency_key = ''
    ): ?array {
        $event_name = trim($event_name);
        if ($event_name === '') {
            $this->fail('event_name is empty');
            return null;
        }
        if ($payload === []) {
            $this->fail('payload is empty');
            return null;
        }

        $body = [
            'event_name' => $event_name,
            'payload' => $payload,
        ];

        $identifier = trim($identifier);
        if ($identifier !== '') {
            $body['identifier'] = $identifier;
        }

        $timestamp = trim($timestamp);
        if ($timestamp !== '') {
            $body['timestamp'] = $timestamp;
        }

        return $this->request_json(
            'POST',
            '/v2/billing/meter_events',
            [],
            $body,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * List billing meter event summaries.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_meter_event_summaries(
        string $meter_id,
        string $customer_id,
        int $start_time,
        int $end_time,
        string $value_grouping_window = '',
        array $query = []
    ): ?array {
        $meter_id = $this->normalize_required_id($meter_id, 'meter_id');
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($meter_id === null || $customer_id === null) {
            return null;
        }

        if ($start_time <= 0 || $end_time <= 0) {
            $this->fail('start_time and end_time must be > 0');
            return null;
        }
        if ($end_time <= $start_time) {
            $this->fail('end_time must be greater than start_time');
            return null;
        }

        $value_grouping_window = strtolower(trim($value_grouping_window));
        if ($value_grouping_window !== '' && $value_grouping_window !== 'hour' && $value_grouping_window !== 'day') {
            $this->fail('value_grouping_window must be hour, day, or empty');
            return null;
        }

        $query['customer'] = $customer_id;
        $query['start_time'] = $start_time;
        $query['end_time'] = $end_time;
        if ($value_grouping_window !== '') {
            $query['value_grouping_window'] = $value_grouping_window;
        }

        return $this->get('/v1/billing/meters/' . rawurlencode($meter_id) . '/event_summaries', $query);
    }

    /**
     * Create v2 billing meter event stream auth session.
     *
     * @return array<mixed>|null
     */
    public function create_meter_event_session_v2(
        string $stripe_version = self::STRIPE_V2_BILLING_PREVIEW_VERSION,
        string $idempotency_key = ''
    ): ?array {
        return $this->request_json(
            'POST',
            '/v2/billing/meter_event_session',
            [],
            null,
            'none',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create v2 billing meter events through stream endpoint (asynchronous validation).
     *
     * @param array<int,array<string,mixed>> $events
     * @return array<mixed>|null
     */
    public function create_meter_event_stream_v2(
        array $events,
        string $session_auth_token,
        string $stripe_version = self::STRIPE_V2_BILLING_PREVIEW_VERSION
    ): ?array {
        $session_auth_token = trim($session_auth_token);
        if ($session_auth_token === '') {
            $this->fail('session_auth_token is empty');
            return null;
        }
        if ($events === []) {
            $this->fail('events is empty');
            return null;
        }

        $headers = $this->connected_account_headers('', $stripe_version);
        $headers[] = 'Authorization: Bearer ' . $session_auth_token;

        return $this->request_json(
            'POST',
            self::STRIPE_METER_EVENTS_STREAM_URL,
            [],
            ['events' => $events],
            'json',
            '',
            $headers
        );
    }

    /**
     * Create v2 billing meter event adjustment.
     *
     * @return array<mixed>|null
     */
    public function create_meter_event_adjustment_v2(
        string $event_name,
        string $identifier,
        string $type = 'cancel',
        string $stripe_version = self::STRIPE_V2_BILLING_PREVIEW_VERSION,
        string $idempotency_key = ''
    ): ?array {
        $event_name = trim($event_name);
        if ($event_name === '') {
            $this->fail('event_name is empty');
            return null;
        }

        $identifier = trim($identifier);
        if ($identifier === '') {
            $this->fail('identifier is empty');
            return null;
        }

        $type = strtolower(trim($type));
        if ($type !== 'cancel') {
            $this->fail('type must be cancel');
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/billing/meter_event_adjustments',
            [],
            [
                'event_name' => $event_name,
                'type' => $type,
                'cancel' => ['identifier' => $identifier],
            ],
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create billing meter event adjustment (v1).
     *
     * @return array<mixed>|null
     */
    public function create_meter_event_adjustment(
        string $event_name,
        string $identifier,
        string $type = 'cancel',
        string $idempotency_key = ''
    ): ?array {
        $event_name = trim($event_name);
        if ($event_name === '') {
            $this->fail('event_name is empty');
            return null;
        }

        $identifier = trim($identifier);
        if ($identifier === '') {
            $this->fail('identifier is empty');
            return null;
        }

        $type = strtolower(trim($type));
        if ($type !== 'cancel') {
            $this->fail('type must be cancel');
            return null;
        }

        return $this->post_form(
            '/v1/billing/meter_event_adjustments',
            [
                'event_name' => $event_name,
                'type' => $type,
                'cancel' => ['identifier' => $identifier],
            ],
            $idempotency_key
        );
    }

    /**
     * Create billing meter event (v1).
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_meter_event(
        string $event_name,
        array $payload,
        string $identifier = '',
        int $timestamp = 0,
        string $idempotency_key = ''
    ): ?array {
        $event_name = trim($event_name);
        if ($event_name === '') {
            $this->fail('event_name is empty');
            return null;
        }

        if ($payload === []) {
            $this->fail('payload is empty');
            return null;
        }

        $body = [
            'event_name' => $event_name,
            'payload' => $payload,
        ];

        $identifier = trim($identifier);
        if ($identifier !== '') {
            $body['identifier'] = $identifier;
        }
        if ($timestamp > 0) {
            $body['timestamp'] = $timestamp;
        }

        return $this->post_form('/v1/billing/meter_events', $body, $idempotency_key);
    }

    /**
     * Create billing meter.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_meter(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/billing/meters', $params, $idempotency_key);
    }

    /**
     * Update billing meter.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_meter(string $meter_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $meter_id = $this->normalize_required_id($meter_id, 'meter_id');
        if ($meter_id === null) {
            return null;
        }

        return $this->post_form('/v1/billing/meters/' . rawurlencode($meter_id), $params, $idempotency_key);
    }

    /**
     * Retrieve billing meter.
     *
     * @return array<mixed>|null
     */
    public function retrieve_meter(string $meter_id): ?array
    {
        $meter_id = $this->normalize_required_id($meter_id, 'meter_id');
        if ($meter_id === null) {
            return null;
        }

        return $this->get('/v1/billing/meters/' . rawurlencode($meter_id));
    }

    /**
     * List billing meters.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_meters(array $query = []): ?array
    {
        return $this->get('/v1/billing/meters', $query);
    }

    /**
     * Deactivate billing meter.
     *
     * @return array<mixed>|null
     */
    public function deactivate_meter(string $meter_id, string $idempotency_key = ''): ?array
    {
        $meter_id = $this->normalize_required_id($meter_id, 'meter_id');
        if ($meter_id === null) {
            return null;
        }

        return $this->post_form('/v1/billing/meters/' . rawurlencode($meter_id) . '/deactivate', [], $idempotency_key);
    }

    /**
     * Reactivate billing meter.
     *
     * @return array<mixed>|null
     */
    public function reactivate_meter(string $meter_id, string $idempotency_key = ''): ?array
    {
        $meter_id = $this->normalize_required_id($meter_id, 'meter_id');
        if ($meter_id === null) {
            return null;
        }

        return $this->post_form('/v1/billing/meters/' . rawurlencode($meter_id) . '/reactivate', [], $idempotency_key);
    }

    /**
     * Retrieve invoice rendering template.
     *
     * @return array<mixed>|null
     */
    public function retrieve_invoice_rendering_template(string $template_id): ?array
    {
        $template_id = $this->normalize_required_id($template_id, 'template_id');
        if ($template_id === null) {
            return null;
        }

        return $this->get('/v1/invoice_rendering_templates/' . rawurlencode($template_id));
    }

    /**
     * List invoice rendering templates.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_invoice_rendering_templates(array $query = []): ?array
    {
        return $this->get('/v1/invoice_rendering_templates', $query);
    }

    /**
     * Archive invoice rendering template.
     *
     * @return array<mixed>|null
     */
    public function archive_invoice_rendering_template(string $template_id, string $idempotency_key = ''): ?array
    {
        $template_id = $this->normalize_required_id($template_id, 'template_id');
        if ($template_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/invoice_rendering_templates/' . rawurlencode($template_id) . '/archive',
            [],
            $idempotency_key
        );
    }

    /**
     * Unarchive invoice rendering template.
     *
     * @return array<mixed>|null
     */
    public function unarchive_invoice_rendering_template(string $template_id, string $idempotency_key = ''): ?array
    {
        $template_id = $this->normalize_required_id($template_id, 'template_id');
        if ($template_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/invoice_rendering_templates/' . rawurlencode($template_id) . '/unarchive',
            [],
            $idempotency_key
        );
    }

    /**
     * Retrieve invoice payment.
     *
     * @return array<mixed>|null
     */
    public function retrieve_invoice_payment(string $invoice_payment_id): ?array
    {
        $invoice_payment_id = $this->normalize_required_id($invoice_payment_id, 'invoice_payment_id');
        if ($invoice_payment_id === null) {
            return null;
        }

        return $this->get('/v1/invoice_payments/' . rawurlencode($invoice_payment_id));
    }

    /**
     * List invoice payments.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_invoice_payments(array $query = []): ?array
    {
        return $this->get('/v1/invoice_payments', $query);
    }

    /**
     * Update invoice line item.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_invoice_line_item(
        string $invoice_id,
        string $line_item_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        $line_item_id = $this->normalize_required_id($line_item_id, 'line_item_id');
        if ($invoice_id === null || $line_item_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/invoices/' . rawurlencode($invoice_id) . '/lines/' . rawurlencode($line_item_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve invoice line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_invoice_line_items(string $invoice_id, array $query = []): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->get('/v1/invoices/' . rawurlencode($invoice_id) . '/lines', $query);
    }

    /**
     * Bulk add invoice line items.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function add_invoice_lines(string $invoice_id, array $params, string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/add_lines', $params, $idempotency_key);
    }

    /**
     * Bulk remove invoice line items.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function remove_invoice_lines(string $invoice_id, array $params, string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/remove_lines', $params, $idempotency_key);
    }

    /**
     * Bulk update invoice line items.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_invoice_lines(string $invoice_id, array $params, string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/update_lines', $params, $idempotency_key);
    }

    /**
     * Create invoice item.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_invoice_item(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/invoiceitems', $params, $idempotency_key);
    }

    /**
     * Update invoice item.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_invoice_item(
        string $invoice_item_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $invoice_item_id = $this->normalize_required_id($invoice_item_id, 'invoice_item_id');
        if ($invoice_item_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoiceitems/' . rawurlencode($invoice_item_id), $params, $idempotency_key);
    }

    /**
     * Retrieve invoice item.
     *
     * @return array<mixed>|null
     */
    public function retrieve_invoice_item(string $invoice_item_id): ?array
    {
        $invoice_item_id = $this->normalize_required_id($invoice_item_id, 'invoice_item_id');
        if ($invoice_item_id === null) {
            return null;
        }

        return $this->get('/v1/invoiceitems/' . rawurlencode($invoice_item_id));
    }

    /**
     * List invoice items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_invoice_items(array $query = []): ?array
    {
        return $this->get('/v1/invoiceitems', $query);
    }

    /**
     * Delete invoice item.
     *
     * @return array<mixed>|null
     */
    public function delete_invoice_item(string $invoice_item_id): ?array
    {
        $invoice_item_id = $this->normalize_required_id($invoice_item_id, 'invoice_item_id');
        if ($invoice_item_id === null) {
            return null;
        }

        return $this->delete_request('/v1/invoiceitems/' . rawurlencode($invoice_item_id));
    }

    /**
     * Create invoice preview.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_invoice_preview(array $params = [], string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/invoices/create_preview', $params, $idempotency_key);
    }

    /**
     * Create invoice.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_invoice(array $params = [], string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/invoices', $params, $idempotency_key);
    }

    /**
     * Update invoice.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_invoice(string $invoice_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id), $params, $idempotency_key);
    }

    /**
     * Retrieve invoice.
     *
     * @return array<mixed>|null
     */
    public function retrieve_invoice(string $invoice_id): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->get('/v1/invoices/' . rawurlencode($invoice_id));
    }

    /**
     * List invoices.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_invoices(array $query = []): ?array
    {
        return $this->get('/v1/invoices', $query);
    }

    /**
     * Delete draft invoice.
     *
     * @return array<mixed>|null
     */
    public function delete_invoice(string $invoice_id): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->delete_request('/v1/invoices/' . rawurlencode($invoice_id));
    }

    /**
     * Attach payment to invoice.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function attach_invoice_payment(
        string $invoice_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/attach_payment', $params, $idempotency_key);
    }

    /**
     * Finalize invoice.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function finalize_invoice(
        string $invoice_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/finalize', $params, $idempotency_key);
    }

    /**
     * Mark invoice as uncollectible.
     *
     * @return array<mixed>|null
     */
    public function mark_invoice_uncollectible(string $invoice_id, string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/mark_uncollectible', [], $idempotency_key);
    }

    /**
     * Pay invoice.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function pay_invoice(string $invoice_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/pay', $params, $idempotency_key);
    }

    /**
     * Search invoices.
     *
     * @param array<string> $expand
     * @return array<mixed>|null
     */
    public function search_invoices(string $query_string, int $limit = 10, string $page = '', array $expand = []): ?array
    {
        $query_string = trim($query_string);
        if ($query_string === '') {
            $this->fail('query is empty');
            return null;
        }

        if ($limit < 1 || $limit > 100) {
            $this->fail('limit must be in range 1..100');
            return null;
        }

        $query = [
            'query' => $query_string,
            'limit' => $limit,
        ];

        $page = trim($page);
        if ($page !== '') {
            $query['page'] = $page;
        }
        if ($expand !== []) {
            $query['expand'] = $expand;
        }

        return $this->get('/v1/invoices/search', $query);
    }

    /**
     * Send invoice for manual payment.
     *
     * @return array<mixed>|null
     */
    public function send_invoice(string $invoice_id, string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/send', [], $idempotency_key);
    }

    /**
     * Void invoice.
     *
     * @return array<mixed>|null
     */
    public function void_invoice(string $invoice_id, string $idempotency_key = ''): ?array
    {
        $invoice_id = $this->normalize_required_id($invoice_id, 'invoice_id');
        if ($invoice_id === null) {
            return null;
        }

        return $this->post_form('/v1/invoices/' . rawurlencode($invoice_id) . '/void', [], $idempotency_key);
    }

    /**
     * Create customer portal session.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_billing_portal_session(array $params, string $idempotency_key = ''): ?array
    {
        if (!isset($params['customer']) && !isset($params['customer_account'])) {
            $this->fail('customer or customer_account is required');
            return null;
        }

        return $this->post_form('/v1/billing_portal/sessions', $params, $idempotency_key);
    }

    /**
     * Create customer portal configuration.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_billing_portal_configuration(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/billing_portal/configurations', $params, $idempotency_key);
    }

    /**
     * Update customer portal configuration.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_billing_portal_configuration(
        string $configuration_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $configuration_id = $this->normalize_required_id($configuration_id, 'configuration_id');
        if ($configuration_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/billing_portal/configurations/' . rawurlencode($configuration_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve customer portal configuration.
     *
     * @return array<mixed>|null
     */
    public function retrieve_billing_portal_configuration(string $configuration_id): ?array
    {
        $configuration_id = $this->normalize_required_id($configuration_id, 'configuration_id');
        if ($configuration_id === null) {
            return null;
        }

        return $this->get('/v1/billing_portal/configurations/' . rawurlencode($configuration_id));
    }

    /**
     * List customer portal configurations.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_billing_portal_configurations(array $query = []): ?array
    {
        return $this->get('/v1/billing_portal/configurations', $query);
    }

    /**
     * Create customer balance transaction.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_customer_balance_transaction(
        string $customer_id,
        int $amount,
        string $currency,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        if ($amount === 0) {
            $this->fail('amount must not be 0');
            return null;
        }

        $currency = strtolower(trim($currency));
        if (!$this->is_currency($currency)) {
            $this->fail('currency must be 3-letter ISO code');
            return null;
        }

        $body = $params;
        $body['amount'] = $amount;
        $body['currency'] = $currency;

        return $this->post_form(
            '/v1/customers/' . rawurlencode($customer_id) . '/balance_transactions',
            $body,
            $idempotency_key
        );
    }

    /**
     * Update customer balance transaction.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_customer_balance_transaction(
        string $customer_id,
        string $transaction_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $transaction_id = $this->normalize_required_id($transaction_id, 'transaction_id');
        if ($customer_id === null || $transaction_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/customers/' . rawurlencode($customer_id) . '/balance_transactions/' . rawurlencode($transaction_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve customer balance transaction.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_balance_transaction(string $customer_id, string $transaction_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $transaction_id = $this->normalize_required_id($transaction_id, 'transaction_id');
        if ($customer_id === null || $transaction_id === null) {
            return null;
        }

        return $this->get(
            '/v1/customers/' . rawurlencode($customer_id) . '/balance_transactions/' . rawurlencode($transaction_id)
        );
    }

    /**
     * List customer balance transactions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customer_balance_transactions(string $customer_id, array $query = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/balance_transactions', $query);
    }

    /**
     * Create credit note.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_credit_note(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/credit_notes', $params, $idempotency_key);
    }

    /**
     * Update credit note.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_credit_note(
        string $credit_note_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $credit_note_id = $this->normalize_required_id($credit_note_id, 'credit_note_id');
        if ($credit_note_id === null) {
            return null;
        }

        return $this->post_form('/v1/credit_notes/' . rawurlencode($credit_note_id), $params, $idempotency_key);
    }

    /**
     * Retrieve credit note.
     *
     * @return array<mixed>|null
     */
    public function retrieve_credit_note(string $credit_note_id): ?array
    {
        $credit_note_id = $this->normalize_required_id($credit_note_id, 'credit_note_id');
        if ($credit_note_id === null) {
            return null;
        }

        return $this->get('/v1/credit_notes/' . rawurlencode($credit_note_id));
    }

    /**
     * Retrieve credit note preview.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function preview_credit_note(array $query): ?array
    {
        return $this->get('/v1/credit_notes/preview', $query);
    }

    /**
     * Retrieve credit note preview line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_credit_note_preview_lines(array $query): ?array
    {
        return $this->get('/v1/credit_notes/preview/lines', $query);
    }

    /**
     * Retrieve credit note line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_credit_note_lines(string $credit_note_id, array $query = []): ?array
    {
        $credit_note_id = $this->normalize_required_id($credit_note_id, 'credit_note_id');
        if ($credit_note_id === null) {
            return null;
        }

        return $this->get('/v1/credit_notes/' . rawurlencode($credit_note_id) . '/lines', $query);
    }

    /**
     * List credit notes.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_credit_notes(array $query = []): ?array
    {
        return $this->get('/v1/credit_notes', $query);
    }

    /**
     * Void credit note.
     *
     * @return array<mixed>|null
     */
    public function void_credit_note(string $credit_note_id, string $idempotency_key = ''): ?array
    {
        $credit_note_id = $this->normalize_required_id($credit_note_id, 'credit_note_id');
        if ($credit_note_id === null) {
            return null;
        }

        return $this->post_form('/v1/credit_notes/' . rawurlencode($credit_note_id) . '/void', [], $idempotency_key);
    }

    /**
     * Create credit grant.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_credit_grant(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/billing/credit_grants', $params, $idempotency_key);
    }

    /**
     * Update credit grant.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_credit_grant(
        string $credit_grant_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $credit_grant_id = $this->normalize_required_id($credit_grant_id, 'credit_grant_id');
        if ($credit_grant_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/billing/credit_grants/' . rawurlencode($credit_grant_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve credit grant.
     *
     * @return array<mixed>|null
     */
    public function retrieve_credit_grant(string $credit_grant_id): ?array
    {
        $credit_grant_id = $this->normalize_required_id($credit_grant_id, 'credit_grant_id');
        if ($credit_grant_id === null) {
            return null;
        }

        return $this->get('/v1/billing/credit_grants/' . rawurlencode($credit_grant_id));
    }

    /**
     * List credit grants.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_credit_grants(array $query = []): ?array
    {
        return $this->get('/v1/billing/credit_grants', $query);
    }

    /**
     * Expire credit grant.
     *
     * @return array<mixed>|null
     */
    public function expire_credit_grant(string $credit_grant_id, string $idempotency_key = ''): ?array
    {
        $credit_grant_id = $this->normalize_required_id($credit_grant_id, 'credit_grant_id');
        if ($credit_grant_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/billing/credit_grants/' . rawurlencode($credit_grant_id) . '/expire',
            [],
            $idempotency_key
        );
    }

    /**
     * Void credit grant.
     *
     * @return array<mixed>|null
     */
    public function void_credit_grant(string $credit_grant_id, string $idempotency_key = ''): ?array
    {
        $credit_grant_id = $this->normalize_required_id($credit_grant_id, 'credit_grant_id');
        if ($credit_grant_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/billing/credit_grants/' . rawurlencode($credit_grant_id) . '/void',
            [],
            $idempotency_key
        );
    }

    /**
     * Retrieve credit balance transaction.
     *
     * @return array<mixed>|null
     */
    public function retrieve_credit_balance_transaction(string $credit_balance_transaction_id): ?array
    {
        $credit_balance_transaction_id = $this->normalize_required_id(
            $credit_balance_transaction_id,
            'credit_balance_transaction_id'
        );
        if ($credit_balance_transaction_id === null) {
            return null;
        }

        return $this->get('/v1/billing/credit_balance_transactions/' . rawurlencode($credit_balance_transaction_id));
    }

    /**
     * List credit balance transactions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_credit_balance_transactions(array $query = []): ?array
    {
        return $this->get('/v1/billing/credit_balance_transactions', $query);
    }

    /**
     * Retrieve credit balance summary.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_credit_balance_summary(array $query): ?array
    {
        return $this->get('/v1/billing/credit_balance_summary', $query);
    }

    /**
     * Create billing alert.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_billing_alert(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/billing/alerts', $params, $idempotency_key);
    }

    /**
     * Retrieve billing alert.
     *
     * @return array<mixed>|null
     */
    public function retrieve_billing_alert(string $alert_id): ?array
    {
        $alert_id = $this->normalize_required_id($alert_id, 'alert_id');
        if ($alert_id === null) {
            return null;
        }

        return $this->get('/v1/billing/alerts/' . rawurlencode($alert_id));
    }

    /**
     * List billing alerts.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_billing_alerts(array $query = []): ?array
    {
        return $this->get('/v1/billing/alerts', $query);
    }

    /**
     * Activate billing alert.
     *
     * @return array<mixed>|null
     */
    public function activate_billing_alert(string $alert_id, string $idempotency_key = ''): ?array
    {
        $alert_id = $this->normalize_required_id($alert_id, 'alert_id');
        if ($alert_id === null) {
            return null;
        }

        return $this->post_form('/v1/billing/alerts/' . rawurlencode($alert_id) . '/activate', [], $idempotency_key);
    }

    /**
     * Archive billing alert.
     *
     * @return array<mixed>|null
     */
    public function archive_billing_alert(string $alert_id, string $idempotency_key = ''): ?array
    {
        $alert_id = $this->normalize_required_id($alert_id, 'alert_id');
        if ($alert_id === null) {
            return null;
        }

        return $this->post_form('/v1/billing/alerts/' . rawurlencode($alert_id) . '/archive', [], $idempotency_key);
    }

    /**
     * Deactivate billing alert.
     *
     * @return array<mixed>|null
     */
    public function deactivate_billing_alert(string $alert_id, string $idempotency_key = ''): ?array
    {
        $alert_id = $this->normalize_required_id($alert_id, 'alert_id');
        if ($alert_id === null) {
            return null;
        }

        return $this->post_form('/v1/billing/alerts/' . rawurlencode($alert_id) . '/deactivate', [], $idempotency_key);
    }

    /**
     * Create payment link.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_payment_link(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/payment_links', $params, $idempotency_key);
    }

    /**
     * Update payment link.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_payment_link(
        string $payment_link_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $payment_link_id = $this->normalize_required_id($payment_link_id, 'payment_link_id');
        if ($payment_link_id === null) {
            return null;
        }

        return $this->post_form('/v1/payment_links/' . rawurlencode($payment_link_id), $params, $idempotency_key);
    }

    /**
     * Retrieve payment link.
     *
     * @return array<mixed>|null
     */
    public function retrieve_payment_link(string $payment_link_id): ?array
    {
        $payment_link_id = $this->normalize_required_id($payment_link_id, 'payment_link_id');
        if ($payment_link_id === null) {
            return null;
        }

        return $this->get('/v1/payment_links/' . rawurlencode($payment_link_id));
    }

    /**
     * List payment links.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_payment_links(array $query = []): ?array
    {
        return $this->get('/v1/payment_links', $query);
    }

    /**
     * Retrieve payment link line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_payment_link_line_items(string $payment_link_id, array $query = []): ?array
    {
        $payment_link_id = $this->normalize_required_id($payment_link_id, 'payment_link_id');
        if ($payment_link_id === null) {
            return null;
        }

        return $this->get('/v1/payment_links/' . rawurlencode($payment_link_id) . '/line_items', $query);
    }

    /**
     * Create checkout session.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_checkout_session(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/checkout/sessions', $params, $idempotency_key);
    }

    /**
     * Update checkout session.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_checkout_session(string $session_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $session_id = $this->normalize_required_id($session_id, 'session_id');
        if ($session_id === null) {
            return null;
        }

        return $this->post_form('/v1/checkout/sessions/' . rawurlencode($session_id), $params, $idempotency_key);
    }

    /**
     * Retrieve checkout session.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_checkout_session(string $session_id, array $query = []): ?array
    {
        $session_id = $this->normalize_required_id($session_id, 'session_id');
        if ($session_id === null) {
            return null;
        }

        return $this->get('/v1/checkout/sessions/' . rawurlencode($session_id), $query);
    }

    /**
     * List checkout session line items.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_checkout_session_line_items(string $session_id, array $query = []): ?array
    {
        $session_id = $this->normalize_required_id($session_id, 'session_id');
        if ($session_id === null) {
            return null;
        }

        return $this->get('/v1/checkout/sessions/' . rawurlencode($session_id) . '/line_items', $query);
    }

    /**
     * List checkout sessions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_checkout_sessions(array $query = []): ?array
    {
        return $this->get('/v1/checkout/sessions', $query);
    }

    /**
     * Expire checkout session.
     *
     * @return array<mixed>|null
     */
    public function expire_checkout_session(string $session_id, string $idempotency_key = ''): ?array
    {
        $session_id = $this->normalize_required_id($session_id, 'session_id');
        if ($session_id === null) {
            return null;
        }

        return $this->post_form('/v1/checkout/sessions/' . rawurlencode($session_id) . '/expire', [], $idempotency_key);
    }

    /**
     * Create shipping rate.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_shipping_rate(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/shipping_rates', $params, $idempotency_key);
    }

    /**
     * Update shipping rate.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_shipping_rate(
        string $shipping_rate_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $shipping_rate_id = $this->normalize_required_id($shipping_rate_id, 'shipping_rate_id');
        if ($shipping_rate_id === null) {
            return null;
        }

        return $this->post_form('/v1/shipping_rates/' . rawurlencode($shipping_rate_id), $params, $idempotency_key);
    }

    /**
     * Retrieve shipping rate.
     *
     * @return array<mixed>|null
     */
    public function retrieve_shipping_rate(string $shipping_rate_id): ?array
    {
        $shipping_rate_id = $this->normalize_required_id($shipping_rate_id, 'shipping_rate_id');
        if ($shipping_rate_id === null) {
            return null;
        }

        return $this->get('/v1/shipping_rates/' . rawurlencode($shipping_rate_id));
    }

    /**
     * List shipping rates.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_shipping_rates(array $query = []): ?array
    {
        return $this->get('/v1/shipping_rates', $query);
    }

    /**
     * Create tax rate.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_tax_rate(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/tax_rates', $params, $idempotency_key);
    }

    /**
     * Update tax rate.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_tax_rate(string $tax_rate_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $tax_rate_id = $this->normalize_required_id($tax_rate_id, 'tax_rate_id');
        if ($tax_rate_id === null) {
            return null;
        }

        return $this->post_form('/v1/tax_rates/' . rawurlencode($tax_rate_id), $params, $idempotency_key);
    }

    /**
     * Retrieve tax rate.
     *
     * @return array<mixed>|null
     */
    public function retrieve_tax_rate(string $tax_rate_id): ?array
    {
        $tax_rate_id = $this->normalize_required_id($tax_rate_id, 'tax_rate_id');
        if ($tax_rate_id === null) {
            return null;
        }

        return $this->get('/v1/tax_rates/' . rawurlencode($tax_rate_id));
    }

    /**
     * List tax rates.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_tax_rates(array $query = []): ?array
    {
        return $this->get('/v1/tax_rates', $query);
    }

    /**
     * Retrieve tax code.
     *
     * @return array<mixed>|null
     */
    public function retrieve_tax_code(string $tax_code_id): ?array
    {
        $tax_code_id = $this->normalize_required_id($tax_code_id, 'tax_code_id');
        if ($tax_code_id === null) {
            return null;
        }

        return $this->get('/v1/tax_codes/' . rawurlencode($tax_code_id));
    }

    /**
     * List tax codes.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_tax_codes(array $query = []): ?array
    {
        return $this->get('/v1/tax_codes', $query);
    }

    /**
     * Delete customer discount.
     *
     * @return array<mixed>|null
     */
    public function delete_customer_discount(string $customer_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->delete_request('/v1/customers/' . rawurlencode($customer_id) . '/discount');
    }

    /**
     * Delete subscription discount.
     *
     * @return array<mixed>|null
     */
    public function delete_subscription_discount(string $subscription_id): ?array
    {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        return $this->delete_request('/v1/subscriptions/' . rawurlencode($subscription_id) . '/discount');
    }

    /**
     * Create promotion code.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_promotion_code(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/promotion_codes', $params, $idempotency_key);
    }

    /**
     * Update promotion code.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_promotion_code(
        string $promotion_code_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $promotion_code_id = $this->normalize_required_id($promotion_code_id, 'promotion_code_id');
        if ($promotion_code_id === null) {
            return null;
        }

        return $this->post_form('/v1/promotion_codes/' . rawurlencode($promotion_code_id), $params, $idempotency_key);
    }

    /**
     * Retrieve promotion code.
     *
     * @return array<mixed>|null
     */
    public function retrieve_promotion_code(string $promotion_code_id): ?array
    {
        $promotion_code_id = $this->normalize_required_id($promotion_code_id, 'promotion_code_id');
        if ($promotion_code_id === null) {
            return null;
        }

        return $this->get('/v1/promotion_codes/' . rawurlencode($promotion_code_id));
    }

    /**
     * List promotion codes.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_promotion_codes(array $query = []): ?array
    {
        return $this->get('/v1/promotion_codes', $query);
    }

    /**
     * Create coupon.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_coupon(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/coupons', $params, $idempotency_key);
    }

    /**
     * Update coupon.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_coupon(string $coupon_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $coupon_id = $this->normalize_required_id($coupon_id, 'coupon_id');
        if ($coupon_id === null) {
            return null;
        }

        return $this->post_form('/v1/coupons/' . rawurlencode($coupon_id), $params, $idempotency_key);
    }

    /**
     * Retrieve coupon.
     *
     * @return array<mixed>|null
     */
    public function retrieve_coupon(string $coupon_id): ?array
    {
        $coupon_id = $this->normalize_required_id($coupon_id, 'coupon_id');
        if ($coupon_id === null) {
            return null;
        }

        return $this->get('/v1/coupons/' . rawurlencode($coupon_id));
    }

    /**
     * List coupons.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_coupons(array $query = []): ?array
    {
        return $this->get('/v1/coupons', $query);
    }

    /**
     * Delete coupon.
     *
     * @return array<mixed>|null
     */
    public function delete_coupon(string $coupon_id): ?array
    {
        $coupon_id = $this->normalize_required_id($coupon_id, 'coupon_id');
        if ($coupon_id === null) {
            return null;
        }

        return $this->delete_request('/v1/coupons/' . rawurlencode($coupon_id));
    }

    /**
     * Create price.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_price(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/prices', $params, $idempotency_key);
    }

    /**
     * Update price.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_price(string $price_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $price_id = $this->normalize_required_id($price_id, 'price_id');
        if ($price_id === null) {
            return null;
        }

        return $this->post_form('/v1/prices/' . rawurlencode($price_id), $params, $idempotency_key);
    }

    /**
     * Retrieve price.
     *
     * @return array<mixed>|null
     */
    public function retrieve_price(string $price_id): ?array
    {
        $price_id = $this->normalize_required_id($price_id, 'price_id');
        if ($price_id === null) {
            return null;
        }

        return $this->get('/v1/prices/' . rawurlencode($price_id));
    }

    /**
     * List prices.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_prices(array $query = []): ?array
    {
        return $this->get('/v1/prices', $query);
    }

    /**
     * Search prices.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function search_prices(string $search_query, array $query = []): ?array
    {
        $search_query = trim($search_query);
        if ($search_query === '') {
            $this->fail('query is empty');
            return null;
        }

        $query['query'] = $search_query;
        return $this->get('/v1/prices/search', $query);
    }

    /**
     * Create product.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_product(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/products', $params, $idempotency_key);
    }

    /**
     * Update product.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_product(string $product_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $product_id = $this->normalize_required_id($product_id, 'product_id');
        if ($product_id === null) {
            return null;
        }

        return $this->post_form('/v1/products/' . rawurlencode($product_id), $params, $idempotency_key);
    }

    /**
     * Retrieve product.
     *
     * @return array<mixed>|null
     */
    public function retrieve_product(string $product_id): ?array
    {
        $product_id = $this->normalize_required_id($product_id, 'product_id');
        if ($product_id === null) {
            return null;
        }

        return $this->get('/v1/products/' . rawurlencode($product_id));
    }

    /**
     * List products.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_products(array $query = []): ?array
    {
        return $this->get('/v1/products', $query);
    }

    /**
     * Delete product.
     *
     * @return array<mixed>|null
     */
    public function delete_product(string $product_id): ?array
    {
        $product_id = $this->normalize_required_id($product_id, 'product_id');
        if ($product_id === null) {
            return null;
        }

        return $this->delete_request('/v1/products/' . rawurlencode($product_id));
    }

    /**
     * Search products.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function search_products(string $search_query, array $query = []): ?array
    {
        $search_query = trim($search_query);
        if ($search_query === '') {
            $this->fail('query is empty');
            return null;
        }

        $query['query'] = $search_query;
        return $this->get('/v1/products/search', $query);
    }

    /**
     * Create source.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_source(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/sources', $params, $idempotency_key);
    }

    /**
     * Update source.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_source(string $source_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $source_id = $this->normalize_required_id($source_id, 'source_id');
        if ($source_id === null) {
            return null;
        }

        return $this->post_form('/v1/sources/' . rawurlencode($source_id), $params, $idempotency_key);
    }

    /**
     * Retrieve source.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_source(string $source_id, array $query = []): ?array
    {
        $source_id = $this->normalize_required_id($source_id, 'source_id');
        if ($source_id === null) {
            return null;
        }

        return $this->get('/v1/sources/' . rawurlencode($source_id), $query);
    }

    /**
     * Attach source to customer.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function attach_source_to_customer(
        string $customer_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->post_form('/v1/customers/' . rawurlencode($customer_id) . '/sources', $params, $idempotency_key);
    }

    /**
     * Detach source from customer.
     *
     * @return array<mixed>|null
     */
    public function detach_source_from_customer(string $customer_id, string $source_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $source_id = $this->normalize_required_id($source_id, 'source_id');
        if ($customer_id === null || $source_id === null) {
            return null;
        }

        return $this->delete_request('/v1/customers/' . rawurlencode($customer_id) . '/sources/' . rawurlencode($source_id));
    }

    /**
     * Create customer card.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_customer_card(string $customer_id, array $params, string $idempotency_key = ''): ?array
    {
        return $this->attach_source_to_customer($customer_id, $params, $idempotency_key);
    }

    /**
     * Update customer card.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_customer_card(
        string $customer_id,
        string $card_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $card_id = $this->normalize_required_id($card_id, 'card_id');
        if ($customer_id === null || $card_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/customers/' . rawurlencode($customer_id) . '/sources/' . rawurlencode($card_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve customer card.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_card(string $customer_id, string $card_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $card_id = $this->normalize_required_id($card_id, 'card_id');
        if ($customer_id === null || $card_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/cards/' . rawurlencode($card_id));
    }

    /**
     * List customer cards.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customer_cards(string $customer_id, array $query = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/cards', $query);
    }

    /**
     * Delete customer card.
     *
     * @return array<mixed>|null
     */
    public function delete_customer_card(string $customer_id, string $card_id): ?array
    {
        return $this->detach_source_from_customer($customer_id, $card_id);
    }

    /**
     * Create customer bank account.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_customer_bank_account(string $customer_id, array $params, string $idempotency_key = ''): ?array
    {
        return $this->attach_source_to_customer($customer_id, $params, $idempotency_key);
    }

    /**
     * Update customer bank account.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_customer_bank_account(
        string $customer_id,
        string $bank_account_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $bank_account_id = $this->normalize_required_id($bank_account_id, 'bank_account_id');
        if ($customer_id === null || $bank_account_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/customers/' . rawurlencode($customer_id) . '/sources/' . rawurlencode($bank_account_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve customer bank account.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_bank_account(string $customer_id, string $bank_account_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $bank_account_id = $this->normalize_required_id($bank_account_id, 'bank_account_id');
        if ($customer_id === null || $bank_account_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/bank_accounts/' . rawurlencode($bank_account_id));
    }

    /**
     * List customer bank accounts.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customer_bank_accounts(string $customer_id, array $query = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/bank_accounts', $query);
    }

    /**
     * Delete customer bank account.
     *
     * @return array<mixed>|null
     */
    public function delete_customer_bank_account(string $customer_id, string $bank_account_id): ?array
    {
        return $this->detach_source_from_customer($customer_id, $bank_account_id);
    }

    /**
     * Verify customer bank account.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function verify_customer_bank_account(
        string $customer_id,
        string $bank_account_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $bank_account_id = $this->normalize_required_id($bank_account_id, 'bank_account_id');
        if ($customer_id === null || $bank_account_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/customers/' . rawurlencode($customer_id) . '/sources/' . rawurlencode($bank_account_id) . '/verify',
            $params,
            $idempotency_key
        );
    }

    /**
     * Create or retrieve customer funding instructions.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_or_retrieve_customer_funding_instructions(
        string $customer_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/customers/' . rawurlencode($customer_id) . '/funding_instructions',
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve customer cash balance transaction.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_cash_balance_transaction(
        string $customer_id,
        string $transaction_id
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $transaction_id = $this->normalize_required_id($transaction_id, 'transaction_id');
        if ($customer_id === null || $transaction_id === null) {
            return null;
        }

        return $this->get(
            '/v1/customers/' . rawurlencode($customer_id) . '/cash_balance_transactions/' . rawurlencode($transaction_id)
        );
    }

    /**
     * List customer cash balance transactions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customer_cash_balance_transactions(string $customer_id, array $query = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/cash_balance_transactions', $query);
    }

    /**
     * Fund test customer cash balance.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function fund_test_customer_cash_balance(
        string $customer_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/test_helpers/customers/' . rawurlencode($customer_id) . '/fund_cash_balance',
            $params,
            $idempotency_key
        );
    }

    /**
     * Update customer cash balance.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_customer_cash_balance(
        string $customer_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->post_form('/v1/customers/' . rawurlencode($customer_id) . '/cash_balance', $params, $idempotency_key);
    }

    /**
     * Retrieve customer cash balance.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_cash_balance(string $customer_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/cash_balance');
    }

    /**
     * Create payment method domain.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_payment_method_domain(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/payment_method_domains', $params, $idempotency_key);
    }

    /**
     * Update payment method domain.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_payment_method_domain(
        string $payment_method_domain_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $payment_method_domain_id = $this->normalize_required_id($payment_method_domain_id, 'payment_method_domain_id');
        if ($payment_method_domain_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/payment_method_domains/' . rawurlencode($payment_method_domain_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve payment method domain.
     *
     * @return array<mixed>|null
     */
    public function retrieve_payment_method_domain(string $payment_method_domain_id): ?array
    {
        $payment_method_domain_id = $this->normalize_required_id($payment_method_domain_id, 'payment_method_domain_id');
        if ($payment_method_domain_id === null) {
            return null;
        }

        return $this->get('/v1/payment_method_domains/' . rawurlencode($payment_method_domain_id));
    }

    /**
     * List payment method domains.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_payment_method_domains(array $query = []): ?array
    {
        return $this->get('/v1/payment_method_domains', $query);
    }

    /**
     * Validate payment method domain.
     *
     * @return array<mixed>|null
     */
    public function validate_payment_method_domain(
        string $payment_method_domain_id,
        string $idempotency_key = ''
    ): ?array {
        $payment_method_domain_id = $this->normalize_required_id($payment_method_domain_id, 'payment_method_domain_id');
        if ($payment_method_domain_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/payment_method_domains/' . rawurlencode($payment_method_domain_id) . '/validate',
            [],
            $idempotency_key
        );
    }

    /**
     * Create payment method configuration.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_payment_method_configuration(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/payment_method_configurations', $params, $idempotency_key);
    }

    /**
     * Update payment method configuration.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_payment_method_configuration(
        string $payment_method_configuration_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $payment_method_configuration_id = $this->normalize_required_id(
            $payment_method_configuration_id,
            'payment_method_configuration_id'
        );
        if ($payment_method_configuration_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/payment_method_configurations/' . rawurlencode($payment_method_configuration_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * Retrieve payment method configuration.
     *
     * @return array<mixed>|null
     */
    public function retrieve_payment_method_configuration(string $payment_method_configuration_id): ?array
    {
        $payment_method_configuration_id = $this->normalize_required_id(
            $payment_method_configuration_id,
            'payment_method_configuration_id'
        );
        if ($payment_method_configuration_id === null) {
            return null;
        }

        return $this->get('/v1/payment_method_configurations/' . rawurlencode($payment_method_configuration_id));
    }

    /**
     * List payment method configurations.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_payment_method_configurations(array $query = []): ?array
    {
        return $this->get('/v1/payment_method_configurations', $query);
    }

    /**
     * Create payment method.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_payment_method(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/payment_methods', $params, $idempotency_key);
    }

    /**
     * Update payment method.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_payment_method(
        string $payment_method_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $payment_method_id = $this->normalize_required_id($payment_method_id, 'payment_method_id');
        if ($payment_method_id === null) {
            return null;
        }

        return $this->post_form('/v1/payment_methods/' . rawurlencode($payment_method_id), $params, $idempotency_key);
    }

    /**
     * Retrieve customer payment method.
     *
     * @return array<mixed>|null
     */
    public function retrieve_customer_payment_method(string $customer_id, string $payment_method_id): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        $payment_method_id = $this->normalize_required_id($payment_method_id, 'payment_method_id');
        if ($customer_id === null || $payment_method_id === null) {
            return null;
        }

        return $this->get(
            '/v1/customers/' . rawurlencode($customer_id) . '/payment_methods/' . rawurlencode($payment_method_id)
        );
    }

    /**
     * Retrieve payment method.
     *
     * @return array<mixed>|null
     */
    public function retrieve_payment_method(string $payment_method_id): ?array
    {
        $payment_method_id = $this->normalize_required_id($payment_method_id, 'payment_method_id');
        if ($payment_method_id === null) {
            return null;
        }

        return $this->get('/v1/payment_methods/' . rawurlencode($payment_method_id));
    }

    /**
     * List customer payment methods.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_customer_payment_methods(string $customer_id, array $query = []): ?array
    {
        $customer_id = $this->normalize_required_id($customer_id, 'customer_id');
        if ($customer_id === null) {
            return null;
        }

        return $this->get('/v1/customers/' . rawurlencode($customer_id) . '/payment_methods', $query);
    }

    /**
     * List payment methods.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_payment_methods(array $query = []): ?array
    {
        return $this->get('/v1/payment_methods', $query);
    }

    /**
     * Attach payment method.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function attach_payment_method(
        string $payment_method_id,
        array $params,
        string $idempotency_key = ''
    ): ?array {
        $payment_method_id = $this->normalize_required_id($payment_method_id, 'payment_method_id');
        if ($payment_method_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/payment_methods/' . rawurlencode($payment_method_id) . '/attach',
            $params,
            $idempotency_key
        );
    }

    /**
     * Detach payment method.
     *
     * @return array<mixed>|null
     */
    public function detach_payment_method(string $payment_method_id, string $idempotency_key = ''): ?array
    {
        $payment_method_id = $this->normalize_required_id($payment_method_id, 'payment_method_id');
        if ($payment_method_id === null) {
            return null;
        }

        return $this->post_form('/v1/payment_methods/' . rawurlencode($payment_method_id) . '/detach', [], $idempotency_key);
    }

    /**
     * Create token.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_token(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/tokens', $params, $idempotency_key);
    }

    /**
     * Retrieve token.
     *
     * @return array<mixed>|null
     */
    public function retrieve_token(string $token_id): ?array
    {
        $token_id = $this->normalize_required_id($token_id, 'token_id');
        if ($token_id === null) {
            return null;
        }

        return $this->get('/v1/tokens/' . rawurlencode($token_id));
    }

    /**
     * Retrieve confirmation token.
     *
     * @return array<mixed>|null
     */
    public function retrieve_confirmation_token(string $confirmation_token_id): ?array
    {
        $confirmation_token_id = $this->normalize_required_id($confirmation_token_id, 'confirmation_token_id');
        if ($confirmation_token_id === null) {
            return null;
        }

        return $this->get('/v1/confirmation_tokens/' . rawurlencode($confirmation_token_id));
    }

    /**
     * Create test confirmation token.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_test_confirmation_token(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/test_helpers/confirmation_tokens', $params, $idempotency_key);
    }

    /**
     * Update refund.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_refund(string $refund_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $refund_id = $this->normalize_required_id($refund_id, 'refund_id');
        if ($refund_id === null) {
            return null;
        }

        return $this->post_form('/v1/refunds/' . rawurlencode($refund_id), $params, $idempotency_key);
    }

    /**
     * Retrieve refund.
     *
     * @return array<mixed>|null
     */
    public function retrieve_refund(string $refund_id): ?array
    {
        $refund_id = $this->normalize_required_id($refund_id, 'refund_id');
        if ($refund_id === null) {
            return null;
        }

        return $this->get('/v1/refunds/' . rawurlencode($refund_id));
    }

    /**
     * List refunds.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_refunds(array $query = []): ?array
    {
        return $this->get('/v1/refunds', $query);
    }

    /**
     * Cancel refund.
     *
     * @return array<mixed>|null
     */
    public function cancel_refund(string $refund_id, string $idempotency_key = ''): ?array
    {
        $refund_id = $this->normalize_required_id($refund_id, 'refund_id');
        if ($refund_id === null) {
            return null;
        }

        return $this->post_form('/v1/refunds/' . rawurlencode($refund_id) . '/cancel', [], $idempotency_key);
    }

    /**
     * Create payout.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_payout(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/payouts', $params, $idempotency_key);
    }

    /**
     * Update payout.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_payout(string $payout_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $payout_id = $this->normalize_required_id($payout_id, 'payout_id');
        if ($payout_id === null) {
            return null;
        }

        return $this->post_form('/v1/payouts/' . rawurlencode($payout_id), $params, $idempotency_key);
    }

    /**
     * Retrieve payout.
     *
     * @return array<mixed>|null
     */
    public function retrieve_payout(string $payout_id): ?array
    {
        $payout_id = $this->normalize_required_id($payout_id, 'payout_id');
        if ($payout_id === null) {
            return null;
        }

        return $this->get('/v1/payouts/' . rawurlencode($payout_id));
    }

    /**
     * List payouts.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_payouts(array $query = []): ?array
    {
        return $this->get('/v1/payouts', $query);
    }

    /**
     * Cancel payout.
     *
     * @return array<mixed>|null
     */
    public function cancel_payout(string $payout_id, string $idempotency_key = ''): ?array
    {
        $payout_id = $this->normalize_required_id($payout_id, 'payout_id');
        if ($payout_id === null) {
            return null;
        }

        return $this->post_form('/v1/payouts/' . rawurlencode($payout_id) . '/cancel', [], $idempotency_key);
    }

    /**
     * Reverse payout.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function reverse_payout(string $payout_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $payout_id = $this->normalize_required_id($payout_id, 'payout_id');
        if ($payout_id === null) {
            return null;
        }

        return $this->post_form('/v1/payouts/' . rawurlencode($payout_id) . '/reverse', $params, $idempotency_key);
    }

    /**
     * Create subscription schedule.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_subscription_schedule(array $params = [], string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/subscription_schedules', $params, $idempotency_key);
    }

    /**
     * Update subscription schedule.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_subscription_schedule(
        string $schedule_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $schedule_id = $this->normalize_required_id($schedule_id, 'schedule_id');
        if ($schedule_id === null) {
            return null;
        }

        return $this->post_form('/v1/subscription_schedules/' . rawurlencode($schedule_id), $params, $idempotency_key);
    }

    /**
     * Retrieve subscription schedule.
     *
     * @return array<mixed>|null
     */
    public function retrieve_subscription_schedule(string $schedule_id): ?array
    {
        $schedule_id = $this->normalize_required_id($schedule_id, 'schedule_id');
        if ($schedule_id === null) {
            return null;
        }

        return $this->get('/v1/subscription_schedules/' . rawurlencode($schedule_id));
    }

    /**
     * List subscription schedules.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_subscription_schedules(array $query = []): ?array
    {
        return $this->get('/v1/subscription_schedules', $query);
    }

    /**
     * Cancel subscription schedule.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function cancel_subscription_schedule(
        string $schedule_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $schedule_id = $this->normalize_required_id($schedule_id, 'schedule_id');
        if ($schedule_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/subscription_schedules/' . rawurlencode($schedule_id) . '/cancel',
            $params,
            $idempotency_key
        );
    }

    /**
     * Release subscription schedule.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function release_subscription_schedule(
        string $schedule_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $schedule_id = $this->normalize_required_id($schedule_id, 'schedule_id');
        if ($schedule_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/subscription_schedules/' . rawurlencode($schedule_id) . '/release',
            $params,
            $idempotency_key
        );
    }

    /**
     * Create subscription item.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_subscription_item(array $params, string $idempotency_key = ''): ?array
    {
        return $this->post_form('/v1/subscription_items', $params, $idempotency_key);
    }

    /**
     * Update subscription item.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_subscription_item(
        string $item_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $item_id = $this->normalize_required_id($item_id, 'item_id');
        if ($item_id === null) {
            return null;
        }

        return $this->post_form('/v1/subscription_items/' . rawurlencode($item_id), $params, $idempotency_key);
    }

    /**
     * Retrieve subscription item.
     *
     * @return array<mixed>|null
     */
    public function retrieve_subscription_item(string $item_id): ?array
    {
        $item_id = $this->normalize_required_id($item_id, 'item_id');
        if ($item_id === null) {
            return null;
        }

        return $this->get('/v1/subscription_items/' . rawurlencode($item_id));
    }

    /**
     * List subscription items for a subscription.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_subscription_items(string $subscription_id, array $query = []): ?array
    {
        $subscription_id = $this->normalize_required_id($subscription_id, 'subscription_id');
        if ($subscription_id === null) {
            return null;
        }

        $query['subscription'] = $subscription_id;
        return $this->get('/v1/subscription_items', $query);
    }

    /**
     * Delete subscription item.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function delete_subscription_item(string $item_id, array $params = []): ?array
    {
        $item_id = $this->normalize_required_id($item_id, 'item_id');
        if ($item_id === null) {
            return null;
        }

        if ($params === []) {
            return $this->delete_request('/v1/subscription_items/' . rawurlencode($item_id));
        }

        return $this->request_json(
            'DELETE',
            '/v1/subscription_items/' . rawurlencode($item_id),
            [],
            $params,
            'form'
        );
    }

    /**
     * Create a direct charge (legacy API, deprecated by Stripe).
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_charge(
        int $amount,
        string $currency,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        if ($amount <= 0) {
            $this->fail('amount must be > 0');
            return null;
        }

        $currency = strtolower(trim($currency));
        if (!$this->is_currency($currency)) {
            $this->fail('currency must be 3-letter ISO code');
            return null;
        }

        $body = $params;
        $body['amount'] = $amount;
        $body['currency'] = $currency;

        return $this->post_form('/v1/charges', $body, $idempotency_key);
    }

    /**
     * Update a charge.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_charge(string $charge_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $charge_id = $this->normalize_required_id($charge_id, 'charge_id');
        if ($charge_id === null) {
            return null;
        }

        return $this->post_form('/v1/charges/' . rawurlencode($charge_id), $params, $idempotency_key);
    }

    /**
     * Retrieve a charge by ID.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_charge(string $charge_id, array $query = []): ?array
    {
        $charge_id = $this->normalize_required_id($charge_id, 'charge_id');
        if ($charge_id === null) {
            return null;
        }

        return $this->get('/v1/charges/' . rawurlencode($charge_id), $query);
    }

    /**
     * List charges.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_charges(array $query = []): ?array
    {
        return $this->get('/v1/charges', $query);
    }

    /**
     * Capture an uncaptured charge.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function capture_charge(string $charge_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $charge_id = $this->normalize_required_id($charge_id, 'charge_id');
        if ($charge_id === null) {
            return null;
        }

        return $this->post_form('/v1/charges/' . rawurlencode($charge_id) . '/capture', $params, $idempotency_key);
    }

    /**
     * Search charges.
     *
     * @param array<string,mixed> $query_params
     * @return array<mixed>|null
     */
    public function search_charges(string $query, array $query_params = []): ?array
    {
        $query = trim($query);
        if ($query === '') {
            $this->fail('query is empty');
            return null;
        }

        $request_query = $query_params;
        $request_query['query'] = $query;

        return $this->get('/v1/charges/search', $request_query);
    }

    /**
     * Retrieve a single balance transaction.
     *
     * @return array<mixed>|null
     */
    public function retrieve_balance_transaction(string $transaction_id): ?array
    {
        $transaction_id = $this->normalize_required_id($transaction_id, 'transaction_id');
        if ($transaction_id === null) {
            return null;
        }

        return $this->get('/v1/balance_transactions/' . rawurlencode($transaction_id));
    }

    /**
     * List balance transactions.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_balance_transactions(array $query = []): ?array
    {
        return $this->get('/v1/balance_transactions', $query);
    }

    /**
     * Retrieve Stripe account balance.
     *
     * @return array<mixed>|null
     */
    public function retrieve_balance(): ?array
    {
        return $this->get('/v1/balance');
    }

    /**
     * Create account token via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_v2_account_token(
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        return $this->request_json(
            'POST',
            '/v2/core/account_tokens',
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Retrieve account token via Stripe Core v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function retrieve_v2_account_token(
        string $account_token_id,
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $account_token_id = $this->normalize_required_id($account_token_id, 'account_token_id');
        if ($account_token_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/account_tokens/' . rawurlencode($account_token_id),
            [],
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create account link via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_v2_account_link(
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        return $this->request_json(
            'POST',
            '/v2/core/account_links',
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create account link via Stripe Core v2 endpoint using explicit use_case params.
     *
     * @param array<int,string> $configurations
     * @param array<string,mixed> $collection_options
     * @return array<mixed>|null
     */
    public function create_v2_account_link_with_use_case(
        string $account_id,
        string $use_case_type,
        array $configurations,
        string $refresh_url,
        string $return_url = '',
        array $collection_options = [],
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        $use_case_type = strtolower(trim($use_case_type));
        if (!isset(self::V2_ACCOUNT_LINK_USE_CASE_TYPES[$use_case_type])) {
            $this->fail('use_case_type must be account_onboarding or account_update');
            return null;
        }

        $normalized_configurations = $this->normalize_v2_account_link_configurations($configurations);
        if ($normalized_configurations === null) {
            return null;
        }

        $refresh_url = trim($refresh_url);
        if (!$this->is_http_url($refresh_url)) {
            $this->fail('refresh_url must be valid http/https URL');
            return null;
        }

        $return_url = trim($return_url);
        if ($return_url !== '' && !$this->is_http_url($return_url)) {
            $this->fail('return_url must be valid http/https URL');
            return null;
        }

        $normalized_collection_options = $this->normalize_v2_account_link_collection_options($collection_options);
        if ($normalized_collection_options === null) {
            return null;
        }

        $use_case_config = [
            'configurations' => $normalized_configurations,
            'refresh_url' => $refresh_url,
        ];
        if ($return_url !== '') {
            $use_case_config['return_url'] = $return_url;
        }
        if ($normalized_collection_options !== []) {
            $use_case_config['collection_options'] = $normalized_collection_options;
        }

        return $this->create_v2_account_link(
            [
                'account' => $account_id,
                'use_case' => [
                    'type' => $use_case_type,
                    $use_case_type => $use_case_config,
                ],
            ],
            $stripe_version,
            $idempotency_key
        );
    }

    /**
     * Shortcut for account_onboarding v2 account link creation.
     *
     * @param array<int,string> $configurations
     * @param array<string,mixed> $collection_options
     * @return array<mixed>|null
     */
    public function create_v2_account_onboarding_link(
        string $account_id,
        array $configurations,
        string $refresh_url,
        string $return_url = '',
        array $collection_options = [],
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        return $this->create_v2_account_link_with_use_case(
            $account_id,
            'account_onboarding',
            $configurations,
            $refresh_url,
            $return_url,
            $collection_options,
            $stripe_version,
            $idempotency_key
        );
    }

    /**
     * Shortcut for account_update v2 account link creation.
     *
     * @param array<int,string> $configurations
     * @param array<string,mixed> $collection_options
     * @return array<mixed>|null
     */
    public function create_v2_account_update_link(
        string $account_id,
        array $configurations,
        string $refresh_url,
        string $return_url = '',
        array $collection_options = [],
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        return $this->create_v2_account_link_with_use_case(
            $account_id,
            'account_update',
            $configurations,
            $refresh_url,
            $return_url,
            $collection_options,
            $stripe_version,
            $idempotency_key
        );
    }

    /**
     * Create account session for Connect embedded components.
     *
     * @param array<string,mixed> $components
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_account_session(
        string $account_id,
        array $components,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }
        if ($components === []) {
            $this->fail('components is empty');
            return null;
        }

        $body = $params;
        $body['account'] = $account_id;
        $body['components'] = $components;

        return $this->post_form('/v1/account_sessions', $body, $idempotency_key);
    }

    /**
     * Create account link for onboarding/update.
     *
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_account_link(
        string $account_id,
        string $type,
        string $refresh_url,
        string $return_url,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        $type = strtolower(trim($type));
        if (!isset(self::ACCOUNT_LINK_TYPES[$type])) {
            $this->fail('type must be account_onboarding or account_update');
            return null;
        }

        $refresh_url = trim($refresh_url);
        $return_url = trim($return_url);
        if (!$this->is_http_url($refresh_url)) {
            $this->fail('refresh_url must be valid http/https URL');
            return null;
        }
        if (!$this->is_http_url($return_url)) {
            $this->fail('return_url must be valid http/https URL');
            return null;
        }

        $body = $params;
        $body['account'] = $account_id;
        $body['type'] = $type;
        $body['refresh_url'] = $refresh_url;
        $body['return_url'] = $return_url;

        return $this->post_form('/v1/account_links', $body, $idempotency_key);
    }

    /**
     * Create single-use Express Dashboard login link.
     *
     * @return array<mixed>|null
     */
    public function create_login_link(string $account_id, string $idempotency_key = ''): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v1/accounts/' . rawurlencode($account_id) . '/login_links',
            [],
            null,
            'none',
            $idempotency_key
        );
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_secrets(array $scope, array $query = []): ?array
    {
        if (!$this->validate_scope($scope)) {
            return null;
        }
        $query['scope'] = $scope;
        return $this->get('/v1/apps/secrets', $query);
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<mixed>|null
     */
    public function find_secret(string $name, array $scope): ?array
    {
        $name = trim($name);
        if ($name === '') {
            $this->fail('name is empty');
            return null;
        }
        if (!$this->validate_scope($scope)) {
            return null;
        }
        return $this->get('/v1/apps/secrets/find', [
            'name' => $name,
            'scope' => $scope,
        ]);
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<mixed>|null
     */
    public function create_secret(
        string $name,
        string $payload,
        array $scope,
        ?int $expires_at = null,
        string $idempotency_key = ''
    ): ?array {
        $name = trim($name);
        if ($name === '') {
            $this->fail('name is empty');
            return null;
        }

        if (!$this->validate_scope($scope)) {
            return null;
        }

        $body = [
            'name' => $name,
            'payload' => $payload,
            'scope' => $scope,
        ];
        if ($expires_at !== null && $expires_at > 0) {
            $body['expires_at'] = $expires_at;
        }

        return $this->post_form('/v1/apps/secrets', $body, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<mixed>|null
     */
    public function delete_secret(string $name, array $scope, string $idempotency_key = ''): ?array
    {
        $name = trim($name);
        if ($name === '') {
            $this->fail('name is empty');
            return null;
        }

        if (!$this->validate_scope($scope)) {
            return null;
        }

        return $this->post_form('/v1/apps/secrets/delete', [
            'name' => $name,
            'scope' => $scope,
        ], $idempotency_key);
    }

    /**
     * Update person via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function update_v2_account_person(
        string $account_id,
        string $person_id,
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_id = $this->normalize_required_id($person_id, 'person_id');
        if ($account_id === null || $person_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/persons/' . rawurlencode($person_id),
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create person via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_v2_account_person(
        string $account_id,
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/persons',
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Retrieve person via Stripe Connect v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function retrieve_v2_account_person(
        string $account_id,
        string $person_id,
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_id = $this->normalize_required_id($person_id, 'person_id');
        if ($account_id === null || $person_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/persons/' . rawurlencode($person_id),
            [],
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * List persons via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_v2_account_persons(
        string $account_id,
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/persons',
            $query,
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Delete person via Stripe Connect v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function delete_v2_account_person(
        string $account_id,
        string $person_id,
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_id = $this->normalize_required_id($person_id, 'person_id');
        if ($account_id === null || $person_id === null) {
            return null;
        }

        return $this->request_json(
            'DELETE',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/persons/' . rawurlencode($person_id),
            [],
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create person token via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_v2_person_token(
        string $account_id,
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/person_tokens',
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Retrieve person token via Stripe Connect v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function retrieve_v2_person_token(
        string $account_id,
        string $person_token_id,
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $person_token_id = $this->normalize_required_id($person_token_id, 'person_token_id');
        if ($account_id === null || $person_token_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/accounts/' . rawurlencode($account_id) . '/person_tokens/' . rawurlencode($person_token_id),
            [],
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Create event destination via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_v2_event_destination(
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        return $this->request_json(
            'POST',
            '/v2/core/event_destinations',
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Update event destination via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function update_v2_event_destination(
        string $event_destination_id,
        array $payload,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $event_destination_id = $this->normalize_required_id($event_destination_id, 'event_destination_id');
        if ($event_destination_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/event_destinations/' . rawurlencode($event_destination_id),
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Retrieve event destination via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_v2_event_destination(
        string $event_destination_id,
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $event_destination_id = $this->normalize_required_id($event_destination_id, 'event_destination_id');
        if ($event_destination_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/event_destinations/' . rawurlencode($event_destination_id),
            $query,
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * List event destinations via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_v2_event_destinations(
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        return $this->request_json(
            'GET',
            '/v2/core/event_destinations',
            $query,
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Delete event destination via Stripe Core v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function delete_v2_event_destination(
        string $event_destination_id,
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $event_destination_id = $this->normalize_required_id($event_destination_id, 'event_destination_id');
        if ($event_destination_id === null) {
            return null;
        }

        return $this->request_json(
            'DELETE',
            '/v2/core/event_destinations/' . rawurlencode($event_destination_id),
            [],
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Disable event destination via Stripe Core v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function disable_v2_event_destination(
        string $event_destination_id,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $event_destination_id = $this->normalize_required_id($event_destination_id, 'event_destination_id');
        if ($event_destination_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/event_destinations/' . rawurlencode($event_destination_id) . '/disable',
            [],
            null,
            'none',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Enable event destination via Stripe Core v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function enable_v2_event_destination(
        string $event_destination_id,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $event_destination_id = $this->normalize_required_id($event_destination_id, 'event_destination_id');
        if ($event_destination_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/event_destinations/' . rawurlencode($event_destination_id) . '/enable',
            [],
            null,
            'none',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Send ping event to event destination via Stripe Core v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function ping_v2_event_destination(
        string $event_destination_id,
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        $event_destination_id = $this->normalize_required_id($event_destination_id, 'event_destination_id');
        if ($event_destination_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/event_destinations/' . rawurlencode($event_destination_id) . '/ping',
            [],
            null,
            'none',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Retrieve event via Stripe Core v2 endpoint.
     *
     * @return array<mixed>|null
     */
    public function retrieve_v2_event(string $event_id, string $stripe_version = '2026-02-25.preview'): ?array
    {
        $event_id = $this->normalize_required_id($event_id, 'event_id');
        if ($event_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/events/' . rawurlencode($event_id),
            [],
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * List events via Stripe Core v2 endpoint.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_v2_events(
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        return $this->request_json(
            'GET',
            '/v2/core/events',
            $query,
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * List v2 events filtered by event type.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_v2_events_by_type(
        string $event_type,
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $event_type = trim($event_type);
        if ($event_type === '') {
            $this->fail('event_type is empty');
            return null;
        }

        $query['type'] = $event_type;
        return $this->list_v2_events($query, $stripe_version);
    }

    /**
     * Create account via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function create_v2_account(
        array $payload = [],
        string $stripe_version = '2026-02-25.preview',
        string $idempotency_key = ''
    ): ?array {
        return $this->request_json(
            'POST',
            '/v2/core/accounts',
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Retrieve account via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function retrieve_v2_account(
        string $account_id,
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->request_json(
            'GET',
            '/v2/core/accounts/' . rawurlencode($account_id),
            $query,
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * List accounts via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_v2_accounts(
        array $query = [],
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        return $this->request_json(
            'GET',
            '/v2/core/accounts',
            $query,
            null,
            'none',
            '',
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Update account via Stripe Connect v2 endpoint.
     *
     * @param array<string,mixed> $payload
     * @return array<mixed>|null
     */
    public function update_v2_account(
        string $account_id,
        array $payload,
        string $stripe_version = '2025-11-17.preview',
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            '/v2/core/accounts/' . rawurlencode($account_id),
            [],
            $payload,
            'json',
            $idempotency_key,
            $this->connected_account_headers('', $stripe_version)
        );
    }

    /**
     * Helper for representative switch on v2 API.
     *
     * @return array<string,mixed>|null
     */
    public function switch_representative_v2(
        string $account_id,
        string $old_person_id,
        string $new_person_id,
        string $stripe_version = '2026-02-25.preview'
    ): ?array {
        $old = $this->update_v2_account_person(
            $account_id,
            $old_person_id,
            ['relationship' => ['representative' => false]],
            $stripe_version
        );
        if ($old === null) {
            return null;
        }

        $new = $this->update_v2_account_person(
            $account_id,
            $new_person_id,
            ['relationship' => ['representative' => true]],
            $stripe_version
        );
        if ($new === null) {
            return null;
        }

        return [
            'ok' => true,
            'old' => $old,
            'new' => $new,
        ];
    }

    /**
     * Helper for repeated SSA acceptance after Tax ID update on v2 API.
     *
     * @return array<mixed>|null
     */
    public function reaccept_tos_after_tax_id_update_v2(
        string $account_id,
        string $ip,
        string $accepted_at_iso8601 = '',
        string $stripe_version = '2025-11-17.preview',
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        $ip = trim($ip);
        if ($ip === '') {
            $this->fail('ip is empty');
            return null;
        }

        $accepted_at_iso8601 = trim($accepted_at_iso8601);
        if ($accepted_at_iso8601 === '') {
            $accepted_at_iso8601 = gmdate('Y-m-d\TH:i:s\Z');
        }

        return $this->update_v2_account(
            $account_id,
            [
                'identity' => [
                    'attestations' => [
                        'terms_of_service' => [
                            'account' => [
                                'date' => $accepted_at_iso8601,
                                'ip' => $ip,
                            ],
                        ],
                    ],
                ],
            ],
            $stripe_version,
            $idempotency_key
        );
    }

    /**
     * @param array<string,mixed> $params
     * @param array<string,mixed>|string $external_account
     * @return array<mixed>|null
     */
    public function create_external_account(
        string $account_id,
        array|string $external_account,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        if (is_string($external_account) && trim($external_account) === '') {
            $this->fail('external_account is empty');
            return null;
        }
        if (is_array($external_account) && $external_account === []) {
            $this->fail('external_account is empty');
            return null;
        }

        $body = $params;
        $body['external_account'] = $external_account;
        return $this->post_form('/v1/accounts/' . rawurlencode($account_id) . '/external_accounts', $body, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_external_account(
        string $account_id,
        string $external_account_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $external_account_id = $this->normalize_required_id($external_account_id, 'external_account_id');
        if ($account_id === null || $external_account_id === null) {
            return null;
        }

        return $this->post_form(
            '/v1/accounts/' . rawurlencode($account_id) . '/external_accounts/' . rawurlencode($external_account_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_external_account(string $account_id, string $external_account_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $external_account_id = $this->normalize_required_id($external_account_id, 'external_account_id');
        if ($account_id === null || $external_account_id === null) {
            return null;
        }

        return $this->get('/v1/accounts/' . rawurlencode($account_id) . '/external_accounts/' . rawurlencode($external_account_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_external_accounts(string $account_id, array $query = []): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->get('/v1/accounts/' . rawurlencode($account_id) . '/external_accounts', $query);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_external_cards(string $account_id, array $query = []): ?array
    {
        $query['object'] = 'card';
        return $this->list_external_accounts($account_id, $query);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_external_bank_accounts(string $account_id, array $query = []): ?array
    {
        $query['object'] = 'bank_account';
        return $this->list_external_accounts($account_id, $query);
    }

    /**
     * @return array<mixed>|null
     */
    public function delete_external_account(string $account_id, string $external_account_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $external_account_id = $this->normalize_required_id($external_account_id, 'external_account_id');
        if ($account_id === null || $external_account_id === null) {
            return null;
        }

        return $this->delete_request('/v1/accounts/' . rawurlencode($account_id) . '/external_accounts/' . rawurlencode($external_account_id));
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_balance_settings(string $connected_account_id = ''): ?array
    {
        return $this->request_json(
            'GET',
            '/v1/balance_settings',
            [],
            null,
            'none',
            '',
            $this->connected_account_headers($connected_account_id, '')
        );
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_balance_settings(
        array $params,
        string $connected_account_id = '',
        string $idempotency_key = ''
    ): ?array {
        return $this->request_json(
            'POST',
            '/v1/balance_settings',
            [],
            $params,
            'form',
            $idempotency_key,
            $this->connected_account_headers($connected_account_id, '')
        );
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_country_spec(string $country_code): ?array
    {
        $country_code = strtoupper(trim($country_code));
        if (preg_match('/^[A-Z]{2}$/', $country_code) !== 1) {
            $this->fail('country_code must be ISO2');
            return null;
        }

        return $this->get('/v1/country_specs/' . rawurlencode($country_code));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_country_specs(array $query = []): ?array
    {
        return $this->get('/v1/country_specs', $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_account_capability(
        string $account_id,
        string $capability_id,
        bool $requested,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $capability_id = $this->normalize_required_id($capability_id, 'capability_id');
        if ($account_id === null || $capability_id === null) {
            return null;
        }

        $body = $params;
        $body['requested'] = $requested;

        return $this->post_form(
            '/v1/accounts/' . rawurlencode($account_id) . '/capabilities/' . rawurlencode($capability_id),
            $body,
            $idempotency_key
        );
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_account_capability(string $account_id, string $capability_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        $capability_id = $this->normalize_required_id($capability_id, 'capability_id');
        if ($account_id === null || $capability_id === null) {
            return null;
        }

        return $this->get('/v1/accounts/' . rawurlencode($account_id) . '/capabilities/' . rawurlencode($capability_id));
    }

    /**
     * @return array<mixed>|null
     */
    public function list_account_capabilities(string $account_id): ?array
    {
        $account_id = $this->normalize_required_id($account_id, 'account_id');
        if ($account_id === null) {
            return null;
        }

        return $this->get('/v1/accounts/' . rawurlencode($account_id) . '/capabilities');
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_application_fee(string $fee_id): ?array
    {
        $fee_id = $this->normalize_required_id($fee_id, 'fee_id');
        if ($fee_id === null) {
            return null;
        }
        return $this->get('/v1/application_fees/' . rawurlencode($fee_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_application_fees(array $query = []): ?array
    {
        return $this->get('/v1/application_fees', $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function create_application_fee_refund(string $fee_id, array $params = [], string $idempotency_key = ''): ?array
    {
        $fee_id = $this->normalize_required_id($fee_id, 'fee_id');
        if ($fee_id === null) {
            return null;
        }
        return $this->post_form('/v1/application_fees/' . rawurlencode($fee_id) . '/refunds', $params, $idempotency_key);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function update_application_fee_refund(
        string $fee_id,
        string $refund_id,
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $fee_id = $this->normalize_required_id($fee_id, 'fee_id');
        $refund_id = $this->normalize_required_id($refund_id, 'refund_id');
        if ($fee_id === null || $refund_id === null) {
            return null;
        }
        return $this->post_form(
            '/v1/application_fees/' . rawurlencode($fee_id) . '/refunds/' . rawurlencode($refund_id),
            $params,
            $idempotency_key
        );
    }

    /**
     * @return array<mixed>|null
     */
    public function retrieve_application_fee_refund(string $fee_id, string $refund_id): ?array
    {
        $fee_id = $this->normalize_required_id($fee_id, 'fee_id');
        $refund_id = $this->normalize_required_id($refund_id, 'refund_id');
        if ($fee_id === null || $refund_id === null) {
            return null;
        }
        return $this->get('/v1/application_fees/' . rawurlencode($fee_id) . '/refunds/' . rawurlencode($refund_id));
    }

    /**
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function list_application_fee_refunds(string $fee_id, array $query = []): ?array
    {
        $fee_id = $this->normalize_required_id($fee_id, 'fee_id');
        if ($fee_id === null) {
            return null;
        }
        return $this->get('/v1/application_fees/' . rawurlencode($fee_id) . '/refunds', $query);
    }

    /**
     * @return array<int,string>
     */
    public function stripe_section_aliases(): array
    {
        return array_keys(self::STRIPE_SECTION_PATHS);
    }

    /**
     * Resolve section alias to endpoint path.
     * You can pass raw path (for example /v1/custom/path) to bypass alias map.
     */
    public function stripe_section_path(string $section, array $path_params = []): ?string
    {
        return $this->resolve_stripe_section_path($section, $path_params);
    }

    /**
     * Universal request helper for added Stripe sections.
     *
     * @param array<string,mixed> $path_params
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @return array<mixed>|null
     */
    public function stripe_section_request(
        string $method,
        string $section,
        array $path_params = [],
        array $query = [],
        array $body = [],
        string $body_type = 'none',
        string $idempotency_key = ''
    ): ?array {
        $path = $this->resolve_stripe_section_path($section, $path_params);
        if ($path === null) {
            return null;
        }

        $body_type = strtolower(trim($body_type));
        if (!in_array($body_type, ['none', 'form', 'json'], true)) {
            $this->fail('body_type must be none, form or json');
            return null;
        }

        $request_body = $body_type === 'none' ? null : $body;
        return $this->request_json(
            strtoupper(trim($method)),
            $path,
            $query,
            $request_body,
            $body_type,
            $idempotency_key
        );
    }

    /**
     * @param array<string,mixed> $path_params
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function stripe_section_list(string $section, array $path_params = [], array $query = []): ?array
    {
        return $this->stripe_section_request('GET', $section, $path_params, $query);
    }

    /**
     * @param array<string,mixed> $path_params
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function stripe_section_create(
        string $section,
        array $path_params = [],
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        return $this->stripe_section_request('POST', $section, $path_params, [], $params, 'form', $idempotency_key);
    }

    /**
     * @param array<string,mixed> $path_params
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function stripe_section_retrieve(
        string $section,
        string $resource_id,
        array $path_params = [],
        array $query = []
    ): ?array {
        $resource_id = $this->normalize_required_id($resource_id, 'resource_id');
        if ($resource_id === null) {
            return null;
        }

        $path = $this->resolve_stripe_section_path($section, $path_params);
        if ($path === null) {
            return null;
        }

        return $this->request_json('GET', $path . '/' . rawurlencode($resource_id), $query);
    }

    /**
     * @param array<string,mixed> $path_params
     * @param array<string,mixed> $params
     * @return array<mixed>|null
     */
    public function stripe_section_update(
        string $section,
        string $resource_id,
        array $path_params = [],
        array $params = [],
        string $idempotency_key = ''
    ): ?array {
        $resource_id = $this->normalize_required_id($resource_id, 'resource_id');
        if ($resource_id === null) {
            return null;
        }

        $path = $this->resolve_stripe_section_path($section, $path_params);
        if ($path === null) {
            return null;
        }

        return $this->request_json(
            'POST',
            $path . '/' . rawurlencode($resource_id),
            [],
            $params,
            'form',
            $idempotency_key
        );
    }

    /**
     * @param array<string,mixed> $path_params
     * @param array<string,mixed> $query
     * @return array<mixed>|null
     */
    public function stripe_section_delete(
        string $section,
        string $resource_id,
        array $path_params = [],
        array $query = []
    ): ?array {
        $resource_id = $this->normalize_required_id($resource_id, 'resource_id');
        if ($resource_id === null) {
            return null;
        }

        $path = $this->resolve_stripe_section_path($section, $path_params);
        if ($path === null) {
            return null;
        }

        return $this->request_json('DELETE', $path . '/' . rawurlencode($resource_id), $query);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @param array<string> $extra_headers
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        string $body_type = 'none',
        string $idempotency_key = '',
        array $extra_headers = [],
        bool $expect_json = true
    ): array {
        $this->fail('');
        $this->last_http_code = 0;
        $this->last_url = '';
        $this->last_request_id = '';
        $this->last_response_raw = '';
        $this->last_error_type = '';
        $this->last_error_code = '';
        $this->last_error_decline_code = '';
        $this->last_error_message = '';
        $this->last_error_param = '';
        $this->last_error_advice_code = '';
        $this->last_error_network_advice_code = '';
        $this->last_error_network_decline_code = '';
        $this->last_error_doc_url = '';
        $this->last_error_request_log_url = '';

        $method = strtoupper(trim($method));
        if ($method === '') {
            $this->fail('HTTP method is empty');
            return ['ok' => false];
        }

        if (!$this->has_api_key()) {
            return ['ok' => false];
        }

        $url = $this->build_url($path, $query);
        if ($url === null) {
            return ['ok' => false];
        }
        $this->last_url = $url;

        $headers = [];
        $this->append_header($headers, 'Accept: application/json');
        $this->append_header($headers, 'Authorization: Bearer ' . $this->api_key);

        if ($this->stripe_account !== '') {
            $this->append_header($headers, 'Stripe-Account: ' . $this->stripe_account);
        }
        if ($this->stripe_version !== '') {
            $this->append_header($headers, 'Stripe-Version: ' . $this->stripe_version);
        }

        $idempotency_key = trim($idempotency_key);
        if ($idempotency_key !== '' && $method === 'POST') {
            $this->append_header($headers, 'Idempotency-Key: ' . $idempotency_key);
        }

        foreach ($extra_headers as $header) {
            $this->append_header($headers, $header);
        }

        $payload = null;
        $body_type = strtolower(trim($body_type));
        if ($body !== null && $body_type === 'none') {
            $body_type = 'form';
        }

        if ($body !== null) {
            if ($body_type === 'form') {
                $normalized = $this->strip_nulls($body);
                if (!is_array($normalized)) {
                    $this->fail('Invalid form body');
                    return ['ok' => false];
                }
                $payload = http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
                $this->append_header($headers, 'Content-Type: application/x-www-form-urlencoded');
            } elseif ($body_type === 'json') {
                $payload = json_encode(
                    $body,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE
                    | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if (!is_string($payload)) {
                    $this->fail('Failed to encode JSON body');
                    return ['ok' => false];
                }
                $this->append_header($headers, 'Content-Type: application/json');
            } elseif ($body_type === 'multipart') {
                $normalized = $this->strip_nulls($body);
                if (!is_array($normalized)) {
                    $this->fail('Invalid multipart body');
                    return ['ok' => false];
                }
                $payload = $normalized;
            } else {
                $this->fail('Unsupported body_type');
                return ['ok' => false];
            }
        }

        $response_headers = [];
        $request_id = '';

        $ch = curl_init($url);
        if ($ch === false) {
            $this->fail('curl_init failed');
            return ['ok' => false];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request_connect_timeout_seconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->request_timeout_seconds);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt(
            $ch,
            CURLOPT_HEADERFUNCTION,
            static function ($ch, string $header_line) use (&$response_headers, &$request_id): int {
                $len = strlen($header_line);
                $line = trim($header_line);
                if ($line === '' || !str_contains($line, ':')) {
                    return $len;
                }

                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $value = trim($value);
                $response_headers[$name] = $value;
                if ($name === 'request-id') {
                    $request_id = $value;
                }
                return $len;
            }
        );

        if ($payload !== null && $payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $raw_text = is_string($raw) ? $raw : '';
        $this->last_http_code = $http_code;
        $this->last_request_id = $request_id;
        $this->last_response_raw = $raw_text;

        $this->push_debug([
            'ts' => date('c'),
            'method' => $method,
            'url' => $url,
            'query' => $query,
            'body_type' => $body_type,
            'request_body' => $body,
            'request_payload' => $payload,
            'http_code' => $http_code,
            'response_headers' => $response_headers,
            'response_raw' => $raw_text,
            'curl_error' => $curl_error,
            'idempotency_key' => $idempotency_key,
        ]);

        if ($curl_error !== '') {
            $this->fail('CURL error: ' . $curl_error);
            return ['ok' => false];
        }

        $json = null;
        $trimmed = trim($raw_text);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        if ($http_code < 200 || $http_code >= 300) {
            if (is_array($json)) {
                $this->apply_stripe_error_from_response($json);
            }
            $this->fail($this->build_http_error_message($http_code, $json, $raw_text));
            return [
                'ok' => false,
                'http_code' => $http_code,
                'raw' => $raw_text,
                'json' => $json,
                'request_id' => $request_id,
            ];
        }

        if ($expect_json && $trimmed !== '' && !is_array($json)) {
            $this->fail('Invalid JSON response');
            return ['ok' => false];
        }

        $this->ok();
        return [
            'ok' => true,
            'http_code' => $http_code,
            'raw' => $raw_text,
            'json' => ($expect_json && is_array($json)) ? $json : [],
            'request_id' => $request_id,
        ];
    }

    /**
     * @param array<string,mixed> $query
     */
    private function build_url(string $path, array $query = []): ?string
    {
        $path = trim($path);
        if ($path === '') {
            $this->fail('path is empty');
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            $url = $path;
        } else {
            $url = rtrim($this->base_url, '/') . '/' . ltrim($path, '/');
        }

        $normalized_query = $this->strip_nulls($query);
        if (is_array($normalized_query) && $normalized_query !== []) {
            $qs = http_build_query($normalized_query, '', '&', PHP_QUERY_RFC3986);
            if ($qs !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
            }
        }

        return $url;
    }

    private function has_api_key(): bool
    {
        if (trim($this->api_key) === '') {
            $this->fail('api_key is empty');
            return false;
        }
        return true;
    }

    /**
     * @param array<string,mixed>|null $json
     */
    private function build_http_error_message(int $http_code, ?array $json, string $raw_text): string
    {
        $message = '';
        if (is_array($json)) {
            $error = $json['error'] ?? null;
            if (is_string($error)) {
                $message = trim($error);
            } elseif (is_array($error)) {
                $message = trim((string)($error['message'] ?? ''));
                if ($message === '') {
                    $message = trim((string)($error['type'] ?? $error['code'] ?? ''));
                }
            }
        }

        if ($message === '') {
            $text = trim($raw_text);
            if ($text !== '') {
                if (strlen($text) > 320) {
                    $text = substr($text, 0, 320) . '...';
                }
                $message = $text;
            }
        }

        if ($message === '') {
            return 'HTTP error: ' . (string)$http_code;
        }
        return 'HTTP error: ' . (string)$http_code . ' - ' . $message;
    }

    /**
     * @param array<string,mixed> $json
     */
    private function apply_stripe_error_from_response(array $json): void
    {
        $this->last_error_type = '';
        $this->last_error_code = '';
        $this->last_error_decline_code = '';
        $this->last_error_message = '';
        $this->last_error_param = '';
        $this->last_error_advice_code = '';
        $this->last_error_network_advice_code = '';
        $this->last_error_network_decline_code = '';
        $this->last_error_doc_url = '';
        $this->last_error_request_log_url = '';

        $error = $json['error'] ?? null;
        if (!is_array($error)) {
            return;
        }

        $this->last_error_type = trim((string)($error['type'] ?? ''));
        $this->last_error_code = trim((string)($error['code'] ?? ''));
        $this->last_error_decline_code = trim((string)($error['decline_code'] ?? ''));
        $this->last_error_message = trim((string)($error['message'] ?? ''));
        $this->last_error_param = trim((string)($error['param'] ?? ''));
        $this->last_error_advice_code = trim((string)($error['advice_code'] ?? ''));
        $this->last_error_network_advice_code = trim((string)($error['network_advice_code'] ?? ''));
        $this->last_error_network_decline_code = trim((string)($error['network_decline_code'] ?? ''));
        $this->last_error_doc_url = trim((string)($error['doc_url'] ?? ''));
        $this->last_error_request_log_url = trim((string)($error['request_log_url'] ?? ''));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function strip_nulls(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            if ($item === null) {
                continue;
            }
            $out[$key] = $this->strip_nulls($item);
        }
        return $out;
    }

    private function detect_mime_type(string $file_path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $file_path);
                finfo_close($finfo);
                if (is_string($mime) && trim($mime) !== '') {
                    return trim($mime);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($file_path);
            if (is_string($mime) && trim($mime) !== '') {
                return trim($mime);
            }
        }

        return 'application/octet-stream';
    }

    private function is_currency(string $currency): bool
    {
        return preg_match('/^[a-z]{3}$/', $currency) === 1;
    }

    private function is_http_url(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * @param array<string,mixed> $path_params
     */
    private function resolve_stripe_section_path(string $section, array $path_params = []): ?string
    {
        $section = trim($section);
        if ($section === '') {
            $this->fail('section is empty');
            return null;
        }

        $template = $section;
        if (str_starts_with($section, '/v1/') || str_starts_with($section, '/v2/')) {
            return $this->apply_path_template($template, $path_params);
        }

        $key = $this->normalize_section_alias($section);
        $template = self::STRIPE_SECTION_PATHS[$key] ?? '';
        if ($template === '') {
            $this->fail('unknown Stripe section alias: ' . $section);
            return null;
        }

        return $this->apply_path_template($template, $path_params);
    }

    private function normalize_section_alias(string $section): string
    {
        $section = strtolower(trim($section));
        if ($section === '') {
            return '';
        }

        $section = str_replace(['_', '-'], ' ', $section);
        $section = preg_replace('/\s+/', ' ', $section);
        return is_string($section) ? trim($section) : '';
    }

    /**
     * @param array<string,mixed> $path_params
     */
    private function apply_path_template(string $template, array $path_params = []): ?string
    {
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches) === 0) {
            return $template;
        }

        foreach ($matches[1] as $param_name) {
            $raw_value = $path_params[$param_name] ?? null;
            if ($raw_value === null) {
                $this->fail('missing path param: ' . $param_name);
                return null;
            }

            $value = $this->normalize_required_id((string)$raw_value, $param_name);
            if ($value === null) {
                return null;
            }

            $template = str_replace('{' . $param_name . '}', rawurlencode($value), $template);
        }

        return $template;
    }

    private function normalize_required_id(string $id, string $field_name): ?string
    {
        $id = trim($id);
        if ($id === '') {
            $this->fail($field_name . ' is empty');
            return null;
        }
        return $id;
    }

    /**
     * @param array<string,mixed> $scope
     */
    private function validate_scope(array $scope): bool
    {
        $type = trim((string)($scope['type'] ?? ''));
        if ($type === '') {
            $this->fail('scope.type is required');
            return false;
        }
        return true;
    }

    /**
     * @param array<int,string> $configurations
     * @return array<int,string>|null
     */
    private function normalize_v2_account_link_configurations(array $configurations): ?array
    {
        $normalized = [];
        foreach ($configurations as $configuration) {
            $value = strtolower(trim((string)$configuration));
            if ($value === '') {
                continue;
            }
            if (!isset(self::V2_ACCOUNT_LINK_CONFIGURATIONS[$value])) {
                $this->fail('configuration must be customer, merchant or recipient');
                return null;
            }
            $normalized[$value] = true;
        }

        if ($normalized === []) {
            $this->fail('configurations is empty');
            return null;
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string,mixed> $collection_options
     * @return array<string,mixed>|null
     */
    private function normalize_v2_account_link_collection_options(array $collection_options): ?array
    {
        if ($collection_options === []) {
            return [];
        }

        if (array_key_exists('fields', $collection_options)) {
            $fields = strtolower(trim((string)$collection_options['fields']));
            if ($fields === '') {
                unset($collection_options['fields']);
            } elseif (!isset(self::V2_ACCOUNT_LINK_COLLECTION_FIELDS[$fields])) {
                $this->fail('collection_options.fields must be currently_due or eventually_due');
                return null;
            } else {
                $collection_options['fields'] = $fields;
            }
        }

        if (array_key_exists('future_requirements', $collection_options)) {
            $future_requirements = strtolower(trim((string)$collection_options['future_requirements']));
            if ($future_requirements === '') {
                unset($collection_options['future_requirements']);
            } elseif (!isset(self::V2_ACCOUNT_LINK_FUTURE_REQUIREMENTS[$future_requirements])) {
                $this->fail('collection_options.future_requirements must be include or omit');
                return null;
            } else {
                $collection_options['future_requirements'] = $future_requirements;
            }
        }

        return $collection_options;
    }

    /**
     * @return array<string>
     */
    private function connected_account_headers(string $connected_account_id, string $stripe_version): array
    {
        $headers = [];

        $connected_account_id = trim($connected_account_id);
        if ($connected_account_id !== '') {
            $headers[] = 'Stripe-Account: ' . $connected_account_id;
        }

        $stripe_version = trim($stripe_version);
        if ($stripe_version !== '') {
            $headers[] = 'Stripe-Version: ' . $stripe_version;
        }

        return $headers;
    }

    /**
     * @param array<string> $headers
     */
    private function append_header(array &$headers, string $line): void
    {
        $line = trim($line);
        if ($line === '') {
            return;
        }

        $pos = strpos($line, ':');
        if ($pos === false) {
            $headers[] = $line;
            return;
        }

        $name = strtolower(trim(substr($line, 0, $pos)));
        foreach ($headers as $index => $existing) {
            $existing_pos = strpos($existing, ':');
            if ($existing_pos === false) {
                continue;
            }

            $existing_name = strtolower(trim(substr($existing, 0, $existing_pos)));
            if ($existing_name === $name) {
                $headers[$index] = $line;
                return;
            }
        }

        $headers[] = $line;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function push_debug(array $entry): void
    {
        $this->debug = $entry;
        if (!$this->debug_enabled) {
            return;
        }

        $this->debug_history[] = $entry;
        if (count($this->debug_history) > 200) {
            array_splice($this->debug_history, 0, count($this->debug_history) - 200);
        }
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $msg): void
    {
        $this->status = false;
        $this->error = $msg;
    }
}
