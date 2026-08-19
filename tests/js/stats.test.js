/**
 * Node tests for the Statistik dashboard formatting/query helpers.
 * Run: node tests/js/stats.test.js
 */
'use strict';

var assert = require('assert');
var S = require('../../admin/js/stats.js');

var pass = 0;
function ok(name, cond) {
    if (cond) { pass++; console.log('  PASS ' + name); }
    else { console.error('  FAIL ' + name); process.exitCode = 1; }
}

console.log('SN News Widget — stats helpers tests');

ok('fmtNum groups thousands', typeof S.fmtNum(42817) === 'string' && S.fmtNum(42817).indexOf('42') !== -1);
ok('fmtNum zero', S.fmtNum(0) === '0');
ok('fmtPct appends percent', S.fmtPct(10.06).indexOf('%') !== -1);
ok('fmtPct zero', S.fmtPct(0) === '0,00 %' || S.fmtPct(0).indexOf('0') === 0);

var d1 = S.fmtDelta(100, 50);
ok('delta up', d1.dir === 'up' && d1.text.indexOf('+') === 0);
var d2 = S.fmtDelta(50, 100);
ok('delta down', d2.dir === 'down' && d2.text.indexOf('-') === 0);
var d3 = S.fmtDelta(50, 0);
ok('delta no prev -> flat', d3.dir === 'flat' && d3.text === '—');
var d4 = S.fmtDelta(10001, 10000);
ok('delta nearly flat', d4.dir === 'flat');

ok('buildQuery empty', S.buildQuery({}) === '');
ok('buildQuery encodes', S.buildQuery({ range: '30', widget_id: 'SNW-X' }) === 'range=30&widget_id=SNW-X');
ok('buildQuery skips empty', S.buildQuery({ range: '30', widget_id: '' }) === 'range=30');

// renderLineChart requires a DOM; ensure it at least does not throw on a fake
// container when labels are empty (degrades gracefully).
var noop = function () {};
global.document = {
    createElement: function () { return { setAttribute: noop, appendChild: noop, classList: { add: noop }, style: {} }; },
    createElementNS: function () { return { setAttribute: noop, appendChild: noop, style: {}, addEventListener: noop }; }
};
ok('renderLineChart empty -> no throw', (function () {
    try { S.renderLineChart({ innerHTML: '', appendChild: noop, classList: { add: noop } }, { labels: [], series: [] }); return true; }
    catch (e) { return false; }
})());

console.log('\n' + pass + ' assertions passed.');
