<?php
declare(strict_types=1);
/* Sogerien.php */
/** ======================================================= START Bootstrap ядра: единый контроль вывода и ошибок ============================= */
/**
 * Parse Accept-Encoding and check if encoding is allowed (q>0).
 */
function sogerien_accepts_encoding(string $encoding, string $acceptEncoding): bool
{
    $encoding = strtolower(trim($encoding));
    if ($encoding === '') {
        return false;
    }

    $acceptEncoding = strtolower(trim($acceptEncoding));
    if ($acceptEncoding === '') {
        return false;
    }

    $parts = explode(',', $acceptEncoding);
    foreach ($parts as $part) {
        $chunk = trim($part);
        if ($chunk === '') {
            continue;
        }

        $token = $chunk;
        $q = 1.0;
        if (str_contains($chunk, ';')) {
            [$token, $params] = array_pad(explode(';', $chunk, 2), 2, '');
            $token = trim($token);

            $paramsParts = explode(';', (string)$params);
            foreach ($paramsParts as $param) {
                $param = trim($param);
                if ($param === '' || !str_starts_with($param, 'q=')) {
                    continue;
                }
                $qRaw = trim(substr($param, 2));
                if (is_numeric($qRaw)) {
                    $q = (float)$qRaw;
                }
                break;
            }
        }

        if (($token === $encoding || $token === '*') && $q > 0.0) {
            return true;
        }
    }

    return false;
}

/**
 * Detect if zlib.output_compression is already active.
 */
function sogerien_is_zlib_output_compression_enabled(): bool
{
    $raw = ini_get('zlib.output_compression');
    if ($raw === false) {
        return false;
    }

    $value = strtolower(trim((string)$raw));
    if ($value === '' || $value === '0' || $value === 'off' || $value === 'false' || $value === 'no') {
        return false;
    }

    return true;
}

/**
 * Pick best available encoding for this request.
 */
function sogerien_pick_output_encoding(): string
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return '';
    }

    if (sogerien_is_zlib_output_compression_enabled()) {
        return '';
    }

    $acceptEncoding = (string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
    if ($acceptEncoding === '') {
        return '';
    }

    if (function_exists('brotli_compress') && sogerien_accepts_encoding('br', $acceptEncoding)) {
        return 'br';
    }

    if (function_exists('gzencode') && sogerien_accepts_encoding('gzip', $acceptEncoding)) {
        return 'gzip';
    }

    return '';
}

/**
 * Detect if current response content type is safe and useful to compress.
 */
function sogerien_is_compressible_content_type(): bool
{
    $contentType = '';
    foreach (headers_list() as $headerLine) {
        if (stripos($headerLine, 'Content-Type:') !== 0) {
            continue;
        }
        $contentType = trim(substr($headerLine, strlen('Content-Type:')));
        break;
    }

    if ($contentType === '') {
        return true;
    }

    $contentType = strtolower(trim(explode(';', $contentType, 2)[0]));
    if ($contentType === '') {
        return true;
    }

    if (str_starts_with($contentType, 'text/')) {
        return true;
    }

    $allowed = [
        'application/json',
        'application/javascript',
        'application/x-javascript',
        'application/xml',
        'application/xhtml+xml',
        'image/svg+xml',
    ];

    return in_array($contentType, $allowed, true);
}

/**
 * Top-level output compression handler.
 */
