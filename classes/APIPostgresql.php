<?php
declare(strict_types=1);

final class APIPostgresql
{
    public bool $status = false;
    public string $error = '';

    private string $db_alias = 'front';

    public function __construct()
    {
        $this->ok();
    }

    public function set_db_alias(string $db_alias): void
    {
        $db_alias = trim($db_alias);
        if ($db_alias === '') {
            $this->fail('db_alias is empty');
            return;
        }

        $this->db_alias = $db_alias;
        $this->ok();
    }

    public function get_db_alias(): string
    {
        return $this->db_alias;
    }

    /**
     * Universal SQL executor.
     * Input: ['sql' => '...', 'params' => [], 'db_alias' => 'front']
     *
     * @param array<string,mixed>|string $request
     * @return array<string,mixed>
     */
    public function query(array|string $request): array
    {
        try {
            $req = is_array($request) ? $request : $this->decode_json($request);

            $alias = $this->db_alias;
            if (isset($req['db_alias']) && is_string($req['db_alias'])) {
                $aliasCandidate = trim($req['db_alias']);
                if ($aliasCandidate !== '') {
                    $alias = $aliasCandidate;
                }
            }

            if ($alias === '') {
                throw new RuntimeException('db_alias is empty');
            }

            $sql = '';
            if (isset($req['sql']) && is_string($req['sql'])) {
                $sql = trim($req['sql']);
            } elseif (isset($req['query']) && is_string($req['query'])) {
                $sql = trim($req['query']);
            }

            if ($sql === '') {
                throw new InvalidArgumentException('sql is empty');
            }

            $params = [];
            if (array_key_exists('params', $req)) {
                if (!is_array($req['params'])) {
                    throw new InvalidArgumentException('params must be array');
                }
                $params = $req['params'];
            }

            $respJson = Sogerien::DbController()->sql_request($alias, [
                'sql' => $sql,
                'params' => $params,
            ]);

            $resp = json_decode($respJson, true);
            if (!is_array($resp)) {
                throw new RuntimeException('invalid sql_request response');
            }

            if (($resp['result'] ?? false) !== true) {
                $this->fail($this->extract_error_text($resp));
            } else {
                $this->ok();
            }

            return $resp;
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
            return [
                'result' => false,
                'error' => $this->error,
            ];
        }
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function sql(string $sql, array $params = [], string $db_alias = ''): array
    {
        $request = [
            'sql' => $sql,
            'params' => $params,
        ];

        $db_alias = trim($db_alias);
        if ($db_alias !== '') {
            $request['db_alias'] = $db_alias;
        }

        return $this->query($request);
    }

    /**
     * @return array<string,mixed>
     */
    private function decode_json(string $request): array
    {
        $decoded = json_decode($request, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('request must be valid JSON object');
        }
        return $decoded;
    }

    /**
     * @param array<string,mixed> $resp
     */
    private function extract_error_text(array $resp): string
    {
        $error = $resp['error'] ?? null;
        if (is_string($error) && trim($error) !== '') {
            return trim($error);
        }

        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
            $message = trim($error['message']);
            if ($message !== '') {
                return $message;
            }
        }

        return 'sql query failed';
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
        if ($this->error === '') {
            $this->error = 'unknown error';
        }
    }
}
