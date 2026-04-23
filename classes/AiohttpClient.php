<?php
declare(strict_types=1);

class AiohttpClient
{ 
    public array $rows = [];          // сырые строки после нормализации типов
    public array $select = [];        // алиас rows
    public array $select_assoc = [];  // [id] => row

    // ---- Новое: журнал и кеш ----
    public ?string $last_sql = null;        // последний SQL
    public array  $last_params = [];        // последние параметры (для PDO)
    public ?string $last_hash = null;       // md5 ключ запроса
    public ?array $last_result = null;      // последний нормализованный результат
    public ?array $last_raw = null;         // последний "сыроой" ответ из БД (до normalize)

    /** Кеш только для SELECT: md5(sql [+ params]) => raw rows (array<array>) */
    private array $cache_select_raw = [];

    /** История вызовов (для отладки/аудита) */
    public array $history = []; // каждый элемент: ['ts'=>..., 'sql'=>..., 'params'=>..., 'hash'=>..., 'cached'=>bool, 'rows'=>int]

    /** @var \PgSql\Connection|null */
    private $conn = null;
    /** @var \PDO|null */
    private ?\PDO $pdo = null;

    public function __construct(
        string $host = 'localhost',
        string $port = '5432',
        string $dbname = 'cabinet',
        string $user = 'cabinet_usr',
        string $password = ''
    ) { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $dsn_pg = "host={$host} port={$port} dbname={$dbname} user={$user} password={$password}";
        $this->conn = @pg_connect($dsn_pg);
        if (!$this->conn) throw new \RuntimeException('Не удалось подключиться (pg_*): ' . pg_last_error());

        $dsn_pdo = "pgsql:host={$host};port={$port};dbname={$dbname}";
        try {
            $this->pdo = new \PDO($dsn_pdo, $user, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Не удалось подключиться (PDO): ' . $e->getMessage(), 0, $e);
        }
}

    /** Быстрый чек «выглядит как JSON» */
    private static function looksJson($s): bool { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!is_string($s)) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        $s = trim($s);
        return   Sogerien::Debager()->capture_return(($s !== '' && (($s[0] === '{' && substr($s,-1) === '}') || ($s[0] === '[' && substr($s,-1) === ']'))), __CLASS__, __FUNCTION__);
}

