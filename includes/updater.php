<?php
defined('ABSPATH') || exit;

/** Public release metadata only; no credentials or site/visitor data are sent. */
function hps_consent_release() {
    $cached = get_transient('hps_consent_release_v1');
    if ($cached !== false) { return $cached ?: false; }
    $response = wp_remote_get('https://api.github.com/repos/health-performance-specialists/hps-consent/releases/latest', [
        'timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'HPS-Consent/' . HPS_CONSENT_VERSION],
        'limit_response_size' => 262144,
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        set_transient('hps_consent_release_v1', [], 15 * MINUTE_IN_SECONDS);
        return false;
    }
    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) ||
        !preg_match('/^v?(\d+\.\d+\.\d+)$/', $release['tag_name'] ?? '', $match)) { return false; }
    $package = '';
    foreach (($release['assets'] ?? []) as $asset) {
        $url = $asset['browser_download_url'] ?? '';
        if (($asset['name'] ?? '') === 'hps-consent.zip' && preg_match('#^https://github\.com/health-performance-specialists/hps-consent/releases/download/v?\d+\.\d+\.\d+/hps-consent\.zip$#', $url)) {
            $package = $url;
        }
    }
    if (!$package) { return false; }
    $result = ['version' => $match[1], 'package' => $package, 'body' => sanitize_textarea_field($release['body'] ?? ''), 'published' => sanitize_text_field($release['published_at'] ?? '')];
    set_transient('hps_consent_release_v1', $result, 12 * HOUR_IN_SECONDS);
    return $result;
}

function hps_consent_update($update, $plugin_data, $plugin_file, $locales) {
    if ($plugin_file !== plugin_basename(HPS_CONSENT_FILE)) { return $update; }
    $release = hps_consent_release();
    if (!$release) { return false; }
    return [
        'id' => 'https://github.com/health-performance-specialists/hps-consent', 'slug' => 'hps-consent',
        'version' => $release['version'], 'url' => 'https://github.com/health-performance-specialists/hps-consent',
        'package' => $release['package'], 'requires_php' => '8.0', 'requires' => '6.5',
    ];
}

function hps_consent_plugin_info($result, $action, $args) {
    if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'hps-consent') { return $result; }
    $release = hps_consent_release();
    return (object) [
        'name' => 'HPS Consent', 'slug' => 'hps-consent', 'version' => $release['version'] ?? HPS_CONSENT_VERSION,
        'author' => 'HPS Network', 'homepage' => 'https://github.com/health-performance-specialists/hps-consent',
        'requires' => '6.5', 'requires_php' => '8.0', 'download_link' => $release['package'] ?? '',
        'sections' => [
            'description' => '<p>Reusable WordPress consent controls with Basic and Advanced Google Consent Mode v2, Elementor global colors and GitHub release updates.</p>',
            'changelog' => nl2br(esc_html($release['body'] ?? 'See the GitHub repository for release notes.')),
        ],
    ];
}
