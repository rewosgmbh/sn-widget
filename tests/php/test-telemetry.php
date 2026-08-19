<?php
/**
 * Standalone PHP tests for the Telemetry subsystem (server-side logic).
 *
 * WordPress core functions are stubbed so the pure validation, normalization,
 * visitor-key and aggregation-helper logic can be verified without a full
 * WordPress install. DB-dependent methods are not exercised here.
 *
 * Run: php tests/php/test-telemetry.php
 */

define('ABSPATH', '/tmp/');
define('SNW_URL', 'https://example.com/wp-content/plugins/steigerwald-news-widget/');
define('SNW_VERSION', '1.2.0');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

// --- Stubs for WordPress core functions -------------------------------
$GLOBALS['__options'] = array();

function absint($v) { return abs((int) $v); }
function wp_json_encode($v) { return json_encode($v); }
function rest_url($path = '') { return 'https://example.com/wp-json/' . ltrim($path, '/'); }
function home_url($path = '') { return 'https://example.com/' . ltrim($path, '/'); }
function esc_url_raw($url) { return preg_replace('/[^a-zA-Z0-9:\/?=&._-]/', '', (string) $url); }
function esc_url($url) { return $url; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function __($s, $d = '') { return $s; }
function wp_rand($min = 0, $max = 0) { return rand($min, $max ?: 1000); }
function current_time($t = 'mysql') { return gmdate('Y-m-d H:i:s'); }
function get_option($k, $d = false) { return isset($GLOBALS['__options'][$k]) ? $GLOBALS['__options'][$k] : $d; }
function update_option($k, $v, $a = true) { $GLOBALS['__options'][$k] = $v; return true; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_hash($data, $scheme = 'snw-telemetry') { return hash('sha256', $scheme . ':' . $data); }
function apply_filters($tag, $value) { return $value; }
function sanitize_email($e) { return filter_var($e, FILTER_SANITIZE_EMAIL); }
function is_email($e) { return filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }
function get_bloginfo($show = '') { return 'Test Site'; }

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public function __construct($code = '', $message = '', $data = array()) {
            $this->errors[$code] = $message;
        }
        public function get_error_message() { return reset($this->errors); }
    }
}
function is_wp_error($x) { return $x instanceof WP_Error; }

if (!defined('WP_REST_Server')) { define('WP_REST_Server', 'WP_REST_Server'); }

require_once __DIR__ . '/../../includes/Helpers.php';
require_once __DIR__ . '/../../includes/Telemetry.php';

// --- Test harness ----------------------------------------------------
$pass = 0; $fail = 0;
function snw_tassert($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS $name\n"; }
    else { $fail++; echo "  FAIL $name\n"; }
}

echo "SN News Widget — Telemetry logic tests\n";

// --- normalize_host ---
snw_tassert('host lowercase+strip port', SNW_Telemetry::normalize_host('Example.COM:8080') === 'example.com');
snw_tassert('host from url', SNW_Telemetry::normalize_host('https://www.ASV.de/path') === 'www.asv.de');
snw_tassert('host rejects invalid', SNW_Telemetry::normalize_host('not a host') === '');
snw_tassert('host rejects public ip', SNW_Telemetry::normalize_host('8.8.8.8') === '');
snw_tassert('host accepts loopback ip', SNW_Telemetry::normalize_host('127.0.0.1') === '127.0.0.1');
snw_tassert('host accepts localhost', SNW_Telemetry::normalize_host('localhost') === 'localhost');

// --- normalize_page_path ---
snw_tassert('path strips query', SNW_Telemetry::normalize_page_path('/fussball-news/?fbclid=123&utm_source=x') === '/fussball-news/');
snw_tassert('path strips fragment', SNW_Telemetry::normalize_page_path('/news/#section') === '/news/');
snw_tassert('path ensures leading slash', SNW_Telemetry::normalize_page_path('news') === '/news');
snw_tassert('path empty -> slash', SNW_Telemetry::normalize_page_path('') === '/');

// --- event types ---
snw_tassert('valid event accepted', SNW_Telemetry::sanitize_event_type('widget_load') === 'widget_load');
snw_tassert('unknown event rejected', SNW_Telemetry::sanitize_event_type('hack') === '');
snw_tassert('mixed case normalized', SNW_Telemetry::sanitize_event_type('VIEWABLE_Impression') === 'viewable_impression');

// --- coarse_ua / bot ---
snw_tassert('googlebot is bot', SNW_Telemetry::is_bot_ua('Mozilla/5.0 Googlebot/2.1') === true);
snw_tassert('curl is bot', SNW_Telemetry::is_bot_ua('curl/7.0') === true);
snw_tassert('chrome not bot', SNW_Telemetry::is_bot_ua('Mozilla/5.0 Chrome/120 Safari/537') === false);
snw_tassert('coarse chrome/win', SNW_Telemetry::coarse_ua('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537') === 'chrome/win');
snw_tassert('coarse firefox/mac', SNW_Telemetry::coarse_ua('Mozilla/5.0 (Macintosh; Intel Mac OS X) Firefox/121') === 'firefox/mac');
snw_tassert('coarse iphone', SNW_Telemetry::coarse_ua('Mozilla/5.0 (iPhone) Safari') === 'safari/ios');

