<?php
/**
 * Plugin Name: HPS Consent
 * Description: Reusable consent controls, Google Tag Manager Consent Mode v2, and HPS branding.
 * Version: 1.0.3
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: HPS Network
 * Author URI: https://hps-network.com
 * Plugin URI: https://github.com/health-performance-specialists/hps-consent
 * Update URI: https://github.com/health-performance-specialists/hps-consent
 * License: GPL-2.0-or-later
 * Text Domain: hps-consent
 */
defined('ABSPATH') || exit;
define('HPS_CONSENT_VERSION', '1.0.3');
define('HPS_CONSENT_FILE', __FILE__);
define('HPS_CONSENT_DIR', plugin_dir_path(__FILE__));
require_once HPS_CONSENT_DIR . 'includes/settings.php';
require_once HPS_CONSENT_DIR . 'includes/frontend.php';
require_once HPS_CONSENT_DIR . 'includes/updater.php';
add_action('admin_menu', 'hps_consent_admin_menu');
add_action('admin_init', 'hps_consent_register_settings');
add_action('admin_enqueue_scripts', 'hps_consent_admin_assets');
add_action('wp_head', 'hps_consent_head', -999);
add_action('wp_footer', 'hps_consent_markup', 99);
add_filter('update_plugins_github.com', 'hps_consent_update', 10, 4);
add_filter('upgrader_pre_download', 'hps_consent_verify_download', 99, 4);
add_filter('plugins_api', 'hps_consent_plugin_info', 10, 3);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=hps-consent')) . '">' . esc_html__('Settings', 'hps-consent') . '</a>');
    return $links;
});
