<?php
defined('ABSPATH') || exit;

function hps_consent_defaults(): array {
    return [
        'enabled' => 0, 'gtm_id' => '', 'mode' => 'basic', 'container_reviewed' => 0,
        'policy_url' => '', 'excluded_urls' => '', 'consent_version' => '1', 'days' => 180,
        'confirm_reject' => 1, 'respect_gpc' => 1, 'language' => 'en',
        'title' => 'Your privacy choices',
        'message' => 'We use optional cookies and similar technologies to understand website use and measure advertising. You can accept, decline, or choose which categories to allow.',
        'reject_message' => 'Some optional features, such as embedded videos or maps, may not load without third-party services. You can still contact us and browse the website. Continue without optional cookies?',
        'accept_label' => 'Accept all', 'reject_label' => 'Opt out', 'preferences_label' => 'Customize',
        'yes_label' => 'Yes, opt out', 'no_label' => 'No, go back',
        'primary_color' => '#3850b8ff', 'button_color' => '#3850b8ff', 'button_text_color' => '#ffffffff',
        'background_color' => '#ffffffff', 'text_color' => '#182034ff', 'border_color' => '#d6dbe5ff',
        'link_color' => '#293e94ff', 'shadow_color' => '#10182833',
        'radius' => 16, 'border_width' => 1, 'shadow_enabled' => 1, 'shadow_blur' => 32,
        'shadow_y' => 8, 'shadow_spread' => 0, 'position' => 'bottom',
    ];
}

function hps_consent_options(): array {
    return array_merge(hps_consent_defaults(), (array) get_option('hps_consent', []));
}

function hps_consent_colors(): array {
    $colors = [];
    if (!did_action('elementor/loaded')) { return $colors; }
    $kit_id = absint(get_option('elementor_active_kit'));
    $settings = get_post_meta($kit_id, '_elementor_page_settings', true);
    if (!is_array($settings)) { return $colors; }
    foreach (['system_colors', 'custom_colors'] as $group) {
        foreach (($settings[$group] ?? []) as $color) {
            $id = (string) ($color['_id'] ?? '');
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $id) && isset($color['color'])) {
                $colors[$id] = ['title' => sanitize_text_field($color['title'] ?? $id), 'color' => hps_consent_hex($color['color'], '#3850b8ff')];
            }
        }
    }
    return $colors;
}

function hps_consent_hex($value, string $fallback): string {
    return is_string($value) && preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $value) ? strtolower($value) : $fallback;
}

