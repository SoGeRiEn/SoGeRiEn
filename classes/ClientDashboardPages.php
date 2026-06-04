<?php
declare(strict_types=1);

final class ClientDashboardPages
{
    private string $db_alias = 'front';
    /** @var array{ok:bool,message:string}|null */
    private ?array $password_notice = null;

    /** @var array<string,array<string,mixed>> */
    private array $pages = [
        'dashboard' => [
            'title' => 'Dashboard',
            'subtitle' => 'Account summary, active proxy products, invoices and support state.',
            'cards' => [
                ['label' => 'Products', 'value' => 'services_count'],
                ['label' => 'Balance USD', 'value' => 'balance_usd'],
                ['label' => 'Payments', 'value' => 'payments_count'],
                ['label' => 'Tickets', 'value' => 'tickets_count'],
            ],
            'blocks' => ['quick_actions', 'services', 'tickets'],
        ],
        'add_funds' => [
            'title' => 'Add Funds',
            'subtitle' => 'Top up account balance before ordering or renewing proxy products.',
            'blocks' => ['funds_form', 'payments'],
        ],
        'invoices' => [
            'title' => 'My Invoices',
            'subtitle' => 'Invoices and payment history from checkout sessions.',
            'blocks' => ['payments', 'charges'],
        ],
        'payment_methods' => [
            'title' => 'Payment Methods',
            'subtitle' => 'Saved cards and default billing method for this account.',
            'blocks' => ['payment_methods'],
        ],
        'services' => [
            'title' => 'My Products & Services',
            'subtitle' => 'Purchased proxy packages and access data.',
            'blocks' => ['services'],
        ],
        'service_api' => [
            'title' => 'API Tool',
            'subtitle' => 'Proxy list generation and integration helpers for active services.',
            'blocks' => ['api_tool'],
        ],
        'profile' => [
            'title' => 'Profile Details',
            'subtitle' => 'Main account details and security settings.',
            'blocks' => ['profile', 'security'],
        ],
        'change_password' => [
            'title' => 'Change Password',
            'subtitle' => 'Set a new password for this client account.',
            'blocks' => ['security'],
        ],
        'contacts' => [
            'title' => 'Contacts',
            'subtitle' => 'Billing, technical and administrative contacts for this account.',
            'blocks' => ['contacts'],
        ],
        'email_history' => [
            'title' => 'Email History',
            'subtitle' => 'Account notices, payment emails and support notifications.',
            'blocks' => ['email_history'],
        ],
        'users' => [
            'title' => 'User Management',
            'subtitle' => 'Team members, roles and account access.',
            'blocks' => ['team_users'],
        ],
        'subscriptions' => [
            'title' => 'Subscriptions',
            'subtitle' => 'Legacy billing page.',
            'blocks' => ['subscriptions'],
        ],
        'support' => [
            'title' => 'Support Tickets',
            'subtitle' => 'Create tickets and track replies.',
            'blocks' => ['ticket_form', 'tickets'],
        ],
        'ticket' => [
            'title' => 'Ticket Details',
            'subtitle' => 'Ticket conversation placeholder.',
            'blocks' => ['ticket_view'],
        ],
        'manuals' => [
            'title' => 'Manuals',
            'subtitle' => 'API and proxy usage documentation.',
            'blocks' => ['manuals'],
        ],
        'partners' => [
            'title' => 'Partners',
            'subtitle' => 'Referral and partner program placeholder.',
            'blocks' => ['partners'],
        ],
        'marketplace' => [
            'title' => 'Marketplace',
            'subtitle' => 'Additional proxy and scraping add-ons.',
            'blocks' => ['marketplace'],
        ],
        'scraper_pricing' => [
            'title' => 'Web Scraper API Pricing',
            'subtitle' => 'Scraper API packages and request volumes.',
            'blocks' => ['scraper_pricing'],
        ],
        'scraper_api' => [
            'title' => 'My Scraper API',
            'subtitle' => 'Purchased scraper API services and keys.',
            'blocks' => ['scraper_api'],
        ],
    ];

    public function init_db_alias(string $db_alias): void
    {
        $db_alias = trim($db_alias);
        $this->db_alias = $db_alias !== '' ? $db_alias : 'front';
    }

    public function render(string $page_key): void
    {
        $page = $this->pages[$page_key] ?? null;
        if (!is_array($page)) {
            http_response_code(404);
            echo 'Dashboard page not found.';
            return;
        }

        [$userId, $user] = $this->current_user();
        if ($userId <= 0) {
            $_GET['next'] = (string)($_SERVER['REQUEST_URI'] ?? '/dashboard');
            require Sogerien::$SOGERIEN_DIR . '/page/admin_panel/page_login_form.php';
            Sogerien::markDone();
            return;
        }
        $this->apply_user_timezone($user);

        $this->password_notice = $this->handle_account_action($userId, $user);
        if (is_array($this->password_notice) && $this->password_notice['ok']) {
            [$userId, $user] = $this->current_user();
        }

        $shop = new ProxyShop();
        $shop->init_db_alias($this->db_alias);
        $services = $shop->list_user_services($userId);
        $payments = $shop->list_user_payments($userId);
        if (in_array($page_key, ['add_funds', 'invoices'], true)) {
            $payments = $this->paid_payments($payments);
        }
        $charges = $shop->list_user_charges($userId);
        if ($page_key === 'invoices') {
            $charges = $this->paid_charges($charges);
        }
        $tickets = $this->list_user_tickets($userId);

        $title = $this->t('client.' . $page_key . '.title', (string)$page['title']);
        $subtitle = $this->t('client.' . $page_key . '.subtitle', (string)$page['subtitle']);

        Sogerien::Page()->title = $title;
        Sogerien::Page()->header();
        Sogerien::Page()->mainmenu();

        echo '<main class="container my-4 sog-ui client-dashboard-page">';
        $this->styles();
        echo '<div class="pm-dash-head">';
        echo '<div><h1>' . $this->h($title) . '</h1><p>' . $this->h($subtitle) . '</p></div>';
        echo '<div class="pm-dash-user">' . $this->h($this->t('client.user_number', 'User #')) . (int)$userId . '<br><strong>$' . $this->h($shop->get_user_balance_usd($userId)) . '</strong></div>';
        echo '</div>';

        if (isset($page['cards']) && is_array($page['cards'])) {
            $stats = [
                'services_count' => (string)count($services),
                'balance_usd' => '$' . $shop->get_user_balance_usd($userId),
                'payments_count' => (string)count($payments),
                'tickets_count' => (string)count($tickets),
            ];
            echo '<div class="pm-dash-grid pm-dash-stats">';
            foreach ($page['cards'] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $valueKey = (string)($card['value'] ?? '');
                echo '<section class="card shadow-sm"><div class="card-body">';
                $cardLabel = (string)($card['label'] ?? '');
                $cardKey = strtolower(str_replace([' ', '&'], ['_', 'and'], $cardLabel));
                echo '<div class="text-muted small">' . $this->h($this->t('client.card.' . $cardKey, $cardLabel)) . '</div>';
                echo '<div class="pm-stat">' . $this->h($stats[$valueKey] ?? '-') . '</div>';
                echo '</div></section>';
            }
            echo '</div>';
        }

        foreach (($page['blocks'] ?? []) as $block) {
            $this->render_block((string)$block, $userId, $user, $services, $payments, $charges, $tickets);
        }

        echo '</main>';
        Sogerien::Page()->footer();
    }

