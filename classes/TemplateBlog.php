<?php

declare(strict_types=1);

final class TemplateBlog
{
    public function __construct(private Template $template)
    {
    }

    /**
     * @return array<int,string>
     */
    public function get_head_css_urls(): array
    {
        $domain = (string)Sogerien::InputRequest()->sogerien_domain . '/sogerien';

        return array_merge([
            $domain . '/page/css/blog/blog.css',
        ], Affects::get_head_css_urls($domain));
    }

    /**
     * @return array<int,array{src:string,defer:bool}>
     */
    public function get_head_js_urls(): array
    {
        $domain = (string)Sogerien::InputRequest()->sogerien_domain . '/sogerien';

        return array_merge([
            ['src' => $domain . '/page/js/blog/blog.js', 'defer' => true],
        ], Affects::get_head_js_urls($domain));
    }

    public function get_body_class(): string
    {
        return 'pm-template-blog';
    }

    /**
     * @return array<string,string>
     */
    public function get_body_attributes(): array
    {
        return [
            'data-template-blog' => '1',
        ];
    }

    public function render_body_open(): void
    {
    }

    public function render_body_close(): void
    {
    }
}

