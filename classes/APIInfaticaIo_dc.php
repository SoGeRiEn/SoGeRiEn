<?php
declare(strict_types=1);

final class APIInfaticaIo_dc
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
        $this->api->set_dc_api_key($api_key);
        $this->api->set_api_key($api_key);
    }

    private function with_dc_api_key(callable $callback): mixed
    {
        $previous = $this->api->api_key;
        $dcKey = trim($this->api->api_key_dc);
        if ($dcKey !== '') {
            $this->api->set_api_key($dcKey);
        }
        try {
            return $callback();
        } finally {
            if ($dcKey !== '') {
                $this->api->set_api_key($previous);
            }
        }
    }

    /** @return array<mixed>|null */
    public function balance(): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_balance());
    }

    /** @return array<mixed>|null */
    public function countries(): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_countries());
    }

    /** @return array<mixed>|null */
    public function online_nodes(): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_nodes_info());
    }

    /** @return array<mixed>|null */
    public function detailed_geos(): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->count_by_geo_dc());
    }

    /** @return array<mixed>|null */
    public function create_package(string $country, int $count = 1): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_package_create($country, $count));
    }

    /** @return array<mixed>|null */
    public function package_info(string $package_key): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_package_info($package_key));
    }

    /** @return array<mixed>|null */
    public function suspend(string $package_key): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_package_suspend($package_key));
    }

    /** @return array<mixed>|null */
    public function resume(string $package_key): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_package_resume($package_key));
    }

    /** @return array<mixed>|null */
    public function cancel(string $package_key): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_package_cancel($package_key));
    }

    /** @return array<mixed>|null */
    public function deactivate(string $package_key): ?array
    {
        return $this->with_dc_api_key(fn(): ?array => $this->api->dc_package_deactivate($package_key));
    }
}
