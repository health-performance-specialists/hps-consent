<?php
// Minimal WordPress contract harness: sanitizer, rendering and update boundary tests.
define('ABSPATH', '/'); define('HPS_CONSENT_VERSION', '1.0.0'); define('HPS_CONSENT_FILE', '/plugin/hps-consent.php');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);
$errors = []; $options = []; $transient = false; $response = null;
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
function get_transient($key) { global $transient; return $transient; } function set_transient($key, $value, $ttl) {}
function wp_remote_get($url, $args) { global $response; return $response; } function is_wp_error($value) { return false; }
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
$response = ['status' => 200, 'body' => ['tag_name' => 'v1.2.3', 'assets' => [['name' => 'hps-consent.zip', 'browser_download_url' => 'https://evil.test/plugin.zip']]]]; check(hps_consent_release() === false, 'Untrusted update package rejected');
$response['body']['assets'][0]['browser_download_url'] = 'https://github.com/health-performance-specialists/hps-consent/releases/download/v1.2.3/hps-consent.zip'; check(hps_consent_release()['version'] === '1.2.3', 'Valid release accepted');
$response['body']['prerelease'] = true; check(hps_consent_release() === false, 'Prerelease excluded');
echo "PHP checks passed: settings validation, output escaping, Elementor colors, exception validation and release boundaries.\n";
