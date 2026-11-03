'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const {zipSync} = require('fflate');
const root = path.resolve(__dirname, '..');
const files = {};
for (const file of ['hps-consent.php', 'readme.txt', 'LICENSE', 'README.md']) {
    files['hps-consent/' + file] = new Uint8Array(fs.readFileSync(path.join(root, file)));
}
for (const folder of ['assets', 'includes']) {
    for (const file of fs.readdirSync(path.join(root, folder))) {
        files['hps-consent/' + folder + '/' + file] = new Uint8Array(fs.readFileSync(path.join(root, folder, file)));
    }
}
fs.mkdirSync(path.join(root, 'dist'), {recursive: true});
const zip = zipSync(files, {level: 9});
fs.writeFileSync(path.join(root, 'dist/hps-consent.zip'), zip);
fs.writeFileSync(path.join(root, 'dist/hps-consent.zip.sha256'), crypto.createHash('sha256').update(zip).digest('hex') + '  hps-consent.zip\n');
console.log(`Built dist/hps-consent.zip (${zip.length} bytes), ${Object.keys(files).length} files, stable hps-consent/ root.`);
