<?php
/**
 * Standalone PHP tests for partner requests, domain locking and rate limiting.
 *
 * WordPress core functions are stubbed so the logic can be verified without a
 * full WordPress install. Run: php tests/php/test-requests.php
 */

define('ABSPATH', '/tmp/');
define('SNW_URL', 'https://example.com/wp-content/plugins/steigerwald-news-widget/');
define('SNW_VERSION', '1.3.0');
define('HOUR_IN_SECONDS', 3600);

// --- Stubs for WordPress core functions -------------------------------
$GLOBALS['__options'] = array();
$GLOBALS['__transients'] = array();

function absint($v) { return abs((int) $v); }
function wp_json_encode($v) { return json_encode($v); }
function rest_url($path = '') { return 'https://example.com/wp-json/' . ltrim($path, '/'); }
function get_bloginfo($show = '') { return 'Test Site'; }
function home_url($path = '') { return 'https://example.com/' . ltrim($path, '/'); }
function esc_url_raw($url) { return preg_replace('/[^a-zA-Z0-9:\/?=&._-]/', '', (string) $url); }
function esc_url($url) { return $url; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_email($s) {
    $s = trim((string) $s);
    if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $s)) { return $s; }
    return '';
}
function is_email($s) { return '' !== sanitize_email($s); }
function __($s, $d = '') { return $s; }
function wp_rand($min = 0, $max = 0) { return rand($min, $max ?: 1000); }
function current_time($t = 'mysql') { return date('Y-m-d H:i:s'); }
function get_option($k, $d = false) { return isset($GLOBALS['__options'][$k]) ? $GLOBALS['__options'][$k] : $d; }
function update_option($k, $v, $a = true) { $GLOBALS['__options'][$k] = $v; return true; }
function wp_parse_url($url, $component = -1) {
    $p = parse_url($url);
    if ($component === PHP_URL_HOST) { return isset($p['host']) ? $p['host'] : ''; }
    return $p;
}
function get_transient($k) { return isset($GLOBALS['__transients'][$k]) ? $GLOBALS['__transients'][$k] : false; }
function set_transient($k, $v, $e = 0) { $GLOBALS['__transients'][$k] = $v; return true; }
function wp_insert_post($a, $return_error = false) { return 123; }
function wp_list_pluck($list, $field) { return array_map(function ($e) use ($field) { return is_object($e) ? $e->$field : $e[$field]; }, $list); }
function get_post($id) { return (object) array('post_status' => 'publish'); }
function get_permalink($id) { return 'https://example.com/widget/new'; }
function register_rest_route() { return true; }
function sanitize_title($s) { return strtolower(preg_replace('/[^a-z0-9-]+/i', '-', (string) $s)); }

// --- Test harness ----------------------------------------------------
$pass = 0; $fail = 0;
function snw_assert($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS $name\n"; }
    else { $fail++; echo "  FAIL $name\n"; }
}

require_once __DIR__ . '/../../includes/Helpers.php';
require_once __DIR__ . '/../../includes/Presets.php';
require_once __DIR__ . '/../../includes/Requests.php';
require_once __DIR__ . '/../../includes/Rest.php';

echo "Steigerwald-News Widget — requests / domain / rate-limit tests\n";

// --- sanitize_domain ---
snw_assert('sanitize_domain bare host', SNW_Helpers::sanitize_domain('Example.com') === 'example.com');
snw_assert('sanitize_domain strips port', SNW_Helpers::sanitize_domain('example.com:8080') === 'example.com');
snw_assert('sanitize_domain from url', SNW_Helpers::sanitize_domain('https://www.Example.com/path') === 'www.example.com');
snw_assert('sanitize_domain rejects junk', SNW_Helpers::sanitize_domain('not a domain') === '');

// --- domain_allowed ---
snw_assert('domain_allowed exact', SNW_Helpers::domain_allowed('example.com', 'example.com') === true);
snw_assert('domain_allowed subdomain', SNW_Helpers::domain_allowed('example.com', 'news.example.com') === true);
snw_assert('domain_allowed other', SNW_Helpers::domain_allowed('example.com', 'evil.com') === false);
snw_assert('domain_allowed sibling', SNW_Helpers::domain_allowed('example.com', 'notexample.com') === false);
snw_assert('domain_allowed empty', SNW_Helpers::domain_allowed('', 'example.com') === false);

