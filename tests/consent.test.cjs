'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const script = fs.readFileSync(path.join(__dirname, '../assets/consent.js'), 'utf8');
const markup = `<div id="hps-consent-root" hidden><div data-hps-panel="banner"><h2 id="hps-consent-title" tabindex="-1">Privacy</h2><button data-hps-action="accept">Accept</button><button data-hps-action="reject">Opt out</button><button data-hps-action="preferences">Customize</button><p data-hps-gpc hidden>GPC</p></div><div data-hps-panel="confirm" hidden><h2 tabindex="-1">Confirm</h2><button data-hps-action="confirm-reject">Yes</button><button data-hps-action="back">No</button></div><div data-hps-panel="preferences" hidden><h2 tabindex="-1">Preferences</h2><input type="checkbox" data-hps-category="analytics"><input type="checkbox" data-hps-category="advertising"><input type="checkbox" data-hps-category="functional"><button data-hps-action="save">Save</button><button data-hps-action="back">Back</button></div></div><button id="hps-consent-revisit" hidden>Privacy choices</button><a href="#hps-consent-settings">Footer privacy</a>`;
function browser(options = {}) {
    const dom = new JSDOM('<!doctype html><html><head></head><body>' + markup + (options.extra || '') + '</body></html>', {url: options.url || 'https://clinic.test/service/', runScripts: 'outside-only'});
    const w = dom.window;
    w.HPSConsentConfig = Object.assign({id: 'GTM-TEST123', mode: 'basic', cookie: 'hps_consent_v1', version: '1', days: 180, exceptions: [], gpc: true, confirmReject: true}, options.config);
    if (options.gpc) Object.defineProperty(w.navigator, 'globalPrivacyControl', {value: true});
    if (options.cookie) w.document.cookie = 'hps_consent_v1=' + encodeURIComponent(JSON.stringify(options.cookie)) + '; Path=/';
    w.eval(script);
    w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    const click = (action) => w.document.querySelector('[data-hps-action="' + action + '"]').click();
    const commands = () => w.dataLayer.filter(e => e[0] === 'consent');
    const count = () => w.document.querySelectorAll('script[src*="googletagmanager.com/gtm.js"]').length;
    return {w, dom, click, commands, count};
}
function remembered(choice, overrides = {}) { return Object.assign({schema: 1, version: '1', expires: Date.now() + 86400000, choice}, overrides); }