function sogerien_output_compression_handler(string $buffer, int $phase = 0): string
{
    if ($buffer === '') {
        return $buffer;
    }

    // For ob_end_clean() phase do not mutate headers/body.
    if (($phase & PHP_OUTPUT_HANDLER_CLEAN) === PHP_OUTPUT_HANDLER_CLEAN) {
        return $buffer;
    }

    if (headers_sent()) {
        return $buffer;
    }

    $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
    if ($requestMethod === 'HEAD') {
        return $buffer;
    }

    $statusCode = http_response_code();
    if (in_array($statusCode, [204, 205, 304], true)) {
        return $buffer;
    }

    if (strlen($buffer) < 1024) {
        return $buffer;
    }

    if (!sogerien_is_compressible_content_type()) {
        return $buffer;
    }

    foreach (headers_list() as $headerLine) {
        if (stripos($headerLine, 'Content-Encoding:') === 0) {
            return $buffer;
        }
    }

    $encoding = (string)($GLOBALS['_sogerien_output_encoding'] ?? '');
    if ($encoding === '') {
        return $buffer;
    }

    $compressed = false;
    if ($encoding === 'br' && function_exists('brotli_compress')) {
        $brotliMode = defined('BROTLI_GENERIC') ? constant('BROTLI_GENERIC') : 0;
        $compressed = brotli_compress($buffer, 5, $brotliMode);
    } elseif ($encoding === 'gzip' && function_exists('gzencode')) {
        $compressed = gzencode($buffer, 6, FORCE_GZIP);
    }

    if (!is_string($compressed) || $compressed === '' || strlen($compressed) >= strlen($buffer)) {
        return $buffer;
    }

    header('Vary: Accept-Encoding', false);
    header('Content-Encoding: ' . $encoding);
    if (function_exists('header_remove')) {
        header_remove('Content-Length');
    }

    return $compressed;
}

/**
 * Start main output buffer with optional br/gzip compression.
 */
function sogerien_start_output_buffer(): void
{
    $encoding = sogerien_pick_output_encoding();
    $GLOBALS['_sogerien_output_encoding'] = $encoding;

    if ($encoding !== '') {
        ob_start('sogerien_output_compression_handler');
        return;
    }

    ob_start();
}

sogerien_start_output_buffer(); // ВКЛЮЧАЕМ БУФЕР САМОЙ ПЕРВОЙ СТРОКОЙ

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$hs = static fn() => headers_sent();
$safe_code = static fn(int $c) => $hs() ? null : http_response_code($c);
$safe_header = static fn(string $h) => $hs() ? null : header($h);
$esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Абсолютный путь к ядру Sogerien - подключается откуда угодно */

if (!is_dir(__DIR__ . '/classes')) {
    throw new RuntimeException('SOGERIEN_ROOT/classes not found: ' . __DIR__ . '/classes');
}

$selfUpdaterPath = __DIR__ . '/self_update.php';
if (is_file($selfUpdaterPath)) {
    require_once $selfUpdaterPath;
    if (function_exists('sogerien_self_update_maybe_run')) {
        sogerien_self_update_maybe_run();
    }
}

/**
 * Core-контейнер объявляем РАНО, чтобы show_errors был доступен обработчикам.
 */

final class Sogerien
{
    private static bool $done = false;

    public static string $patch_to_cookies_keyFile = '';
    public static string $patch_to_cache_File = '';

    /** show_errors=true - показываем 500/trace и не режем вывод; false - тихо */
    public static bool $show_errors = true;
    public static bool $debag = false;
    public static array $debag_array = [];
    public static string $SOGERIEN_DIR = __DIR__;

    /* ===== DI ===== */
    private static ?Users $Users = null;
    private static ?AccessCheck $AccessCheck = null;
    private static ?AccessToken $AccessToken = null;
    private static ?AEAD $AEAD = null;
    private static ?API $API = null;
    private static ?APIStripe $APIStripe = null;
    private static ?DbController $DbController = null;
    private static ?Forms $Forms = null;
    private static ?InputRequest $InputRequest = null;
    private static ?Template $Template = null;
    private static ?Routes $Routes = null;
    private static ?SmtpMailer $SmtpMailer = null;
    private static ?TableRenderer $TableRenderer = null;
    private static ?TableRendererCache $TableRendererCache = null;
    private static ?Cache $Cache = null;
    private static ?Roles $Roles = null;
    private static ?ECharts $ECharts = null;
    private static ?Debager $Debager = null;
    private static ?Lang $Lang = null;
    private static ?ProxyCatalogCache $ProxyCatalogCache = null;