// --- Requests store ---
$rid = SNW_Requests::add('Max Mustermann', 'max@example.com', 'partner.example', SNW_Helpers::default_config());
snw_assert('request added', $rid !== '');
$req = SNW_Requests::get($rid);
snw_assert('request retrievable', $req && $req['email'] === 'max@example.com');
snw_assert('request domain sanitized', $req['domain'] === 'partner.example');
snw_assert('request status pending', $req['status'] === 'pending');

$all = SNW_Requests::get_all();
snw_assert('request in list', count($all) === 1);

SNW_Requests::update($rid, array('status' => 'accepted', 'preset_id' => 'SNW-TEST1'));
snw_assert('request updated', SNW_Requests::get($rid)['status'] === 'accepted');

snw_assert('request delete', SNW_Requests::delete($rid) === true);
snw_assert('request gone', SNW_Requests::get($rid) === null);

// --- Presets meta (domain lock) ---
$preset = SNW_Presets::save('Locked Widget', SNW_Helpers::default_config());
snw_assert('preset saved', !empty($preset['id']));
$pk = $preset['id'];
snw_assert('preset meta set', SNW_Presets::save_meta($pk, array('allowed_domain' => 'locked.example', 'email' => 'a@b.com', 'source' => 'request')) !== false);
$again = SNW_Presets::get($pk);
snw_assert('preset has allowed_domain', isset($again['allowed_domain']) && $again['allowed_domain'] === 'locked.example');
snw_assert('preset email sanitized', isset($again['email']) && $again['email'] === 'a@b.com');

// --- Rate limit ---
$GLOBALS['__transients'] = array(); // reset
$blocked = false;
for ($i = 1; $i <= 12; $i++) {
    if (SNW_Requests::rate_limited('1.2.3.4')) { $blocked = true; break; }
}
snw_assert('rate limit blocks after 10', $blocked === true);
snw_assert('rate limit allows first 10', SNW_Requests::rate_limited('9.9.9.9') === false);

// --- Rest helpers ---
snw_assert('Rest host_from_url', SNW_REST::host_from_url('https://News.Example.com:8080/x') === 'news.example.com');
snw_assert('Rest host_from_url empty', SNW_REST::host_from_url('') === '');
$_SERVER['REMOTE_ADDR'] = '5.6.7.8';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 2.2.2.2';
snw_assert('Rest client_ip honors proxy', SNW_REST::client_ip() === '1.1.1.1');

// --- 1 code per e-mail (partner dedupe) ---
function snw_simulate_accept($email, $domain) {
    $existing = SNW_Presets::find_by_email($email);
    if ($existing) {
        SNW_Presets::save_meta($existing['id'], array('allowed_domain' => $domain, 'email' => $email, 'source' => 'request'));
        return $existing['id'];
    }
    $p = SNW_Presets::save($email, SNW_Helpers::default_config());
    SNW_Presets::save_meta($p['id'], array('allowed_domain' => $domain, 'email' => $email, 'source' => 'request'));
    return $p['id'];
}
$code1 = snw_simulate_accept('partner@dup.com', 'a.example');
$code2 = snw_simulate_accept('partner@dup.com', 'b.example');
snw_assert('same e-mail reuses code', $code1 === $code2);
$count_dup = 0;
foreach (SNW_Presets::get_all() as $p) {
    if (isset($p['email']) && $p['email'] === 'partner@dup.com') { $count_dup++; }
}
snw_assert('no duplicate preset per e-mail', $count_dup === 1);
snw_assert('reused code keeps latest domain', SNW_Presets::get($code1)['allowed_domain'] === 'b.example');

echo "\n" . ($fail === 0 ? 'ALL PASS' : $fail . ' FAILED') . " — $pass assertions passed.\n";
exit($fail === 0 ? 0 : 1);
