<?php
declare(strict_types=1);

final class APIInfaticaIo_mobile
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
        $this->api->set_mobile_api_key($api_key);
        $this->api->set_api_key($api_key);
    }

    private function with_mobile_api_key(callable $callback): mixed
    {
        $previous = $this->api->api_key;
        $mobileKey = trim($this->api->api_key_mobile);
        if ($mobileKey !== '') {
            $this->api->set_api_key($mobileKey);
        }
        try {
            return $callback();
        } finally {
            if ($mobileKey !== '') {
                $this->api->set_api_key($previous);
            }
        }
    }

    /** @return array<mixed>|null */
    public function reseller_stats(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->stats());
    }

    /** @return array<mixed>|null */
    public function online_statistics(string $country = '', string $period = '', int $interval = 1): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->online_info($country, 'mobile', $period, $interval));
    }

    /** @return array<mixed>|null */
    public function geos(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->mobile_nodes_info());
    }

    /** @return array<mixed>|null */
    public function detailed_geos(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->count_by_geo_mob());
    }

    /** @return array<mixed>|null */
    public function keys(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->keys());
    }

    /** @return array<mixed>|null */
    public function subdivision_codes(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->subdivision_codes());
    }

    /** @return array<mixed>|null */
    public function isp_codes(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->isp_codes());
    }

    /** @return array<mixed>|null */
    public function zip_codes(string $country = ''): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->zip_codes($country));
    }

    /** @return array<mixed>|null */
    public function geo_db(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->geo());
    }

    /** @return array<mixed>|null */
    public function countries(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->proxylist_countries());
    }

    /** @return array<mixed>|null */
    public function regions(string $country): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->regions($country));
    }

    /** @return array<mixed>|null */
    public function cities(string $country, string $region): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->cities($country, $region));
    }

    /** @return array<mixed>|null */
    public function packages(): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_packages());
    }

    /** @return array<mixed>|null */
    public function packages_filtered(string $packages = ''): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_packages_filtered($packages));
    }

    /** @return array<mixed>|null */
    public function package_info(string $package_key): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_info($package_key));
    }

    /** @return array<mixed>|null */
    public function package_usage(string $package_key): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_usage($package_key));
    }

    /** @return array<mixed>|null */
    public function usage(bool $post = false): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->usage($post));
    }

    /** @return array<mixed>|null */
    public function usage_pagination(int $page = 1): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->usage_pagination($page));
    }

    /** @return array<mixed>|null */
    public function traffic_details(string $package_key, string $period = 'daily'): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->traffic_details($package_key, $period));
    }

    /**
     * Builds consumption speed from Infatica traffic-details buckets.
     *
     * @return array<string,array<string,array{bytes:int,mb:float,avg_mbps:float}>>
     */
    public function traffic_speed(string $package_key, string $period = 'daily', int $bucket_seconds = 3600): array
    {
        $details = $this->traffic_details($package_key, $period);
        if (!is_array($details) || $bucket_seconds <= 0) {
            return [];
        }

        $rows = isset($details['results']) && is_array($details['results']) ? $details['results'] : $details;
        $out = [];
        foreach ($rows as $login => $points) {
            if (!is_array($points)) {
                continue;
            }
            $loginKey = (string)$login;
            $out[$loginKey] = [];
            foreach ($points as $time => $bytesRaw) {
                if (!is_numeric($bytesRaw)) {
                    continue;
                }
                $bytes = (int)$bytesRaw;
                $out[$loginKey][(string)$time] = [
                    'bytes' => $bytes,
                    'mb' => round($bytes / 1024 / 1024, 4),
                    'avg_mbps' => round(($bytes * 8) / $bucket_seconds / 1000 / 1000, 4),
                ];
            }
        }

        return $out;
    }

    /** @return array<mixed>|null */
    public function create_package_bytes(int $limit_bytes, string $expired_at): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_create([
            'limit_traffic_common' => $limit_bytes,
            'expired_at' => $expired_at,
        ]));
    }

    /** @return array<mixed>|null */
    public function create_package_gib(float $limit_gib, string $expired_at): ?array
    {
        return $this->create_package_bytes($this->gib_to_bytes($limit_gib), $expired_at);
    }

    /** @return array<mixed>|null */
    public function set_traffic_limit_bytes(string $package_key, int $limit_bytes, string $expired_at = ''): ?array
    {
        $form = ['limit_traffic_common' => $limit_bytes];
        if (trim($expired_at) !== '') {
            $form['expired_at'] = trim($expired_at);
        }
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_update($package_key, $form));
    }

    /** @return array<mixed>|null */
    public function prolongate(string $package_key, string $expired_at): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_prolongate($package_key, $expired_at));
    }

    /** @return array<mixed>|null */
    public function add_traffic_bytes(string $package_key, int $add_bytes, bool $resume = true): ?array
    {
        $info = $this->package_info($package_key);
        $result = is_array($info) && isset($info['results']) && is_array($info['results']) ? $info['results'] : $info;
        $current = (int)($result['traffic_limits']['common'] ?? 0);
        $expiredAt = (string)($result['expired_at'] ?? '');
        $updated = $this->set_traffic_limit_bytes($package_key, $current + $add_bytes, $expiredAt);
        if ($resume) {
            $this->resume($package_key);
        }
        return $updated;
    }

    /** @return array<mixed>|null */
    public function add_traffic_mib(string $package_key, int $add_mib, bool $resume = true): ?array
    {
        return $this->add_traffic_bytes($package_key, $add_mib * 1024 * 1024, $resume);
    }

    /** @return array<mixed>|null */
    public function generate_access(string $package_key, string $name, string $login, string $password, string $country = '', int $rotation = 0, int $format = 3): ?array
    {
        $form = [
            'proxy-list-name' => $name,
            'proxy-list-login' => $login,
            'proxy-list-password' => $password,
            'proxy-list-rotation-period' => $rotation,
            'proxy-list-format' => $format,
        ];
        if (trim($country) !== '') {
            $form['proxy-list-country'] = strtoupper(trim($country));
        }
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_generate($package_key, $form));
    }

    /** @return array<mixed>|null */
    public function generate_access_from_options(string $package_key, array $options): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_generate_from_options($package_key, $options));
    }

    /** @return array<mixed>|null */
    public function api_tool_access(string $package_key, array $options): ?array
    {
        return $this->with_mobile_api_key(function () use ($package_key, $options): ?array {
            $form = $this->api->build_proxy_list_payload($options);
            return is_array($form) ? $this->api->package_api_tool($package_key, $form) : null;
        });
    }

    /** @return array<mixed>|null */
    public function update_access(string $package_key, int|string $id, string $name, array $options = []): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_updatelist_from_options($package_key, $id, $name, $options));
    }

    /** @return array<mixed>|null */
    public function regenerate_access_password(string $package_key, int|string $id, string $name): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_pwd_regenerate($package_key, $id, $name));
    }

    /** @return array<mixed>|null */
    public function view_access(string $package_key, int|string $id, string $name): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_viewlist($package_key, $id, $name));
    }

    /** @return array<mixed>|null */
    public function remove_access(string $package_key, int|string $id, string $name): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_removelist($package_key, $id, $name));
    }

    /** @return array<mixed>|null */
    public function lists(string $package_key): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->package_lists($package_key));
    }

    /** @return array<mixed>|null */
    public function suspend(string $package_key): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_suspend($package_key));
    }

    /** @return array<mixed>|null */
    public function resume(string $package_key): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_resume($package_key));
    }

    /** @return array<mixed>|null */
    public function deactivate(string $package_key): ?array
    {
        return $this->with_mobile_api_key(fn(): ?array => $this->api->reseller_package_deactivate($package_key));
    }

    public function check_ip_block(string $ip): ?string
    {
        return $this->with_mobile_api_key(fn(): ?string => $this->api->check_ip_block($ip));
    }

    public function unblock_ip(string $ip): ?string
    {
        return $this->with_mobile_api_key(fn(): ?string => $this->api->ip_unblock($ip));
    }

    /**
     * @param array<mixed> $package
     * @return array{limit_bytes:int,used_bytes:int,left_bytes:int,exhausted:bool}
     */
    public function traffic_snapshot(array $package): array
    {
        $row = isset($package['results']) && is_array($package['results']) ? $package['results'] : $package;
        $limit = (int)($row['traffic_limits']['common'] ?? 0);
        $used = (int)($row['traffic_usage']['common'] ?? 0);
        $left = max(0, $limit - $used);
        return [
            'limit_bytes' => $limit,
            'used_bytes' => $used,
            'left_bytes' => $left,
            'exhausted' => $limit > 0 && $used >= $limit,
        ];
    }

    private function gib_to_bytes(float $gib): int
    {
        return (int)round($gib * 1024 * 1024 * 1024);
    }
}
