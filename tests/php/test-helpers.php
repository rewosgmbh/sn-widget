<?php
/**
 * Standalone PHP tests for the plugin's server-side logic.
 *
 * WordPress core functions are stubbed so the sanitization, embed generation
 * and preset storage logic can be verified without a full WordPress install.
 *
 * Run: php tests/php/test-helpers.php
 */

define('ABSPATH', '/tmp/');
define('SNW_URL', 'https://example.com/wp-content/plugins/steigerwald-news-widget/');
define('SNW_VERSION', '1.1.1');

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
function wp_list_pluck($list, $field) { return array_map(function ($e) use ($field) { return is_object($e) ? $e->$field : $e[$field]; }, $list); }

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

echo "Steigerwald-News Widget — PHP logic tests\n";

// --- default_config ---
$def = SNW_Helpers::default_config();
snw_assert('default_config has api', !empty($def['api']));
snw_assert('default_config limit=5', $def['limit'] === 5);
snw_assert('default_config teaser=180', $def['teaser'] === 180);
snw_assert('default_config show.image true', $def['show']['image'] === true);
snw_assert('default_config layout list', $def['layout'] === 'list');

// --- sanitize_config: drops unknown keys / enums ---
$raw = array(
    'mode' => 'INVALID_MODE',
    'limit' => 999,
    'teaser' => 0,
    'partner' => 'ASV!@#Sass 123',
    'evil' => 'should-be-dropped',
    'show' => array('image' => 'yes', 'date' => false),
    'design' => array('accent' => 'not-a-color', 'radius' => 999, 'typography' => 'comic'),
    'category' => array('x', 5, -3, 12),
);
$san = SNW_Helpers::sanitize_config($raw);
snw_assert('sanitize drops unknown key', !isset($san['evil']));
snw_assert('sanitize invalid mode -> latest', $san['mode'] === 'latest');
snw_assert('sanitize limit clamped to 20', $san['limit'] === 20);
snw_assert('sanitize teaser 0 preserved (0 is a valid in-range value)', $san['teaser'] === 0);
snw_assert('sanitize partner alnum only', $san['partner'] === 'asvsass123');
snw_assert('sanitize show.image truthy -> true', $san['show']['image'] === true);
snw_assert('sanitize show.date false kept', $san['show']['date'] === false);
snw_assert('sanitize invalid color dropped', $san['design']['accent'] === '');
snw_assert('sanitize radius clamped to 32', $san['design']['radius'] === 32);
snw_assert('sanitize invalid typography -> host', $san['design']['typography'] === 'host');
snw_assert('sanitize id list drops non-int', $san['category'] === array(5, 12));

// --- sanitize_config: valid passthrough ---
$raw2 = array(
    'mode' => 'hybrid', 'limit' => 7, 'teaser' => 120, 'partner' => 'asv-x',
    'layout' => 'cards', 'sort' => 'title',
    'design' => array('accent' => '#ff0000', 'radius' => 12, 'typography' => 'arial', 'spacing' => 'spacious'),
    'show' => array('image' => false, 'category' => true),
);
$san2 = SNW_Helpers::sanitize_config($raw2);
snw_assert('sanitize valid mode kept', $san2['mode'] === 'hybrid');
snw_assert('sanitize valid limit kept', $san2['limit'] === 7);
snw_assert('sanitize valid layout kept', $san2['layout'] === 'cards');
snw_assert('sanitize valid accent kept', $san2['design']['accent'] === '#ff0000');
snw_assert('sanitize valid spacing kept', $san2['design']['spacing'] === 'spacious');
snw_assert('sanitize valid show.category kept', $san2['show']['category'] === true);

// --- widget id generation ---
$wid = SNW_Helpers::generate_widget_id();
snw_assert('widget id format', preg_match('/^SNW-[A-Z0-9]{5}$/', $wid) === 1);
$wid2 = SNW_Helpers::generate_widget_id(array($wid));
snw_assert('widget id unique vs existing', $wid2 !== $wid && preg_match('/^SNW-[A-Z0-9]{5}$/', $wid2) === 1);

// --- embed encode / decode roundtrip ---
$cfg = SNW_Helpers::default_config();
$cfg['title'] = 'Über Grüße 🚀';
$cfg['partner'] = 'asv';
$enc = SNW_Embed_Generator::encode_config($cfg);
snw_assert('encode is url-safe', !preg_match('/[+=]/', $enc));
$dec = SNW_Embed_Generator::decode_config($enc);
snw_assert('decode roundtrip', $dec['title'] === 'Über Grüße 🚀' && $dec['partner'] === 'asv');
snw_assert('decode invalid -> null', SNW_Embed_Generator::decode_config('###notbase64###') === null);

// --- embed html snippet ---
$html = SNW_Embed_Generator::generate($cfg, 'https://example.com/w.js');
snw_assert('embed has widget div', strpos($html, 'class="steigerwald-news-widget"') !== false);
snw_assert('embed has script', strpos($html, '<script src="https://example.com/w.js" async>') !== false);
snw_assert('embed has data-config', strpos($html, 'data-config="') !== false);

// --- presets storage (in-memory option) ---
$p1 = SNW_Presets::save('Vereinswidget', $cfg);
snw_assert('preset saved with id', !empty($p1['id']) && $p1['config']['widget_id'] === $p1['id']);
$all = SNW_Presets::get_all();
snw_assert('preset listed', count($all) === 1);
$p2 = SNW_Presets::save('Vereinswidget 2', SNW_Helpers::default_config());
snw_assert('two presets', count(SNW_Presets::get_all()) === 2);
$upd = SNW_Presets::save('Renamed', $cfg, $p1['id']);
snw_assert('preset update keeps id', $upd['id'] === $p1['id'] && $upd['name'] === 'Renamed');
// P1: widget id must be bound verbindlich to the preset id on update, even if
// a different/empty widget_id travelled in the submitted config.
$stray = SNW_Helpers::default_config();
$stray['widget_id'] = 'SNW-ZZZZZ';
$upd2 = SNW_Presets::save('Rebound', $stray, $p1['id']);
snw_assert('preset update rebinds widget_id to preset id', $upd2['config']['widget_id'] === $p1['id']);
snw_assert('still two presets after update', count(SNW_Presets::get_all()) === 2);
$dup = SNW_Presets::duplicate($p2['id']);
snw_assert('duplicate creates new id', $dup['id'] !== $p2['id'] && strpos($dup['name'], 'Kopie') !== false);
snw_assert('three presets after duplicate', count(SNW_Presets::get_all()) === 3);
snw_assert('delete returns true', SNW_Presets::delete($p1['id']) === true);
snw_assert('two presets after delete', count(SNW_Presets::get_all()) === 2);
snw_assert('delete missing returns false', SNW_Presets::delete('SNW-00000') === false);

echo "\n$pass assertions passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
