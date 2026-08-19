/**
 * Node tests for the public widget telemetry helpers + viewability logic.
 * Run: node tests/js/telemetry.test.js
 */
'use strict';

var assert = require('assert');
var W = require('../../public/js/widget.js');
var T = W.telemetry;

var pass = 0;
function ok(name, cond) {
    if (cond) { pass++; console.log('  PASS ' + name); }
    else { console.error('  FAIL ' + name); process.exitCode = 1; }
}

console.log('SN News Widget — telemetry client tests');

// --- endpoint derivation ---
ok('endpoint from api base', T.endpointFor({ api: 'https://src.example/wp-json/wp/v2' }) === 'https://src.example/sn-widget/telemetry/v1');
ok('endpoint empty when no api', T.endpointFor({}) === '');
ok('endpoint from custom origin', T.endpointFor({ api: 'https://news.example.org/wp-json/wp/v2' }) === 'https://news.example.org/sn-widget/telemetry/v1');

// --- error code mapping ---
ok('err AbortError -> REST_TIMEOUT', T.errorCode({ name: 'AbortError' }) === 'REST_TIMEOUT');
ok('err HTTP 403 -> REST_403', T.errorCode(new Error('HTTP 403')) === 'REST_403');
ok('err HTTP 500 -> REST_500', T.errorCode(new Error('HTTP 500')) === 'REST_500');
ok('err network -> NETWORK_ERROR', T.errorCode(new Error('NetworkError')) === 'NETWORK_ERROR');
ok('err cors -> CORS_ERROR', T.errorCode(new Error('CORS error')) === 'CORS_ERROR');
ok('err generic -> RENDER_ERROR', T.errorCode(new Error('boom')) === 'RENDER_ERROR');
ok('err null -> UNKNOWN', T.errorCode(null) === 'UNKNOWN');

// --- viewability logic (>=50% for >=1000ms => exactly one send) ---
// Mock IntersectionObserver + capture setTimeout so we drive timing manually.
var capturedTimer = null;
global.setTimeout = function (fn) { capturedTimer = fn; return 1; };
global.clearTimeout = function () { capturedTimer = null; };

function makeIO() {
    var cbs = [];
    function IO(cb) {
        this.cb = cb;
        this.observe = function () {};
        this.disconnect = function () {};
        this.trigger = function (entries) { this.cb(entries, this); };
    }
    return IO;
}
global.IntersectionObserver = makeIO();

var sendCalls = [];
Object.defineProperty(global, 'navigator', {
    configurable: true,
    value: {
        sendBeacon: function (url, blob) {
            sendCalls.push({ url: url, blob: blob });
            return true;
        }
    }
});
global.fetch = function () { return Promise.resolve(); };

var config = { api: 'https://src.example/wp-json/wp/v2', widget_id: 'SNW-8F3K2', partner: 'asv', layout: 'list', mode: 'latest' };
var ctx = T.context(config, { __snwInstanceId: 'i1' });

// Case A: 50% visible for 1000ms -> exactly 1 impression.
sendCalls = []; capturedTimer = null;
var elA = {};
T.observeViewable(elA, config, ctx);
ok('A: timer not set before visible', capturedTimer === null);
var ioA = elA.__snwViewableObserver;
ioA.trigger([{ isIntersecting: true, intersectionRatio: 0.5 }]);
ok('A: timer set once >=50%', capturedTimer !== null && sendCalls.length === 0);
capturedTimer(); // simulate 1000ms elapsed
ok('A: impression sent after 1000ms', sendCalls.length === 1);
ok('A: payload is viewable_impression', sendCalls[0].blob && sendCalls[0].url.indexOf('/event') !== -1);
// Triggering again must not double-send.
ioA.trigger([{ isIntersecting: true, intersectionRatio: 1 }]);
capturedTimer && capturedTimer();
ok('A: no second impression', sendCalls.length === 1);

// Case B: never visible -> 0 impressions.
sendCalls = []; capturedTimer = null;
var elB = {};
T.observeViewable(elB, config, ctx);
var ioB = elB.__snwViewableObserver;
ioB.trigger([{ isIntersecting: false, intersectionRatio: 0.1 }]);
ok('B: no timer when <50%', capturedTimer === null && sendCalls.length === 0);

// Case C: 49% for a long time -> 0 impressions.
sendCalls = []; capturedTimer = null;
var elC = {};
T.observeViewable(elC, config, ctx);
var ioC = elC.__snwViewableObserver;
ioC.trigger([{ isIntersecting: true, intersectionRatio: 0.49 }]);
ok('C: 49% does not arm timer', capturedTimer === null);

// Case D: 50% for 500ms then drops below -> 0 impressions.
sendCalls = []; capturedTimer = null;
var elD = {};
T.observeViewable(elD, config, ctx);
var ioD = elD.__snwViewableObserver;
ioD.trigger([{ isIntersecting: true, intersectionRatio: 0.5 }]);
ok('D: timer armed at 50%', capturedTimer !== null);
ioD.trigger([{ isIntersecting: false, intersectionRatio: 0.1 }]);
ok('D: timer cleared when dropping', capturedTimer === null);
capturedTimer && capturedTimer();
ok('D: no impression after drop', sendCalls.length === 0);

// Case E: multiple widgets get distinct instance ids independent.
var elE1 = { __snwInstanceId: 'a' };
var elE2 = { __snwInstanceId: 'b' };
ok('instance ids independent', T.context(config, elE1).instance_id === 'a' && T.context(config, elE2).instance_id === 'b');

console.log('\n' + pass + ' assertions passed.');