// --- visitor key: same material -> same key, rotation -> different key ---
$mat1 = SNW_Telemetry::visitor_material('1.2.3.4', 'Chrome/Win', '2026-08-19');
$mat1b = SNW_Telemetry::visitor_material('1.2.3.4', 'Chrome/Win', '2026-08-19');
snw_tassert('visitor material stable', $mat1 === $mat1b);
$k1 = SNW_Telemetry::visitor_key('1.2.3.4', 'Chrome/Win', strtotime('2026-08-19 12:00:00 UTC'));
$k1same = SNW_Telemetry::visitor_key('1.2.3.4', 'Chrome/Win', strtotime('2026-08-19 23:00:00 UTC'));
snw_tassert('visitor key stable within rotation', $k1 === $k1same);
$k2 = SNW_Telemetry::visitor_key('9.9.9.9', 'Chrome/Win', strtotime('2026-08-19 12:00:00 UTC'));
snw_tassert('visitor key differs by ip', $k1 !== $k2);
$k3 = SNW_Telemetry::visitor_key('1.2.3.4', 'Chrome/Win', strtotime('2026-08-20 00:00:00 UTC'));
snw_tassert('visitor key rotates daily', $k1 !== $k3);
snw_tassert('visitor key is hex hash', (bool) preg_match('/^[a-f0-9]{64}$/', $k1));

// --- pct / avg ---
snw_tassert('pct basic', SNW_Telemetry::pct(10, 100) === 10.0);
snw_tassert('pct zero denom', SNW_Telemetry::pct(10, 0) === 0.0);
snw_tassert('avg basic', SNW_Telemetry::avg(200, 2) === 100.0);
snw_tassert('avg zero n', SNW_Telemetry::avg(200, 0) === 0.0);

// --- status thresholds ---
snw_tassert('status active recent', SNW_Telemetry::status_from_last_seen(gmdate('Y-m-d H:i:s', time() - 60)) === 'active');
snw_tassert('status idle', SNW_Telemetry::status_from_last_seen(gmdate('Y-m-d H:i:s', time() - 60*60*24*10)) === 'idle');
snw_tassert('status removed', SNW_Telemetry::status_from_last_seen(gmdate('Y-m-d H:i:s', time() - 60*60*24*40)) === 'removed');
snw_tassert('status unknown empty', SNW_Telemetry::status_from_last_seen('') === 'unknown');

// --- build_record validation ---
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120 Safari/537';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$_SERVER['HTTP_ORIGIN'] = '';
$_SERVER['HTTP_REFERER'] = '';

$rec = SNW_Telemetry::build_record(array(
    'event' => 'widget_load',
    'widget_id' => 'SNW-8F3K2',
    'partner' => 'asv-sassanfahrt',
    'host' => 'www.asv-sassanfahrt.de',
    'page_path' => '/fussball-news/?fbclid=1',
    'widget_version' => '1.2.0',
    'layout' => 'list',
    'mode' => 'category',
    'performance' => array('rest_ms' => 183, 'render_ms' => 12),
));
snw_tassert('build_record valid widget_load', !is_wp_error($rec) && $rec['event_type'] === 'widget_load');
snw_tassert('build_record normalizes path', $rec['page_path'] === '/fussball-news/');
snw_tassert('build_record performance', $rec['rest_ms'] === 183 && $rec['render_ms'] === 12);
snw_tassert('build_record visitor key set', (bool) preg_match('/^[a-f0-9]{64}$/', $rec['visitor_key']));
snw_tassert('build_record not bot', $rec['is_bot'] === 0);

$bad = SNW_Telemetry::build_record(array('event' => 'widget_load', 'widget_id' => 'BADID'));
snw_tassert('build_record invalid widget id rejected', is_wp_error($bad));

$empty = SNW_Telemetry::build_record(array('event' => 'widget_load', 'widget_id' => '', 'host' => 'h.de', 'page_path' => '/'));
snw_tassert('build_record empty widget id allowed', !is_wp_error($empty) && $empty['widget_id'] === '');

$unk = SNW_Telemetry::build_record(array('event' => 'evil'));
snw_tassert('build_record unknown event rejected', is_wp_error($unk));

$click = SNW_Telemetry::build_record(array('event' => 'article_click', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de', 'article_id' => 456));
snw_tassert('build_record article_click ok', !is_wp_error($click) && $click['article_id'] === 456);

$clickbad = SNW_Telemetry::build_record(array('event' => 'article_click', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de'));
snw_tassert('build_record article_click missing id rejected', is_wp_error($clickbad));

$botrec = SNW_Telemetry::build_record(array('event' => 'widget_load', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de', 'page_path' => '/'));
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
$botrec2 = SNW_Telemetry::build_record(array('event' => 'widget_load', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de', 'page_path' => '/'));
snw_tassert('build_record bot flagged', $botrec2['is_bot'] === 1);
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120 Safari/537';

$err = SNW_Telemetry::build_record(array('event' => 'widget_error', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de', 'error_code' => 'REST_429'));
snw_tassert('build_record error code mapped', !is_wp_error($err) && $err['error_code'] === 'REST_429');
$errbad = SNW_Telemetry::build_record(array('event' => 'widget_error', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de', 'error_code' => 'SQL_INJECT'));
snw_tassert('build_record invalid error code -> UNKNOWN', !is_wp_error($errbad) && $errbad['error_code'] === 'UNKNOWN');

$big = SNW_Telemetry::build_record(array('event' => 'widget_load', 'widget_id' => 'SNW-8F3K2', 'host' => 'h.de', 'performance' => array('rest_ms' => 999999)));
snw_tassert('build_record caps rest_ms', $big['rest_ms'] === 60000);

echo "\n" . $pass . " passed, " . $fail . " failed.\n";
exit($fail > 0 ? 1 : 0);
