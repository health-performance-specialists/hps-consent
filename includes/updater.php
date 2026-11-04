<?php
defined('ABSPATH') || exit;

/** Parse untrusted release metadata; the package must belong to the exact advertised tag. */
function hps_consent_parse_release($release) {
    if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) ||
        !is_string($release['tag_name'] ?? null) || !preg_match('/\Av?(\d+\.\d+\.\d+)\z/', $release['tag_name'], $match) ||
        !is_array($release['assets'] ?? null)) { return false; }
    $expected = 'https://github.com/health-performance-specialists/hps-consent/releases/download/' . $release['tag_name'] . '/hps-consent.zip';
    foreach ($release['assets'] as $asset) {
        if (!is_array($asset) || ($asset['name'] ?? '') !== 'hps-consent.zip' || ($asset['browser_download_url'] ?? '') !== $expected ||
            ($asset['state'] ?? '') !== 'uploaded' || !is_int($asset['size'] ?? null) || $asset['size'] <= 0 || $asset['size'] > 10 * 1024 * 1024 ||
            !is_string($asset['digest'] ?? null) || !preg_match('/\Asha256:([a-f0-9]{64})\z/i', $asset['digest'], $digest)) { continue; }
        return [
            'version' => $match[1], 'package' => $expected, 'sha256' => strtolower($digest[1]), 'size' => $asset['size'],
            'body' => sanitize_textarea_field(is_string($release['body'] ?? null) ? $release['body'] : ''),
            'published' => sanitize_text_field(is_string($release['published_at'] ?? null) ? $release['published_at'] : ''),
        ];
    }
    return false;
}

/** Public release metadata only; no credentials or site/visitor data are sent. */
function hps_consent_release() {
    // New cache key prevents older metadata without a digest from bypassing verification.
    $cached = get_transient('hps_consent_release_v2');
    if ($cached !== false) { return is_array($cached) && !empty($cached['sha256']) ? $cached : false; }
    $response = wp_safe_remote_get('https://api.github.com/repos/health-performance-specialists/hps-consent/releases/latest', [
        'timeout' => 10, 'redirection' => 3,
        'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'HPS-Consent/' . HPS_CONSENT_VERSION],
        'limit_response_size' => 262144,
    ]);
    $result = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
        ? hps_consent_parse_release(json_decode(wp_remote_retrieve_body($response), true)) : false;
    set_transient('hps_consent_release_v2', $result ?: [], $result ? 12 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
    return $result;
}

/** Verify HPS updates before WordPress unpacks or replaces executable files. */
function hps_consent_verify_download($reply, $package, $upgrader, $hook_extra) {
    $own_update = ($hook_extra['action'] ?? '') === 'update' && ($hook_extra['plugin'] ?? '') === plugin_basename(HPS_CONSENT_FILE);
    $own_url = is_string($package) && strpos($package, 'https://github.com/health-performance-specialists/hps-consent/releases/download/') === 0;
    if ((!$own_update && !$own_url) || is_wp_error($reply)) { return $reply; }
    $release = hps_consent_release();
    if (!$release || !is_string($package) || $package !== $release['package']) {
        return new WP_Error('hps_consent_unverified_package', 'HPS Consent could not verify this update against its published release. No plugin files were replaced. Try checking for updates again.');
    }
    if ($reply !== false && !is_string($reply)) {
        return new WP_Error('hps_consent_invalid_download', 'HPS Consent received an invalid update download.');
    }
    $owned = $reply === false;
    $file = $owned ? download_url($package, 60) : $reply;
    if (is_wp_error($file)) { return $file; }
    $hash = is_string($file) && is_file($file) && is_readable($file) ? hash_file('sha256', $file) : false;
    if (!is_string($hash) || !hash_equals($release['sha256'], $hash) || filesize($file) !== $release['size']) {
        // Never delete a path supplied by another filter; only clean up our own temporary download.
        if ($owned && is_string($file) && is_file($file)) { wp_delete_file($file); }
        return new WP_Error('hps_consent_integrity_failed', 'HPS Consent update integrity verification failed. No plugin files were replaced.');
    }
    return $file;
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
