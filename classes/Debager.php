<?php
declare(strict_types=1);

final class Debager
{
    use SogerienClassHelp;

    public function start(bool $clear = true): self
    {
        Sogerien::$debag = true;

        if ($clear) {
            Sogerien::$debag_array = [];
        }

        return $this;
    }

    public function end(bool $disable_debug = true): self
    {
        if ($disable_debug) {
            Sogerien::$debag = false;
        }

        return $this;
    }

    public function reset(): self
    {
        Sogerien::$debag_array = [];

        return $this;
    }

    public function log_input(string $class_name, string $method_name, mixed $input): void
    {
        if (!Sogerien::$debag) {
            return;
        }

        Sogerien::$debag_array[] = [
            'class' => $class_name,
            'method' => $method_name,
            'input' => $input,
            'output' => null,
        ];
    }

    public function log_output(string $class_name, string $method_name, mixed $output): void
    {
        if (!Sogerien::$debag) {
            return;
        }

        for ($i = count(Sogerien::$debag_array) - 1; $i >= 0; $i--) {
            if (!isset(Sogerien::$debag_array[$i])) {
                continue;
            }

            $row = Sogerien::$debag_array[$i];
            if (($row['class'] ?? '') !== $class_name) {
                continue;
            }
            if (($row['method'] ?? '') !== $method_name) {
                continue;
            }
            if (array_key_exists('output', $row) && $row['output'] !== null) {
                continue;
            }

            Sogerien::$debag_array[$i]['output'] = $output;
            return;
        }

        Sogerien::$debag_array[] = [
            'class' => $class_name,
            'method' => $method_name,
            'input' => null,
            'output' => $output,
        ];
    }

    public function capture_return(mixed $value, string $class_name, string $method_name): mixed
    {
        $this->log_output($class_name, $method_name, $value);
        return $value;
    }

    public function capture_void(string $class_name, string $method_name): void
    {
        $this->log_output($class_name, $method_name, null);
    }

    /**
     * @return array<int,array{class:string,method:string,input:mixed,output:mixed}>
     */
    public function get_log(): array
    {
        return Sogerien::$debag_array;
    }

    public function print(bool $echo = true): string
    {
        $lines = [];

        foreach ($this->get_log() as $entry) {
            $lines[] = $entry['class'] . '->' . $entry['method'];
            $lines[] = 'input:';
            $lines[] = trim(print_r($entry['input'], true));
            $lines[] = 'output:';

            $output = $entry['output'];
            if ($entry['class'] === 'APICyberyozh' && $entry['method'] === 'proxiesList' && is_array($output)) {
                $output = $this->build_proxies_schema($output);
            }

            $lines[] = trim(print_r($output, true));
            $lines[] = '';
        }

        $text = implode("\n", $lines);
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = "<pre>\n" . $safe . "\n</pre>";

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    /**
     * @param array<string,mixed> $output
     * @return array<string,mixed>
     */
    private function build_proxies_schema(array $output): array
    {
        $schema = [
            'location_country_code' => [],
            'days' => [],
            'price_usd' => [],
            'proxy_category' => [],
            'stock_status' => [],
            'traffic_limitation' => [],
            'is_auto_renewal_possible' => [],
            'access_type' => [],
        ];

        $data = $output['data'] ?? null;
        if (!is_array($data)) {
            return $schema;
        }
        $results = $data['results'] ?? null;
        if (!is_array($results)) {
            return $schema;
        }

        foreach ($results as $proxyRow) {
            if (!is_array($proxyRow)) {
                continue;
            }

            $accessType = (string)($proxyRow['access_type'] ?? '');
            if ($accessType !== '') {
                $schema['access_type'][$accessType] = '';
            }

            $locTop = strtoupper((string)($proxyRow['location_country_code'] ?? ''));
            if ($locTop !== '') {
                $codes = preg_split('/\s*,\s*/', $locTop) ?: [];
                foreach ($codes as $code) {
                    $code = trim($code);
                    if ($code === '') {
                        continue;
                    }
                    $schema['location_country_code'][$code] = '';
                }
            }

            $products = $proxyRow['proxy_products'] ?? null;
            if (!is_array($products)) {
                continue;
            }

            foreach ($products as $prod) {
                if (!is_array($prod)) {
                    continue;
                }

                $loc = strtoupper((string)($prod['location_country_code'] ?? ''));
                if ($loc !== '') {
                    $codes = preg_split('/\s*,\s*/', $loc) ?: [];
                    foreach ($codes as $code) {
                        $code = trim($code);
                        if ($code === '') {
                            continue;
                        }
                        $schema['location_country_code'][$code] = '';
                    }
                }

                $days = (string)($prod['days'] ?? '');
                if ($days !== '') {
                    $schema['days'][$days] = '';
                }

                $price = (string)($prod['price_usd'] ?? '');
                if ($price !== '') {
                    $schema['price_usd'][$price] = '';
                }

                $cat = (string)($prod['proxy_category'] ?? '');
                if ($cat !== '') {
                    $schema['proxy_category'][$cat] = '';
                }

                $status = (string)($prod['stock_status'] ?? '');
                if ($status !== '') {
                    $schema['stock_status'][$status] = '';
                }

                $traffic = (string)($prod['traffic_limitation'] ?? '');
                if ($traffic !== '') {
                    $schema['traffic_limitation'][$traffic] = '';
                }

                $auto = (string)($prod['is_auto_renewal_possible'] ?? '');
                if ($auto !== '') {
                    $schema['is_auto_renewal_possible'][$auto] = '';
                }
            }
        }

        ksort($schema['location_country_code']);
        ksort($schema['days']);
        ksort($schema['price_usd']);
        ksort($schema['proxy_category']);
        ksort($schema['stock_status']);
        ksort($schema['traffic_limitation']);
        ksort($schema['is_auto_renewal_possible']);
        ksort($schema['access_type']);

        return $schema;
    }
}
