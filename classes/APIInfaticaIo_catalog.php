<?php
declare(strict_types=1);

final class APIInfaticaIo_catalog
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

    /**
     * @param array<string,array<int,float>> $pricing
     */
    public function set_pricing(array $pricing): void
    {
        $this->api->set_pricing($pricing);
    }

    /** @return array<string,array<int,float>> */
    public function retail_pricing(): array
    {
        return $this->api->retail_pricing();
    }

    /** @return array<string,array<int,float>> */
    public function cost_pricing(): array
    {
        return $this->api->cost_pricing();
    }

    /** @return array<string,array<string,float|int>> */
    public function trial_retail_pricing(): array
    {
        return $this->api->trial_retail_pricing();
    }

    /** @return array<string,array<string,float|int>> */
    public function trial_cost_pricing(): array
    {
        return $this->api->trial_cost_pricing();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function proxies_list(array $params = []): array
    {
        return $this->api->proxiesList($params);
    }

    /**
     * @return array<string,string>|null
     */
    public function shared_proxy_urls(string $login, string $password, array $options = []): ?array
    {
        return $this->api->shared_proxy_urls_from_options($login, $password, $options);
    }

    public function shared_proxy_host(): string
    {
        return $this->api->shared_proxy_host;
    }

    public function shared_proxy_port(): int
    {
        return $this->api->shared_proxy_port;
    }
}
