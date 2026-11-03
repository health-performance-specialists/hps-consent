import fs from 'node:fs';
import path from 'node:path';
import {PHP} from '@php-wasm/universal';
import {loadNodeRuntime} from '@php-wasm/node';
const root = path.resolve(import.meta.dirname, '..');
const php = new PHP(await loadNodeRuntime('8.0', {emscriptenOptions: {processId: 1}}));
php.mkdir('/plugin'); php.mkdir('/plugin/includes'); php.mkdir('/plugin/tests');
for (const name of ['hps-consent.php', 'includes/settings.php', 'includes/frontend.php', 'includes/updater.php', 'tests/php.php']) {
    php.writeFile('/plugin/' + name, fs.readFileSync(path.join(root, name)));
}
const result = await php.run({scriptPath: '/plugin/tests/php.php'});
console.log(result.text);
if (result.errors || result.exitCode !== 0 || !result.text.includes('PHP checks passed')) {
    console.error(result.errors); process.exitCode = 1;
}
// Persist the actual PHP-rendered markup for local visual checks, not a UI mock.
fs.mkdirSync(path.join(root, 'tmp'), {recursive: true});
if (php.fileExists('/preview.html')) fs.writeFileSync(path.join(root, 'tmp/preview.html'), php.readFileAsText('/preview.html'));
php.exit();
