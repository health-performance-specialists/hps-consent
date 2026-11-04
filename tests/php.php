<?php
// Minimal WordPress contract harness: sanitizer, rendering and update boundary tests.
define('ABSPATH', '/'); define('HPS_CONSENT_VERSION', '1.0.0'); define('HPS_CONSENT_FILE', '/plugin/hps-consent.php');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);
$errors = []; $options = []; $transient = false; $response = null; $last_cache = null; $last_http = null; $downloads = 0; $download_value = false;
class WP_Error { public $code; public function __construct($code, $message) { $this->code = $code; } }
function wp_delete_file($file) { unlink($file); }
function download_url($url, $timeout) { global $downloads, $download_value; $downloads++; return $download_value; }
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function did_action($key) { return 1; }
function absint($value) { return abs((int) $value); }
function get_post_meta($id, $key, $single) { return ['system_colors' => [['_id' => 'primary', 'title' => 'Primary', 'color' => '#336699']]]; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function esc_url_raw($v, $protocols = null) { return filter_var($v, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $v) ? $v : ''; }
function wp_parse_url($v) { return parse_url($v); }
function home_url($v) { return 'https://clinic.test' . $v; }
function add_settings_error($a, $b, $c) { global $errors; $errors[] = $c; }
function add_shortcode($a, $b) {}
function is_admin() { return false; } function is_feed() { return false; } function is_customize_preview() { return false; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES); } function esc_attr($v) { return esc_html($v); } function esc_url($v) { return esc_attr($v); }
function plugins_url($p, $f) { return '/plugin/' . $p; } function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function get_transient($key) { global $transient; return $transient; } function set_transient($key, $value, $ttl) { global $last_cache; $last_cache = [$key, $value, $ttl]; }
function wp_safe_remote_get($url, $args) { global $response, $last_http; $last_http = [$url, $args]; return $response; } function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['status']; } function wp_remote_retrieve_body($response) { return json_encode($response['body']); }
function plugin_basename($file) { return 'hps-consent/hps-consent.php'; }
require '/plugin/includes/settings.php'; require '/plugin/includes/frontend.php'; require '/plugin/includes/updater.php';
function check($value, $message) { if (!$value) { throw new Exception($message); } }
$s = hps_consent_sanitize(['enabled' => 1, 'gtm_id' => '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC123"></script>', 'mode' => 'advanced']);
check($s['gtm_id'] === 'GTM-ABC123', 'Extract GTM ID'); check($s['mode'] === 'basic', 'Advanced review gate');
$s = hps_consent_sanitize(['enabled' => 1, 'gtm_id' => 'GTM-ONE GTM-TWO']); check(!$s['enabled'], 'Reject multiple IDs');
$s = hps_consent_sanitize(['gtm_id' => 'GTM-ONE', 'mode' => 'advanced', 'container_reviewed' => 1, 'radius' => '999', 'border_color' => 'red;}</style><script>alert(1)</script>', 'primary_color' => 'elementor:primary', 'shadow_color' => '#12345678']);
check($s['mode'] === 'advanced', 'Advanced mode selected'); check($s['radius'] === 64, 'Radius bounded'); check($s['border_color'] === '#d6dbe5ff', 'CSS injection blocked'); check($s['primary_color'] === 'elementor:primary', 'Global color selected');
$s = hps_consent_sanitize(['excluded_urls' => "/offer/\nhttps://clinic.test/campaign/*\nhttps://evil.test/\n/offer?x=1\n//evil.test/path\n/a*b\n/a/../private/\n/encoded%2fpath"]);
check(strpos($s['excluded_urls'], 'evil') === false, 'External URLs rejected'); check(strpos($s['excluded_urls'], '?') === false, 'Query exception rejected'); check(strpos($s['excluded_urls'], '/campaign/*') !== false, 'Wildcard allowed'); check(strpos($s['excluded_urls'], '..') === false, 'Dot-segment paths rejected');
$options['hps_consent'] = array_merge(hps_consent_defaults(), ['enabled' => 1, 'gtm_id' => 'GTM-ABC123', 'primary_color' => 'elementor:primary', 'title' => '<script>alert(1)</script>']);
check(strpos(hps_consent_css(), 'var(--e-global-color-primary, #336699)') !== false, 'Dynamic Elementor color fallback');
ob_start(); hps_consent_head(); $head = ob_get_clean();
ob_start(); hps_consent_markup(); $body = ob_get_clean();
check(strpos($body, '<h2 id="hps-consent-title" tabindex="-1">&lt;script&gt;') !== false, 'HTML escaped');
check(strpos($head, 'noscript') === false && strpos($head, 'gtm.js') === false, 'No unconditional GTM fallback');
file_put_contents('/preview.html', '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $head . '</head><body style="background:#edf1f8;font:18px system-ui;margin:50px"><h1>HPS Consent preview</h1><p>Actual plugin markup rendered through PHP. This preview uses a test container ID.</p>' . $body . '</body></html>');
// Pure release parser and update-download boundary checks.
$fixture = 'test archive bytes';
$asset = ['name' => 'hps-consent.zip', 'browser_download_url' => 'https://github.com/health-performance-specialists/hps-consent/releases/download/v1.2.3/hps-consent.zip', 'state' => 'uploaded', 'size' => strlen($fixture), 'digest' => 'sha256:' . hash('sha256', $fixture)];
$valid = ['tag_name' => 'v1.2.3', 'assets' => [$asset]];
check(hps_consent_parse_release($valid)['sha256'] === hash('sha256', $fixture), 'Valid release digest parsed');
foreach ([null, [], ['tag_name' => ['v1.2.3']], ['tag_name' => 'v1.2.3', 'assets' => 'bad']] as $bad) { check(hps_consent_parse_release($bad) === false, 'Malformed metadata rejected'); }
foreach (['https://evil.test/plugin.zip', 'https://github.com/health-performance-specialists/hps-consent/releases/download/v9.9.9/hps-consent.zip', $asset['browser_download_url'] . '?redirect=evil', $asset['browser_download_url'] . "\n"] as $url) {
    $bad = $valid; $bad['assets'][0]['browser_download_url'] = $url;
    check(hps_consent_parse_release($bad) === false, 'Exact repository/tag/package binding');
}
foreach ([null, 'sha256:bad', 'md5:' . str_repeat('a', 64), []] as $digest) {
    $bad = $valid; $bad['assets'][0]['digest'] = $digest; check(hps_consent_parse_release($bad) === false, 'Digest required and validated');
}
foreach ([0, -1, 10485761, '12'] as $size) { $bad = $valid; $bad['assets'][0]['size'] = $size; check(hps_consent_parse_release($bad) === false, 'Asset size bounded'); }
foreach (['draft', 'prerelease'] as $flag) { $bad = $valid; $bad[$flag] = true; check(hps_consent_parse_release($bad) === false, 'Unpublished release excluded'); }
$response = ['status' => 200, 'body' => $valid];
check(hps_consent_release()['version'] === '1.2.3', 'Release fetch uses strict parser');
check($last_http[0] === 'https://api.github.com/repos/health-performance-specialists/hps-consent/releases/latest' && $last_http[1]['limit_response_size'] === 262144, 'Fixed bounded metadata endpoint');
$response['body']['assets'][0]['digest'] = null; check(hps_consent_release() === false && $last_cache[2] === 900, 'Malformed metadata negatively cached');
$response = ['status' => 200, 'body' => $valid];
$hook = ['plugin' => 'hps-consent/hps-consent.php', 'action' => 'update', 'type' => 'plugin'];
check(hps_consent_verify_download(false, 'https://elsewhere.test/other.zip', null, ['plugin' => 'other/main.php', 'action' => 'update']) === false, 'Other plugin downloads unaffected');
check(hps_consent_verify_download(false, '/manual-install.zip', null, ['action' => 'install']) === false, 'Manual local installation unaffected');
$prior_error = new WP_Error('prior', 'existing failure'); check(hps_consent_verify_download($prior_error, $asset['browser_download_url'], null, $hook) === $prior_error, 'Earlier updater errors preserved');
check(is_wp_error(hps_consent_verify_download(false, 'https://evil.test/plugin.zip', null, $hook)) && $downloads === 0, 'Untrusted HPS download fails before any fetch');
$response['body']['assets'][0]['digest'] = null; check(is_wp_error(hps_consent_verify_download(false, $asset['browser_download_url'], null, $hook)) && $downloads === 0, 'Missing digest fails closed');
$response['body'] = $valid;
file_put_contents('/good.zip', $fixture); $download_value = '/good.zip';
check(hps_consent_verify_download(false, $asset['browser_download_url'], null, $hook) === '/good.zip' && file_exists('/good.zip'), 'Valid download verified before unpack');
file_put_contents('/corrupt.zip', $fixture . ' altered'); $download_value = '/corrupt.zip';
check(is_wp_error(hps_consent_verify_download(false, $asset['browser_download_url'], null, $hook)) && !file_exists('/corrupt.zip'), 'Corrupted download rejected and own temporary file deleted');
file_put_contents('/supplied.zip', 'other filter content');
check(is_wp_error(hps_consent_verify_download('/supplied.zip', $asset['browser_download_url'], null, $hook)) && file_exists('/supplied.zip'), 'Never deletes a file owned by another filter');
check(hps_consent_verify_download('/good.zip', $asset['browser_download_url'], null, $hook) === '/good.zip', 'Earlier downloaded package still verified');
$download_value = new WP_Error('download_failed', 'network failure'); check(hps_consent_verify_download(false, $asset['browser_download_url'], null, $hook) === $download_value, 'Download failure returned safely');

echo "PHP checks passed: settings validation, output escaping, Elementor colors, exception validation, release metadata and downloaded-package integrity boundaries.\n";