    public static function Users(): Users                      { return self::$Users         ??= new Users(); }
    public static function AccessCheck(): AccessCheck          { return self::$AccessCheck   ??= new AccessCheck(); }
    public static function AccessToken(): AccessToken          { return self::$AccessToken   ??= new AccessToken(); }
    public static function AEAD(): AEAD                        { return self::$AEAD          ??= new AEAD(); }
    public static function API(): API                          { return self::$API           ??= new API(); }
    public static function APIStripe(): APIStripe              { return self::$APIStripe     ??= new APIStripe(); }
    public static function DbController(): DbController        { return self::$DbController  ??= new DbController(); }
    public static function Forms(): Forms                      { return self::$Forms         ??= new Forms(); }
    public static function InputRequest(): InputRequest        { return self::$InputRequest  ??= new InputRequest(); }
    public static function Template(): Template                { return self::$Template      ??= new Template(); }
    public static function Page(): Template                    { return self::Template(); }
    public static function Routes(): Routes                    { return self::$Routes        ??= new Routes(); }
    public static function SmtpMailer(): SmtpMailer            { return self::$SmtpMailer    ??= new SmtpMailer(); }
    public static function TableRenderer(): TableRenderer      { return self::$TableRenderer ??= new TableRenderer(); }
    public static function TableRendererCache(): TableRendererCache { return self::$TableRendererCache ??= new TableRendererCache(); }
    public static function Cache(): Cache                      { return self::$Cache         ??= new Cache(); }
    public static function Roles(): Roles                      { return self::$Roles         ??= new Roles(); }
    public static function ECharts(): ECharts                  { return self::$ECharts       ??= new ECharts(); }
    public static function Debager(): Debager                  { return self::$Debager       ??= new Debager(); }
    public static function Lang(): Lang                        { return self::$Lang          ??= new Lang(); }
    public static function ProxyCatalogCache(): ProxyCatalogCache { return self::$ProxyCatalogCache ??= new ProxyCatalogCache(); }

    public static function markDone(): void { self::$done = true; }
    public static function isDone(): bool   { return self::$done; }

    /**
     * Жёсткая остановка в любой точке.
     * - show_errors=false: чистая остановка (буфер чистим, ничего не показываем)
     * - show_errors=true : просто выходим, оставляя всё что уже выведено до exit()
     */
    public static function exit(): never
    {
        self::markDone();

        // Всегда выталкиваем накопленный вывод наружу
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        // На всякий случай принудительно отправляем в браузер
        @flush();

        // Если PHP-FPM - это гарантированно “досылает” ответ клиенту
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        exit;
    }

    /*
     * останавливает но без вывода в браузер чего либо
    */
    public static function stop_silent(int $code = 204): never
    {
        self::markDone();

        // убрать весь вывод
        while (ob_get_level() > 0) { @ob_end_clean(); }

        // если заголовки не ушли - поставим код (204 = No Content)
        if (!headers_sent()) { http_response_code($code); }

        exit;
    }
}

/**
 * Унифицированный рендер 500
 * - show_errors=false: минимальный вывод (можно вообще пусто)
 * - show_errors=true : подробности + trace
 */
