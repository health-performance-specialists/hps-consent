/* HPS Consent. No external runtime dependencies. */
(function (w, d) {
    'use strict';
    if (w.HPSConsent || !w.HPSConsentConfig) return;
    var config = w.HPSConsentConfig;
    var blank = function () { return {analytics: false, advertising: false, functional: false}; };
    var gpc = config.gpc && w.navigator.globalPrivacyControl === true;
    var current = blank(), stored = null, loaded = false, root, returnFocus;
    var exceptions = Array.isArray(config.exceptions) ? config.exceptions : [];
    function normal(path) {
        try { path = decodeURI(path); } catch (ignore) { /* malformed paths remain literal */ }
        return path === '/' ? '/' : path.replace(/\/+$/, '');
    }
    var bypass = exceptions.some(function (rule) {
        if (rule.endsWith('/*')) {
            var base = normal(rule.slice(0, -2));
            var path = normal(w.location.pathname);
            return base === '/' || path === base || path.indexOf(base + '/') === 0;
        }
        return normal(rule) === normal(w.location.pathname);
    });
    function read() {
        try {
            var match = d.cookie.split('; ').find(function (part) { return part.indexOf(config.cookie + '=') === 0; });
            if (!match) return null;
            var value = JSON.parse(decodeURIComponent(match.slice(config.cookie.length + 1)));
            if (value.schema !== 1 || value.version !== config.version || !Number.isFinite(value.expires) || value.expires <= Date.now() ||
                value.expires > Date.now() + 366 * 86400000 || !value.choice) return null;
            if (['analytics', 'advertising', 'functional'].some(function (key) { return typeof value.choice[key] !== 'boolean'; })) return null;
            return value.choice;
        } catch (ignore) { return null; }
    }
    stored = read();
    current = bypass ? {analytics: true, advertising: true, functional: true} : Object.assign(blank(), stored || {});
    if (gpc) current.advertising = false;
    w.dataLayer = w.dataLayer || [];
    // Use the documented gtag argument-object queue format before the container loads.
    function gtag() { w.dataLayer.push(arguments); }
    function googleState() {
        return {
            analytics_storage: current.analytics ? 'granted' : 'denied',
            ad_storage: current.advertising ? 'granted' : 'denied',
            ad_user_data: current.advertising ? 'granted' : 'denied',
            ad_personalization: current.advertising ? 'granted' : 'denied',
            functionality_storage: current.functional ? 'granted' : 'denied',
            personalization_storage: current.functional ? 'granted' : 'denied',
            security_storage: 'granted'
        };
    }
    gtag('consent', 'default', googleState());
    gtag('set', 'ads_data_redaction', true);
    gtag('set', 'url_passthrough', false);
    function ctmAllowed() {
        return current.analytics || (config.mode === 'advanced' && config.ctmBeforeChoice === true && !stored);
    }
    function publish(source) {
        var payload = {event: 'hps_consent_update', hps_analytics: current.analytics, hps_advertising: current.advertising,
            hps_functional: current.functional, hps_ctm_allowed: ctmAllowed(), hps_consent_source: source, hps_consent_bypass: bypass, hps_gpc: gpc};
        w.dataLayer.push(payload);
        d.dispatchEvent(new w.CustomEvent('hps:consent', {detail: Object.assign({}, payload)}));
    }
    function loadGTM() {
        if (loaded || !/^GTM-[A-Z0-9]+$/.test(config.id || '')) return;
        // Do not inject a second container if an administrator left another loader installed.
        var existing = Array.prototype.some.call(d.scripts, function (s) {
            try { var u = new URL(s.src, w.location.href); return u.hostname === 'www.googletagmanager.com' && u.pathname === '/gtm.js' && u.searchParams.get('id') === config.id; }
            catch (ignore) { return false; }
        });
        loaded = true;
        if (existing) {
            if (w.console) w.console.warn('HPS Consent: another GTM loader exists. Remove it to enforce Basic mode before consent.');
            return;
        }
        w.dataLayer.push({'gtm.start': Date.now(), event: 'gtm.js'});
        var script = d.createElement('script');
        script.async = true;
        script.id = 'hps-consent-gtm';
        script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(config.id);
        d.head.appendChild(script);
    }
    function maybeLoad() {
        if (config.mode === 'advanced' || bypass || current.analytics || current.advertising || current.functional) loadGTM();
    }
    // Consent state must be queued before gtm.js / page-view triggers.
    publish(bypass ? 'page-exception' : stored ? 'remembered' : 'default');
    maybeLoad();
    function cleanup(previous, previousCTM) {
        var patterns = [];
        if (previous.analytics && !current.analytics) patterns.push(/^_ga($|_)/, /^_gid$/, /^_gat/);
        if (previous.advertising && !current.advertising) patterns.push(/^_gcl_/, /^_fbp$/, /^_fbc$/, /^_uet/);
        if (previousCTM && !ctmAllowed()) patterns.push(/^__ctm/, /^_ctm/, /^:ct\d+$/);
        if (!patterns.length) return;
        var domains = [''];
        var host = w.location.hostname.split('.');
        for (var i = 0; i < host.length - 1; i++) domains.push('; Domain=.' + host.slice(i).join('.'));
        var paths = ['/'], segments = w.location.pathname.split('/').filter(Boolean);
        for (var j = 1; j <= segments.length; j++) paths.push('/' + segments.slice(0, j).join('/'));
        d.cookie.split(';').forEach(function (pair) {
            var name = pair.trim().split('=')[0];
            if (patterns.some(function (pattern) { return pattern.test(name); })) {
                domains.forEach(function (domain) { paths.forEach(function (path) {
                    d.cookie = name + '=; Max-Age=0; Path=' + path + domain + '; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
                }); });
            }
        });
        if (!current.advertising) { try { w.localStorage.removeItem('multiFbc'); w.localStorage.removeItem('fbc'); } catch (ignore) {} }
    }
    function releaseContent() {
        d.querySelectorAll('[data-hps-src]').forEach(function (element) {
            var category = element.getAttribute('data-hps-consent');
            if (!Object.prototype.hasOwnProperty.call(current, category) || !current[category]) return;
            var source = element.getAttribute('data-hps-src');
            try { var url = new URL(source, w.location.href); if (!['http:', 'https:'].includes(url.protocol) || (url.protocol !== 'https:' && url.origin !== w.location.origin)) return; }
            catch (ignore) { return; }
            if (element.tagName === 'IFRAME' || element.tagName === 'IMG') {
                element.setAttribute('src', source);
                element.removeAttribute('data-hps-src');
            } else if (element.tagName === 'SCRIPT' && element.type === 'text/plain') {
                var script = d.createElement('script'); script.src = source; script.async = true;
                element.replaceWith(script);
            }
        });
    }
    function hide() {
        if (root) root.hidden = true;
        if (returnFocus && returnFocus.isConnected) returnFocus.focus();
    }
    function show(panel, focus) {
        if (!root || bypass) return;
        root.hidden = false;
        root.querySelectorAll('[data-hps-panel]').forEach(function (element) { element.hidden = element.getAttribute('data-hps-panel') !== panel; });
        var heading = root.querySelector('[data-hps-panel="' + panel + '"] h2');
        if (heading) {
            root.setAttribute('aria-labelledby', heading.id || (heading.id = 'hps-consent-' + panel + '-title'));
            if (focus) heading.focus();
        }
    }
    function save(choice, source) {
        if (bypass) return;
        var previous = Object.assign({}, current), previousCTM = ctmAllowed();
        current = {analytics: choice.analytics === true, advertising: !gpc && choice.advertising === true, functional: choice.functional === true};
        var days = Math.max(1, Math.min(365, Number(config.days) || 180));
        var value = {schema: 1, version: config.version, choice: current, expires: Date.now() + days * 86400000};
        try { d.cookie = config.cookie + '=' + encodeURIComponent(JSON.stringify(value)) + '; Max-Age=' + (days * 86400) + '; Path=/; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : ''); } catch (ignore) {}
        stored = Object.assign({}, current);
        gtag('consent', 'update', googleState());
        publish(source);
        cleanup(previous, previousCTM);
        maybeLoad();
        releaseContent();
        hide();
        // Already-running vendor scripts cannot be unloaded. A reload removes their listeners.
        if ((previousCTM && !ctmAllowed()) || Object.keys(previous).some(function (key) { return previous[key] && !current[key]; })) w.location.reload();
    }
    function open() {
        if (!root || bypass) return;
        returnFocus = d.activeElement;
        root.querySelectorAll('[data-hps-category]').forEach(function (checkbox) { checkbox.checked = current[checkbox.getAttribute('data-hps-category')]; });
        show('preferences', true);
    }
    function init() {
        root = d.getElementById('hps-consent-root');
        releaseContent();
        if (!root) return;
        if (bypass) { root.hidden = true; return; }
        root.querySelectorAll('[data-hps-gpc]').forEach(function (p) { p.hidden = !gpc; });
        var ads = root.querySelector('[data-hps-category="advertising"]');
        if (ads && gpc) { ads.disabled = true; ads.checked = false; }
        if (!stored) show('banner', false);
        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-hps-action]');
            if (!button) return;
            var action = button.getAttribute('data-hps-action');
            if (action === 'accept') save({analytics: true, advertising: true, functional: true}, 'accept');
            if (action === 'reject') {
                returnFocus = button;
                if (config.confirmReject) show('confirm', true); else save(blank(), 'reject');
            }
            if (action === 'confirm-reject') save(blank(), 'reject');
            if (action === 'back') { if (stored) hide(); else show('banner', true); }
            if (action === 'preferences') open();
            if (action === 'save') {
                var choice = blank(); root.querySelectorAll('[data-hps-category]').forEach(function (box) { choice[box.getAttribute('data-hps-category')] = box.checked; });
                save(choice, 'preferences');
            }
        });
        root.addEventListener('keydown', function (event) { if (event.key === 'Escape') { if (stored) hide(); else show('banner', true); } });
        d.addEventListener('click', function (event) {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            var link = event.target.closest('a[href]');
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
            try {
                var url = new URL(link.href, w.location.href);
                if (url.origin === w.location.origin && url.pathname === w.location.pathname && url.search === w.location.search && url.hash === '#hps-consent-settings') {
                    event.preventDefault(); open();
                }
            } catch (ignore) { /* Other links retain normal navigation. */ }
        });
        function openFromURL() { if (w.location.hash === '#hps-consent-settings') open(); }
        w.addEventListener('hashchange', openFromURL);
        openFromURL();
    }
    if (gpc && stored && stored.advertising) cleanup(stored);
    w.HPSConsent = Object.freeze({open: open, getState: function () { return Object.assign({}, current, {gpc: gpc, bypass: bypass, mode: config.mode, hasChoice: !!stored, ctmAllowed: ctmAllowed(), gtmLoaded: loaded}); }});
    if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', init, {once: true}); else init();
})(window, document);
