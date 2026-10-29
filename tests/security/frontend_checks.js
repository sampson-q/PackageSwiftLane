'use strict';
// Exercise the real CSRF prefilter with a minimal browser/jQuery boundary, without loading the app.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..');
const footer = fs.readFileSync(path.join(root, 'views/inc/footer.php'), 'utf8');
const block = [...footer.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(text => text.includes('$.ajaxPrefilter'));
assert(block, 'real CSRF prefilter exists');
let prefilter;
function jquery() { return {attr: () => 'fixture-csrf-token'}; }
jquery.ajaxSetup = true;
jquery.ajaxPrefilter = callback => { prefilter = callback; };
const location = {href: 'https://portal.example.invalid/app/index.php', origin: 'https://portal.example.invalid'};
vm.runInNewContext(block, {window: {jQuery: jquery, location}, URL});
let checks = 0;
function headers(options) {
    const found = [];
    prefilter(options, {}, {setRequestHeader: (name, value) => found.push([name, value])});
    return found;
}
assert.deepEqual(headers({type: 'POST', url: 'ajax/save.php'}), [['X-CSRF-Token', 'fixture-csrf-token']]); checks++;
assert.deepEqual(headers({type: 'POST', url: 'https://external.example.invalid/', crossDomain: false}), []); checks++;
assert.deepEqual(headers({type: 'GET', url: 'ajax/list.php'}), []); checks++;
const provenance = JSON.parse(fs.readFileSync(path.join(root, 'docs/SECURITY_ASSET_PROVENANCE.json')));
for (const artifact of provenance.updated_files) {
    const bytes = fs.readFileSync(path.join(root, artifact.path));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), artifact.sha256);
    checks++;
}
console.log(`Frontend security regressions passed: ${checks} checks (prefilter and artifact integrity; full UI requires staging).`);