    /** Мягкий json_decode в ассоц.массив, иначе вернуть исходное */
    private static function jsonDecodeSoft($s) { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!self::looksJson($s)) return   Sogerien::Debager()->capture_return($s, __CLASS__, __FUNCTION__);
        $v = json_decode($s, true);
        return   Sogerien::Debager()->capture_return((json_last_error() === JSON_ERROR_NONE) ? $v : $s, __CLASS__, __FUNCTION__);
}

    /** Нормализация одной строки: id->int, JSON поля -> массивы, выравнивание table_value */
    private static function normalizeRow(array $r): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        // id -> int, если возможно
        if (isset($r['id']) && is_numeric($r['id'])) $r['id'] = (int)$r['id'];

        // перечислимые поля, которые часто приходят json-строкой
        foreach (['table_value','index','locale','parents','tags'] as $k) {
            if (array_key_exists($k, $r)) {
                $r[$k] = self::jsonDecodeSoft($r[$k]);
                // пустые/NULL → удобные значения
                if ($k === 'parents' || $k === 'tags') {
                    if (!is_array($r[$k])) $r[$k] = [];
                }
                if ($k === 'locale' && !is_array($r[$k])) $r[$k] = [];
            }
        }

        // Гарантируем, что table_value — массив
        if (!isset($r['table_value']) || !is_array($r['table_value'])) $r['table_value'] = [];

        // Проталкиваем базовые поля в table_value, но не затираем уже заданные в table_value
        foreach (['id','name','table_name','status'] as $k) {
            if (array_key_exists($k, $r) && !array_key_exists($k, $r['table_value'])) {
                $r['table_value'][$k] = $r[$k];
            }
        }

        // Синхронизация id внутри table_value с основным id
        if (isset($r['id'])) $r['table_value']['id'] = $r['id'];

        // Иногда в table_value['id'] строка — приведём
        if (isset($r['table_value']['id']) && is_numeric($r['table_value']['id'])) {
            $r['table_value']['id'] = (int)$r['table_value']['id'];
        }

        return   Sogerien::Debager()->capture_return($r, __CLASS__, __FUNCTION__);
}

    /** Нормализация набора строк + построение индекса */
    private function finish(array $rows): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $norm = [];
        foreach ($rows as $row) $norm[] = self::normalizeRow($row);

        $this->rows = $this->select = $norm;

        // индекс по id
        $idx = [];
        foreach ($norm as $r) {
            if (array_key_exists('id', $r)) {
                $k = is_numeric($r['id']) ? (int)$r['id'] : (string)$r['id'];
                $idx[$k] = $r;
            }
        }
        $this->select_assoc = $idx;
        $this->last_result  = $norm;
        return   Sogerien::Debager()->capture_return($this->select, __CLASS__, __FUNCTION__);
}

    // ---------- Новые утилиты для кеша/журнала ----------

    /** Это SELECT? (простая проверка по началу строки, игнорируя пробелы/комментарии '--' и '/* ... *\/') */
    private static function isSelect(string $sql): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $s = ltrim($sql);
        // грубо убираем ведущие SQL-комментарии
        while (str_starts_with($s, '--')) {
            $pos = strpos($s, "\n");
            $s = ($pos === false) ? '' : ltrim(substr($s, $pos + 1));
        }
        if ($s === '') return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        return Sogerien::Debager()->capture_return((stripos($s, 'select') === 0 || preg_match('/^\s*with\b/i', $sql) === 1), __CLASS__, __FUNCTION__); // допускаем WITH ... SELECT
    }

    /** Формируем md5 ключ из SQL и (опционально) параметров */
    private static function hashKey(string $sql, ?array $params = null): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $base = $sql;
        if ($params !== null) {
            // Параметры важны для PDO — учитываем их в ключе, но компактно
            $p = json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $base .= '|$p=' . ($p === false ? 'json-error' : md5($p));
        }
        return Sogerien::Debager()->capture_return(md5($base), __CLASS__, __FUNCTION__);
    }

    /** Добавить запись в историю запросов */
    private function logHistory(string $sql, array $params, string $hash, bool $fromCache, int $rowsCount): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->history[] = [
            'ts'      => date('c'),
            'sql'     => $sql,
            'params'  => $params,
            'hash'    => $hash,
            'cached'  => $fromCache,
            'rows'    => $rowsCount,
        ];
        // ограничим историю до, скажем, 500 последних записей
        if (count($this->history) > 500) {
            array_splice($this->history, 0, count($this->history) - 500);
        }
}

    // ---------- Публичные методы ----------

    /** SELECT через pg_* (без параметров) с кешем по md5(sql) */
    public function send_request(string $sql): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->last_sql    = $sql;
        $this->last_params = [];
        $this->last_hash   = self::hashKey($sql, null);
        $this->last_result = null;
        $this->last_raw    = null;

        $isSelect = self::isSelect($sql);

        // Попытка вернуть из кеша
        if ($isSelect && isset($this->cache_select_raw[$this->last_hash])) {
            $raw = $this->cache_select_raw[$this->last_hash];
            $this->last_raw = $raw;
            $out = $this->finish($raw);
            $this->logHistory($sql, [], $this->last_hash, true, count($raw));
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        // Не кеш или нет в кеше — выполняем
        if (!$this->conn || pg_connection_status($this->conn) !== PGSQL_CONNECTION_OK) {
            throw new \RuntimeException('Соединение pg_* недоступно.');
        }

        $res = @pg_query($this->conn, $sql);
        if (!$res) throw new \RuntimeException('Ошибка SQL (pg_*): ' . pg_last_error($this->conn));

        $rows = [];
        while ($row = pg_fetch_assoc($res)) $rows[] = $row;
        pg_free_result($res);

        $this->last_raw = $rows;

        // Кешируем только SELECT
        if ($isSelect) {
            $this->cache_select_raw[$this->last_hash] = $rows;
        }

        $out = $this->finish($rows);
        $this->logHistory($sql, [], $this->last_hash, false, count($rows));
        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /** SELECT через PDO (подготовленные) с кешем по md5(sql+params) */
    public function send_request_pdo(string $sql, array $params = []): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!$this->pdo) throw new \RuntimeException('Соединение PDO недоступно.');

        $this->last_sql    = $sql;
        $this->last_params = $params;
        $this->last_hash   = self::hashKey($sql, $params);
        $this->last_result = null;
        $this->last_raw    = null;

        $isSelect = self::isSelect($sql);

        // Попытка вернуть из кеша
        if ($isSelect && isset($this->cache_select_raw[$this->last_hash])) {
            $raw = $this->cache_select_raw[$this->last_hash];
            $this->last_raw = $raw;
            $out = $this->finish($raw);
            $this->logHistory($sql, $params, $this->last_hash, true, count($raw));
            return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
        }

        // Не кеш — выполняем
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $this->last_raw = $rows;

        // Кешируем только SELECT
        if ($isSelect) {
            $this->cache_select_raw[$this->last_hash] = $rows;
        }

        $out = $this->finish($rows);
        $this->logHistory($sql, $params, $this->last_hash, false, count($rows));
        return   Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
}

    /** Non-SELECT через PDO — не кешируем и не сохраняем результат */
    public function exec_pdo(string $sql, array $params = []): int
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!$this->pdo) throw new \RuntimeException('Соединение PDO недоступно.');

        // Журналируем сам факт входящего запроса (без rows и без кеша)
        $hash = self::hashKey($sql, $params);
        $this->last_sql    = $sql;
        $this->last_params = $params;
        $this->last_hash   = $hash;
        $this->last_result = null;
        $this->last_raw    = null;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $affected = $stmt->rowCount();

        // Для изменяющих запросов фиксируем историю, но не кешируем
        $this->history[] = [
            'ts'      => date('c'),
            'sql'     => $sql,
            'params'  => $params,
            'hash'    => $hash,
            'cached'  => false,
            'rows'    => -$affected, // условная пометка: отрицательное = non-select
        ];
        if (count($this->history) > 500) {
            array_splice($this->history, 0, count($this->history) - 500);
        }

        return   Sogerien::Debager()->capture_return($affected, __CLASS__, __FUNCTION__);
}

    public function __destruct()
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->conn instanceof \PgSql\Connection) { @pg_close($this->conn); $this->conn = null; }
        $this->pdo = null;
}
}
