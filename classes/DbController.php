<?php
declare(strict_types=1);

/* DbController.php */

final class DbConfig
{
    use SogerienClassHelp;

    // Пустой конфиг по умолчанию - заполняешь в index.php
    public string $DB_HOST = '';
    public string $DB_PORT = '5432';
    public string $DB_NAME = '';
    public string $DB_USER = '';
    public string $DB_PASS = '';
    public string $DB_CHARSET = 'utf8mb4';

    // Если включить true - PDO может жить дольше объекта (не рекомендую если хочешь "строго пока жив объект")
    public bool $PERSISTENT = false;
}

final class DbController
{
    use SogerienClassHelp;

    /** Один общий объект конфига - ты его перезаписываешь в index.php */
    public DbConfig $DbConfig;

    /** @var array<string,\PDO> alias => PDO */
    private array $pool = [];

    /** @var array<string,mixed> alias => pgsql connection */
    private array $pgPool = [];

    /** @var array<string,DbConfig> alias => config snapshot */
    private array $confSnap = [];

    public function __construct()
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->DbConfig = new DbConfig(); // по умолчанию пустой, без коннекта
}

    public function __destruct()
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        // Явное закрытие всех соединений
        foreach ($this->pool as $alias => $pdo) {
            $this->pool[$alias] = null;
            unset($this->pool[$alias]);
        }
        foreach ($this->pgPool as $alias => $conn) {
            if (is_resource($conn) || (is_object($conn) && get_class($conn) === 'PgSql\\Connection')) {
                @pg_close($conn);
            }
            unset($this->pgPool[$alias]);
        }
        $this->confSnap = [];
}

    /**
     * Подключает alias к базе и держит активное соединение в пуле.
     * ВАЖНО: делает "снимок" DbConfig, чтобы последующие перезаписи DbConfig не влияли на уже подключённые alias.
     */
    public function connect(string $alias, ?DbConfig $conf = null): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $alias = trim($alias);
        if ($alias === '') {
            throw new \InvalidArgumentException('DB alias must be non-empty');
        }

        $conf ??= $this->DbConfig;

        $this->validate($conf);

        $snap = $this->cloneConfig($conf);
        $this->confSnap[$alias] = $snap;

        $dsn = 'pgsql:host=' . $snap->DB_HOST
            . ';port=' . $snap->DB_PORT
            . ';dbname=' . $snap->DB_NAME;

        $opts = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($snap->PERSISTENT) {
            $opts[\PDO::ATTR_PERSISTENT] = true;
        }

        try {
            $this->pool[$alias] = new \PDO($dsn, $snap->DB_USER, $snap->DB_PASS, $opts);
            unset($this->pgPool[$alias]);
            return;
        } catch (\PDOException $e) {
            if (!$this->isMissingPdoPgsqlDriver($e)) {
                throw $e;
            }
            $this->connectViaPgsql($alias, $snap);
        }
}

    /** Явно отключить конкретный alias */
    public function disconnect(string $alias): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (isset($this->pool[$alias])) {
            $this->pool[$alias] = null;
            unset($this->pool[$alias]);
        }
        if (isset($this->pgPool[$alias])) {
            $conn = $this->pgPool[$alias];
            if (is_resource($conn) || (is_object($conn) && get_class($conn) === 'PgSql\\Connection')) {
                @pg_close($conn);
            }
            unset($this->pgPool[$alias]);
        }
        unset($this->confSnap[$alias]);
}

    /** Получить PDO по алиасу */
    public function pdo(string $alias): \PDO
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!isset($this->pool[$alias])) {
            throw new \RuntimeException("DB '{$alias}' is not connected. Call connect('{$alias}', DbConfig) first.");
        }
        return   Sogerien::Debager()->capture_return($this->pool[$alias], __CLASS__, __FUNCTION__);
}

    /**
     * Выполнить SQL на нужной базе:
     * echo DbController->sql_request('front', ['sql'=>'select now()','params'=>[]]);
     */
    public function sql_request(string $alias, string|array $input): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        try {
            $req = is_array($input) ? $input : $this->decodeJsonLenient($input);

            $sql = (isset($req['sql']) && is_string($req['sql'])) ? trim($req['sql']) : '';
            if ($sql === '') {
                throw new \InvalidArgumentException("Missing/invalid 'sql'");
            }

            $params = [];
            if (array_key_exists('params', $req)) {
                if ($req['params'] === null) {
                    $params = [];
                } elseif (is_array($req['params'])) {
                    $params = $req['params'];
                } else {
                    throw new \InvalidArgumentException("Invalid 'params'");
                }
            }

            // jsonb support + safe strings
            $params = $this->sanitizeParams($params);

            [$isSelectLike, $rows, $rowCount, $colsCount] = $this->executeSql($alias, $sql, $params);

            return   Sogerien::Debager()->capture_return($this->encodeJson([
                'result'    => true,
                'rowCount'  => $rowCount,
                'colsCount' => $colsCount,
                'rows'      => $rows,
                'data'      => $isSelectLike,
                'db'        => $alias,
            ]), __CLASS__, __FUNCTION__);

        } catch (\Throwable $e) {
            return   Sogerien::Debager()->capture_return($this->encodeJson([
                'result' => false,
                'error' => [
                    'type'    => get_class($e),
                    'message' => $this->toSafeText($e->getMessage()),
                ],
            ]), __CLASS__, __FUNCTION__);
        }
}

    /* ========================= Helpers ========================= */

    private function validate(DbConfig $c): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $k) {
            if (!isset($c->$k) || trim((string)$c->$k) === '') {
                throw new \InvalidArgumentException("DbConfig.$k must be non-empty");
            }
        }
        // DB_PASS может быть пустым - допускаем (pg_hba / trust / peer)
}

    private function cloneConfig(DbConfig $c): DbConfig
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $n = new DbConfig();
        $n->DB_HOST    = $c->DB_HOST;
        $n->DB_PORT    = $c->DB_PORT;
        $n->DB_NAME    = $c->DB_NAME;
        $n->DB_USER    = $c->DB_USER;
        $n->DB_PASS    = $c->DB_PASS;
        $n->DB_CHARSET = $c->DB_CHARSET;
        $n->PERSISTENT = $c->PERSISTENT;
        return   Sogerien::Debager()->capture_return($n, __CLASS__, __FUNCTION__);
}

    /**
     * @param array<string|int,mixed> $params
     * @return array{0:bool,1:array<int,array<string,mixed>>,2:int,3:int}
     */
    private function executeSql(string $alias, string $sql, array $params): array
    {
        if (isset($this->pool[$alias])) {
            return $this->executeViaPdo($alias, $sql, $params);
        }
        if (isset($this->pgPool[$alias])) {
            return $this->executeViaPgsql($alias, $sql, $params);
        }

        throw new \RuntimeException("DB '{$alias}' is not connected. Call connect('{$alias}', DbConfig) first.");
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array{0:bool,1:array<int,array<string,mixed>>,2:int,3:int}
     */
    private function executeViaPdo(string $alias, string $sql, array $params): array
    {
        $st = $this->pdo($alias)->prepare($sql);

        foreach ($params as $k => $v) {
            $name = str_starts_with((string)$k, ':') ? (string)$k : ':' . $k;
            $st->bindValue($name, $v);
        }

        $st->execute();

        $isSelectLike = ($st->columnCount() > 0);
        $rows = $isSelectLike ? $st->fetchAll() : [];
        if (!is_array($rows)) {
            $rows = [];
        }

        if ($isSelectLike && !empty($rows)) {
            $rows = $this->expandJsonInRows($rows);
        }

        $colsCount = $st->columnCount();
        $rowCount  = $isSelectLike ? count($rows) : $st->rowCount();

        return [$isSelectLike, $rows, $rowCount, $colsCount];
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array{0:bool,1:array<int,array<string,mixed>>,2:int,3:int}
     */
    private function executeViaPgsql(string $alias, string $sql, array $params): array
    {
        if (!function_exists('pg_query')) {
            throw new \RuntimeException('pgsql extension is not available');
        }

        $conn = $this->pgPool[$alias] ?? null;
        if ($conn === null) {
            throw new \RuntimeException("DB '{$alias}' pgsql connection not found");
        }

        [$sqlForPg, $orderedParams] = $this->normalizeSqlAndParamsForPg($sql, $params);

        if ($orderedParams === []) {
            $result = @pg_query($conn, $sqlForPg);
        } else {
            $result = @pg_query_params($conn, $sqlForPg, $orderedParams);
        }

        if ($result === false) {
            $err = function_exists('pg_last_error') ? (string)pg_last_error($conn) : 'pg_query failed';
            $err = trim($err);
            if ($err === '') {
                $err = 'pg_query failed';
            }
            throw new \RuntimeException($err);
        }

        $colsCount = (int)pg_num_fields($result);
        $isSelectLike = ($colsCount > 0);

        if ($isSelectLike) {
            $rows = pg_fetch_all($result);
            if (!is_array($rows)) {
                $rows = [];
            }
            if (!empty($rows)) {
                $rows = $this->expandJsonInRows($rows);
            }
            $rowCount = count($rows);
        } else {
            $rows = [];
            $rowCount = (int)pg_affected_rows($result);
        }

        @pg_free_result($result);

        return [$isSelectLike, $rows, $rowCount, $colsCount];
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array{0:string,1:array<int,mixed>}
     */
    private function normalizeSqlAndParamsForPg(string $sql, array $params): array
    {
        if ($params === []) {
            return [$sql, []];
        }

        $normalized = [];
        foreach ($params as $k => $v) {
            $key = (string)$k;
            if (str_starts_with($key, ':')) {
                $key = substr($key, 1);
            }
            $normalized[$key] = $this->castParamForPg($v);
        }

        $resolvedIndexes = [];
        $orderedParams = [];
        $sqlWithDollarParams = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            function (array $m) use ($normalized, &$resolvedIndexes, &$orderedParams): string {
                $name = $m[1];
                if (!array_key_exists($name, $normalized)) {
                    throw new \InvalidArgumentException("Missing param :{$name}");
                }
                if (!isset($resolvedIndexes[$name])) {
                    $orderedParams[] = $normalized[$name];
                    $resolvedIndexes[$name] = count($orderedParams);
                }
                return '$' . $resolvedIndexes[$name];
            },
            $sql
        );

        if ($sqlWithDollarParams === null) {
            throw new \RuntimeException('Failed to normalize SQL params');
        }

        if ($sqlWithDollarParams !== $sql) {
            return [$sqlWithDollarParams, $orderedParams];
        }

        $orderedParams = [];
        foreach ($params as $v) {
            $orderedParams[] = $this->castParamForPg($v);
        }

        return [$sql, $orderedParams];
    }

    private function castParamForPg(mixed $value): mixed
    {
        if (is_null($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 't' : 'f';
        }

        if (is_array($value) || is_object($value)) {
            return $this->encodeJsonValueForDb($value);
        }

        if (is_resource($value)) {
            throw new \InvalidArgumentException('Unsupported SQL param type: resource');
        }

        return (string)$value;
    }

    private function isMissingPdoPgsqlDriver(\PDOException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'could not find driver')
            || str_contains($message, 'pdo_pgsql');
    }

    private function connectViaPgsql(string $alias, DbConfig $snap): void
    {
        if (!function_exists('pg_connect')) {
            throw new \RuntimeException('pdo_pgsql driver is not available and pgsql extension is missing');
        }

        $connString = $this->buildPgConnectionString($snap);
        $conn = @pg_connect($connString);
        if ($conn === false) {
            $err = function_exists('pg_last_error') ? trim((string)pg_last_error()) : '';
            if ($err === '') {
                $err = 'pg_connect failed';
            }
            throw new \RuntimeException($err);
        }

        $this->pgPool[$alias] = $conn;
        unset($this->pool[$alias]);
    }

    private function buildPgConnectionString(DbConfig $snap): string
    {
        $parts = [
            'host=' . $this->pgConnectionValue($snap->DB_HOST),
            'port=' . $this->pgConnectionValue($snap->DB_PORT),
            'dbname=' . $this->pgConnectionValue($snap->DB_NAME),
            'user=' . $this->pgConnectionValue($snap->DB_USER),
        ];

        if ($snap->DB_PASS !== '') {
            $parts[] = 'password=' . $this->pgConnectionValue($snap->DB_PASS);
        }

        return implode(' ', $parts);
    }

    private function pgConnectionValue(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    private function decodeJsonLenient(string $raw): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $raw = $this->forceUtf8($raw);

        try {
            $req = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $req = null;
        }

        if (!is_array($req)) {
            throw new \InvalidArgumentException('JSON must decode to object/array');
        }
        return   Sogerien::Debager()->capture_return($req, __CLASS__, __FUNCTION__);
}

    private function forceUtf8(string $s): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $out = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($out === false) {
            $out = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xC2-\xF4\x80-\xBF]/', '?', $s) ?? '';
        }
        return  Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    private function toSafeText(string $s): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $s = str_replace("\0", '', $s);
        $s = $this->forceUtf8($s);
        return Sogerien::Debager()->capture_return((preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? ''), __CLASS__, __FUNCTION__);
}

    /**
     * Санация параметров:
     * - string -> safe text (или валидный JSON, если похож на JSON)
     * - array/stdClass -> валидный JSON string (для jsonb)
     * - nested arrays -> рекурсивно
     */
    private function sanitizeParams(array $params): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        foreach ($params as $k => $v) {
            if (is_string($v)) {
                $params[$k] = $this->sanitizeStringPossiblyJson($v);
            } elseif (is_array($v)) {
                $params[$k] = $this->encodeJsonValueForDb($v);
            } elseif (is_object($v)) {
                $params[$k] = $this->encodeJsonValueForDb($v);
            }
        }
        return  Sogerien::Debager()->capture_return($params, __CLASS__, __FUNCTION__);
}

    private function sanitizeStringPossiblyJson(string $s): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $s = $this->toSafeText($s);

        if ($this->looksLikeJson($s)) {
            $decoded = json_decode($s, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return   Sogerien::Debager()->capture_return($this->encodeJsonValueForDb($decoded), __CLASS__, __FUNCTION__);
            }
            throw new \InvalidArgumentException('Invalid JSON in params (looks like JSON but cannot decode)');
        }

        return   Sogerien::Debager()->capture_return($s, __CLASS__, __FUNCTION__);
}

    private function encodeJsonValueForDb(mixed $value): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $value = $this->deepSanitizeForJson($value);

        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json === false) {
            $msg = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json encode failed';
            $msg = $this->toSafeText($msg);
            throw new \RuntimeException('JSON encode failed: ' . $msg);
        }

        return Sogerien::Debager()->capture_return(str_replace("\0", '', $json), __CLASS__, __FUNCTION__);
    }

    private function deepSanitizeForJson(mixed $v): mixed
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (is_string($v)) {
            return Sogerien::Debager()->capture_return($this->toSafeText($v), __CLASS__, __FUNCTION__);
        }

        if (is_array($v)) {
            foreach ($v as $kk => $vv) {
                $v[$kk] = $this->deepSanitizeForJson($vv);
            }
            return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
        }

        if (is_object($v)) {
            $arr = json_decode(
                json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                true
            );
            if (!is_array($arr)) return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
            return Sogerien::Debager()->capture_return($this->deepSanitizeForJson($arr), __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
    }

    private function looksLikeJson(string $s): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $t = ltrim($s);
        if ($t === '') return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        $c = $t[0];
        return Sogerien::Debager()->capture_return(($c === '{' || $c === '['), __CLASS__, __FUNCTION__);
    }

    /**
     * Рекурсивно разворачивает json/jsonb строки, пришедшие из PDO pgsql.
     */
    private function expandJsonInRows(array $rows): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } foreach ($rows as $ri => $row) {
            if (is_array($row)) {
                $rows[$ri] = $this->expandJsonInValue($row);
            }
        }
        return Sogerien::Debager()->capture_return($rows, __CLASS__, __FUNCTION__);
    }

    private function expandJsonInValue(mixed $v): mixed
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (is_array($v)) {
            foreach ($v as $k => $vv) {
                $v[$k] = $this->expandJsonInValue($vv);
            }
            return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
        }

        if (is_string($v) && $this->looksLikeJson($v)) {
            $decoded = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return Sogerien::Debager()->capture_return($this->expandJsonInValue($decoded), __CLASS__, __FUNCTION__);
            }
            return Sogerien::Debager()->capture_return($this->toSafeText($v), __CLASS__, __FUNCTION__);
        }

        return Sogerien::Debager()->capture_return($v, __CLASS__, __FUNCTION__);
    }

    private function encodeJson(array $payload): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json !== false) return Sogerien::Debager()->capture_return($json, __CLASS__, __FUNCTION__);

        $msg = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json encode failed';
        $msg = $this->toSafeText($msg);

        return Sogerien::Debager()->capture_return('{"result":false,"error":{"type":"RuntimeException","message":"' .
            str_replace(['\\', '"'], ['\\\\', '\"'], $msg) .
            '"
}}', __CLASS__, __FUNCTION__);
    }
}


////пример использования
//
//
//Sogerien::DbController()->DbConfig->DB_HOST    = 'localhost';
//Sogerien::DbController()->DbConfig->DB_PORT    = '5432';
//Sogerien::DbController()->DbConfig->DB_NAME    = 'sogerien';
//Sogerien::DbController()->DbConfig->DB_USER    = 'sogerien';
//Sogerien::DbController()->DbConfig->DB_PASS    = '';
//Sogerien::DbController()->DbConfig->DB_CHARSET = 'utf8mb4';
//
//// держим активный коннект под алиасом 'front'
//Sogerien::DbController()->connect('front', Sogerien::DbController()->DbConfig);
//
//
///**
// * ТЕСТЫ ЗАПРОСОВ
// */
//echo "<pre>";
//
//// front
//echo Sogerien::DbController()->sql_request('front', [
//    'sql'=>'select pg_backend_pid() as pid, now() as t',
//    'params'=>[]
//]);
//echo "<br>";
//echo Sogerien::DbController()->sql_request('front', [
//    'sql'=>'select pg_backend_pid() as pid, now() as t',
//    'params'=>[]
//]);
//
//
//echo "</pre>";
//
//Sogerien::markDone();
