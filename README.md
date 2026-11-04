# HPS Consent

A reusable, self-hosted WordPress consent plugin for HPS client websites. No paid consent service, remote banner dependency, visitor telemetry, or per-site license key.

## Installation

1. Download **hps-consent.zip** from this repository's latest GitHub Release. Use the release asset, not GitHub's automatically generated Source code ZIP.
2. WordPress → Plugins → Add Plugin → Upload Plugin → install and activate.
3. Open **Settings → HPS Consent**. The plugin starts disabled so it cannot silently replace an existing consent system.
4. Remove all existing GTM loaders, including Elementor header snippets, GTM noscript iframes, theme injections and other GTM plugins. Keep a rollback copy.
5. Paste your GTM container ID or its installation snippet. The plugin extracts the ID and never executes arbitrary pasted code.
6. Configure and test your GTM container as described below. Add your privacy policy, brand styling, text and any intentional exceptions.
7. Exclude `hps-consent-config` and `assets/consent.js` from script-delay, async/defer and script-combining optimizers. The runtime must execute in the head before other tracking code. Do not add a GTM noscript fallback.
8. Enable HPS Consent, save, purge page/CDN caches, and test in a fresh browser.

Installing this plugin does not modify your GTM workspace. **GTM consent configuration remains required.**

## Basic and Advanced Consent Mode v2

| State | Basic | Advanced |
| --- | --- | --- |
| New visitor / rejected optional categories | GTM does not load | GTM loads with denied Google consent defaults |
| Accept all | GTM loads; optional categories granted | Consent updates to granted |
| Customize | Load GTM after any optional category is explicitly granted; only selected categories granted | Update selected categories |
| Page exception | Load GTM and grant optional categories for this page only | Same |
| GPC enabled in browser | Advertising stays denied, including after Accept all and on page exceptions | Same |

Defaults are queued before the container loads. Google signals include `analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`, `functionality_storage`, `personalization_storage` and `security_storage`. Security storage remains granted. Ads data redaction is enabled and URL passthrough is disabled. Advanced mode allows Google's consent-aware cookieless pings; it is not zero tracking before acceptance.

### Configure GTM before enabling

1. Enable Consent Overview in the GTM container. Audit **every tag**, including Custom HTML tags. Google consent signals do not automatically block Meta, Microsoft, LinkedIn, Amazon, TVSquared, CTM or other scripts.
2. Google Analytics tags: require `analytics_storage` before firing for Basic mode. For Advanced mode, use built-in Google consent behavior so Google can send consent-aware cookieless measurements before acceptance.
3. Advertising tags and attribution helpers: require appropriate advertising consent, typically `ad_storage`; Google's advertising data use also requires `ad_user_data` and `ad_personalization` where applicable. Configure Microsoft UET's own consent handling as well as its GTM gate. Do not enable Clarity as a side effect.
4. Optional functional tags: require `functionality_storage`. Never categorize an advertising attribution script as necessary just to avoid blocking it. Keep ordinary contact forms and a fallback phone number available without advertising consent.
5. Create a **Custom Event** trigger named `hps_consent_update`. Category-specific triggers can use these Data Layer Variables (Version 2): `hps_analytics`, `hps_advertising`, `hps_functional`. Compare them to boolean `true`.
6. Add consent-update triggers to tags blocked at page load so they can start after a choice. GTM does not automatically replay a blocked All Pages tag. Use **Once per page** for base/page-load tags to avoid duplicate pageviews when preferences are changed repeatedly; keep normal interaction/conversion triggers on their respective event tags.
7. Do not attach `hps_consent_update` to a Lead or purchase tag. Consent acceptance is not a conversion.
8. Publish and test with Tag Assistant and browser network inspection. Advanced mode is intentionally gated by a settings acknowledgment that this review has been completed.

`hps_consent_update` is pushed on initialization and after choices, with category booleans, `hps_consent_source`, `hps_consent_bypass`, and `hps_gpc`. On initial load it is queued before `gtm.js`. Google defaults/updates are always queued before the corresponding event. A `hps:consent` DOM event carries the same payload for custom site integrations. Read current state with `window.HPSConsent.getState()`; reopen preferences with `window.HPSConsent.open()`.

## Visitor experience