$render500 = static function (string $title, string $msg, ?array $trace = null) use ($safe_code, $safe_header, $esc): void {
    while (ob_get_level()) { @ob_end_clean(); }

    // In case compression handler set these headers before CLEAN.
    if (function_exists('header_remove')) {
        @header_remove('Content-Encoding');
        @header_remove('Content-Length');
    }

    $safe_code(500);
    $safe_header('Content-Type: text/html; charset=UTF-8');

    if (!Sogerien::$show_errors) {
        // вообще ничего не показываем
        exit;
    }

    $t = $esc($title);
    $m = $esc($msg);

    echo "<!doctype html><meta charset='utf-8'><title>500 - {$t}</title>
<style>
body{font:14px/1.5 ui-monospace,Consolas,monospace;padding:24px}
h1{margin:0 0 12px;font:600 20px system-ui}
pre{background:#f6f8fa;padding:12px;border-radius:8px;white-space:pre-wrap}
</style>
<h1>500 - {$t}</h1><pre>{$m}</pre>";

    if ($trace) {
        $lines = array_map(
            static fn($i, $n) => sprintf(
                "#%s %s(%s): %s%s%s",
                $n,
                $i['file'] ?? '[internal]',
                $i['line'] ?? '?',
                $i['class'] ?? '',
                $i['type'] ?? '',
                $i['function'] ?? ''
            ),
            $trace,
            array_keys($trace)
        );
        echo "<h1>Trace</h1><pre>".$esc(implode("\n", $lines))."</pre>";
    }

    exit;
};

/** Все ошибки - исключения */
set_error_handler(
/**
 * @throws ErrorException
 */
    static function (int $sev, string $msg, string $file = '', int $line = 0) {
        if (!(error_reporting() & $sev)) return false;
        throw new ErrorException($msg, 0, $sev, $file, $line);
    }
);

/** Непойманные исключения */
set_exception_handler(static function (Throwable $e) use ($render500) {
    $msg = $e->getMessage() . "\n\n" . $e->getFile() . ":" . $e->getLine();
    $render500('Unhandled Exception', $msg, $e->getTrace());
});

/** Фаталы + финализация */
register_shutdown_function(static function () use ($render500) {

    // 1) Фаталы
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $render500('Fatal Error', $e['message']."\n\n".$e['file'].":".$e['line']);
        return;
    }

    // 2) Обычный конец
    if (Sogerien::$show_errors) {
        if (ob_get_length() !== false) { @ob_end_flush(); }
    } else {
        if (ob_get_length() !== false) { @ob_end_clean(); }
    }
    // 3) Если забыли markDone()
});

/** !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! END Bootstrap ядра: единый контроль вывода и ошибок !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! */


/** =================================== START Автозагрузка классов ==================== */
spl_autoload_register(static function (string $class): void {

    // защита от мусора / traversal
    if ($class === '' || strpbrk($class, "/\\\0") !== false) {
        echo 'Autoload error - invalid class name: ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        exit;
    }

    $path = __DIR__ . '/classes/' . $class . '.php';

    if (!is_file($path)) {
        echo 'Autoload error - class file not found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        exit;
    }

    require_once $path;
});
/** =========================================== END Автозагрузка классов =============== */



////пример использования
//
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
//error_reporting(E_ALL);
//
//$path = '/var/www/proxymint_co_usr/data/www/proxymint.com/sogerien/Sogerien.php';
//
//if (!is_file($path)) { http_response_code(500); echo "BOOT FAIL - no file: {$path}"; exit; }
//if (!is_readable($path)) { http_response_code(500); echo "BOOT FAIL - not readable: {$path}"; exit; }
//
//require_once $path;
//
//Sogerien::$show_errors = true;
//
///**
// * FRONT DB
// */
//Sogerien::DbController()->DbConfig->DB_HOST    = 'localhost';
//Sogerien::DbController()->DbConfig->DB_PORT    = '5432';
//Sogerien::DbController()->DbConfig->DB_NAME    = 'db_proxymint_com';
//Sogerien::DbController()->DbConfig->DB_USER    = 'db_proxymin_usr';
//Sogerien::DbController()->DbConfig->DB_PASS    = 'your_db_password_here';
//Sogerien::DbController()->DbConfig->DB_CHARSET = 'utf8mb4';
//
//Sogerien::InputRequest()->domain = 'https://proxymint.com'; // явно указываем где хранится CSS и JS стили
//// держим активный коннект под алиасом 'front'
//Sogerien::DbController()->connect('front', Sogerien::DbController()->DbConfig);
//
//Sogerien::Routes()->add_template('test1', '/page/login_form.php');
//Sogerien::Routes()->add_template('test2', '/page/test1.php');
//Sogerien::Routes()->add_template('test3', '/page/test3.php');
//Sogerien::Routes()->add_template('test4', '/page/test4.php');
//
//Sogerien::Routes()->template(); // вызывается всегда, и базовые маршруты тоже работают
