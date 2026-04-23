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

        return array_merge([
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            'https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css',
            'https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css',
            $domain . '/page/css/admin_panel/main_menu.css',
            $domain . '/page/css/BasePage/forms.css',
            $domain . '/page/css/BasePage/table_renderer.css',
            $domain . '/page/css/admin_panel/main.css',
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

        $adminItems = [
            ['label' => 'Main page', 'url' => '/', 'tag' => 'Landing'],
            ['label' => $t('menu.users', 'Users'), 'url' => '/page_users', 'tag' => 'CRM'],
            ['label' => $t('menu.rules', 'Rules'), 'url' => '/page_rules', 'tag' => 'Access'],
            ['label' => $t('menu.rules_access', 'Rules Access'), 'url' => '/page_rules_access', 'tag' => 'ACL'],
        ];

        $proxyItems = [
            ['label' => $t('menu.proxy_catalog', 'Proxy Catalog'), 'url' => '/proxies', 'tag' => 'Core'],
            ['label' => $t('menu.proxy_catalog_infatica', 'Infatica'), 'url' => '/proxies/infatica_io', 'tag' => 'Feed'],
            ['label' => 'All Proxy', 'url' => '/all_proxy', 'tag' => 'Table'],
            ['label' => 'Proxy Catalog Proxysmart', 'url' => '/proxies/proxysmartorg', 'tag' => 'Feed'],
            ['label' => $t('menu.my_proxies', 'My Proxies'), 'url' => '/my/proxies', 'tag' => 'Client'],
            ['label' => 'Profile', 'url' => '/profile', 'tag' => 'Account'],
            ['label' => $t('menu.my_payments', 'My Payments'), 'url' => '/my/payments', 'tag' => 'Billing'],
            ['label' => $t('menu.manage_proxy', 'Manage Proxy'), 'url' => '/proxy/manage', 'tag' => 'Ops'],
            ['label' => $t('menu.demo_purchase', 'Demo Purchase'), 'url' => '/demo/purchase', 'tag' => 'Checkout'],
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
        echo '                      <div class="pm-brand-sub">admin catalog</div>';
        echo '                  </div>';
        echo '              </div>';
        echo '              <div class="pm-sidebar-section">';
        echo '                  <div class="pm-sidebar-section-title">' . $h($t('menu.admin', 'Admin')) . '</div>';
        echo                    $this->render_nav_items($adminItems);
        echo '              </div>';
        echo '              <div class="pm-sidebar-section">';
        echo '                  <div class="pm-sidebar-section-title">' . $h($t('menu.proxy', 'Proxy')) . '</div>';
        echo                    $this->render_nav_items($proxyItems);
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
        if ($is_authenticated) {
            echo '                  <a class="pm-cta pm-cta-danger" href="' . $h($this->build_logout_url()) . '">' . $h($t('menu.logout', 'Logout')) . '</a>';
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