- Large Accept button; readable, underlined, keyboard-accessible Opt out and Customize controls.
- Optional confirmation before opting out: Yes confirms rejection; No, Accept Cookies accepts optional categories subject to GPC.
- Turn off opt-out confirmation for one-click rejection. **Unequal prominence and additional rejection steps can undermine valid consent in some jurisdictions.** Review the intended design for your audience; the plugin does not claim this requested flow is universally compliant.
- Necessary / Analytics / Advertising / Optional content categories; optional categories default off.
- Reopen settings from footer or page links using `#hps-consent-settings`, or the `[hps_consent_preferences]` shortcode. No floating control.
- Keyboard focus management and Escape handling, mobile layouts, and English/Spanish preference-interface labels. Banner copy and action labels are editable per site.

## Styling

Configure primary/focus color, Accept background/text, background, text, link, border and shadow colors. Each color has a hex picker and opacity control. Configure corner radius, solid border width, shadow offset/blur/spread and banner/box placement. The admin screen has an immediate styling preview.

When Elementor is active, the current kit's system and custom global colors are listed. Global selections use Elementor CSS variables with a stored-kit fallback; changing a kit color updates the banner. Removing Elementor or its kit falls back to safe default colors. The plugin otherwise works without Elementor.

## Page exceptions — intentional bypass

One same-site path or URL per line. `/offer/` matches exactly (with or without trailing slash); `/offer/*` also matches descendants. Query strings and fragments are ignored on visits; settings must not include them. External domains are rejected.

**An exception suppresses the banner, grants consent and loads GTM even after a previous rejection.** It does not remember acceptance for other pages. GPC still denies advertising when enabled. This is the owner's requested override behavior, not a recommended default. No exceptions ship with the plugin. Do not confuse these with pages where tracking should be disabled.

## Optional embeds and scripts outside GTM

The plugin does not intercept arbitrary scripts or network requests. To delay an optional iframe, omit `src` and use:

```html
<iframe title="Location map" data-hps-src="https://example.com/map" data-hps-consent="functional"></iframe>
```

External scripts can use `<script type="text/plain" data-hps-src="https://example.com/script.js" data-hps-consent="advertising"></script>`. Only `analytics`, `advertising` and `functional` categories are accepted. This helper does not execute inline code. Third-party iframe contents and server-side tracking need separate configuration. Nothing automatically blocks a script already inserted by another plugin.

## Reopen privacy settings anywhere

Use the same URL on any link or Elementor button: `#hps-consent-settings`. The plugin opens the preferences panel without granting consent. You can also link to a full page URL ending in this fragment, such as `https://example.com/privacy-policy/#hps-consent-settings`; settings open when that page loads. The shortcode `[hps_consent_preferences]` outputs the same link. No floating button is added. Keep a visible footer link on every page so visitors can revisit their choice.

In the opt-out confirmation, **Yes, opt out** rejects optional categories; **No, Accept Cookies** accepts all optional categories (subject to GPC advertising restrictions). The preferences Back button still closes preferences or returns to the first banner.

## Withdrawal and storage

Choices are stored in a first-party, host-only SameSite=Lax cookie with an expiry and policy version; HTTPS uses Secure. Invalid, expired or outdated choices prompt again. Default expiry is 180 days, configurable from 1–365. The plugin attempts to delete known first-party GA/Google Ads/Meta/UET cookies when the relevant category is withdrawn and clears the legacy Meta attribution local-storage values. It cannot delete third-party, HttpOnly, or unknown vendor cookies.

Withdrawing an enabled category updates consent and reloads the page so previously loaded scripts lose their event listeners. Future category loading depends on correctly configured GTM gates. Already-transmitted data cannot be recalled. No central consent ledger is provided in this release; the browser choice is not presented as an audit-ready server log.

Global Privacy Control is honored for advertising by default. The plugin does not claim certification as a Google CMP, support for IAB TCF, automatic legal compliance or authorization to disclose protected health information. Do not send patient form answers or sensitive data to advertising vendors merely because a visitor accepted cookies.

## GitHub updates

The plugin's `Update URI` points to this public repository. WordPress checks the latest published, non-prerelease GitHub Release through the normal update mechanism. Only the release asset named **hps-consent.zip** from this exact repository is accepted. No credentials are needed on client websites. Update checks send a generic plugin User-Agent, not site URLs, settings or consent records. Network metadata such as the server's IP is necessarily visible to GitHub.