function hps_consent_sanitize($input): array {
    $input = is_array($input) ? $input : [];
    $old = hps_consent_options();
    $out = hps_consent_defaults();
    foreach (['enabled', 'confirm_reject', 'respect_gpc', 'shadow_enabled', 'container_reviewed'] as $key) {
        $out[$key] = empty($input[$key]) ? 0 : 1;
    }
    $raw = trim((string) ($input['gtm_id'] ?? ''));
    preg_match_all('/\bGTM-[A-Z0-9]+\b/i', $raw, $matches);
    $ids = array_values(array_unique(array_map('strtoupper', $matches[0])));
    if (count($ids) === 1) {
        $out['gtm_id'] = $ids[0];
    } elseif ($raw !== '') {
        $out['gtm_id'] = $old['gtm_id'];
        add_settings_error('hps_consent', 'gtm', 'Enter one valid GTM container ID or a snippet containing exactly one container ID. Previous ID retained.');
    }
    if ($out['enabled'] && !$out['gtm_id']) {
        $out['enabled'] = 0;
        add_settings_error('hps_consent', 'missing_gtm', 'Add a GTM container before enabling HPS Consent.');
    }
    $out['mode'] = ($input['mode'] ?? '') === 'advanced' ? 'advanced' : 'basic';
    if ($out['mode'] === 'advanced' && !$out['container_reviewed']) {
        $out['mode'] = 'basic';
        add_settings_error('hps_consent', 'review', 'Advanced mode requires confirmation that every tag in your GTM container has been reviewed for consent. Basic mode retained.');
    }
    $out['days'] = max(1, min(365, absint($input['days'] ?? 180)));
    $out['consent_version'] = sanitize_text_field($input['consent_version'] ?? '1');
    $out['language'] = ($input['language'] ?? '') === 'es' ? 'es' : 'en';
    $out['policy_url'] = esc_url_raw($input['policy_url'] ?? '', ['https', 'http']);
    $out['position'] = ($input['position'] ?? '') === 'bottom-left' ? 'bottom-left' : 'bottom';
    foreach (['title', 'message', 'reject_message', 'accept_label', 'reject_label', 'preferences_label', 'yes_label', 'no_label'] as $key) {
        $out[$key] = sanitize_textarea_field($input[$key] ?? $out[$key]);
        if ($out[$key] === '') { $out[$key] = hps_consent_defaults()[$key]; }
    }
    $valid = [];
    foreach (preg_split('/\R/', (string) ($input['excluded_urls'] ?? '')) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $parts = wp_parse_url($line);
        $home = wp_parse_url(home_url('/'));
        if (!$parts || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass']) ||
            (isset($parts['host']) && (strtolower($parts['host']) !== strtolower($home['host']) || ($parts['port'] ?? null) !== ($home['port'] ?? null))) ||
            (isset($parts['scheme']) && !in_array($parts['scheme'], ['http', 'https'], true))) {
            add_settings_error('hps_consent', 'url', 'An invalid or external exception URL was ignored. Use same-site paths without query strings or fragments.');
            continue;
        }
        $path = $parts['path'] ?? '/';
        if (substr($path, 0, 1) !== '/' || substr($path, 0, 2) === '//' || preg_match('/[<>\\\\]/', $path) || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', rawurldecode($path)) ||
            (strpos($path, '*') !== false && !preg_match('#^/[^*]*?/\*$#', $path))) {
            add_settings_error('hps_consent', 'url', 'An invalid exception path was ignored. A wildcard is permitted only as a final /*.');
            continue;
        }
        $valid[] = $path;
    }
    $out['excluded_urls'] = implode("\n", array_unique($valid));
    $globals = hps_consent_colors();
    foreach (array_keys($out) as $key) {
        if (substr($key, -6) === '_color') {
            $value = (string) ($input[$key] ?? $out[$key]);
            $id = substr($value, 10);
            $out[$key] = strpos($value, 'elementor:') === 0 && isset($globals[$id]) ? $value : hps_consent_hex($value, hps_consent_defaults()[$key]);
        }
    }
    foreach (['radius' => [0, 64], 'border_width' => [0, 12], 'shadow_blur' => [0, 100], 'shadow_y' => [-50, 50], 'shadow_spread' => [-30, 30]] as $key => $range) {
        $out[$key] = max($range[0], min($range[1], intval($input[$key] ?? $out[$key])));
    }
    return $out;
}

