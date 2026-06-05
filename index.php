<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$corePath = __DIR__ . '/Sogerien.php';
if (!is_file($corePath)) {
    $corePath = __DIR__ . '/sogerien/Sogerien.php';
}
if (!is_file($corePath)) {
    http_response_code(500);
    echo 'BOOT FAIL - Sogerien.php not found';
    exit;
}

require_once $corePath;

$configPath = __DIR__ . '/config/local.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/config/config.example.php';
}
$config = require $configPath;

Sogerien::$show_errors = (bool)($config['app']['show_errors'] ?? true);
Sogerien::InputRequest()->sogerien_domain = (string)($config['app']['sogerien_domain'] ?? '');
Sogerien::InputRequest()->domain = (string)($config['app']['domain'] ?? '');

$dbAlias = (string)($config['db']['alias'] ?? 'front');
Sogerien::DbController()->DbConfig->DB_HOST = (string)($config['db']['host'] ?? '127.0.0.1');
Sogerien::DbController()->DbConfig->DB_PORT = (string)($config['db']['port'] ?? '5432');
Sogerien::DbController()->DbConfig->DB_NAME = (string)($config['db']['name'] ?? 'sogerien');
Sogerien::DbController()->DbConfig->DB_USER = (string)($config['db']['user'] ?? 'sogerien');
Sogerien::DbController()->DbConfig->DB_PASS = (string)($config['db']['pass'] ?? '');
Sogerien::DbController()->DbConfig->DB_CHARSET = (string)($config['db']['charset'] ?? 'utf8mb4');
Sogerien::DbController()->connect($dbAlias, Sogerien::DbController()->DbConfig);

$keysDir = (string)($config['app']['keys_dir'] ?? (__DIR__ . '/runtime/keys'));
$cacheDir = rtrim((string)($config['app']['cache_dir'] ?? (__DIR__ . '/runtime/cache')), '/\\') . DIRECTORY_SEPARATOR;
if (!is_dir($keysDir)) {
    mkdir($keysDir, 0775, true);
}
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}
$cookiesKey = $keysDir . DIRECTORY_SEPARATOR . 'cookies.key';
if (!is_file($cookiesKey)) {
    Sogerien::AccessToken()->create_key($keysDir, 'cookies.key');
}
Sogerien::$patch_to_cookies_keyFile = $cookiesKey;
Sogerien::$patch_to_cache_File = $cacheDir;

$smtp = Sogerien::SmtpMailer();
$smtp->host = (string)($config['smtp']['host'] ?? '');
$smtp->port = (int)($config['smtp']['port'] ?? 587);
$smtp->encryption = (string)($config['smtp']['encryption'] ?? 'tls');
$smtp->setAuth((string)($config['smtp']['user'] ?? ''), (string)($config['smtp']['pass'] ?? ''));

$infaticaCfg = $config['apis']['infatica'] ?? [];
$infatica = Sogerien::API()->InfaticaIo();
$infatica->set_base_url((string)($infaticaCfg['base_url'] ?? 'https://api.infatica.io'));
$infatica->set_client_base_url((string)($infaticaCfg['client_base_url'] ?? ''));
$infatica->set_scraper_base_url((string)($infaticaCfg['scraper_base_url'] ?? ''));
$infatica->set_api_key((string)($infaticaCfg['api_key'] ?? ''));
$infatica->set_residential_api_key((string)($infaticaCfg['residential_api_key'] ?? ''));
$infatica->set_mobile_api_key((string)($infaticaCfg['mobile_api_key'] ?? ''));
$infatica->set_isp_api_key((string)($infaticaCfg['isp_api_key'] ?? ''));
$infatica->set_dc_api_key((string)($infaticaCfg['dc_api_key'] ?? ''));
$infatica->set_scraper_api_key((string)($infaticaCfg['scraper_api_key'] ?? ''));
$infatica->set_client_auth((string)($infaticaCfg['client_login'] ?? ''), (string)($infaticaCfg['client_password'] ?? ''));

$stripeCfg = $config['apis']['stripe'] ?? [];
Sogerien::API()->Stripe()->set_api_key((string)($stripeCfg['secret_key'] ?? ''));

$googleCfg = $config['apis']['google_oauth'] ?? [];
Sogerien::API()->GoogleOAuth()->set_credentials(
    (string)($googleCfg['client_id'] ?? ''),
    (string)($googleCfg['client_secret'] ?? ''),
    (string)($googleCfg['redirect_uri'] ?? '')
);

Sogerien::AccessCheck()->init_db_alias($dbAlias);

$routes = Sogerien::Routes();
$routes->add_template('', '/page/admin_panel/universal_elements.php');
$routes->add_template('elements', '/page/admin_panel/universal_elements.php');
$routes->add_template('login', '/page/admin_panel/page_login_form.php');
$routes->template();
