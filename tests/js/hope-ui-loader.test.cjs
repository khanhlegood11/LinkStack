const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'assets/js/hope-ui.js'), 'utf8');
const loaderInitSource = source.match(/const loaderInit = \(\) => \{[\s\S]*?\n\}/)[0];

function runLoaderInit({ loader = null } = {}) {
    const timers = [];
    const classes = new Set();
    const document = {
        querySelector: (selector) => (selector === '.loader' ? loader : null),
    };
    const setTimeout = (callback, delay) => {
        timers.push({ callback, delay });
        return timers.length;
    };
    vm.runInNewContext(`${loaderInitSource}; loaderInit();`, { document, setTimeout });
    return { timers, classes };
}

test('loaderInit does nothing when the page has no loader element', () => {
    const { timers } = runLoaderInit();
    assert.equal(timers.length, 0);
});

test('loaderInit still fades and hides an existing loader', () => {
    const loader = {
        classList: {
            add: (...names) => names.forEach((name) => loader.classes.add(name)),
        },
        classes: new Set(),
    };
    const { timers } = runLoaderInit({ loader });
    assert.equal(timers.length, 1);
    assert.equal(timers[0].delay, 500);
    timers[0].callback();
    assert.deepEqual([...loader.classes], ['animate__animated', 'animate__fadeOut']);
    assert.equal(timers.length, 2);
    assert.equal(timers[1].delay, 500);
    timers[1].callback();
    assert.deepEqual([...loader.classes], ['animate__animated', 'animate__fadeOut', 'd-none']);
});

test('DOMContentLoaded initialization calls loaderInit', () => {
    assert.match(source, /document\.addEventListener\('DOMContentLoaded', \(event\) => \{\s*resizePlugins\(\)\s*loaderInit\(\)\s*\}\);/);
});
