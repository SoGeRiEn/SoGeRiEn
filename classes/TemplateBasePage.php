<?php

declare(strict_types=1);

final class TemplateBasePage
{
    use SogerienClassHelp;

    private Template $template;
    private bool $shellOpened = false;

    public function __construct(Template $template)
    {
        $this->template = $template;

        if (Sogerien::$debag) {
            Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args());
        }
    }

    /**
     * @return array<int,string>
     */
    public function get_head_css_urls(): array
    {
        $domain = $this->get_sogerien_domain();
        $mainMenuCssVersion = (string)@filemtime(Sogerien::$SOGERIEN_DIR . '/page/css/admin_panel/main_menu.css');
        $mainMenuCssUrl = $domain . '/page/css/admin_panel/main_menu.css';
        if ($mainMenuCssVersion !== '') {
            $mainMenuCssUrl .= '?v=' . rawurlencode($mainMenuCssVersion . '-client-account-2');
        }
        $mainCssVersion = (string)@filemtime(Sogerien::$SOGERIEN_DIR . '/page/css/admin_panel/main.css');
        $mainCssUrl = $domain . '/page/css/admin_panel/main.css';
        if ($mainCssVersion !== '') {
            $mainCssUrl .= '?v=' . rawurlencode($mainCssVersion . '-select-theme-1');
        }

        return array_merge([
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            'https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css',
            'https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css',
            $mainMenuCssUrl,
            $domain . '/page/css/BasePage/forms.css',
            $domain . '/page/css/BasePage/table_renderer.css',
            $mainCssUrl,
        ], Affects::get_head_css_urls($domain));
    }

    /**
     * @return array<int,array{src:string,defer:bool}>
     */
    public function get_head_js_urls(): array
    {
        $domain = $this->get_sogerien_domain();
        $effectsJs = Affects::get_head_js_urls($domain);
        $tableRendererJsVersion = (string)@filemtime(Sogerien::$SOGERIEN_DIR . '/page/js/BasePage/table_renderer.js');
        $tableRendererJsUrl = $domain . '/page/js/BasePage/table_renderer.js';
        if ($tableRendererJsVersion !== '') {
            $tableRendererJsUrl .= '?v=' . rawurlencode($tableRendererJsVersion . '-facet-default-1');
        }

        return array_merge([
            ['src' => 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js', 'defer' => false],
            ['src' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', 'defer' => true],
            ['src' => 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', 'defer' => false],
            ['src' => 'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js', 'defer' => false],
            ['src' => 'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js', 'defer' => false],
            ['src' => 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js', 'defer' => false],
            ['src' => 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', 'defer' => false],
            ['src' => 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', 'defer' => false],
            ['src' => 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js', 'defer' => false],
            ['src' => 'https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js', 'defer' => false],
            ['src' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'defer' => false],
            ['src' => $domain . '/page/js/admin_panel/main.js', 'defer' => true],
        ], $effectsJs, [
            ['src' => $domain . '/page/js/admin_panel/main_menu.js', 'defer' => true],
            ['src' => $domain . '/page/js/BasePage/forms.js', 'defer' => true],
            ['src' => $tableRendererJsUrl, 'defer' => true],
        ]);
    }

    public function get_body_class(): string
    {
        return 'pm-admin-body pm-theme-midnight';
    }

    /**
     * @return array<string,string>
     */
    public function get_body_attributes(): array
    {
        $bodyTitle = $this->template->title !== ''
            ? $this->template->title
            : Sogerien::Lang()->get('app.name');

        return [
            'data-page-title' => $bodyTitle,
            'data-pm-default-theme' => 'midnight',
        ];
    }

    public function render_body_open(): void
    {
    }

    public function render_body_close(): void
    {
        if ($this->shellOpened) {
            $this->render_admin_view_user_id_bridge();
            echo '</div></div></div>';
            $this->shellOpened = false;
        }
    }

    public function mainmenu(): void
    {
        if (Sogerien::$debag) {
            Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args());
        }

        if ($this->shellOpened) {
            return;
        }

        $this->shellOpened = true;
        $this->mainmenu_html();
    }

    public function brand_logo_html(string $extraClass = ''): string
    {
        return $this->render_brand_logo_img_html($extraClass);
    }

    public function admin_brand_logo_html(string $extraClass = ''): string
    {
        return $this->render_brand_logo_img_html($extraClass);
    }

    /**
     * @return array{label:string,email:string,balance:string}
     */
    private function client_topbar_context(): array
    {
        $label = 'Client';
        $email = '';
        $balance = '$0.00';

        $dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
        if ($dbAlias === '') {
            $dbAlias = 'front';
        }

        $users = Sogerien::Users();
        $users->init_db_alias($dbAlias);
        $users->load_identity_from_token();

        $userId = (int)$users->user_id;
        $user = is_array($users->user_data ?? null) ? $users->user_data : [];
        $email = trim((string)($user['email'] ?? ''));
        $name = trim((string)($user['fio'] ?? $user['name'] ?? ''));
        if ($name !== '') {
            $label = $name;
        } elseif ($email !== '') {
            $label = $email;
        } elseif (trim((string)($user['login'] ?? '')) !== '') {
            $label = trim((string)$user['login']);
        } elseif ($userId > 0) {
            $label = 'User #' . (string)$userId;
        }

        if ($userId > 0 && class_exists('ProxyShop')) {
            $shop = new ProxyShop();
            $shop->init_db_alias($dbAlias);
            $balance = '$' . $shop->get_user_balance_usd($userId);
        }

        return ['label' => $label, 'email' => $email, 'balance' => $balance];
    }

    /**
     * @param array<int,array{label:string,url:string,tag?:string,permission?:string}> $items
     */
    private function render_nav_items(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $label = trim((string)($item['label'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            $tag = trim((string)($item['tag'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }

            $labelKey = trim((string)($item['label_key'] ?? ''));
            $tagKey = trim((string)($item['tag_key'] ?? ''));
            if ($labelKey !== '') {
                $label = $this->t($labelKey, $label);
            }
            if ($tagKey !== '') {
                $tag = $this->t($tagKey, $tag);
            }

            $active = $this->is_active_url($url) === 'active' ? ' is-active' : '';
            $url = $this->append_admin_view_user_id($url);
            $tagHtml = $tag !== '' ? '<span class="pm-nav-tag">' . $this->h($tag) . '</span>' : '';
            $html .= '<a class="pm-nav-link' . $active . '" href="' . $this->h($url) . '"><span class="pm-nav-label">' . $this->h($label) . '</span>' . $tagHtml . '</a>';
        }

        return $html;
    }

    private function mainmenu_html(): void
    {
        $h = fn(string $s): string => $this->h($s);
        $t = fn(string $key, string $fallback = ''): string => $this->t($key, $fallback);
        $current_lang = strtolower(Sogerien::Lang()->get_current_lang());
        $supported_langs = Sogerien::Lang()->get_supported_langs();
        $langFallbackNames = [
            'ru' => 'Russian',
            'en' => 'English',
            'de' => 'German',
        ];
        $langItems = [];
        foreach ($supported_langs as $lang_code_raw) {
            $lang_code = strtolower(trim((string)$lang_code_raw));
            if ($lang_code === '') {
                continue;
            }
            $fallbackName = $langFallbackNames[$lang_code] ?? strtoupper($lang_code);
            $langItems[] = [
                'code' => $lang_code,
                'code_ui' => strtoupper($lang_code),
                'label' => $t('lang.' . $lang_code, $fallbackName),
                'url' => $this->build_url_with_lang($lang_code),
            ];
        }
        $is_authenticated = $this->is_authenticated_user();
        $page_title = $this->template->title !== '' ? $this->template->title : $t('app.name', 'ProxyMint');
        $currentPath = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
        $isAdminSystem = $currentPath === 'admin' || str_starts_with($currentPath, 'admin/');

        $homeItems = [
            ['label' => 'Dashboard', 'label_key' => 'client.nav.dashboard', 'url' => '/client/dashboard', 'tag' => 'Client', 'tag_key' => 'client.tag.client'],
        ];
        $proxyProductItems = [
            ['label' => 'Order Proxies', 'label_key' => 'client.nav.order_proxies', 'url' => '/client/all_proxy', 'tag' => 'Order', 'tag_key' => 'client.tag.order'],
            ['label' => 'My Services', 'label_key' => 'client.nav.my_services', 'url' => '/client/my/proxies', 'tag' => 'Client', 'tag_key' => 'client.tag.client'],
        ];
        $scraperItems = [
            ['label' => 'Scraper Pricing', 'label_key' => 'client.nav.scraper_pricing', 'url' => '/client/scraper/pricing', 'tag' => 'API'],
            ['label' => 'My Scraper API', 'label_key' => 'client.nav.my_scraper_api', 'url' => '/client/scraper/my', 'tag' => 'Client', 'tag_key' => 'client.tag.client'],
            ['label' => 'Playground', 'label_key' => 'client.nav.playground', 'url' => '/client/scraper/playground', 'tag' => 'Tools', 'tag_key' => 'client.tag.tools'],
        ];
        $billingItems = [
            ['label' => 'Add Funds', 'label_key' => 'client.nav.add_funds', 'url' => '/client/add-funds', 'tag' => 'Pay', 'tag_key' => 'client.tag.pay'],
            ['label' => 'Invoices', 'label_key' => 'client.nav.invoices', 'url' => '/client/my/payments', 'tag' => 'Billing', 'tag_key' => 'client.tag.billing'],
            ['label' => 'Payment Methods', 'label_key' => 'client.nav.payment_methods', 'url' => '/client/payment-methods', 'tag' => 'Billing', 'tag_key' => 'client.tag.billing'],
        ];
        $supportItems = [
            ['label' => 'Tickets', 'label_key' => 'client.nav.tickets', 'url' => '/client/support/tickets', 'tag' => 'Help', 'tag_key' => 'client.tag.help'],
            ['label' => 'Manuals', 'label_key' => 'client.nav.manuals', 'url' => '/client/manuals', 'tag' => 'Docs', 'tag_key' => 'client.tag.docs'],
        ];
        $accountItems = [
            ['label' => 'Profile', 'label_key' => 'client.nav.profile', 'url' => '/client/profile', 'tag' => 'Account', 'tag_key' => 'client.tag.account'],
            ['label' => 'Contacts', 'label_key' => 'client.nav.contacts', 'url' => '/client/contacts', 'tag' => 'Account', 'tag_key' => 'client.tag.account'],
            ['label' => 'Email History', 'label_key' => 'client.nav.email_history', 'url' => '/client/email-history', 'tag' => 'Account', 'tag_key' => 'client.tag.account'],
            ['label' => 'Users', 'label_key' => 'client.nav.users', 'url' => '/client/users', 'tag' => 'Team', 'tag_key' => 'client.tag.team'],
        ];
        $accountDropItems = [
            ['label' => 'Account Details', 'url' => '/client/profile', 'icon' => 'ID'],
            ['label' => 'User Management', 'url' => '/client/users', 'icon' => 'US'],
            ['label' => 'Payment Methods', 'url' => '/client/payment-methods', 'icon' => 'PM'],
            ['label' => 'Contacts', 'url' => '/client/contacts', 'icon' => 'CT'],
            ['label' => 'Subscriptions', 'url' => '/client/subscriptions', 'icon' => 'SB'],
            ['label' => 'Email History', 'url' => '/client/email-history', 'icon' => 'EM'],
        ];
        $adminItems = $this->is_admin_user() ? [
            ['label' => 'Proxy Orders', 'label_key' => 'admin.orders.title', 'url' => '/admin/orders', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Statistics', 'label_key' => 'admin.statistics.title', 'url' => '/admin/statistics', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Client Services', 'label_key' => 'admin.services.title', 'url' => '/admin/services', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Traffic', 'label_key' => 'admin.traffic.title', 'url' => '/admin/traffic', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Access Lists', 'label_key' => 'admin.access.title', 'url' => '/admin/access-lists', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Guard', 'label_key' => 'admin.guard.title', 'url' => '/admin/guard', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Tickets', 'label_key' => 'admin.support_tickets', 'url' => '/admin/support/tickets', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Users', 'url' => '/admin/users', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Access Groups', 'label_key' => 'menu.rules', 'url' => '/admin/access_groups', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
            ['label' => 'Access Rights', 'label_key' => 'menu.rules_access', 'url' => '/admin/access_list', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
        ] : [];
        $adminHomeItems = [
            ['label' => 'Provider Dashboard', 'label_key' => 'admin.provider.title', 'url' => '/admin/provider', 'tag' => 'Admin', 'tag_key' => 'menu.admin'],
        ];

        echo '<div class="pm-admin-app">';
        echo '  <div class="pm-admin-bg" aria-hidden="true">';
        echo '      <div class="pm-admin-cosmic" data-pm-cosmic></div>';
        echo '      <div class="pm-admin-aura pm-admin-aura-one"></div>';
        echo '      <div class="pm-admin-aura pm-admin-aura-two"></div>';
        echo '      <div class="pm-admin-grid"></div>';
        echo '      <div class="pm-admin-vignette"></div>';
        echo '  </div>';
        echo '  <div class="pm-admin-shell">';
        echo '      <button class="pm-mobile-backdrop" type="button" data-pm-sidebar-close aria-label="' . $h($t('menu.close_navigation', 'Close navigation')) . '"></button>';
        echo '      <aside class="pm-sidebar" id="pmSidebar">';
        echo '          <div class="pm-sidebar-inner">';
        echo '              <div class="pm-brand">';
        echo                    $this->admin_brand_logo_html();
        echo '                  <div class="pm-brand-copy">';
        echo '                      <div class="pm-brand-title">' . $h($t('app.name', 'ProxyMint')) . '</div>';
        echo '                      <div class="pm-brand-sub">' . ($isAdminSystem ? 'admin system' : 'client system') . '</div>';
        echo '                  </div>';
        echo '              </div>';
        if ($isAdminSystem) {
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">Admin</div>';
            echo                    $this->render_nav_items($adminHomeItems);
            echo                    $this->render_nav_items($adminItems);
            echo '              </div>';
        } else {
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">' . $h($t('client.section.home', 'Dashboard')) . '</div>';
            echo                    $this->render_nav_items($homeItems);
            echo '              </div>';
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">' . $h($t('client.section.proxy', 'Proxy')) . '</div>';
            echo                    $this->render_nav_items($proxyProductItems);
            echo '              </div>';
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">' . $h($t('client.section.scraper', 'Scraper')) . '</div>';
            echo                    $this->render_nav_items($scraperItems);
            echo '              </div>';
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">' . $h($t('client.section.billing', 'Billing')) . '</div>';
            echo                    $this->render_nav_items($billingItems);
            echo '              </div>';
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">' . $h($t('client.section.support', 'Support')) . '</div>';
            echo                    $this->render_nav_items($supportItems);
            echo '              </div>';
            echo '              <div class="pm-sidebar-section">';
            echo '                  <div class="pm-sidebar-section-title">' . $h($t('client.section.account', 'Account')) . '</div>';
            echo                    $this->render_nav_items($accountItems);
            echo '              </div>';
        }
        echo '              <div class="pm-sidebar-foot"></div>';
        echo '          </div>';
        echo '      </aside>';
        echo '      <div class="pm-main">';
        echo '          <header class="pm-topbar">';
        echo '              <div class="pm-topbar-left">';
        echo '                  <button class="pm-icon-btn pm-mobile-toggle" type="button" data-pm-sidebar-toggle aria-controls="pmSidebar" aria-expanded="false" aria-label="' . $h($t('menu.toggle_navigation', 'Toggle navigation')) . '">';
        echo '                      <span></span><span></span><span></span>';
        echo '                  </button>';
        echo '                  <div class="pm-page-title">' . $h($page_title) . '</div>';
        echo '              </div>';
        echo '              <div class="pm-topbar-right">';
        echo '                  <div class="pm-pill-group" role="group" aria-label="Theme switcher">';
        echo '                      <button type="button" class="pm-pill-btn" data-pm-theme="ice">Ice</button>';
        echo '                      <button type="button" class="pm-pill-btn" data-pm-theme="midnight">Midnight</button>';
        echo '                  </div>';
        $lang_toggle_id = 'pm_lang_dropdown_toggle';
        $current_lang_label = $t('lang.' . $current_lang, $langFallbackNames[$current_lang] ?? strtoupper($current_lang));
        $current_lang_code = strtoupper($current_lang);
        echo '                  <div class="pm-lang-control" role="group" aria-label="' . $h($t('menu.language', 'Language')) . '">';
        echo '                      <span class="pm-lang-control-label">' . $h($t('menu.language', 'Language')) . '</span>';
        echo '                      <div class="dropdown pm-lang-dropdown">';
        echo '                          <button class="btn btn-sm btn-outline-secondary dropdown-toggle pm-lang-dd-toggle" type="button" id="' . $h($lang_toggle_id) . '" data-bs-toggle="dropdown" aria-expanded="false" title="' . $h($current_lang_label) . '">';
        echo '                              <span class="pm-lang-code">' . $h($current_lang_code) . '</span>';
        echo '                              <span class="pm-lang-name">' . $h($current_lang_label) . '</span>';
        echo '                          </button>';
        echo '                          <div class="dropdown-menu p-2 pm-lang-dd-menu" aria-labelledby="' . $h($lang_toggle_id) . '">';
        foreach ($langItems as $langItem) {
            $lang_code = (string)$langItem['code'];
            $lang_label = (string)$langItem['label'];
            $lang_code_ui = (string)$langItem['code_ui'];
            $active = $lang_code === $current_lang ? ' active' : '';
            echo '<a class="dropdown-item pm-lang-dd-item' . $active . '" href="' . $h((string)$langItem['url']) . '" title="' . $h($lang_label) . '" aria-label="' . $h($lang_label) . '"><span class="pm-lang-code">' . $h($lang_code_ui) . '</span><span class="pm-lang-name">' . $h($lang_label) . '</span></a>';
        }
        echo '                          </div>';
        echo '                      </div>';
        echo '                  </div>';
        if (!$isAdminSystem) {
            $clientTopbar = $this->client_topbar_context();
            echo '                  <a class="pm-client-add-funds" href="' . $h($this->append_admin_view_user_id('/client/add-funds')) . '">' . $h($t('client.nav.add_funds', 'Add Funds')) . '</a>';
            echo '                  <div class="pm-client-balance" title="' . $h($t('client.balance', 'Account balance')) . '"><span>' . $h($t('client.balance_short', 'Balance')) . '</span><strong>' . $h((string)$clientTopbar['balance']) . '</strong></div>';
            echo '                  <div class="dropdown pm-client-icon-dropdown">';
            echo '                      <button class="pm-client-icon-btn" type="button" id="pm_notify_dropdown_toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications">!</button>';
            echo '                      <div class="dropdown-menu dropdown-menu-end pm-client-mini-menu" aria-labelledby="pm_notify_dropdown_toggle">';
            echo '                          <div class="pm-client-empty">No notifications.</div>';
            echo '                      </div>';
            echo '                  </div>';
            echo '                  <a class="pm-client-icon-btn" href="' . $h($this->append_admin_view_user_id('/client/proxy/checkout')) . '" title="Cart" aria-label="Cart">$</a>';
            echo '                  <div class="dropdown pm-client-account-dropdown">';
            echo '                      <button class="pm-client-account-btn" type="button" id="pm_personal_dropdown_toggle" data-bs-toggle="dropdown" aria-expanded="false">';
            echo '                          <span class="pm-client-avatar" aria-hidden="true">U</span>';
            echo '                          <span class="pm-client-account-copy"><span>Account</span><strong>' . $h($t('client.account.my_profile', 'Мой профиль')) . '</strong></span>';
            echo '                      </button>';
            echo '                      <div class="dropdown-menu dropdown-menu-end pm-client-account-menu" aria-labelledby="pm_personal_dropdown_toggle">';
            if ((string)$clientTopbar['email'] !== '') {
                echo '<div class="pm-client-account-email">' . $h((string)$clientTopbar['email']) . '</div>';
                echo '<div class="dropdown-divider"></div>';
            }
            foreach ($accountDropItems as $item) {
                $url = $this->append_admin_view_user_id((string)$item['url']);
                echo '<a class="dropdown-item pm-client-account-item" href="' . $h($url) . '"><span class="pm-client-menu-icon">' . $h((string)$item['icon']) . '</span><span>' . $h((string)$item['label']) . '</span></a>';
            }
            echo '<div class="dropdown-divider"></div>';
            echo '<a class="dropdown-item pm-client-account-item" href="' . $h($this->append_admin_view_user_id('/client/support/tickets')) . '"><span class="pm-client-menu-icon">TK</span><span>Tickets</span></a>';
            echo '<a class="dropdown-item pm-client-account-item pm-client-logout" href="' . $h($this->build_logout_url()) . '"><span class="pm-client-menu-icon">LO</span><span>Logout</span></a>';
            echo '                      </div>';
            echo '                  </div>';
        }
        if ($is_authenticated && $isAdminSystem) {
            echo '                  <a class="pm-cta pm-cta-danger" href="' . $h($this->build_logout_url()) . '">' . $h($t('menu.logout', 'Logout')) . '</a>';
        } elseif (!$is_authenticated) {
            echo '                  <a class="pm-cta pm-cta-primary" href="' . $h($this->build_login_url()) . '">' . $h($t('auth.authorize', 'Authorize')) . '</a>';
        }
        echo '              </div>';
        echo '          </header>';
    }

    private function render_brand_logo_img_html(string $extraClass = ''): string
    {
        $className = trim('pm-brand-logo ' . $extraClass);
        $classAttr = $className !== '' ? ' class="' . $this->h($className) . '"' : '';
        $src = $this->h(
            $this->get_sogerien_domain() . '/page/img/admin_panel/pm-admin-logo-6-full-vector-no-bg.png'
        );

        return <<<HTML
<span{$classAttr} aria-hidden="true">
    <img src="{$src}" alt="" loading="eager" decoding="async" style="display:block;width:100%;height:100%;object-fit:contain">
</span>
HTML;
    }

    private function get_sogerien_domain(): string
    {
        return (string)Sogerien::InputRequest()->sogerien_domain . '/sogerien';
    }

    private function is_active_url(string $page): string
    {
        $pagePath = (string)(parse_url($page, PHP_URL_PATH) ?: $page);
        $currentPath = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
        $pagePath = trim($pagePath, '/');
        if ($pagePath !== '') {
            return $pagePath === $currentPath ? 'active' : '';
        }

        return $page === Sogerien::InputRequest()->url ? 'active' : '';
    }

    private function admin_view_user_id(): int
    {
        $path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
        if ($path !== 'client' && !str_starts_with($path, 'client/')) {
            return 0;
        }

        $raw = trim((string)($_GET['user_id'] ?? ''));
        if ($raw === '' || preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            return 0;
        }

        return (int)$raw;
    }

    private function append_admin_view_user_id(string $url): string
    {
        $userId = $this->admin_view_user_id();
        if ($userId <= 0 || !str_starts_with($url, '/client')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = (string)($parts['path'] ?? $url);
        $query = [];
        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['user_id'] = (string)$userId;
        $queryString = http_build_query($query);

        return $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function render_admin_view_user_id_bridge(): void
    {
        $userId = $this->admin_view_user_id();
        if ($userId <= 0) {
            return;
        }

        $encodedUserId = json_encode((string)$userId, JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedUserId) || $encodedUserId === '') {
            return;
        }

        echo '<script>(function(){var userId=' . $encodedUserId . ';';
        echo 'function patchUrl(value){try{var url=new URL(value,location.origin);if(url.origin!==location.origin||!url.pathname.startsWith("/client"))return value;url.searchParams.set("user_id",userId);return url.pathname+url.search+url.hash;}catch(e){return value;}}';
        echo 'document.querySelectorAll("a[href]").forEach(function(a){a.setAttribute("href",patchUrl(a.getAttribute("href")||""));});';
        echo 'document.querySelectorAll("form[action]").forEach(function(f){f.setAttribute("action",patchUrl(f.getAttribute("action")||""));});';
        echo '})();</script>';
    }

    private function build_url_with_lang(string $lang): string
    {
        $request_uri = (string)(Sogerien::InputRequest()->REQUEST_URI ?? '/');
        $parts = parse_url($request_uri);

        $path = (string)($parts['path'] ?? '/');
        $query = [];
        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['lang'] = $lang;
        $query_string = http_build_query($query);

        return $query_string === '' ? $path : $path . '?' . $query_string;
    }

    private function build_logout_url(): string
    {
        return '/admin?logout=1';
    }

    private function build_login_url(): string
    {
        return '/admin';
    }

    private function is_authenticated_user(): bool
    {
        return Sogerien::Users()->load_identity_from_token();
    }

    private function is_admin_user(): bool
    {
        Sogerien::Users()->load_identity_from_token();
        $groups = Sogerien::Users()->user_group;
        return is_array($groups) && isset($groups['admin']);
    }

    private function t(string $key, string $fallback = ''): string
    {
        $value = Sogerien::Lang()->get($key);
        if ($fallback !== '' && $value === $key) {
            return $fallback;
        }

        return $value;
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
