<?php
/**
 * Standalone PHP tests for the global branding setting (admin configuration)
 * and how it merges into widget configs.
 *
 * WordPress core functions are stubbed so the logic can be verified without a
 * full WordPress install. Run: php tests/php/test-branding.php
 */

define('ABSPATH', '/tmp/');
define('SNW_URL', 'https://example.com/wp-content/plugins/steigerwald-news-widget/');
define('SNW_VERSION', '1.6.0');

// --- Stubs for WordPress core functions -------------------------------
$GLOBALS['__options'] = array();

function absint($v) { return abs((int) $v); }
function wp_json_encode($v) { return json_encode($v); }
function rest_url($path = '') { return 'https://example.com/wp-json/' . ltrim($path, '/'); }
function get_bloginfo($show = '') { return 'Test Site'; }
function home_url($path = '') { return 'https://example.com/' . ltrim($path, '/'); }
function esc_url_raw($url) { return preg_replace('/[^a-zA-Z0-9:\/?=&._-]/', '', (string) $url); }
function esc_url($url) { return $url; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function __($s, $d = '') { return $s; }
function wp_rand($min = 0, $max = 0) { return rand($min, $max ?: 1000); }
function current_time($t = 'mysql') { return date('Y-m-d H:i:s'); }
function get_option($k, $d = false) { return isset($GLOBALS['__options'][$k]) ? $GLOBALS['__options'][$k] : $d; }
function update_option($k, $v, $a = true) { $GLOBALS['__options'][$k] = $v; return true; }
function wp_parse_args($args, $defaults) {
    if (!is_array($args)) { $args = array(); }
    if (!is_array($defaults)) { $defaults = array(); }
    return array_merge($defaults, $args);
}

// --- Test harness ----------------------------------------------------
$pass = 0; $fail = 0;
function snw_assert($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS $name\n"; }
    else { $fail++; echo "  FAIL $name\n"; }
}

require_once __DIR__ . '/../../includes/Helpers.php';
require_once __DIR__ . '/../../includes/EmbedGenerator.php';
require_once __DIR__ . '/../../includes/Presets.php';
require_once __DIR__ . '/../../includes/Requests.php';
require_once __DIR__ . '/../../includes/Settings.php';

echo "Steigerwald-News Widget — branding tests\n";

// --- default_branding ---
$def = SNW_Settings::default_branding();
snw_assert('default image empty (-> favicon.ico)', $def['image'] === '');
snw_assert('default image_size 32', $def['image_size'] === 32);
snw_assert('default text Nachrichten von', $def['text'] === 'Nachrichten von');
snw_assert('default name empty (-> site title)', $def['name'] === '');
snw_assert('default link empty (-> home)', $def['link'] === '');

// --- default_config branding block ---
$dc = SNW_Helpers::default_config();
snw_assert('default_config branding present', isset($dc['branding']) && is_array($dc['branding']));
snw_assert('default_config branding size 32', $dc['branding']['image_size'] === 32);

// --- sanitize_config branding ---
$raw = array(
    'branding' => array(
        'image' => 'https://example.com/logo.png',
        'image_size' => 9999,
        'text' => '  <b>News</b> von ',
        'name' => '  Mein Name ',
        'link' => 'https://example.com/start',
    ),
);
$san = SNW_Helpers::sanitize_config($raw);
snw_assert('sanitize branding image kept', $san['branding']['image'] === 'https://example.com/logo.png');
snw_assert('sanitize branding image_size clamped to 256', $san['branding']['image_size'] === 256);
snw_assert('sanitize branding text strips tags', $san['branding']['text'] === 'News von');
snw_assert('sanitize branding name trimmed', $san['branding']['name'] === 'Mein Name');
snw_assert('sanitize branding link kept', $san['branding']['link'] === 'https://example.com/start');

// --- save_branding + get_branding roundtrip ---
SNW_Settings::save_branding(array(
    'name' => 'Steigerwald-News',
    'image' => 'https://example.com/w.png',
    'image_size' => 40,
    'text' => 'Aktuelles von',
    'link' => 'https://example.com/',
));
$g = SNW_Settings::get_branding();
snw_assert('saved name read back', $g['name'] === 'Steigerwald-News');
snw_assert('saved image read back', $g['image'] === 'https://example.com/w.png');
snw_assert('saved size read back', $g['image_size'] === 40);
snw_assert('saved text read back', $g['text'] === 'Aktuelles von');
snw_assert('saved link read back', $g['link'] === 'https://example.com/');
snw_assert('unsaved key falls back to default', $g['image_size'] === 40); // set; sanity

// --- save_branding clamps size upper bound ---
SNW_Settings::save_branding(array('image_size' => 9999));
snw_assert('save clamps size to 256', SNW_Settings::get_branding()['image_size'] === 256);

// --- apply_branding fills missing from global, respects existing ---
SNW_Settings::save_branding(array(
    'name' => 'Global Name',
    'text' => 'Global Text',
    'image' => 'https://example.com/g.png',
    'image_size' => 48,
    'link' => 'https://example.com/global',
));
$blank = SNW_Helpers::apply_branding(array());
snw_assert('apply fills name from global', $blank['branding']['name'] === 'Global Name');
snw_assert('apply fills text from global', $blank['branding']['text'] === 'Global Text');
snw_assert('apply fills image from global', $blank['branding']['image'] === 'https://example.com/g.png');
snw_assert('apply fills size from global', $blank['branding']['image_size'] === 48);
snw_assert('apply fills link from global', $blank['branding']['link'] === 'https://example.com/global');

$over = SNW_Helpers::apply_branding(array('branding' => array('name' => 'Widget Name', 'text' => 'Widget Text')));
snw_assert('apply respects existing name', $over['branding']['name'] === 'Widget Name');
snw_assert('apply respects existing text', $over['branding']['text'] === 'Widget Text');
snw_assert('apply fills missing image from global', $over['branding']['image'] === 'https://example.com/g.png');

echo "\n$pass assertions passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
