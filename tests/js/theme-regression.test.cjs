const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const sidebar = fs.readFileSync(path.join(root, 'resources/views/layouts/sidebar.blade.php'), 'utf8');
const theme = fs.readFileSync(path.join(root, 'resources/views/studio/theme.blade.php'), 'utf8');
const bundle = fs.readFileSync(path.join(root, 'assets/js/core/libs.min.js'), 'utf8');
const qrScript = sidebar.match(/<script id="qr-download-script">([\s\S]*?)<\/script>/)[1];

function runQrScript({ button = false, image = true } = {}) {
    const listeners = {};
    const clicks = [];
    const removed = [];
    const elements = {
        downloadButton: button ? { addEventListener: (event, handler) => { listeners[event] = handler; } } : null,
        generatedImage: image ? { src: 'data:image/png;base64,qr', getAttribute: () => null } : null,
    };
    const document = {
        addEventListener: (event, handler) => { listeners[event] = handler; },
        getElementById: (id) => elements[id] ?? null,
        createElement: () => ({ click() { clicks.push({ href: this.href, download: this.download }); } }),
        body: {
            appendChild: () => {},
            removeChild: (node) => removed.push(node),
        },
    };
    vm.runInNewContext(qrScript, { document });
    listeners.DOMContentLoaded();
    return { listeners, clicks, removed };
}

test('SVG QR without a download button does not throw', () => {
    assert.doesNotThrow(() => runQrScript());
});

test('missing QR image does not register a download handler', () => {
    const { listeners } = runQrScript({ button: true, image: false });
    assert.equal(listeners.click, undefined);
});

test('PNG QR download still creates and removes a download link', () => {
    const { listeners, clicks, removed } = runQrScript({ button: true });
    assert.equal(typeof listeners.click, 'function');
    listeners.click();
    assert.deepEqual(clicks, [{ href: 'data:image/png;base64,qr', download: 'generated_image.png' }]);
    assert.equal(removed.length, 1);
});

test('theme reuses the sidebar jQuery bundle and guards the preview iframe', () => {
    assert.doesNotMatch(theme, /jquery-1\.12\.4\.min\.js/);
    assert.match(theme, /@push\('sidebar-scripts'\)/);
    assert.match(theme, /jQuery\('#frPreview'\)\.on\('load'/);
});

test('shared bundle uses jQuery 3.7.1 and confines the legacy unload listener to IE/Edge Legacy', () => {
    assert.match(bundle, /jQuery v3\.7\.1/);
    // jQuery 3.7.1 only registers Sizzle's iframe unload cleanup when
    // msMatchesSelector exists (IE/Edge Legacy), so modern browsers never
    // trigger the Permissions Policy violation.
    assert.match(bundle, /msMatchesSelector&&[\s\S]{0,120}?addEventListener\("unload"/);
    assert.doesNotMatch(bundle, /attachEvent\(["']onunload["']/);
    assert.match(bundle, /No options passed to Waypoint constructor/);
    assert.match(bundle, /Popper=\{\}/);
});
