<?php
defined('ABSPATH') || exit;

function hps_consent_config(): array {
    $o = hps_consent_options();
    $config = [
        'id' => $o['gtm_id'], 'mode' => $o['mode'], 'version' => $o['consent_version'],
        'days' => $o['days'], 'gpc' => (bool) $o['respect_gpc'], 'confirmReject' => (bool) $o['confirm_reject'],
        'exceptions' => array_values(array_filter(explode("\n", $o['excluded_urls']))),
        'ctmBeforeChoice' => (bool) $o['ctm_before_choice'],
        'cookie' => 'hps_consent_v1', 'language' => $o['language'],
    ];
    return $config;
}

function hps_consent_css(): string {
    $o = hps_consent_options();
    $globals = hps_consent_colors();
    $css = '';
    foreach (['primary_color' => 'primary', 'button_color' => 'button', 'button_text_color' => 'button-text', 'background_color' => 'background', 'text_color' => 'text', 'link_color' => 'link', 'border_color' => 'border', 'shadow_color' => 'shadow-color'] as $key => $var) {
        $value = $o[$key];
        if (strpos($value, 'elementor:') === 0) {
            $id = substr($value, 10);
            $fallback = $globals[$id]['color'] ?? hps_consent_defaults()[$key];
            $value = preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? 'var(--e-global-color-' . $id . ', ' . $fallback . ')' : $fallback;
        } else { $value = hps_consent_hex($value, hps_consent_defaults()[$key]); }
        $css .= '--hps-' . $var . ':' . $value . ';';
    }
    $css .= '--hps-radius:' . intval($o['radius']) . 'px;--hps-border-width:' . intval($o['border_width']) . 'px;';
    $css .= '--hps-shadow:' . ($o['shadow_enabled'] ? '0 ' . intval($o['shadow_y']) . 'px ' . intval($o['shadow_blur']) . 'px ' . intval($o['shadow_spread']) . 'px var(--hps-shadow-color)' : 'none') . ';';
    return '#hps-consent-root{' . $css . '}';
}

function hps_consent_head(): void {
    $o = hps_consent_options();
    if (is_admin() || !$o['enabled'] || !$o['gtm_id'] || is_feed() || is_customize_preview()) { return; }
    $json = wp_json_encode(hps_consent_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script id="hps-consent-config">window.HPSConsentConfig=' . $json . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* prevents script termination.
    echo '<script id="hps-consent-runtime" src="' . esc_url(plugins_url('assets/consent.js', HPS_CONSENT_FILE)) . '?ver=' . esc_attr(HPS_CONSENT_VERSION) . '"></script>' . "\n";
    echo '<link rel="stylesheet" href="' . esc_url(plugins_url('assets/consent.css', HPS_CONSENT_FILE)) . '?ver=' . esc_attr(HPS_CONSENT_VERSION) . '">' . "\n";
    echo '<style id="hps-consent-theme">' . hps_consent_css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted tokens, validated hex and integers only.
}

function hps_consent_markup(): void {
    $o = hps_consent_options();
    if (is_admin() || !$o['enabled'] || !$o['gtm_id'] || is_feed() || is_customize_preview()) { return; }
    $es = $o['language'] === 'es';
    $t = $es ? [
        'choices' => 'Opciones de privacidad', 'back' => 'Volver', 'save' => 'Guardar preferencias',
        'necessary' => 'Necesarias', 'necessary_text' => 'Siempre activas para recordar tus opciones y proteger el sitio.',
        'analytics' => 'Analítica', 'analytics_text' => 'Permite medir visitas y uso del sitio.',
        'advertising' => 'Publicidad', 'advertising_text' => 'Permite atribución publicitaria, píxeles y medición de conversiones.',
        'functional' => 'Contenido opcional', 'functional_text' => 'Permite servicios externos opcionales, como videos y mapas configurados para esperar consentimiento.',
        'policy' => 'Política de privacidad', 'gpc' => 'Tu navegador solicita privacidad global. La publicidad está desactivada.',
    ] : [
        'choices' => 'Privacy choices', 'back' => 'Back', 'save' => 'Save preferences',
        'necessary' => 'Necessary', 'necessary_text' => 'Always on to remember privacy choices and protect the website.',
        'analytics' => 'Analytics', 'analytics_text' => 'Allows website visit and engagement measurement.',
        'advertising' => 'Advertising', 'advertising_text' => 'Allows advertising attribution, pixels and conversion measurement.',
        'functional' => 'Optional content', 'functional_text' => 'Allows optional external services, such as videos and maps configured to wait for consent.',
        'policy' => 'Privacy policy', 'gpc' => 'Your browser sends Global Privacy Control. Advertising is disabled.',
    ];
    ?>
    <div id="hps-consent-root" class="hps-consent hps-<?php echo esc_attr($o['position']); ?>" role="dialog" aria-modal="false" aria-labelledby="hps-consent-title" hidden>
        <div data-hps-panel="banner"><h2 id="hps-consent-title" tabindex="-1"><?php echo esc_html($o['title']); ?></h2>
        <p><?php echo esc_html($o['message']); ?></p><p data-hps-gpc hidden><?php echo esc_html($t['gpc']); ?></p>
        <div class="hps-actions"><button type="button" class="hps-accept" data-hps-action="accept"><?php echo esc_html($o['accept_label']); ?></button><button type="button" class="hps-link" data-hps-action="reject"><?php echo esc_html($o['reject_label']); ?></button><button type="button" class="hps-link" data-hps-action="preferences"><?php echo esc_html($o['preferences_label']); ?></button></div></div>
        <div data-hps-panel="confirm" hidden><h2 tabindex="-1"><?php echo esc_html($o['reject_label']); ?></h2><p><?php echo esc_html($o['reject_message']); ?></p><div class="hps-actions"><button type="button" class="hps-link" data-hps-action="confirm-reject"><?php echo esc_html($o['yes_label']); ?></button><button type="button" class="hps-link" data-hps-action="accept"><?php echo esc_html($o['no_label']); ?></button></div></div>
        <div data-hps-panel="preferences" hidden><h2 tabindex="-1"><?php echo esc_html($t['choices']); ?></h2>
        <p data-hps-gpc hidden><?php echo esc_html($t['gpc']); ?></p><div class="hps-category"><strong><?php echo esc_html($t['necessary']); ?></strong><p><?php echo esc_html($t['necessary_text']); ?></p></div>
        <?php foreach (['analytics', 'advertising', 'functional'] as $category) : ?><div class="hps-category"><label><input type="checkbox" data-hps-category="<?php echo esc_attr($category); ?>"> <?php echo esc_html($t[$category]); ?></label><p><?php echo esc_html($t[$category . '_text']); ?></p></div><?php endforeach; ?>
        <div class="hps-actions"><button type="button" class="hps-accept" data-hps-action="save"><?php echo esc_html($t['save']); ?></button><button type="button" class="hps-link" data-hps-action="back"><?php echo esc_html($t['back']); ?></button></div></div>
        <?php if ($o['policy_url']) : ?><a class="hps-policy" href="<?php echo esc_url($o['policy_url']); ?>"><?php echo esc_html($t['policy']); ?></a><?php endif; ?>
    </div>
    <?php
}

add_shortcode('hps_consent_preferences', static function () {
    $label = hps_consent_options()['language'] === 'es' ? 'Opciones de privacidad' : 'Privacy choices';
    return '<a href="#hps-consent-settings">' . esc_html($label) . '</a>';
});