1. Change the version in `hps-consent.php`, `package.json` and `readme.txt`.
2. Run `npm ci`, `npm run check`, `npm test`, `npm run test:php`, `npm run build`.
3. To publish, change `.github/release-version` to the same version and commit it with the versioned source to `main`. This explicit marker requests a release after checks pass. A matching `v1.0.1` tag push also requests a release.
4. The release workflow runs checks and publishes `hps-consent.zip` plus its SHA-256 checksum. The ZIP has a stable `hps-consent/` root, preserving the WordPress plugin path during updates.
5. WordPress's scheduled update check surfaces the release (metadata cached for up to 12 hours). Administrators can use normal plugin updates and WordPress's optional auto-update controls. Ordinary branch commits do not update client sites. Existing releases are never overwritten by this workflow.

Protect release publishing access: a release can deliver executable PHP to every website that installs it. Release a tested update rather than changing repository ownership casually; if the repository moves, ship an updater URL migration before removing the old location.

## Development and validation

Requires PHP 8.0+ and WordPress 6.5+. Development requires Node 22+.

```sh
npm ci
npm run check
npm test
npm run test:php
npm run build
```

Tests cover Basic and Advanced mode, consent ordering, opt-out confirmation, granular choices, GPC, exceptions, cookie expiry/versioning, duplicate loading, optional embeds, sanitizer injection boundaries and public-release validation. A PHP contract harness is included; it is not a replacement for WordPress staging and Tag Assistant testing on each client container.

No client IDs, account credentials, patient information or site-specific configuration are committed to this repository. License: GPL-2.0-or-later.

## Optional CTM pre-choice operation (Advanced only)

Enable the explicit CTM-before-choice setting only when intentional. Normal CTM tracking can use cookies; this is not cookieless tracking or a Google consent grant. In GTM create a Version 2 Data Layer Variable for `hps_ctm_allowed` (default false), and fire the CTM base tag once per page on `hps_consent_update` filtered to that variable equal to true. Remove additional Google consent requirements from that CTM tag only; retain category gates on other vendors. Do not use an unfiltered consent-update trigger.

A remembered analytics denial blocks CTM. Saving preferences with analytics off or confirming rejection revokes CTM and reloads, including when CTM started before the first choice. Known accessible first-party CTM cookies are removed where possible; third-party/HttpOnly storage and data already sent cannot be recalled. Keep ordinary forms and fallback telephone numbers functional without CTM. Disclose the pre-choice behavior and Google Advanced cookieless pings in the site policy and banner. Existing consent records are honored; changing mode does not erase a rejection.

`HPSConsent.getState()` also reports `hasChoice` and `ctmAllowed`. The `hps_ctm_allowed` payload never grants Google's analytics or advertising consent.

## Microsoft UET Advanced mode with HPS Consent

An optional [GTM Custom HTML example](examples/microsoft-uet-advanced.html) connects the existing HPS public state/event API to Microsoft UET. This does not require a plugin update. Replace the example's UET ID with the correct ID for each client site. It sends an explicit denied default before loading Microsoft's library, applies remembered advertising acceptance, and forwards later `hps:consent` advertising changes. Automatic consent inference is disabled so the explicit HPS choice, including GPC, controls UET. Accessible known UET local-storage backups are removed when advertising is denied; Microsoft's `_uetmsdns` opt-out remains untouched.

For a reviewed **Advanced-mode** container, replace its existing UET base tag with this example, fire on **Initialization - All Pages**, select **No additional consent required** for this consent-aware base tag, and use **Once per page**. Remove the previous UET loader to prevent duplicates. Other vendors still need their own consent gates. Do not reuse this Advanced trigger setup in a Basic-mode container: Basic must block UET until advertising acceptance and start it on an advertising-granted update.

Keep Clarity disabled separately in Microsoft Advertising; UET consent is not a Clarity opt-out. Test fresh choice, remembered rejection, acceptance and withdrawal. Denied UET pageviews should have `asc=D`, no persistent `sid`/`vid`/`msclkid` parameters, and no new first-party UET cookies. Accepted pageviews should have `asc=G`. Microsoft may use a separate cookieless endpoint. Cookieless does not mean no data transmission or exemption from applicable privacy obligations. Disclose both pre-choice and post-rejection measurement in the banner and policy. Do not transmit patient answers or contact details in custom UET events.

References: [Microsoft's implementation guide](https://help.ads.microsoft.com/#apex/ads/en/60119/1) and [Advanced-mode guidance](https://www.about.ads.microsoft.com/en/blog/post/february-2026/advanced-consent-mode-preserving-accurate-measurement-while-respecting-user-privacy).