test('Basic: first visit has denied defaults, no GTM request and visible banner', () => {
    const b = browser(); assert.equal(b.count(), 0); assert.equal(b.commands()[0][1], 'default');
    assert.equal(b.commands()[0][2].analytics_storage, 'denied'); assert.equal(b.commands()[0][2].ad_user_data, 'denied');
    assert.equal(b.w.document.querySelector('#hps-consent-root').hidden, false); b.dom.window.close();
});
test('Accept loads exactly one GTM, updates consent before loading, persists choice', () => {
    const b = browser(); b.click('accept'); b.click('accept'); assert.equal(b.count(), 1);
    const update = b.w.dataLayer.findIndex(e => e[0] === 'consent' && e[1] === 'update');
    const gtm = b.w.dataLayer.findIndex(e => e.event === 'gtm.js'); assert.ok(update < gtm);
    assert.equal(b.commands().at(-1)[2].ad_personalization, 'granted'); assert.match(b.w.document.cookie, /hps_consent_v1=/);
    assert.equal(b.w.document.querySelector('#hps-consent-root').hidden, true); b.dom.window.close();
});
test('Requested opt-out flow: confirmation, No returns, Yes rejects without GTM', () => {
    const b = browser(); b.click('reject'); assert.equal(b.w.document.querySelector('[data-hps-panel="confirm"]').hidden, false);
    assert.equal(b.count(), 0); b.click('back'); assert.equal(b.w.document.querySelector('[data-hps-panel="banner"]').hidden, false);
    b.click('reject'); b.click('confirm-reject'); assert.equal(b.count(), 0); assert.equal(b.commands().at(-1)[2].ad_storage, 'denied'); b.dom.window.close();
});
test('One-click rejection is available', () => {
    const b = browser({config: {confirmReject: false}}); b.click('reject'); assert.equal(b.count(), 0); assert.equal(b.w.document.querySelector('#hps-consent-root').hidden, true); b.dom.window.close();
});
test('Advanced: GTM loads immediately after denied defaults', () => {
    const b = browser({config: {mode: 'advanced'}}); assert.equal(b.count(), 1); assert.equal(b.commands()[0][2].ad_storage, 'denied');
    assert.equal(b.w.dataLayer[0][0], 'consent'); b.click('reject'); b.click('confirm-reject'); assert.equal(b.count(), 1); b.dom.window.close();
});
test('Remembered rejection suppresses banner and GTM, preferences can reopen', () => {
    const b = browser({cookie: remembered({analytics: false, advertising: false, functional: false})});
    assert.equal(b.count(), 0); assert.equal(b.w.document.querySelector('#hps-consent-root').hidden, true);
    b.w.document.querySelector('a').click(); assert.equal(b.w.document.querySelector('[data-hps-panel="preferences"]').hidden, false); b.dom.window.close();
});
test('Remembered analytics-only loads GTM but advertising remains denied', () => {
    const b = browser({cookie: remembered({analytics: true, advertising: false, functional: false})}); assert.equal(b.count(), 1);
    assert.equal(b.commands()[0][2].analytics_storage, 'granted'); assert.equal(b.commands()[0][2].ad_storage, 'denied'); b.dom.window.close();
});
test('Granular preferences grant analytics only', () => {
    const b = browser(); b.click('preferences'); b.w.document.querySelector('[data-hps-category="analytics"]').checked = true; b.click('save');
    assert.equal(b.count(), 1); assert.equal(b.commands().at(-1)[2].analytics_storage, 'granted'); assert.equal(b.commands().at(-1)[2].ad_storage, 'denied'); b.dom.window.close();
});
test('GPC blocks advertising despite Accept all and disables its toggle', () => {
    const b = browser({gpc: true}); b.click('accept'); assert.equal(b.w.HPSConsent.getState().advertising, false); assert.equal(b.commands().at(-1)[2].ad_storage, 'denied');
    assert.equal(b.w.document.querySelector('[data-hps-category="advertising"]').disabled, true); b.dom.window.close();
});
test('Exact exception overrides remembered rejection without persisting acceptance', () => {
    const cookie = remembered({analytics: false, advertising: false, functional: false});
    const b = browser({config: {exceptions: ['/campaign/']}, url: 'https://clinic.test/campaign/?utm_source=test#section', cookie});
    assert.equal(b.count(), 1); assert.equal(b.w.HPSConsent.getState().bypass, true); assert.equal(b.commands()[0][2].ad_storage, 'granted');
    assert.equal(b.w.document.querySelector('#hps-consent-root').hidden, true);
    const saved = JSON.parse(decodeURIComponent(b.w.document.cookie.split('=')[1])); assert.equal(saved.choice.advertising, false); b.dom.window.close();
});
test('Exception respects GPC; wildcard matches descendants but not sibling prefixes', () => {
    const a = browser({gpc: true, config: {exceptions: ['/campaign/*']}, url: 'https://clinic.test/campaign/one/'});
    assert.equal(a.count(), 1); assert.equal(a.commands()[0][2].ad_storage, 'denied'); a.dom.window.close();
    const b = browser({config: {exceptions: ['/campaign/*']}, url: 'https://clinic.test/campaign-other/'}); assert.equal(b.count(), 0); b.dom.window.close();
});
test('Expired, changed-version and malformed choices require new consent', () => {
    for (const cookie of [remembered({analytics: true, advertising: true, functional: true}, {expires: Date.now() - 10}), remembered({analytics: true, advertising: true, functional: true}, {version: 'old'}), remembered({analytics: 'true', advertising: true, functional: true})]) {
        const b = browser({cookie}); assert.equal(b.count(), 0); assert.equal(b.w.document.querySelector('#hps-consent-root').hidden, false); b.dom.window.close();
    }
});
test('Invalid container cannot inject scripts', () => {
    const b = browser({config: {id: '"><script>alert(1)</script>', mode: 'advanced'}}); assert.equal(b.count(), 0); b.dom.window.close();
});
test('Optional iframe waits for its category and releases after explicit consent', () => {
    const b = browser({extra: '<iframe data-hps-src="https://video.test/embed" data-hps-consent="functional"></iframe>'});
    assert.equal(b.w.document.querySelector('iframe').getAttribute('src'), null); b.click('preferences');
    b.w.document.querySelector('[data-hps-category="functional"]').checked = true; b.click('save');
    assert.equal(b.w.document.querySelector('iframe').src, 'https://video.test/embed'); b.dom.window.close();
});
test('Never releases javascript: embedded sources or unknown categories', () => {
    const b = browser({extra: '<iframe data-hps-src="javascript:alert(1)" data-hps-consent="functional"></iframe><iframe data-hps-src="https://video.test/embed" data-hps-consent="unknown"></iframe>'});
    b.click('accept'); b.w.document.querySelectorAll('iframe').forEach(e => assert.equal(e.getAttribute('src'), null)); b.dom.window.close();
});
test('Script is idempotent and public state cannot mutate internal consent', () => {
    const b = browser(); b.w.HPSConsent.getState().advertising = true; assert.equal(b.w.HPSConsent.getState().advertising, false);
    const count = b.w.dataLayer.length; b.w.eval(script); assert.equal(b.w.dataLayer.length, count); b.dom.window.close();
});