    /**
     * @return array{0:int,1:array<string,mixed>}
     */
    private function current_user(): array
    {
        $users = Sogerien::Users();
        $users->init_db_alias($this->db_alias);
        $users->load_identity_from_token();
        return [(int)$users->user_id, is_array($users->user_data ?? null) ? $users->user_data : []];
    }

    /**
     * @param array<int,array<string,mixed>> $payments
     * @return array<int,array<string,mixed>>
     */
    private function paid_payments(array $payments): array
    {
        $rows = [];
        foreach ($payments as $payment) {
            $status = strtolower($this->s($payment['payment_status'] ?? $payment['status'] ?? ''));
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
    private function paid_charges(array $charges): array
    {
        $rows = [];
        foreach ($charges as $charge) {
            $checkoutStatus = strtolower($this->s($charge['checkout_status'] ?? ''));
            $fulfillmentStatus = strtolower($this->s($charge['fulfillment_status'] ?? ''));
            if ($checkoutStatus === 'paid' || in_array($fulfillmentStatus, ['fulfilled', 'provider_failed'], true)) {
                $rows[] = $charge;
            }
        }
        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $services
     * @param array<int,array<string,mixed>> $payments
     * @param array<int,array<string,mixed>> $charges
     * @param array<int,array<string,mixed>> $tickets
     * @param array<string,mixed> $user
     */
    private function render_block(string $block, int $userId, array $user, array $services, array $payments, array $charges, array $tickets): void
    {
        if ($block === 'quick_actions') {
            echo '<div class="card shadow-sm mb-3"><div class="card-body"><div class="pm-actions">';
            foreach ([['/client/all_proxy', 'order_proxies', 'Order proxies'], ['/client/add-funds', 'add_funds', 'Add funds'], ['/client/support/tickets', 'open_ticket', 'Open ticket'], ['/client/manuals', 'manuals', 'Manuals']] as $link) {
                echo '<a class="btn btn-primary" href="' . $this->h($link[0]) . '">' . $this->h($this->t('client.action.' . $link[1], $link[2])) . '</a>';
            }
            echo '</div></div></div>';
            return;
        }

        if ($block === 'funds_form') {
            echo '<section class="card shadow-sm mb-3"><div class="card-header">Add funds</div><div class="card-body">';
            echo '<form class="row g-3" method="post">';
            echo '<div class="col-md-4"><label class="form-label">Amount USD</label><input class="form-control" name="amount" value="100"></div>';
            echo '<div class="col-md-4"><label class="form-label">Payment gateway</label><select class="form-select"><option>Stripe card</option><option>Crypto - placeholder</option></select></div>';
            echo '<div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100" type="button">Continue</button></div>';
            echo '</form></div></section>';
            return;
        }

        if ($block === 'services') {
            $rows = [];
            foreach ($services as $service) {
                $rows[] = [
                    'title' => $this->s($service['title'] ?? 'Proxy service'),
                    'status' => $this->s($service['status'] ?? '-'),
                    'country' => $this->s($service['country'] ?? '-'),
                    'traffic' => $this->s($service['traffic_remains'] ?? '-'),
                    'expires' => $this->s($service['expires_at'] ?? '-'),
                    'actions' => '<a class="pm-pill-btn is-active" href="/client/proxy/manage?service_id=' . $this->h(rawurlencode($this->s($service['service_id'] ?? ''))) . '">' . $this->h($this->t('client.action.manage', 'Manage')) . '</a>',
                ];
            }
            $this->table($this->t('client.table.services', 'Products & Services'), $rows, ['title', 'status', 'country', 'traffic', 'expires', 'actions']);
            return;
        }

        if ($block === 'payments') {
            $rows = [];
            foreach ($payments as $payment) {
                $rows[] = [
                    'created' => $this->s($payment['created_at'] ?? '-'),
                    'id' => '<code>' . $this->h($this->s($payment['payment_id'] ?? '-')) . '</code>',
                    'status' => $this->s($payment['payment_status'] ?? $payment['status'] ?? '-'),
                    'amount' => $this->s($payment['amount_usd'] ?? '-') . ' ' . strtoupper($this->s($payment['currency'] ?? 'USD')),
                ];
            }
            $this->table($this->t('client.table.payments', 'Payments'), $rows, ['created', 'id', 'status', 'amount']);
            return;
        }

        if ($block === 'charges') {
            $rows = [];
            foreach ($charges as $charge) {
                $rows[] = [
                    'created' => $this->s($charge['created_at'] ?? '-'),
                    'order' => '<code>' . $this->h($this->s($charge['order_id'] ?? '-')) . '</code>',
                    'status' => $this->s($charge['fulfillment_status'] ?? '-'),
                    'amount' => $this->s($charge['amount_usd'] ?? '-') . ' ' . strtoupper($this->s($charge['currency'] ?? 'USD')),
                ];
            }
            $this->table($this->t('client.table.charges', 'Charges'), $rows, ['created', 'order', 'status', 'amount']);
            return;
        }

        if ($block === 'tickets') {
            echo '<div class="d-flex justify-content-end mb-2"><a class="btn btn-primary" href="/client/support/tickets">' . $this->h($this->t('client.action.create_ticket', 'Create ticket')) . '</a></div>';
            $this->table($this->t('client.table.tickets', 'Support tickets'), $tickets, ['created', 'subject', 'status', 'updated']);
            return;
        }

        if ($block === 'ticket_form') {
            echo '<section class="card shadow-sm mb-3"><div class="card-header">Open ticket</div><div class="card-body">';
            echo '<form class="row g-3"><div class="col-md-6"><label class="form-label">Department</label><select class="form-select"><option>Technical support</option><option>Billing</option></select></div>';
            echo '<div class="col-md-6"><label class="form-label">Subject</label><input class="form-control" placeholder="Proxy connection issue"></div>';
            echo '<div class="col-12"><label class="form-label">Message</label><textarea class="form-control" rows="5"></textarea></div>';
            echo '<div class="col-12"><button class="btn btn-primary" type="button">Create ticket</button></div></form></div></section>';
            return;
        }

        if ($block === 'api_tool') {
            echo '<section class="card shadow-sm mb-3"><div class="card-header">API tool</div><div class="card-body">';
            echo '<div class="row g-3"><div class="col-md-4"><label class="form-label">Protocol</label><select class="form-select"><option>HTTP</option><option>SOCKS5</option></select></div>';
            echo '<div class="col-md-4"><label class="form-label">Format</label><select class="form-select"><option>host:port:login:password</option><option>URL</option></select></div>';
            echo '<div class="col-md-4"><label class="form-label">Country</label><input class="form-control" value="US"></div></div>';
            echo '<pre class="pm-code mt-3">curl https://infatica.proxymint.com/my -d method=InfaticaIo.shared_proxy_urls_from_options</pre>';
            echo '</div></section>';
            return;
        }

        if ($block === 'profile') {
            $this->render_profile_form($userId, $user);
            return;
        }

        if ($block === 'security') {
            $this->render_password_form($userId, $user);
            return;
        }
        if ($block === 'contacts') {
            $this->render_contacts($user);
            return;
        }
        if ($block === 'email_history') {
            $this->render_email_history($user, $payments, $charges, $tickets);
            return;
        }
        if ($block === 'team_users') {
            $this->render_team_users($user);
            return;
        }
        if ($block === 'subscriptions') {
            $this->render_subscriptions($services);
            return;
        }
        if ($block === 'ticket_view') {
            $this->placeholder('Ticket conversation', 'Ticket messages, status changes and close/reopen actions will be shown here.');
            return;
        }
        if ($block === 'manuals') {
            $docs = [
                'Residential proxies' => ['Use rotating residential IPs for sessions that need broad geo coverage.', 'Generate an access list, select countries, choose rotation and use the shown login/password in your proxy client.'],
                'Mobile proxies' => ['Use mobile pools when target sites require carrier-grade IP reputation.', 'Create a mobile access list, keep rotation on request or sticky by interval, then copy HTTP or SOCKS5 details from Details.'],
                'Datacenter proxies' => ['Use datacenter IPs for fast stable traffic where residential reputation is not required.', 'Buy an IP package, open My Services and use issued IP credentials from the service page.'],
                'ISP bandwidth' => ['ISP services are yearly IP packages, not monthly traffic topups.', 'Renewal and lifecycle actions are controlled by ProxyMint admins.'],
                'Web scraper API' => ['ProxyMint gateway hides provider keys from clients.', 'Send scraping requests through your issued client API key after buying a scraper package.'],
                'Authentication' => ['Login/password is the default mode. IP whitelist is available when you want access without credentials.', 'For whitelist mode add your public IP or CIDR before generating the list.'],
            ];
            echo '<section class="card shadow-sm mb-3"><div class="card-header">Manuals</div><div class="card-body pm-doc-grid">';
            foreach ($docs as $title => $doc) {
                echo '<article class="pm-doc-link"><strong>' . $this->h($title) . '</strong><span>' . $this->h($doc[0]) . '</span><small>' . $this->h($doc[1]) . '</small></article>';
            }
            echo '</div></section>';
            return;
        }
        if ($block === 'scraper_pricing') {
            echo '<section class="card shadow-sm mb-3"><div class="card-header">Web Scraper API Pricing</div><div class="card-body">';
            echo '<div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><thead><tr><th>Plan</th><th>Included requests</th><th>Price per 1,000</th><th>Total</th></tr></thead><tbody>';
            foreach ([
                ['Basic', '67,000', '$0.28', '$19'],
                ['Basic', '500,000', '$0.13', '$65'],
                ['Basic', '1,500,000', '$0.11', '$165'],
                ['Basic', '4,000,000', '$0.10', '$400'],
                ['Pro JS render', '23,000', '$0.79', '$18'],
                ['Pro JS render', '82,000', '$0.75', '$62'],
                ['Pro JS render', '216,000', '$0.73', '$158'],
                ['Pro JS render', '455,000', '$0.69', '$314'],
            ] as $row) {
                echo '<tr><td>' . $this->h($row[0]) . '</td><td>' . $this->h($row[1]) . '</td><td>' . $this->h($row[2]) . '</td><td>' . $this->h($row[3]) . '</td></tr>';
            }
            echo '</tbody></table></div></div></section>';
            return;
        }
        if ($block === 'scraper_api') {
            $this->placeholder('My Scraper API', 'Purchased scraper API services and keys will be shown here.');
            return;
        }
        if ($block === 'partners') {
            $this->placeholder('Partners', 'Referral links, commission stats and payout requests will be shown here.');
            return;
        }
        if ($block === 'marketplace') {
            $this->placeholder('Marketplace', 'Additional proxy and scraping add-ons will be shown here.');
            return;
        }
        if ($block === 'payment_methods') {
            $this->render_payment_methods($user);
        }
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function list_user_tickets(int $userId): array
    {
        $tickets = new SupportTickets();
        $tickets->init_db_alias($this->db_alias);
        $rows = [];
        foreach ($tickets->list_user_tickets($userId) as $ticket) {
            $ticketId = $this->s($ticket['ticket_id'] ?? '');
            if ($ticketId === '') {
                continue;
            }
            $rows[] = [
                'created' => substr($this->s($ticket['created_at'] ?? ''), 0, 10),
                'subject' => '<a href="/client/support/ticket?id=' . $this->h(rawurlencode($ticketId)) . '">' . $this->h($this->s($ticket['subject'] ?? $ticketId)) . '</a>',
                'status' => $tickets->status_label($this->s($ticket['status'] ?? '')),
                'updated' => substr($this->s($ticket['updated_at'] ?? ''), 0, 10),
            ];
        }
        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $columns
     */
    private function table(string $title, array $rows, array $columns): void
    {
        echo '<section class="card shadow-sm mb-3"><div class="card-header">' . $this->h($title) . '</div><div class="card-body p-0">';
        if ($rows === []) {
            echo '<div class="p-3 text-muted">' . $this->h($this->t('client.no_data_yet', 'No data yet.')) . '</div></div></section>';
            return;
        }
        echo '<div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><thead><tr>';
        foreach ($columns as $column) {
            $fallback = ucwords(str_replace('_', ' ', $column));
            echo '<th>' . $this->h($this->t('client.column.' . $column, $fallback)) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($columns as $column) {
                $value = (string)($row[$column] ?? '-');
                echo '<td>' . (in_array($column, ['actions', 'id', 'order', 'subject'], true) ? $value : $this->h($value)) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></div></section>';
    }

    private function placeholder(string $title, string $text): void
    {
        echo '<section class="card shadow-sm mb-3"><div class="card-header">' . $this->h($title) . '</div><div class="card-body">';
        echo '<p class="text-muted mb-0">' . $this->h($text) . '</p></div></section>';
    }

    /**
     * @param array<string,mixed> $user
     * @return array{ok:bool,message:string}|null
     */
    private function handle_account_action(int $userId, array $user): ?array
    {
        $post = Sogerien::InputRequest()->request_post_get_cookie_json;
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $setupSessionId = $this->s($_GET['setup_session_id'] ?? '');
            if ($setupSessionId !== '') {
                return $this->save_stripe_setup_session($userId, $user, $setupSessionId);
            }
            return null;
        }

        $action = $this->s($post['action'] ?? '');
        if ($action === 'update_profile') {
            $settings = isset($user['settings']) && is_array($user['settings']) ? $user['settings'] : [];
            $timezone = $this->normalize_timezone($post['settings_tz'] ?? ($settings['tz'] ?? 'Europe/Warsaw'));
            return $this->update_user_patch($userId, [
                'fio' => $this->s($post['fio'] ?? ''),
                'email' => $this->s($post['email'] ?? ''),
                'phone' => $this->s($post['phone'] ?? ''),
                'settings' => [
                    'tz' => $timezone,
                    'lang' => $this->s($post['settings_lang'] ?? 'ru'),
                ],
            ], 'Profile saved.');
        }

        if ($action === 'add_contact') {
            $contacts = $this->list_from_user($user, 'contacts');
            $contacts[] = [
                'id' => 'cnt_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)),
                'type' => $this->s($post['contact_type'] ?? 'technical'),
                'name' => $this->s($post['contact_name'] ?? ''),
                'email' => $this->s($post['contact_email'] ?? ''),
                'phone' => $this->s($post['contact_phone'] ?? ''),
                'notes' => $this->s($post['contact_notes'] ?? ''),
                'created_at' => date('c'),
            ];
            return $this->update_user_patch($userId, ['contacts' => $contacts], 'Contact added.');
        }

        if ($action === 'remove_contact') {
            $removeId = $this->s($post['contact_id'] ?? '');
            $contacts = array_values(array_filter($this->list_from_user($user, 'contacts'), static fn(array $row): bool => (string)($row['id'] ?? '') !== $removeId));
            return $this->update_user_patch($userId, ['contacts' => $contacts], 'Contact removed.');
        }

        if ($action === 'add_payment_method') {
            return $this->start_stripe_card_setup($userId, $user);
        }

        if ($action === 'remove_payment_method' || $action === 'default_payment_method') {
            $methodId = $this->s($post['payment_method_id'] ?? '');
            $methods = $this->list_from_user($user, 'payment_methods');
            if ($action === 'remove_payment_method') {
                $removedDefault = false;
                foreach ($methods as $method) {
                    if ((string)($method['id'] ?? '') === $methodId && $this->s($method['is_default'] ?? '') === '1') {
                        $removedDefault = true;
                        break;
                    }
                }
                if (str_starts_with($methodId, 'pm_')) {
                    $stripe = $this->stripe_api();
                    if ($stripe->detach_payment_method($methodId, 'detach_' . $methodId . '_' . (string)$userId) === null) {
                        return ['ok' => false, 'message' => $stripe->error !== '' ? $stripe->error : 'Stripe payment method was not removed.'];
                    }
                }
                $methods = array_values(array_filter($methods, static fn(array $row): bool => (string)($row['id'] ?? '') !== $methodId));
                if ($removedDefault && $methods !== []) {
                    $methods[0]['is_default'] = '1';
                    $customerId = $this->s($user['stripe_customer_id'] ?? '');
                    $nextDefaultId = $this->s($methods[0]['id'] ?? '');
                    if ($customerId !== '' && str_starts_with($nextDefaultId, 'pm_')) {
                        $stripe = $this->stripe_api();
                        if ($stripe->update_customer($customerId, [
                            'invoice_settings' => ['default_payment_method' => $nextDefaultId],
                        ], 'default_pm_' . $nextDefaultId . '_' . (string)$userId) === null) {
                            return ['ok' => false, 'message' => $stripe->error !== '' ? $stripe->error : 'Stripe default payment method was not updated.'];
                        }
                    }
                }
            } else {
                foreach ($methods as &$method) {
                    $method['is_default'] = (string)($method['id'] ?? '') === $methodId ? '1' : '0';
                }
                unset($method);
                $customerId = $this->s($user['stripe_customer_id'] ?? '');
                if ($customerId !== '' && str_starts_with($methodId, 'pm_')) {
                    $stripe = $this->stripe_api();
                    if ($stripe->update_customer($customerId, [
                        'invoice_settings' => ['default_payment_method' => $methodId],
                    ], 'default_pm_' . $methodId . '_' . (string)$userId) === null) {
                        return ['ok' => false, 'message' => $stripe->error !== '' ? $stripe->error : 'Stripe default payment method was not updated.'];
                    }
                }
            }
            $defaultPaymentMethodId = $this->default_payment_method_id($methods);
            $patch = [
                'payment_methods' => $methods,
                'billing_default_payment_method_id' => $defaultPaymentMethodId,
            ];
            if ($methods === []) {
                $patch['billing_autopay_enabled'] = '0';
            }
            return $this->update_user_patch($userId, $patch, 'Payment methods updated.');
        }

        if ($action === 'add_team_user') {
            $team = $this->list_from_user($user, 'team_users');
            $team[] = [
                'id' => 'usr_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)),
                'name' => $this->s($post['team_name'] ?? ''),
                'email' => $this->s($post['team_email'] ?? ''),
                'role' => $this->s($post['team_role'] ?? 'support'),
                'status' => 'invited',
                'created_at' => date('c'),
            ];
            return $this->update_user_patch($userId, ['team_users' => $team], 'User invited.');
        }

        if ($action === 'remove_team_user') {
            $removeId = $this->s($post['team_user_id'] ?? '');
            $team = array_values(array_filter($this->list_from_user($user, 'team_users'), static fn(array $row): bool => (string)($row['id'] ?? '') !== $removeId));
            return $this->update_user_patch($userId, ['team_users' => $team], 'User removed.');
        }

        if ($action !== 'change_account_password') {
            return null;
        }

        $newPassword = (string)($post['new_password'] ?? '');
        $repeatPassword = (string)($post['repeat_password'] ?? '');
        $identity = $this->s($user['email'] ?? '') !== '' ? $this->s($user['email'] ?? '') : $this->s($user['login'] ?? '');

        if ($userId <= 0 || $identity === '') {
            return ['ok' => false, 'message' => 'Client account was not found.'];
        }
        if (mb_strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if ($newPassword !== $repeatPassword) {
            return ['ok' => false, 'message' => 'Passwords do not match.'];
        }

        $users = Sogerien::Users();
        $users->init_db_alias($this->db_alias);
        $ok = $users->reset_password($identity, $newPassword);

        return [
            'ok' => $ok,
            'message' => $ok ? 'Password changed.' : ($users->error !== '' ? $users->error : 'Password update failed.'),
        ];
    }

    /**
     * @param array<string,mixed> $patch
     * @return array{ok:bool,message:string}
     */
    private function update_user_patch(int $userId, array $patch, string $okMessage): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Client account was not found.'];
        }
        $users = Sogerien::Users();
        $users->init_db_alias($this->db_alias);
        $ok = $users->update_user($userId, $patch);
        return ['ok' => $ok, 'message' => $ok ? $okMessage : ($users->error !== '' ? $users->error : 'Update failed.')];
    }

    private function start_stripe_card_setup(int $userId, array $user): array
    {
        $customerId = $this->ensure_stripe_customer($userId, $user);
        if ($customerId === '') {
            return ['ok' => false, 'message' => 'Stripe customer was not created.'];
        }

        $stripe = $this->stripe_api();
        $returnUrl = $this->absolute_url('/client/payment-methods', [
            'user_id' => (string)$userId,
            'setup_session_id' => '{CHECKOUT_SESSION_ID}',
        ]);
        $returnUrl = str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $returnUrl);

        $session = $stripe->create_checkout_session([
            'mode' => 'setup',
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'success_url' => $returnUrl,
            'cancel_url' => $this->absolute_url('/client/payment-methods', ['user_id' => (string)$userId]),
            'metadata' => [
                'user_id' => (string)$userId,
                'source' => 'client_payment_methods',
            ],
            'setup_intent_data' => [
                'metadata' => [
                    'user_id' => (string)$userId,
                    'source' => 'client_payment_methods',
                ],
            ],
        ], 'setup_card_' . (string)$userId . '_' . bin2hex(random_bytes(6)));

        $url = is_array($session) ? $this->s($session['url'] ?? '') : '';
        if ($url === '') {
            return ['ok' => false, 'message' => $stripe->error !== '' ? $stripe->error : 'Stripe setup session was not created.'];
        }

        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
            exit;
        }

        return ['ok' => true, 'message' => 'Open Stripe to add card: ' . $url];
    }

    private function save_stripe_setup_session(int $userId, array $user, string $setupSessionId): array
    {
        $stripe = $this->stripe_api();
        $session = $stripe->retrieve_checkout_session($setupSessionId, ['expand' => ['setup_intent', 'setup_intent.payment_method']]);
        if (!is_array($session) || $this->s($session['mode'] ?? '') !== 'setup') {
            return ['ok' => false, 'message' => $stripe->error !== '' ? $stripe->error : 'Stripe setup session was not found.'];
        }

        $sessionUserId = (int)($session['metadata']['user_id'] ?? 0);
        if ($sessionUserId !== $userId) {
            return ['ok' => false, 'message' => 'Stripe setup session belongs to another user.'];
        }

        $setupIntent = $session['setup_intent'] ?? null;
        $paymentMethod = is_array($setupIntent) ? ($setupIntent['payment_method'] ?? null) : null;
        if (!is_array($paymentMethod)) {
            return ['ok' => false, 'message' => 'Stripe did not return saved payment method.'];
        }

        $methodId = $this->s($paymentMethod['id'] ?? '');
        $card = isset($paymentMethod['card']) && is_array($paymentMethod['card']) ? $paymentMethod['card'] : [];
        if ($methodId === '' || $card === []) {
            return ['ok' => false, 'message' => 'Stripe payment method has no card data.'];
        }

        $methods = $this->list_from_user($user, 'payment_methods');
        $makeDefault = $methods === [];
        $exists = false;
        foreach ($methods as &$method) {
            if ((string)($method['id'] ?? '') !== $methodId) {
                if ($makeDefault) {
                    $method['is_default'] = '0';
                }
                continue;
            }
            $exists = true;
            $method = $this->stripe_payment_method_row($paymentMethod, $makeDefault || $this->s($method['is_default'] ?? '') === '1');
        }
        unset($method);

        if (!$exists) {
            if ($makeDefault) {
                foreach ($methods as &$method) {
                    $method['is_default'] = '0';
                }
                unset($method);
            }
            $methods[] = $this->stripe_payment_method_row($paymentMethod, $makeDefault);
        }

        $customerId = $this->s($session['customer'] ?? $user['stripe_customer_id'] ?? '');
        $defaultPaymentMethodId = $this->default_payment_method_id($methods);
        $patch = [
            'payment_methods' => $methods,
            'billing_autopay_enabled' => '1',
            'billing_default_payment_method_id' => $defaultPaymentMethodId !== '' ? $defaultPaymentMethodId : $methodId,
        ];
        if ($customerId !== '') {
            $patch['stripe_customer_id'] = $customerId;
        }

        if ($customerId !== '' && $makeDefault) {
            $stripe->update_customer($customerId, [
                'invoice_settings' => ['default_payment_method' => $methodId],
            ], 'default_pm_' . $methodId . '_' . (string)$userId);
        }

        return $this->update_user_patch($userId, $patch, 'Payment method saved.');
    }

    /**
     * @param array<int,array<string,mixed>> $methods
     */
    private function default_payment_method_id(array $methods): string
    {
        $first = '';
        foreach ($methods as $method) {
            $id = $this->s($method['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if ($first === '') {
                $first = $id;
            }
            if ($this->s($method['is_default'] ?? '') === '1') {
                return $id;
            }
        }
        return $first;
    }

    /**
     * @param array<string,mixed> $paymentMethod
     * @return array<string,string>
     */
    private function stripe_payment_method_row(array $paymentMethod, bool $isDefault): array
    {
        $card = isset($paymentMethod['card']) && is_array($paymentMethod['card']) ? $paymentMethod['card'] : [];
        $billing = isset($paymentMethod['billing_details']) && is_array($paymentMethod['billing_details']) ? $paymentMethod['billing_details'] : [];
        return [
            'id' => $this->s($paymentMethod['id'] ?? ''),
            'brand' => strtoupper($this->s($card['brand'] ?? 'CARD')),
            'last4' => $this->s($card['last4'] ?? '----'),
            'exp_month' => $this->s($card['exp_month'] ?? '--'),
            'exp_year' => $this->s($card['exp_year'] ?? '----'),
            'billing_name' => $this->s($billing['name'] ?? ''),
            'stripe_customer_id' => $this->s($paymentMethod['customer'] ?? ''),
            'is_default' => $isDefault ? '1' : '0',
            'created_at' => date('c'),
        ];
    }

    private function ensure_stripe_customer(int $userId, array $user): string
    {
        $existing = $this->s($user['stripe_customer_id'] ?? '');
        if ($existing !== '') {
            return $existing;
        }

        $stripe = $this->stripe_api();
        $customer = $stripe->create_customer([
            'email' => $this->s($user['email'] ?? ''),
            'name' => $this->s($user['fio'] ?? $user['login'] ?? ''),
            'phone' => $this->s($user['phone'] ?? ''),
            'metadata' => [
                'user_id' => (string)$userId,
                'source' => 'proxymint_client',
            ],
        ], 'customer_' . (string)$userId);

        $customerId = is_array($customer) ? $this->s($customer['id'] ?? '') : '';
        if ($customerId === '') {
            return '';
        }

        $this->update_user_patch($userId, ['stripe_customer_id' => $customerId], 'Stripe customer saved.');
        return $customerId;
    }

    private function stripe_api(): APIStripe
    {
        $stripe = Sogerien::API()->Stripe();
        $stripe->debug_enabled = false;
        $stripe->set_api_key(defined('STRIPE_LIVE_SECRET_KEY_LLC') ? (string)STRIPE_LIVE_SECRET_KEY_LLC : '');
        return $stripe;
    }

    /**
     * @param array<string,string> $query
     */
    private function absolute_url(string $path, array $query = []): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $host !== '' ? ($scheme . '://' . $host) : rtrim((string)(Sogerien::InputRequest()->domain ?: Sogerien::InputRequest()->sogerien_domain), '/');
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function list_from_user(array $user, string $key): array
    {
        $raw = $user[$key] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function render_notice(): void
    {
        if (!is_array($this->password_notice)) {
            return;
        }
        echo '<div class="alert ' . ($this->password_notice['ok'] ? 'alert-success' : 'alert-danger') . '">' . $this->h($this->password_notice['message']) . '</div>';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function render_profile_form(int $userId, array $user): void
    {
        $settings = isset($user['settings']) && is_array($user['settings']) ? $user['settings'] : [];
        $timezone = $this->normalize_timezone($settings['tz'] ?? 'Europe/Warsaw');
        $action = (string)($_SERVER['REQUEST_URI'] ?? '/client/profile');
        echo '<section class="card shadow-sm mb-3"><div class="card-header">Account Details</div><div class="card-body">';
        $this->render_notice();
        echo '<form class="row g-3" method="post" action="' . $this->h($action) . '">';
        echo '<input type="hidden" name="action" value="update_profile">';
        echo '<div class="col-md-3"><label class="form-label">User ID</label><input class="form-control" value="' . (int)$userId . '" disabled></div>';
        echo '<div class="col-md-3"><label class="form-label">Login</label><input class="form-control" value="' . $this->h($this->s($user['login'] ?? '')) . '" disabled></div>';
        echo '<div class="col-md-6"><label class="form-label" for="pmFio">Full name</label><input id="pmFio" class="form-control" name="fio" value="' . $this->h($this->s($user['fio'] ?? '')) . '"></div>';
        echo '<div class="col-md-4"><label class="form-label" for="pmEmail">Email</label><input id="pmEmail" class="form-control" type="email" name="email" value="' . $this->h($this->s($user['email'] ?? '')) . '"></div>';
        echo '<div class="col-md-4"><label class="form-label" for="pmPhone">Phone</label><input id="pmPhone" class="form-control" name="phone" value="' . $this->h($this->s($user['phone'] ?? '')) . '"></div>';
        echo '<div class="col-md-2"><label class="form-label" for="pmLang">Language</label><select id="pmLang" class="form-select" name="settings_lang">';
        foreach (['ru' => 'RU', 'en' => 'EN', 'de' => 'DE'] as $value => $label) {
            echo '<option value="' . $this->h($value) . '"' . ($this->s($settings['lang'] ?? 'ru') === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-md-2"><label class="form-label" for="pmTz">Timezone</label><select id="pmTz" class="form-select" name="settings_tz">';
        foreach ($this->timezone_options() as $tz) {
            echo '<option value="' . $this->h($tz) . '"' . ($timezone === $tz ? ' selected' : '') . '>' . $this->h($tz) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save profile</button></div>';
        echo '</form></div></section>';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function apply_user_timezone(array $user): void
    {
        $settings = isset($user['settings']) && is_array($user['settings']) ? $user['settings'] : [];
        date_default_timezone_set($this->normalize_timezone($settings['tz'] ?? 'Europe/Warsaw'));
    }

    private function normalize_timezone(mixed $timezone): string
    {
        $timezone = trim((string)$timezone);
        return in_array($timezone, $this->timezone_options(), true) ? $timezone : 'Europe/Warsaw';
    }

    /**
     * @return array<int,string>
     */
    private function timezone_options(): array
    {
        static $timezones = null;
        if ($timezones === null) {
            $timezones = DateTimeZone::listIdentifiers();
        }
        return $timezones;
    }

    /**
     * @param array<string,mixed> $user
     */
    private function render_contacts(array $user): void
    {
        $contacts = $this->list_from_user($user, 'contacts');
        $rows = [];
        foreach ($contacts as $contact) {
            $id = $this->s($contact['id'] ?? '');
            $rows[] = [
                'type' => $this->s($contact['type'] ?? '-'),
                'name' => $this->s($contact['name'] ?? '-'),
                'email' => $this->s($contact['email'] ?? '-'),
                'phone' => $this->s($contact['phone'] ?? '-'),
                'notes' => $this->s($contact['notes'] ?? ''),
                'actions' => '<form method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/contacts')) . '" class="m-0"><input type="hidden" name="action" value="remove_contact"><input type="hidden" name="contact_id" value="' . $this->h($id) . '"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form>',
            ];
        }
        echo '<section class="card shadow-sm mb-3"><div class="card-header">Add Contact</div><div class="card-body">';
        $this->render_notice();
        echo '<form class="row g-3" method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/contacts')) . '">';
        echo '<input type="hidden" name="action" value="add_contact">';
        echo '<div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="contact_type"><option value="technical">Technical</option><option value="billing">Billing</option><option value="admin">Admin</option></select></div>';
        echo '<div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="contact_name" required></div>';
        echo '<div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="contact_email" required></div>';
        echo '<div class="col-md-2"><label class="form-label">Phone</label><input class="form-control" name="contact_phone"></div>';
        echo '<div class="col-md-2"><label class="form-label">Notes</label><input class="form-control" name="contact_notes"></div>';
        echo '<div class="col-12"><button class="btn btn-primary" type="submit">Add contact</button></div>';
        echo '</form></div></section>';
        $this->table('Contacts', $rows, ['type', 'name', 'email', 'phone', 'notes', 'actions']);
    }

    /**
     * @param array<string,mixed> $user
     */
    private function render_payment_methods(array $user): void
    {
        $methods = $this->list_from_user($user, 'payment_methods');
        echo '<section class="card shadow-sm mb-3"><div class="card-header">' . $this->h($this->t('client.payment_methods.title', 'Payment Methods')) . '</div><div class="card-body">';
        echo '<div class="pm-card-wallet">';
        foreach ($methods as $method) {
            $id = $this->s($method['id'] ?? '');
            $isDefault = $this->s($method['is_default'] ?? '') === '1';
            echo '<article class="pm-card-method">';
            echo '<div class="pm-card-chip"></div><div><strong>' . $this->h($this->s($method['brand'] ?? 'CARD')) . ' **** ' . $this->h($this->s($method['last4'] ?? '----')) . '</strong><span>' . $this->h($this->s($method['billing_name'] ?? $this->t('client.payment_methods.card_holder', 'Card holder'))) . '</span></div>';
            echo '<div class="pm-card-meta"><span>' . $this->h($this->t('client.payment_methods.expires', 'Expires')) . ' ' . $this->h($this->s($method['exp_month'] ?? '--') . '/' . $this->s($method['exp_year'] ?? '----')) . '</span>' . ($isDefault ? '<b>' . $this->h($this->t('client.payment_methods.default_badge', 'Default')) . '</b>' : '') . '</div>';
            echo '<div class="pm-card-actions">';
            if (!$isDefault) {
                echo '<form method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/payment-methods')) . '"><input type="hidden" name="action" value="default_payment_method"><input type="hidden" name="payment_method_id" value="' . $this->h($id) . '"><button class="btn btn-sm btn-outline-primary" type="submit">' . $this->h($this->t('client.payment_methods.default_action', 'Set default')) . '</button></form>';
            }
            echo '<form method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/payment-methods')) . '"><input type="hidden" name="action" value="remove_payment_method"><input type="hidden" name="payment_method_id" value="' . $this->h($id) . '"><button class="btn btn-sm btn-outline-danger" type="submit">' . $this->h($this->t('common.remove', 'Remove')) . '</button></form>';
            echo '</div></article>';
        }
        if ($methods === []) {
            echo '<div class="text-muted">' . $this->h($this->t('client.payment_methods.empty', 'No saved payment methods yet.')) . '</div>';
        }
        echo '</div></div></section>';

        echo '<section class="card shadow-sm mb-3"><div class="card-header">' . $this->h($this->t('client.payment_methods.add_title', 'Add Payment Method')) . '</div><div class="card-body">';
        $this->render_notice();
        echo '<form class="row g-3" method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/payment-methods')) . '">';
        echo '<input type="hidden" name="action" value="add_payment_method">';
        echo '<div class="col-md-8"><div class="text-muted">' . $this->h($this->t('client.payment_methods.stripe_hint', 'Visa and Mastercard are added through Stripe. ProxyMint stores only the Stripe payment method id, brand, last4 and expiry.')) . '</div></div>';
        echo '<div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">' . $this->h($this->t('client.payment_methods.add_card_stripe', 'Add card in Stripe')) . '</button></div>';
        echo '</form><div class="text-muted small mt-2">' . $this->h($this->t('client.payment_methods.autopay_hint', 'Saved cards can be used for automatic renewals and client-approved ordered services.')) . '</div></div></section>';
    }

