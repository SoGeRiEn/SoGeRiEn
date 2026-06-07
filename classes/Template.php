<?php

declare(strict_types=1);

final class Template
{
    use SogerienClassHelp;

    public string $title = '';
    public string $description = '';
    public string $keywords = '';
    public string $abstract = '';
    public string $classification = '';
    public string $copyright = '';
    public string $author = '';
    public string $geography = '';

    /** @var array<string,object> */
    private array $templates = [];
    /** @var array<int,string> */
    private array $templateOrder = [];
    /** @var array<int,string> */
    private array $customCss = [];
    /** @var array<int,array{src:string,defer:bool}> */
    private array $customJs = [];
    private bool $headerRendered = false;

    public function __construct()
    {
        $this->set_templates([TemplateBasePage::class]);

        if (Sogerien::$debag) {
            Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args());
        }
    }

    /**
     * @param array<int,string> $templateClasses
     */
    public function set_templates(array $templateClasses): self
    {
        $this->templates = [];
        $this->templateOrder = [];

        foreach ($templateClasses as $templateClass) {
            $this->add_template($templateClass);
        }

        if ($this->templateOrder === []) {
            $this->add_template(TemplateBasePage::class);
        }

        return $this;
    }

    public function add_template(string $templateClass): self
    {
        $templateClass = trim($templateClass);
        if ($templateClass === '') {
            return $this;
        }

        if (!class_exists($templateClass)) {
            throw new RuntimeException('Template class not found: ' . $templateClass);
        }

        if (!isset($this->templates[$templateClass])) {
            $this->templates[$templateClass] = new $templateClass($this);
        }

        if (!in_array($templateClass, $this->templateOrder, true)) {
            $this->templateOrder[] = $templateClass;
        }

        return $this;
    }

    public function use_base_page(): self
    {
        return $this->add_template(TemplateBasePage::class);
    }

    public function use_blog(): self
    {
        return $this->add_template(TemplateBlog::class);
    }

    public function add_head_css(string $href): self
    {
        $href = $this->normalize_asset_url($href);
        if ($href !== '' && !in_array($href, $this->customCss, true)) {
            $this->customCss[] = $href;
        }

        return $this;
    }

    public function add_head_js(string $src, bool $defer = true): self
    {
        $src = $this->normalize_asset_url($src);
        if ($src === '') {
            return $this;
        }

        foreach ($this->customJs as $item) {
            if ($item['src'] === $src) {
                return $this;
            }
        }

        $this->customJs[] = ['src' => $src, 'defer' => $defer];

        return $this;
    }

    public function header(): void
    {
        if ($this->headerRendered) {
            return;
        }

        $title = $this->h($this->title);
        $description = $this->h($this->description);
        $keywords = $this->h($this->keywords);
        $abstract = $this->h($this->abstract);
        $classification = $this->h($this->classification);
        $copyright = $this->h($this->copyright);
        $author = $this->h($this->author);
        $geography = $this->h($this->geography);
        $lang = Sogerien::Lang();
        $html_lang = $this->h($lang->get_current_lang());
        $js_i18n = $lang->get_current_lang_map_json();
        $domain = (string)Sogerien::InputRequest()->sogerien_domain . '/sogerien';

        echo <<<HTML
<!DOCTYPE html>
<html lang="{$html_lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#0a101b" data-pm-theme-color>
    <meta name="keywords" content="{$keywords}" />
    <meta name="description" content="{$description}" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="abstract" content="{$abstract}">
    <meta name="classification" content="{$classification}">
    <meta name="copyright" content="{$copyright}"/>
    <meta name="distribution" content="Global">
    <meta name="resource-type" content="Document">
    <meta name="author" content="{$author}"/>
    <meta name="geography" content="{$geography}" />
    <meta name="robots" content="max-snippet:-1, max-image-preview:large, max-video-preview:-1"/>
    <title>{$title}</title>
    <link rel="icon" type="image/x-icon" href="{$domain}/page/img/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="{$domain}/page/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="{$domain}/page/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="{$domain}/page/img/apple-touch-icon.png">
    <script>
        window.SOGERIEN_I18N = {$js_i18n};
        window.sogerienLangGet = function(key, fallback) {
            if (Object.prototype.hasOwnProperty.call(window.SOGERIEN_I18N || {}, key)) {
                return window.SOGERIEN_I18N[key];
            }
            return typeof fallback === 'string' ? fallback : key;
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Figtree:wght@400;500;600;700;800;900&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
HTML;

        echo $this->render_css_links();
        echo $this->render_js_scripts();
        echo '</head>';

        $bodyClass = $this->collect_body_classes();
        $bodyAttributes = $this->render_body_attributes();
        echo '<body' . ($bodyClass !== '' ? ' class="' . $this->h($bodyClass) . '"' : '') . $bodyAttributes . '>';

        foreach ($this->get_template_instances() as $template) {
            if (method_exists($template, 'render_body_open')) {
                $template->render_body_open();
            }
        }

        $this->headerRendered = true;
    }

    public function footer(): void
    {
        foreach (array_reverse($this->get_template_instances()) as $template) {
            if (method_exists($template, 'render_body_close')) {
                $template->render_body_close();
            }
        }

        echo '</body></html>';
        $this->headerRendered = false;
    }

    public function mainmenu(): void
    {
        foreach ($this->get_template_instances() as $template) {
            if (method_exists($template, 'mainmenu')) {
                $template->mainmenu();
                return;
            }
        }
    }

    public function brand_logo_html(string $extraClass = ''): string
    {
        foreach ($this->get_template_instances() as $template) {
            if (method_exists($template, 'brand_logo_html')) {
                return $template->brand_logo_html($extraClass);
            }
        }

        return '';
    }

    public function admin_brand_logo_html(string $extraClass = ''): string
    {
        foreach ($this->get_template_instances() as $template) {
            if (method_exists($template, 'admin_brand_logo_html')) {
                return $template->admin_brand_logo_html($extraClass);
            }
        }

        return '';
    }

    /**
     * @return array<int,object>
     */
    private function get_template_instances(): array
    {
        $result = [];
        foreach ($this->templateOrder as $templateClass) {
            $result[] = $this->templates[$templateClass];
        }

        return $result;
    }

    private function render_css_links(): string
    {
        $html = '';
        foreach ($this->collect_css_urls() as $href) {
            $html .= "\n    <link rel=\"stylesheet\" href=\"" . $this->h($href) . "\">";
        }

        return $html . "\n";
    }

    private function render_js_scripts(): string
    {
        $html = '';
        foreach ($this->collect_js_urls() as $item) {
            $deferAttr = $item['defer'] ? ' defer' : '';
            $html .= "\n    <script{$deferAttr} src=\"" . $this->h($item['src']) . "\"></script>";
        }

        return $html . "\n";
    }

    /**
     * @return array<int,string>
     */
    private function collect_css_urls(): array
    {
        $urls = [];
        foreach ($this->get_template_instances() as $template) {
            if (!method_exists($template, 'get_head_css_urls')) {
                continue;
            }

            foreach ($template->get_head_css_urls() as $href) {
                if ($href !== '' && !in_array($href, $urls, true)) {
                    $urls[] = $href;
                }
            }
        }

        foreach ($this->customCss as $href) {
            if (!in_array($href, $urls, true)) {
                $urls[] = $href;
            }
        }

        return $urls;
    }

    /**
     * @return array<int,array{src:string,defer:bool}>
     */
    private function collect_js_urls(): array
    {
        $items = [];
        foreach ($this->get_template_instances() as $template) {
            if (!method_exists($template, 'get_head_js_urls')) {
                continue;
            }

            foreach ($template->get_head_js_urls() as $item) {
                $src = trim((string)($item['src'] ?? ''));
                if ($src === '' || $this->has_js_src($items, $src)) {
                    continue;
                }

                $items[] = [
                    'src' => $src,
                    'defer' => (bool)($item['defer'] ?? true),
                ];
            }
        }

        foreach ($this->customJs as $item) {
            if (!$this->has_js_src($items, $item['src'])) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function collect_body_classes(): string
    {
        $classes = [];
        foreach ($this->get_template_instances() as $template) {
            if (!method_exists($template, 'get_body_class')) {
                continue;
            }

            $bodyClass = trim((string)$template->get_body_class());
            if ($bodyClass === '') {
                continue;
            }

            foreach (preg_split('/\s+/', $bodyClass) ?: [] as $className) {
                if ($className !== '' && !in_array($className, $classes, true)) {
                    $classes[] = $className;
                }
            }
        }

        return implode(' ', $classes);
    }

    private function render_body_attributes(): string
    {
        $attributes = [];
        foreach ($this->get_template_instances() as $template) {
            if (!method_exists($template, 'get_body_attributes')) {
                continue;
            }

            foreach ((array)$template->get_body_attributes() as $key => $value) {
                $key = trim((string)$key);
                if ($key === '') {
                    continue;
                }

                $attributes[$key] = (string)$value;
            }
        }

        $html = '';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . $this->h($key) . '="' . $this->h($value) . '"';
        }

        return $html;
    }

    /**
     * @param array<int,array{src:string,defer:bool}> $items
     */
    private function has_js_src(array $items, string $src): bool
    {
        foreach ($items as $item) {
            if ($item['src'] === $src) {
                return true;
            }
        }

        return false;
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function normalize_asset_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (
            str_starts_with($value, 'http://') ||
            str_starts_with($value, 'https://') ||
            str_starts_with($value, '//') ||
            str_starts_with($value, '/')
        ) {
            return $value;
        }

        return (string)Sogerien::InputRequest()->sogerien_domain . '/sogerien/' . ltrim($value, '/');
    }
}