function hps_consent_register_settings(): void {
    register_setting('hps_consent', 'hps_consent', ['type' => 'array', 'sanitize_callback' => 'hps_consent_sanitize']);
}
function hps_consent_admin_menu(): void {
    add_options_page('HPS Consent', 'HPS Consent', 'manage_options', 'hps-consent', 'hps_consent_settings_page');
}
function hps_consent_admin_assets($hook): void {
    if ($hook !== 'settings_page_hps-consent') { return; }
    wp_enqueue_style('hps-consent-admin', plugins_url('assets/admin.css', HPS_CONSENT_FILE), [], HPS_CONSENT_VERSION);
    wp_enqueue_script('hps-consent-admin', plugins_url('assets/admin.js', HPS_CONSENT_FILE), [], HPS_CONSENT_VERSION, true);
}
function hps_consent_field(string $key, string $type = 'text', string $extra = ''): void {
    $o = hps_consent_options();
    $name = 'hps_consent[' . $key . ']';
    if ($type === 'checkbox') {
        echo '<input id="' . esc_attr($key) . '" type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($o[$key], 1, false) . '>';
    } elseif ($type === 'textarea') {
        echo '<textarea id="' . esc_attr($key) . '" name="' . esc_attr($name) . '" rows="4" class="large-text">' . esc_textarea($o[$key]) . '</textarea>';
    } else {
        echo '<input id="' . esc_attr($key) . '" class="regular-text" type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($o[$key]) . '" ' . $extra . '>';
    }
}
function hps_consent_settings_page(): void {
    if (!current_user_can('manage_options')) { return; }
    $o = hps_consent_options();
    $globals = hps_consent_colors();
    ?>
    <div class="wrap hps-admin"><h1>HPS Consent</h1><p>One reusable consent system for your WordPress websites. Version <?php echo esc_html(HPS_CONSENT_VERSION); ?></p>
    <div class="notice notice-warning inline"><p><strong>Before enabling:</strong> remove other GTM loaders, including header code, noscript iframes and plugins. Every non-Google tag in GTM needs consent requirements. This plugin cannot automatically control scripts installed elsewhere, server-side tracking or iframe contents.</p></div>
    <form method="post" action="options.php"><?php settings_fields('hps_consent'); ?>
    <section><h2>Tracking</h2><p><label><?php hps_consent_field('enabled', 'checkbox'); ?> Enable consent banner and GTM management</label></p>
    <label for="gtm_id">GTM container ID or installation code</label><?php hps_consent_field('gtm_id', 'textarea'); ?><p class="description">Paste GTM-XXXXXXX or the Google installation snippet. Only the container ID is saved; arbitrary JavaScript is never executed.</p>
    <p><label for="mode">Consent mode</label><select id="mode" name="hps_consent[mode]"><option value="basic" <?php selected($o['mode'], 'basic'); ?>>Basic — block GTM until consent</option><option value="advanced" <?php selected($o['mode'], 'advanced'); ?>>Advanced — load GTM with denied defaults</option></select></p>
    <p>Advanced mode permits Google cookieless measurement before acceptance. Both modes require category gates for third-party tags.</p>
    <p><label><?php hps_consent_field('container_reviewed', 'checkbox'); ?> I have reviewed all GTM tags, consent requirements and consent-update triggers.</label></p>
    <p><label><?php hps_consent_field('respect_gpc', 'checkbox'); ?> Honor Global Privacy Control (blocks advertising consent, including on exception pages)</label></p>
    <p><label for="days">Remember choices (days)</label><?php hps_consent_field('days', 'number', 'min="1" max="365"'); ?></p>
    <p><label for="consent_version">Consent policy version</label><?php hps_consent_field('consent_version'); ?> <span class="description">Change this value to request fresh consent.</span></p>
    <p><label for="policy_url">Privacy policy URL</label><?php hps_consent_field('policy_url', 'url'); ?></p>
    </section>
    <section><h2>Pages that bypass consent</h2><div class="notice notice-warning inline"><p><strong>These pages hide the banner, grant consent and load GTM even when the visitor previously rejected cookies.</strong> GPC advertising restrictions still apply when enabled. This is a tracking override, not a tracking exclusion. Leave empty unless intentional. It does not save acceptance for other pages.</p></div>
    <label for="excluded_urls">One same-site URL or path per line</label><?php hps_consent_field('excluded_urls', 'textarea'); ?><p class="description">Exact path: /campaign/ · Path and descendants: /campaign/* · Query strings and fragments are ignored when matching visitors, and are not allowed in settings. Avoid appointment, evaluation and patient pages.</p></section>
    <section><h2>Appearance</h2><p>Choose a custom color and opacity, or a global color from the active Elementor kit. Global selections follow changes to that kit.</p><div class="hps-colors">
    <?php foreach (['primary_color' => 'Primary / focus', 'button_color' => 'Accept button background', 'button_text_color' => 'Accept button text', 'background_color' => 'Background', 'text_color' => 'Text', 'link_color' => 'Links', 'border_color' => 'Border', 'shadow_color' => 'Shadow'] as $key => $label) :
        $value = $o[$key]; $global = strpos($value, 'elementor:') === 0; $hex = $global ? ($globals[substr($value, 10)]['color'] ?? '#3850b8ff') : $value;
        ?>
        <div class="hps-color" data-color-field><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
        <input class="hps-color-value" id="<?php echo esc_attr($key); ?>" name="hps_consent[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" aria-label="<?php echo esc_attr($label . ' hex or global token'); ?>">
        <div><input type="color" class="hps-picker" value="<?php echo esc_attr(substr($hex, 0, 7)); ?>" aria-label="<?php echo esc_attr($label . ' picker'); ?>"><label>Opacity <input type="range" class="hps-alpha" min="0" max="100" value="100" aria-label="<?php echo esc_attr($label . ' opacity'); ?>"></label><output class="hps-alpha-value"></output></div>
        <?php if ($globals) : ?><select class="hps-global" aria-label="<?php echo esc_attr($label . ' Elementor global color'); ?>"><option value="">Custom hex + opacity</option><?php foreach ($globals as $id => $color) : ?><option value="elementor:<?php echo esc_attr($id); ?>" data-color="<?php echo esc_attr($color['color']); ?>" <?php selected($value, 'elementor:' . $id); ?>><?php echo esc_html($color['title']); ?></option><?php endforeach; ?></select><?php endif; ?></div>
    <?php endforeach; ?></div>
    <?php foreach (['radius' => 'Corner radius (px)', 'border_width' => 'Solid border width (px)', 'shadow_blur' => 'Shadow blur (px)', 'shadow_y' => 'Shadow vertical offset (px)', 'shadow_spread' => 'Shadow spread (px)'] as $key => $label) : ?><p><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label><?php hps_consent_field($key, 'number'); ?></p><?php endforeach; ?>
    <p><label><?php hps_consent_field('shadow_enabled', 'checkbox'); ?> Background shadow</label></p>
    <p><label for="position">Position</label><select name="hps_consent[position]" id="position"><option value="bottom" <?php selected($o['position'], 'bottom'); ?>>Bottom banner</option><option value="bottom-left" <?php selected($o['position'], 'bottom-left'); ?>>Bottom-left box</option></select></p>
    <div class="hps-preview" id="hps-preview"><h3>Your privacy choices</h3><p>Preview of your banner colors, corners, border and shadow.</p><button type="button">Accept all</button> <a href="#" class="hps-preview-link">Opt out</a></div></section>
    <section><h2>Banner text and flow</h2><p><label for="language">Interface language</label><select name="hps_consent[language]" id="language"><option value="en" <?php selected($o['language'], 'en'); ?>>English</option><option value="es" <?php selected($o['language'], 'es'); ?>>Español</option></select> Category and preference labels; edit banner copy below for your audience.</p>
    <p><label><?php hps_consent_field('confirm_reject', 'checkbox'); ?> Show the opt-out confirmation step</label></p><p class="description">Optional requested flow: prominent Accept, link-style Opt out, then Yes / No links. Extra rejection steps and unequal prominence may not meet requirements in some regions. Turn this off for one-click rejection.</p>
    <?php foreach (['title' => 'Heading', 'message' => 'Message', 'reject_message' => 'Opt-out confirmation message', 'accept_label' => 'Accept label', 'reject_label' => 'Opt-out label', 'preferences_label' => 'Preferences label', 'yes_label' => 'Confirm opt-out label', 'no_label' => 'Go back label'] as $key => $label) : ?><p><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label><?php hps_consent_field($key, in_array($key, ['message', 'reject_message'], true) ? 'textarea' : 'text'); ?></p><?php endforeach; ?></section>
    <?php submit_button('Save HPS Consent settings'); ?></form>
    <section><h2>Setup and updates</h2><p>Add <code>[hps_consent_preferences]</code> to a footer or use a link to <code>#hps-consent-settings</code>. A floating Privacy choices button is also provided.</p><p>Consent is remembered in this browser using a first-party cookie with an expiry and policy version. This release does not store a central consent log or send visitor choices to HPS or GitHub.</p><p>Updates are checked against published releases at <a href="https://github.com/health-performance-specialists/hps-consent" target="_blank" rel="noopener noreferrer">health-performance-specialists/hps-consent</a>. Install the release asset <code>hps-consent.zip</code>. Use WordPress’s normal plugin update controls.</p><p>Read the repository’s GTM setup guide before activation. This is a consent tool, not a Google-certified CMP or a HIPAA authorization mechanism.</p></section></div>
    <?php
}
