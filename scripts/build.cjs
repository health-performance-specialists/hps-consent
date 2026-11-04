'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const {zipSync} = require('fflate');
const root = path.resolve(__dirname, '..');
const files = {};
// Deliberate allowlist: never ship an accidentally added credential, test, or development file.
for (const file of ['hps-consent.php', 'readme.txt', 'LICENSE', 'README.md',
    'assets/admin.css', 'assets/admin.js', 'assets/consent.css', 'assets/consent.js',
    'includes/frontend.php', 'includes/settings.php', 'includes/updater.php']) {
    const source = path.join(root, file);
    if (!fs.lstatSync(source).isFile()) throw new Error('Release entries must be regular files: ' + file);
    files['hps-consent/' + file] = new Uint8Array(fs.readFileSync(path.join(root, file)));
}
fs.mkdirSync(path.join(root, 'dist'), {recursive: true});
const zip = zipSync(files, {level: 9});
fs.writeFileSync(path.join(root, 'dist/hps-consent.zip'), zip);
fs.writeFileSync(path.join(root, 'dist/hps-consent.zip.sha256'), crypto.createHash('sha256').update(zip).digest('hex') + '  hps-consent.zip\n');
console.log(`Built dist/hps-consent.zip (${zip.length} bytes), ${Object.keys(files).length} files, stable hps-consent/ root.`);
