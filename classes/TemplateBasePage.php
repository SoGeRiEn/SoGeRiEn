<?php

declare(strict_types=1);

final class TemplateBasePage
{
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
            ['src' => $domain . '/page/js/BasePage/table_renderer.js', 'defer' => true],
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
     * @param array<int,array{label:string,url:string,tag?:string}> $items
     */
    private function render_nav_items(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'title') {
                $html .= '<div class="pm-sidebar-section-title">' . $this->h((string)($item['label'] ?? '')) . '</div>';
                continue;
            }

            $label = trim((string)($item['label'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            $tag = trim((string)($item['tag'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }

            $active = $this->is_active_url($url) === 'active' ? ' is-active' : '';
            $tagHtml = $tag !== '' ? '<span class="pm-nav-tag">' . $this->h($tag) . '</span>' : '';
            $html .= '<a class="pm-nav-link' . $active . '" href="' . $this->h($url) . '"><span class="pm-nav-label">' . $this->h($label) . '</span>' . $tagHtml . '</a>';
        }

        return $html;
    }

    /**
     * @param array<int,array{label:string,url:string,tag?:string,level?:int,type?:string}> $items
     */
    private function render_infatica_nav(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'title') {
                $html .= '<div class="pm-sidebar-section-title pm-infatica-title">' . $this->h((string)($item['label'] ?? '')) . '</div>';
                continue;
            }

            $label = trim((string)($item['label'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $tag = trim((string)($item['tag'] ?? ''));
            $level = max(0, min(2, (int)($item['level'] ?? 0)));
            $active = $this->is_active_url($url) === 'active' ? ' is-active' : '';
            $tagHtml = $tag !== '' ? '<span class="pm-nav-tag">' . $this->h($tag) . '</span>' : '';
            $html .= '<a class="pm-nav-link pm-nav-level-' . $level . $active . '" href="' . $this->h($url) . '"><span class="pm-nav-label">' . $this->h($label) . '</span>' . $tagHtml . '</a>';
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

        $infaticaItems = [
            ['label' => $t('menu.home', 'Home'), 'url' => '/my', 'tag' => ''],
            ['type' => 'title', 'label' => $t('menu.proxy', 'Proxy'), 'url' => '#'],
            ['label' => $t('menu.my_proxy_products', 'My Proxy Products'), 'url' => '/my/proxies', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.proxy_pricing', 'Proxy Pricing'), 'url' => '/all_proxy', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.proxy_residential', 'Residential Proxies'), 'url' => '/proxies', 'tag' => '', 'level' => 1],
            ['label' => $t('menu.proxy_residential_ipv6', 'Residential IPv6 proxies'), 'url' => '/all_proxy', 'tag' => '', 'level' => 1],
            ['label' => $t('menu.proxy_mobile', 'Mobile Proxies'), 'url' => '/proxies/infatica_io', 'tag' => '', 'level' => 1],
            ['label' => $t('menu.proxy_isp', 'ISP proxies'), 'url' => '/proxies/isp', 'tag' => '', 'level' => 1],
            ['label' => $t('menu.proxy_dc', 'DC Proxy'), 'url' => '/proxies/proxysmartorg', 'tag' => '', 'level' => 1],
            ['label' => $t('menu.proxy_dedicated_dc', 'Dedicated Datacenter Proxy'), 'url' => '/proxies/proxysmartorg', 'tag' => '', 'level' => 2],
            ['label' => $t('menu.proxy_shared_dc', 'Shared Datacenter Proxy'), 'url' => '/proxies/proxysmartorg', 'tag' => '', 'level' => 2],
            ['type' => 'title', 'label' => $t('menu.scraper', 'Scraper'), 'url' => '#'],
            ['label' => $t('menu.web_scraping_api_pricing', 'Web Scraping API Pricing'), 'url' => '/demo/purchase', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.api_playground', 'API Playground'), 'url' => '/my', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.my_scraping_api', 'My Scraping API'), 'url' => '/my', 'tag' => '', 'level' => 0],
            ['type' => 'title', 'label' => $t('menu.billing', 'Billing'), 'url' => '#'],
            ['label' => $t('menu.my_invoices', 'My Invoices'), 'url' => '/my/payments', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.add_funds', 'Add Funds'), 'url' => '/my/payments', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.payment_methods', 'Payment Methods'), 'url' => '/my/payments', 'tag' => '', 'level' => 0],
            ['type' => 'title', 'label' => $t('menu.support', 'Support'), 'url' => '#'],
            ['label' => $t('menu.support_tickets', 'Tickets'), 'url' => '/support/tickets', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.support_open_ticket', 'Open Ticket'), 'url' => '/support/tickets/create', 'tag' => '', 'level' => 0],
            ['type' => 'title', 'label' => $t('menu.documentation', 'Documentation'), 'url' => '#'],
            ['label' => $t('menu.manuals', 'Manuals'), 'url' => '/manuals', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.partners', 'Partners'), 'url' => '/partners', 'tag' => '', 'level' => 0],
            ['label' => $t('menu.marketplace', 'Marketplace'), 'url' => '/marketplace', 'tag' => '', 'level' => 0],
        ];

        if ($is_authenticated && isset(Sogerien::Users()->user_group['admin'])) {
            $infaticaItems[] = ['type' => 'title', 'label' => $t('menu.admin_tickets', 'Admin tickets'), 'url' => '#'];
            $infaticaItems[] = ['label' => $t('menu.admin_tickets_all', 'All tickets'), 'url' => '/admin/tickets', 'tag' => '', 'level' => 0];
            $infaticaItems[] = ['label' => $t('menu.admin_tickets_pending', 'Tickets requiring reply'), 'url' => '/admin/tickets/pending', 'tag' => '', 'level' => 0];
        }

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
        echo '                      <div class="pm-brand-sub">' . $h($t('app.admin_catalog', 'admin catalog')) . '</div>';
        echo '                  </div>';
        echo '              </div>';
        echo '              <div class="pm-sidebar-section pm-infatica-nav">';
        echo                    $this->render_infatica_nav($infaticaItems);
        echo '              </div>';
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
        echo '                  <div class="pm-pill-group" role="group" aria-label="' . $h($t('theme.switcher', 'Theme switcher')) . '">';
        echo '                      <button type="button" class="pm-pill-btn" data-pm-theme="ice">' . $h($t('theme.ice', 'Ice')) . '</button>';
        echo '                      <button type="button" class="pm-pill-btn" data-pm-theme="midnight">' . $h($t('theme.midnight', 'Midnight')) . '</button>';
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
        if ($is_authenticated) {
            echo '                  <div class="dropdown pm-user-menu">';
            echo '                      <button class="pm-user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
            echo '                          <span class="pm-user-avatar">' . $h($this->get_user_initials()) . '</span>';
            echo '                          <span class="pm-user-name">' . $h($this->get_user_label()) . '</span>';
            echo '                      </button>';
            echo '                      <div class="dropdown-menu dropdown-menu-end pm-user-dropdown">';
            echo '                          <a class="dropdown-item" href="/profile">' . $h($t('menu.profile', 'Profile')) . '</a>';
            echo '                          <a class="dropdown-item" href="/my/payments">' . $h($t('menu.my_payments', 'My Payments')) . '</a>';
            echo '                          <a class="dropdown-item" href="/support/tickets">' . $h($t('menu.support_tickets', 'Tickets')) . '</a>';
            echo '                          <div class="dropdown-divider"></div>';
            echo '                          <a class="dropdown-item pm-user-logout" href="' . $h($this->build_logout_url()) . '">' . $h($t('menu.logout', 'Logout')) . '</a>';
            echo '                      </div>';
            echo '                  </div>';
        } else {
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
        return $page === Sogerien::InputRequest()->url ? 'active' : '';
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

    private function get_user_label(): string
    {
        $userId = (int)(Sogerien::Users()->user_id ?? 0);
        if ($userId <= 0) {
            return $this->t('common.user', 'User');
        }
        $user = Sogerien::Users()->get_user_for_edit($userId);
        if (is_array($user)) {
            foreach (['fio', 'login', 'email', 'name'] as $key) {
                $value = trim((string)($user[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return $this->t('common.user', 'User');
    }

    private function get_user_initials(): string
    {
        $label = $this->get_user_label();
        $first = mb_substr(trim($label), 0, 1);
        return $first !== '' ? mb_strtoupper($first) : 'U';
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