    /**
     * @param array<string,mixed> $user
     * @param array<int,array<string,mixed>> $payments
     * @param array<int,array<string,mixed>> $charges
     * @param array<int,array<string,mixed>> $tickets
     */
    private function render_email_history(array $user, array $payments, array $charges, array $tickets): void
    {
        $rows = [];
        $recipient = $this->s($user['email'] ?? '-');
        foreach ($payments as $payment) {
            $status = $this->s($payment['status'] ?? $payment['payment_status'] ?? '-');
            $amount = $this->s($payment['amount_usd'] ?? '');
            $id = $this->s($payment['id'] ?? $payment['payment_id'] ?? $payment['stripe_session_id'] ?? '');
            $rows[] = [
                'date' => $this->s($payment['created_at'] ?? '-'),
                'recipient' => $recipient,
                'subject' => 'Payment status: ' . $status,
                'status' => 'sent',
                'body' => $this->email_body([
                    'Hello,',
                    '',
                    'Your payment status was updated.',
                    'Status: ' . $status,
                    $amount !== '' ? ('Amount: $' . $amount) : '',
                    $id !== '' ? ('Payment ID: ' . $id) : '',
                    '',
                    'ProxyMint',
                ]),
            ];
        }
        foreach ($charges as $charge) {
            $orderId = $this->s($charge['order_id'] ?? '-');
            $status = $this->s($charge['fulfillment_status'] ?? 'sent');
            $amount = $this->s($charge['amount_usd'] ?? '');
            $title = $this->s($charge['title'] ?? '');
            $rows[] = [
                'date' => $this->s($charge['created_at'] ?? '-'),
                'recipient' => $recipient,
                'subject' => 'Invoice/order: ' . $orderId,
                'status' => $status,
                'body' => $this->email_body([
                    'Hello,',
                    '',
                    'Your order information is below.',
                    'Order ID: ' . $orderId,
                    $title !== '' ? ('Order: ' . $title) : '',
                    $amount !== '' ? ('Amount: $' . $amount) : '',
                    'Status: ' . $status,
                    '',
                    'ProxyMint',
                ]),
            ];
        }
        foreach ($tickets as $ticket) {
            $subject = $this->s($ticket['subject'] ?? '-');
            $status = $this->s($ticket['status'] ?? '-');
            $message = '';
            $messages = isset($ticket['messages']) && is_array($ticket['messages']) ? $ticket['messages'] : [];
            if (isset($messages[0]) && is_array($messages[0])) {
                $message = $this->s($messages[0]['body'] ?? '');
            }
            $rows[] = [
                'date' => $this->s($ticket['created_at'] ?? $ticket['created'] ?? '-'),
                'recipient' => $recipient,
                'subject' => 'Support ticket: ' . $subject,
                'status' => $status,
                'body' => $this->email_body([
                    'Hello,',
                    '',
                    'Your support ticket was received.',
                    'Subject: ' . $subject,
                    'Status: ' . $status,
                    $message !== '' ? ('Message: ' . $message) : '',
                    '',
                    'ProxyMint',
                ]),
            ];
        }
        if ($rows === []) {
            $rows[] = [
                'date' => date('Y-m-d'),
                'recipient' => $recipient,
                'subject' => 'Account created',
                'status' => 'sent',
                'body' => $this->email_body([
                    'Hello,',
                    '',
                    'Your ProxyMint account has been created.',
                    '',
                    'ProxyMint',
                ]),
            ];
        }
        $this->email_history_table($rows);
    }

