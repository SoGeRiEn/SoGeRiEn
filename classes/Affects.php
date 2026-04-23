<?php

declare(strict_types=1);

final class Affects
{
    /**
     * @return array<int,string>
     */
    public static function get_head_css_urls(string $domain): array
    {
        return [
            $domain . '/page/effects/proxymint-background-kit/proxymint-background-kit.css',
        ];
    }

    /**
     * @return array<int,array{src:string,defer:bool}>
     */
    public static function get_head_js_urls(string $domain): array
    {
        return [
            ['src' => $domain . '/page/effects/proxymint-background-kit/cosmic-particle-network.js', 'defer' => true],
            ['src' => $domain . '/page/effects/proxymint-background-kit/proxymint-background-kit.js', 'defer' => true],
        ];
    }
}

