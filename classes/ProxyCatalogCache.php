<?php
declare(strict_types=1);

final class ProxyCatalogCache
{
    public bool $status = false;
    public string $error = '';

    public int $max_rows = 200;

    public string $infatica_cache_file = 'InfaticaIo_proxy_list_cache_v2.json';
    public string $merged_cache_file = 'AllProxy_merged_api_cache_v1.json';

    /**
     * @return array<string,mixed>
     */
    public function refresh_infatica_cache(int $limit = 200): array
    {
        $this->reset();

        $safeLimit = $this->normalize_limit($limit);
        $resp = Sogerien::API()->InfaticaIo()->Catalog()->proxies_list([
            'limit' => $safeLimit,
            'offset' => 0,
        ]);

        if (!$this->is_valid_provider_payload($resp)) {
            $providerError = trim((string)($resp['error'] ?? ''));
            return $this->fail_result('infatica_io cache refresh failed' . ($providerError !== '' ? ': ' . $providerError : ''));
        }

        $resp = $this->trim_provider_payload($resp, $safeLimit);

        if (!Sogerien::Cache()->save($resp, $this->infatica_cache_file, time())) {
            return $this->fail_result('failed to save infatica_io cache: ' . Sogerien::Cache()->error);
        }

        $merged = $this->rebuild_merged_cache();
        if (($merged['ok'] ?? false) !== true) {
            return $this->fail_result('infatica_io cache updated, but merged cache failed: ' . (string)($merged['error'] ?? ''));
        }

        $rowsCount = $this->extract_rows_count($resp);
        $this->ok();
        return [
            'ok' => true,
            'source' => 'infatica_io',
            'rows' => $rowsCount,
            'limit' => $safeLimit,
            'cache_file' => $this->infatica_cache_file,
            'merged_cache_file' => $this->merged_cache_file,
            'updated_at' => time(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rebuild_merged_cache(): array
    {
        $this->reset();

        $infatica = $this->load_source_rows($this->infatica_cache_file, 'infatica_io');

        $rows = $this->merge_rows_balanced(
            [
                $infatica['rows'],
            ],
            $this->max_rows
        );
        $columns = $this->build_columns($rows);

        $warnings = [];
        if ($infatica['warning'] !== '') {
            $warnings[] = $infatica['warning'];
        }

        $errors = [];
        if ($infatica['error'] !== '') {
            $errors[] = $infatica['error'];
        }

        $ok = $rows !== [];
        if (!$ok && $errors === []) {
            $errors[] = 'all sources are empty';
        }

        $payload = [
            'ok' => $ok,
            'data' => [
                'columns' => $columns,
                'rows' => $rows,
                'filters' => [
                    'price_usd' => $this->collect_numeric_facet_values($rows, 'price_usd'),
                    'price_per_day' => $this->collect_numeric_facet_values($rows, 'price_per_day'),
                    'price_per_gb' => $this->collect_numeric_facet_values($rows, 'price_per_gb'),
                ],
                'sources' => [
                    'infatica_io' => [
                        'ok' => $infatica['ok'],
                        'rows' => count($infatica['rows']),
                    ],
                ],
            ],
            'error' => implode(' | ', $errors),
            'warning' => implode(' | ', $warnings),
        ];

        if (!Sogerien::Cache()->save($payload, $this->merged_cache_file, time())) {
            return $this->fail_result('failed to save merged cache: ' . Sogerien::Cache()->error);
        }

        if (!$ok) {
            return $this->fail_result($payload['error']);
        }

        $this->ok();
        return [
            'ok' => true,
            'rows' => count($rows),
            'cache_file' => $this->merged_cache_file,
            'warning' => $payload['warning'],
            'updated_at' => time(),
        ];
    }

    private function normalize_limit(int $limit): int
    {
        if ($limit <= 0) {
            return $this->max_rows;
        }
        if ($limit > $this->max_rows) {
            return $this->max_rows;
        }
        return $limit;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function is_valid_provider_payload(array $payload): bool
    {
        if (($payload['ok'] ?? false) !== true) {
            return false;
        }
        if (!isset($payload['data']) || !is_array($payload['data'])) {
            return false;
        }
        if (!isset($payload['data']['rows']) || !is_array($payload['data']['rows'])) {
            return false;
        }
        return true;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function trim_provider_payload(array $payload, int $limit): array
    {
        $rowsRaw = $payload['data']['rows'] ?? [];
        $rows = [];
        if (is_array($rowsRaw)) {
            foreach ($rowsRaw as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }
        $rows = array_slice($rows, 0, $limit);

        $columnsRaw = $payload['data']['columns'] ?? [];
        $columns = [];
        if (is_array($columnsRaw)) {
            foreach ($columnsRaw as $column) {
                $column = trim((string)$column);
                if ($column === '') {
                    continue;
                }
                $columns[] = $column;
            }
        }
        if ($columns === []) {
            $columns = $this->build_columns($rows);
        }

        $payload['data']['columns'] = $columns;
        $payload['data']['rows'] = $rows;
        $payload['data']['count'] = count($rows);
        $payload['data']['count_total'] = count($rows);
        $payload['data']['filters'] = $this->collect_column_filters($rows, $columns);

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extract_rows_count(array $payload): int
    {
        $rows = $payload['data']['rows'] ?? [];
        if (!is_array($rows)) {
            return 0;
        }
        return count($rows);
    }

    /**
     * @return array{
     *   ok:bool,
     *   rows:array<int,array<string,mixed>>,
     *   warning:string,
     *   error:string
     * }
     */
    private function load_source_rows(string $cacheFile, string $source): array
    {
        $updatedAt = Sogerien::Cache()->get_last_update($cacheFile);
        if ($updatedAt <= 0) {
            return [
                'ok' => false,
                'rows' => [],
                'warning' => '',
                'error' => $source . ': cache file is missing',
            ];
        }

        $payload = Sogerien::Cache()->load($cacheFile, $updatedAt);
        if (!is_array($payload) || (($payload['ok'] ?? false) !== true) || !isset($payload['data']) || !is_array($payload['data'])) {
            return [
                'ok' => false,
                'rows' => [],
                'warning' => '',
                'error' => $source . ': cache payload is invalid',
            ];
        }

        $rowsRaw = $payload['data']['rows'] ?? [];
        $rows = [];
        if (is_array($rowsRaw)) {
            foreach ($rowsRaw as $row) {
                if (is_array($row)) {
                    $rows[] = $this->normalize_row_for_source($row, $source);
                }
            }
        }
        $rows = array_slice($rows, 0, $this->max_rows);

        return [
            'ok' => true,
            'rows' => $rows,
            'warning' => trim((string)($payload['warning'] ?? '')),
            'error' => '',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalize_row_for_source(array $row, string $source): array
    {
        if (!isset($row['API']) || trim((string)$row['API']) === '') {
            $row['API'] = $source;
        }

        if (isset($row['proxy_api_type']) && !isset($row['proxy_category'])) {
            $row['proxy_category'] = $row['proxy_api_type'];
        }
        if (isset($row['traffic_gb']) && !isset($row['traffic_limitation'])) {
            $row['traffic_limitation'] = $row['traffic_gb'];
        }
        unset($row['proxy_api_type'], $row['traffic_gb']);

        if (array_key_exists('access_type', $row)) {
            $row['access_type'] = $this->normalize_access_type($row['access_type']);
        }

        return $row;
    }

    private function normalize_access_type(mixed $value): string
    {
        $raw = strtolower(trim((string)$value));
        if ($raw === '') {
            return '';
        }
        if ($raw === 'shared' || $raw === 'public') {
            return 'public';
        }
        if ($raw === 'private') {
            return 'private';
        }
        return $raw;
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $rowGroups
     * @return array<int,array<string,mixed>>
     */
    private function merge_rows_balanced(array $rowGroups, int $limit): array
    {
        $merged = [];
        if ($limit <= 0) {
            return $merged;
        }

        $indexes = array_fill(0, count($rowGroups), 0);

        while (count($merged) < $limit) {
            $addedInCycle = false;
            foreach ($rowGroups as $groupIndex => $groupRows) {
                $cursor = $indexes[$groupIndex] ?? 0;
                if (!isset($groupRows[$cursor])) {
                    continue;
                }
                $merged[] = $groupRows[$cursor];
                $indexes[$groupIndex] = $cursor + 1;
                $addedInCycle = true;
                if (count($merged) >= $limit) {
                    break;
                }
            }
            if (!$addedInCycle) {
                break;
            }
        }

        return $merged;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function build_columns(array $rows): array
    {
        $columnSet = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                $column = trim((string)$column);
                if ($column === '') {
                    continue;
                }
                $columnSet[$column] = true;
            }
        }

        $preferred = [
            'API',
            'id',
            'title',
            'location_country_code',
            'price_usd',
            'price_per_day',
            'days',
            'proxy_category',
            'stock_status',
            'traffic_limitation',
            'price_per_gb',
            'is_auto_renewal_possible',
            'access_type',
        ];

        $columns = [];
        foreach ($preferred as $column) {
            if (isset($columnSet[$column])) {
                $columns[] = $column;
            }
        }
        foreach (array_keys($columnSet) as $column) {
            if (!in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $columns
     * @return array<string,array<int,string>>
     */
    private function collect_column_filters(array $rows, array $columns): array
    {
        $filters = [];
        foreach ($columns as $column) {
            $set = [];
            foreach ($rows as $row) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }
                $value = trim((string)$row[$column]);
                if ($value === '') {
                    continue;
                }
                $set[$value] = true;
            }
            $values = array_keys($set);
            sort($values, SORT_NATURAL | SORT_FLAG_CASE);
            $filters[$column] = $values;
        }
        return $filters;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,float>
     */
    private function collect_numeric_facet_values(array $rows, string $column): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $raw = trim((string)$row[$column]);
            if ($raw === '' || !is_numeric($raw)) {
                continue;
            }
            $values[] = (float)$raw;
        }
        if ($values === []) {
            return [];
        }
        sort($values, SORT_NUMERIC);
        return array_values(array_unique($values));
    }

    /**
     * @return array<string,mixed>
     */
    private function fail_result(string $error): array
    {
        $this->fail($error);
        return [
            'ok' => false,
            'error' => $this->error,
        ];
    }

    private function ok(): void
    {
        $this->status = true;
        $this->error = '';
    }

    private function fail(string $error): void
    {
        $this->status = false;
        $this->error = trim($error);
    }

    private function reset(): void
    {
        $this->status = false;
        $this->error = '';
    }
}