    /**
     * @param array<int,string> $lines
     */
    private function email_body(array $lines): string
    {
        $out = [];
        foreach ($lines as $line) {
            if ($line !== '') {
                $out[] = $line;
                continue;
            }
            if ($out !== [] && end($out) !== '') {
                $out[] = '';
            }
        }
        return trim(implode("\n", $out));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function email_history_table(array $rows): void
    {
        $columns = ['date', 'recipient', 'subject', 'status'];
        echo '<section class="card shadow-sm mb-3 pm-email-history"><div class="card-header">Email History</div><div class="card-body p-0">';
        echo '<div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><thead><tr>';
        foreach ($columns as $column) {
            $fallback = ucwords(str_replace('_', ' ', $column));
            echo '<th>' . $this->h($this->t('client.column.' . $column, $fallback)) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $idx => $row) {
            echo '<tr class="pm-email-row" tabindex="0" data-email-index="' . (int)$idx . '" role="button">';
            foreach ($columns as $column) {
                echo '<td>' . $this->h((string)($row[$column] ?? '-')) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></div></section>';

        echo '<div id="pmEmailModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pmEmailModalTitle">';
        echo '<div class="panel" tabindex="-1" style="width:min(94vw,820px)">';
        echo '<div class="head"><strong id="pmEmailModalTitle">Email</strong><button class="close" id="pmEmailModalClose" type="button" aria-label="Close">Esc</button></div>';
        echo '<div style="padding:16px;max-height:78vh;overflow:auto">';
        echo '<div class="small text-muted mb-2" id="pmEmailModalMeta"></div>';
        echo '<pre id="pmEmailModalBody" class="pm-code mb-0"></pre>';
        echo '</div></div></div>';

        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script type="application/json" id="pmEmailRowsJson">' . ($json !== false ? $json : '[]') . '</script>';
        echo '<script>
(function(){
    var modal = document.getElementById("pmEmailModal");
    var rowsEl = document.getElementById("pmEmailRowsJson");
    if (!modal || !rowsEl) return;
    var rows = [];
    try { rows = JSON.parse(rowsEl.textContent || "[]"); } catch (err) { rows = []; }
    var titleEl = document.getElementById("pmEmailModalTitle");
    var metaEl = document.getElementById("pmEmailModalMeta");
    var bodyEl = document.getElementById("pmEmailModalBody");
    var closeEl = document.getElementById("pmEmailModalClose");

    function openEmail(idx){
        var row = rows[idx] || {};
        titleEl.textContent = row.subject || "Email";
        metaEl.textContent = (row.date || "-") + " | " + (row.recipient || "-") + " | " + (row.status || "-");
        bodyEl.textContent = row.body || "Email text is not stored for this record.";
        modal.setAttribute("aria-hidden", "false");
        document.documentElement.style.overflow = "hidden";
        closeEl.focus();
    }
    function closeEmail(){
        modal.setAttribute("aria-hidden", "true");
        document.documentElement.style.overflow = "";
    }
    document.addEventListener("click", function(e){
        var tr = e.target.closest(".pm-email-row");
        if (!tr) return;
        openEmail(parseInt(tr.getAttribute("data-email-index") || "0", 10));
    });
    document.addEventListener("keydown", function(e){
        var tr = e.target.closest ? e.target.closest(".pm-email-row") : null;
        if (tr && (e.key === "Enter" || e.key === " ")) {
            e.preventDefault();
            openEmail(parseInt(tr.getAttribute("data-email-index") || "0", 10));
            return;
        }
        if (modal.getAttribute("aria-hidden") === "false" && e.key === "Escape") {
            e.preventDefault();
            closeEmail();
        }
    });
    closeEl.addEventListener("click", closeEmail);
    modal.addEventListener("click", function(e){ if (e.target === modal) closeEmail(); });
})();
</script>';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function render_team_users(array $user): void
    {
        $team = $this->list_from_user($user, 'team_users');
        $rows = [[
            'name' => $this->s($user['fio'] ?? $user['login'] ?? 'Owner'),
            'email' => $this->s($user['email'] ?? '-'),
            'role' => 'owner',
            'status' => 'active',
            'actions' => '',
        ]];
        foreach ($team as $member) {
            $id = $this->s($member['id'] ?? '');
            $rows[] = [
                'name' => $this->s($member['name'] ?? '-'),
                'email' => $this->s($member['email'] ?? '-'),
                'role' => $this->s($member['role'] ?? '-'),
                'status' => $this->s($member['status'] ?? '-'),
                'actions' => '<form method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/users')) . '" class="m-0"><input type="hidden" name="action" value="remove_team_user"><input type="hidden" name="team_user_id" value="' . $this->h($id) . '"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form>',
            ];
        }
        echo '<section class="card shadow-sm mb-3"><div class="card-header">Invite User</div><div class="card-body">';
        $this->render_notice();
        echo '<form class="row g-3" method="post" action="' . $this->h((string)($_SERVER['REQUEST_URI'] ?? '/client/users')) . '">';
        echo '<input type="hidden" name="action" value="add_team_user">';
        echo '<div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="team_name" required></div>';
        echo '<div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="team_email" required></div>';
        echo '<div class="col-md-3"><label class="form-label">Role</label><select class="form-select" name="team_role"><option value="technical">Technical</option><option value="billing">Billing</option><option value="support">Support</option><option value="readonly">Read only</option></select></div>';
        echo '<div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary" type="submit">Invite</button></div>';
        echo '</form></div></section>';
        $this->table('Users', $rows, ['name', 'email', 'role', 'status', 'actions']);
    }

    /**
     * @param array<int,array<string,mixed>> $services
     */
    private function render_subscriptions(array $services): void
    {
        echo '<section class="card shadow-sm mb-3"><div class="card-body">';
        echo '<h2 class="h5">Subscriptions are not used for proxy traffic</h2>';
        echo '<p class="text-muted mb-3">Paid traffic is a yearly balance. Additional traffic is bought from the proxy catalog and added to the existing service.</p>';
        echo '<a class="btn btn-primary" href="/client/my/proxies">Open My Services</a>';
        echo '</div></section>';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function render_password_form(int $userId, array $user): void
    {
        $identity = $this->s($user['email'] ?? '') !== '' ? $this->s($user['email'] ?? '') : $this->s($user['login'] ?? '');
        $action = (string)($_SERVER['REQUEST_URI'] ?? '/client/profile');

        echo '<section class="card shadow-sm mb-3"><div class="card-header">Change Password</div><div class="card-body">';
        $this->render_notice();
        echo '<form class="pm-password-form" method="post" action="' . $this->h($action) . '">';
        echo '<input type="hidden" name="action" value="change_account_password">';
        echo '<div><label class="form-label" for="pmAccountLogin">Client</label><input id="pmAccountLogin" class="form-control" value="' . $this->h($identity !== '' ? $identity : ('User #' . $userId)) . '" disabled></div>';
        echo '<div><label class="form-label" for="pmNewPassword">New password</label><input id="pmNewPassword" class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="8" required></div>';
        echo '<div><label class="form-label" for="pmRepeatPassword">Repeat password</label><input id="pmRepeatPassword" class="form-control" type="password" name="repeat_password" autocomplete="new-password" minlength="8" required></div>';
        echo '<div class="pm-password-actions"><button class="btn btn-primary" type="submit">Set password</button></div>';
        echo '</form></div></section>';
    }

    private function styles(): void
    {
        echo '<style>
            .pm-dash-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
            .pm-dash-head h1{font-size:28px;margin:0 0 6px}
            .pm-dash-head p{margin:0;color:var(--muted)}
            .pm-dash-user{text-align:right;color:var(--muted)}
            .pm-dash-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
            .pm-stat{font-size:24px;font-weight:700}
            .pm-actions{display:flex;flex-wrap:wrap;gap:10px}
            .pm-password-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:end}
            .pm-password-actions{display:flex;align-items:end}
            .pm-code{background:#0f172a;color:#e2e8f0;border-radius:8px;padding:14px;white-space:pre-wrap}
            .pm-doc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
            .pm-doc-link{display:grid;gap:8px;min-height:168px;border:1px solid var(--line);border-radius:var(--pm-radius-md);padding:16px;background:color-mix(in srgb,var(--surface-soft) 88%,transparent);color:var(--text);box-shadow:0 14px 34px color-mix(in srgb,var(--accent-2) 8%,transparent)}
            .pm-doc-link strong{font-size:15px;line-height:1.25}
            .pm-doc-link span{color:color-mix(in srgb,var(--text) 86%,var(--muted));line-height:1.35}
            .pm-doc-link small{color:var(--muted);line-height:1.35}
            .pm-doc-link:hover{border-color:color-mix(in srgb,var(--accent) 34%,var(--line));background:color-mix(in srgb,var(--surface-strong) 86%,var(--accent) 7%)}
            .pm-card-wallet{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
            .pm-card-method{display:grid;gap:14px;min-height:170px;border-radius:8px;padding:18px;color:#e5f0ff;background:linear-gradient(135deg,#172554,#0f766e);box-shadow:0 16px 34px rgba(15,23,42,.18)}
            .pm-card-method strong{display:block;font-size:18px;letter-spacing:1px}
            .pm-card-method span{display:block;color:rgba(226,232,240,.82)}
            .pm-card-chip{width:42px;height:30px;border-radius:6px;background:linear-gradient(135deg,#facc15,#f97316)}
            .pm-card-meta{display:flex;justify-content:space-between;gap:10px;align-items:center}
            .pm-card-meta b{border:1px solid rgba(255,255,255,.45);border-radius:999px;padding:2px 8px;font-size:12px}
            .pm-card-actions{display:flex;gap:8px;flex-wrap:wrap}
            .pm-email-row{cursor:pointer}
            .pm-email-row:hover{background:color-mix(in srgb,var(--accent) 8%,transparent)}
            .pm-email-row:focus{outline:2px solid color-mix(in srgb,var(--accent) 55%,transparent);outline-offset:-2px}
            @media(max-width:900px){.pm-dash-grid,.pm-doc-grid,.pm-password-form{grid-template-columns:1fr 1fr}.pm-dash-head{display:block}.pm-dash-user{text-align:left;margin-top:10px}}
            @media(max-width:560px){.pm-dash-grid,.pm-doc-grid,.pm-password-form{grid-template-columns:1fr}}
        </style>';
    }

    private function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function t(string $key, string $fallback = ''): string
    {
        $value = Sogerien::Lang()->get($key);
        if ($fallback !== '' && $value === $key) {
            return $fallback;
        }
        return $value;
    }

    private function s(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return trim((string)$value);
        }
        return '';
    }
}
