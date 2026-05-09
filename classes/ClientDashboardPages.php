<?php
declare(strict_types=1);

final class ClientDashboardPages
{
    private string $db_alias = 'front';

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
            'subtitle' => 'Saved payment methods placeholder. Stripe setup is connected through checkout.',
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
        'contacts' => [
            'title' => 'Contacts',
            'subtitle' => 'Additional account contacts placeholder.',
            'blocks' => ['contacts'],
        ],
        'email_history' => [
            'title' => 'Email History',
            'subtitle' => 'System email log placeholder.',
            'blocks' => ['email_history'],
        ],
        'users' => [
            'title' => 'User Management',
            'subtitle' => 'Team members and account access placeholder.',
            'blocks' => ['team_users'],
        ],
        'subscriptions' => [
            'title' => 'Subscriptions',
            'subtitle' => 'Renewal and subscription state for proxy products.',
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

        $shop = new ProxyShop();
        $shop->init_db_alias($this->db_alias);
        $services = $shop->list_user_services($userId);
        $payments = $shop->list_user_payments($userId);
        $charges = $shop->list_user_charges($userId);
        $tickets = $this->list_user_tickets($userId);

        Sogerien::Page()->title = (string)$page['title'];
        Sogerien::Page()->header();
        Sogerien::Page()->mainmenu();

        echo '<main class="container my-4 sog-ui client-dashboard-page">';
        $this->styles();
        echo '<div class="pm-dash-head">';
        echo '<div><h1>' . $this->h((string)$page['title']) . '</h1><p>' . $this->h((string)$page['subtitle']) . '</p></div>';
        echo '<div class="pm-dash-user">User #' . (int)$userId . '<br><strong>$' . $this->h($shop->get_user_balance_usd($userId)) . '</strong></div>';
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
                echo '<div class="text-muted small">' . $this->h((string)($card['label'] ?? '')) . '</div>';
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
            foreach ([['/all_proxy', 'Order proxies'], ['/client/add-funds', 'Add funds'], ['/support/tickets', 'Open ticket'], ['/manuals', 'Manuals']] as $link) {
                echo '<a class="btn btn-primary" href="' . $this->h($link[0]) . '">' . $this->h($link[1]) . '</a>';
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
                    'actions' => '<a class="pm-pill-btn is-active" href="/proxy/manage?service_id=' . $this->h(rawurlencode($this->s($service['service_id'] ?? ''))) . '">Manage</a>',
                ];
            }
            $this->table('Products & Services', $rows, ['title', 'status', 'country', 'traffic', 'expires', 'actions']);
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
            $this->table('Payments', $rows, ['created', 'id', 'status', 'amount']);
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
            $this->table('Charges', $rows, ['created', 'order', 'status', 'amount']);
            return;
        }

        if ($block === 'tickets') {
            $this->table('Support tickets', $tickets, ['created', 'subject', 'status', 'updated']);
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
            echo '<section class="card shadow-sm mb-3"><div class="card-header">Profile</div><div class="card-body"><dl class="row mb-0">';
            foreach (['id' => $userId, 'login' => $user['login'] ?? '-', 'email' => $user['email'] ?? '-'] as $label => $value) {
                echo '<dt class="col-sm-3">' . $this->h((string)$label) . '</dt><dd class="col-sm-9">' . $this->h($this->s($value)) . '</dd>';
            }
            echo '</dl></div></section>';
            return;
        }

        if ($block === 'security') {
            $this->placeholder('Security', 'Two-factor authentication, password reset and login security controls are reserved here.');
            return;
        }
        if ($block === 'contacts') {
            $this->placeholder('Contacts', 'Additional billing and technical contacts will be stored here.');
            return;
        }
        if ($block === 'email_history') {
            $this->placeholder('Email history', 'Transactional emails and account notifications will be listed here.');
            return;
        }
        if ($block === 'team_users') {
            $this->placeholder('Users', 'Team member invites, permissions and account seats will be managed here.');
            return;
        }
        if ($block === 'subscriptions') {
            $this->placeholder('Subscriptions', 'Auto-renewal state is currently stored per proxy service.');
            return;
        }
        if ($block === 'ticket_view') {
            $this->placeholder('Ticket conversation', 'Ticket messages, status changes and close/reopen actions will be shown here.');
            return;
        }
        if ($block === 'manuals') {
            echo '<section class="card shadow-sm mb-3"><div class="card-header">Manuals</div><div class="card-body pm-doc-grid">';
            foreach (['Residential proxies', 'Mobile proxies', 'Datacenter proxies', 'ISP bandwidth', 'Web scraper API', 'Authentication'] as $doc) {
                echo '<a href="#" class="pm-doc-link">' . $this->h($doc) . '</a>';
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
            $this->placeholder('Payment methods', 'Saved cards are handled by Stripe. Manual card management is a placeholder until Stripe customer portal is connected.');
        }
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function list_user_tickets(int $userId): array
    {
        return [
            ['created' => date('Y-m-d'), 'subject' => 'Welcome support ticket', 'status' => 'open', 'updated' => date('Y-m-d')],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $columns
     */
    private function table(string $title, array $rows, array $columns): void
    {
        echo '<section class="card shadow-sm mb-3"><div class="card-header">' . $this->h($title) . '</div><div class="card-body p-0">';
        if ($rows === []) {
            echo '<div class="p-3 text-muted">No data yet.</div></div></section>';
            return;
        }
        echo '<div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><thead><tr>';
        foreach ($columns as $column) {
            echo '<th>' . $this->h(ucwords(str_replace('_', ' ', $column))) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($columns as $column) {
                echo '<td>' . (string)($row[$column] ?? '-') . '</td>';
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

    private function styles(): void
    {
        echo '<style>
            .pm-dash-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
            .pm-dash-head h1{font-size:28px;margin:0 0 6px}
            .pm-dash-head p{margin:0;color:#64748b}
            .pm-dash-user{text-align:right;color:#64748b}
            .pm-dash-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
            .pm-stat{font-size:24px;font-weight:700}
            .pm-actions{display:flex;flex-wrap:wrap;gap:10px}
            .pm-code{background:#0f172a;color:#e2e8f0;border-radius:8px;padding:14px;white-space:pre-wrap}
            .pm-doc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
            .pm-doc-link{display:block;border:1px solid rgba(148,163,184,.35);border-radius:8px;padding:12px;text-decoration:none}
            @media(max-width:900px){.pm-dash-grid,.pm-doc-grid{grid-template-columns:1fr 1fr}.pm-dash-head{display:block}.pm-dash-user{text-align:left;margin-top:10px}}
            @media(max-width:560px){.pm-dash-grid,.pm-doc-grid{grid-template-columns:1fr}}
        </style>';
    }

    private function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
