<?php
declare(strict_types=1);

final class APIInfaticaIo_scraper
{
    private APIInfaticaIo_transport $api;

    public function __construct(?APIInfaticaIo_transport $api = null)
    {
        $this->api = $api ?? new APIInfaticaIo_transport();
    }

    public function core(): APIInfaticaIo_transport
    {
        return $this->api;
    }

    public function set_api_key(string $api_key): void
    {
        $this->api->set_scraper_api_key($api_key);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    public function scrape(array $payload): array|string|null
    {
        return $this->api->scraper($payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    public function render(array $payload): array|string|null
    {
        return $this->api->scraper_render($payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<mixed>|string|null
     */
    public function serp(array $payload): array|string|null
    {
        return $this->api->scraper_serp($payload);
    }

    /** @return array<mixed>|string|null */
    public function chatgpt(string $query, bool $return_html = false): array|string|null
    {
        return $this->api->scraper_chatgpt($query, $return_html);
    }

    /** @return array<mixed>|string|null */
    public function gemini(string $query, bool $return_html = false): array|string|null
    {
        return $this->api->scraper_gemini($query, $return_html);
    }

    /** @return array<mixed>|string|null */
    public function perplexity(string $query, bool $return_html = false): array|string|null
    {
        return $this->api->scraper_perplexity($query, $return_html);
    }

    public function decode_base64_html(string $value): ?string
    {
        return $this->api->scraper_decode_base64_html($value);
    }
}

