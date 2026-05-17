<?php
declare(strict_types=1);

final class APIInfaticaIo_isp
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
        $this->api->set_isp_api_key($api_key);
        $this->api->set_api_key($api_key);
    }

    private function with_isp_api_key(callable $callback): mixed
    {
        $previous = $this->api->api_key;
        $ispKey = trim($this->api->api_key_isp);
        if ($ispKey !== '') {
            $this->api->set_api_key($ispKey);
        }
        try {
            return $callback();
        } finally {
            if ($ispKey !== '') {
                $this->api->set_api_key($previous);
            }
        }
    }

    /** @return array<mixed>|null */
    public function balance(): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_balance());
    }

    /** @return array<mixed>|null */
    public function countries(): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_countries());
    }

    /** @return array<mixed>|null */
    public function create_package(string $country, int $count = 1): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_create($country, $count));
    }

    /** @return array<mixed>|null */
    public function package_info(string $package_key): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_info($package_key));
    }

    /** @return array<mixed>|null */
    public function suspend(string $package_key): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_suspend($package_key));
    }

    /** @return array<mixed>|null */
    public function resume(string $package_key): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_resume($package_key));
    }

    /** @return array<mixed>|null */
    public function cancel(string $package_key): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_cancel($package_key));
    }

    /** @return array<mixed>|null */
    public function uncancel(string $package_key): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_uncancel($package_key));
    }

    /** @return array<mixed>|null */
    public function deactivate(string $package_key): ?array
    {
        return $this->with_isp_api_key(fn(): ?array => $this->api->isp_package_deactivate($package_key));
    }
}

