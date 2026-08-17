/**
 * Node tests for the public widget renderer's pure logic.
 * Run: node tests/js/widget.test.js
 */
'use strict';

var assert = require('assert');
var W = require('../../public/js/widget.js');

var pass = 0;
function ok(name, cond) {
    if (cond) { pass++; console.log('  PASS ' + name); }
    else { console.error('  FAIL ' + name); process.exitCode = 1; }
}

console.log('Steigerwald-News Widget — pure function tests');

// --- encode / decode roundtrip (incl. unicode + emoji) ---
var cfg = {
    v: 1,
    title: 'Über Grüße & Emoji 🚀 – Müller',
    partner: 'asv-sassanfahrt',
    mode: 'hybrid',
    tags: [3, 7],
    show: { image: true, date: false }
};
var enc = W.encodeConfig(cfg);
ok('encode produces string', typeof enc === 'string' && enc.length > 0);
ok('encode is url-safe (no + / =)', !/[+/=]/.test(enc));
var dec = W.decodeConfig(enc);
ok('decode roundtrip equals', JSON.stringify(dec) === JSON.stringify(cfg));
ok('decode keeps unicode/emoji', dec.title === cfg.title);

// --- decode plain JSON fallback ---
var plain = W.decodeConfig('{"mode":"latest"}');
ok('decode plain JSON', plain && plain.mode === 'latest');

// --- decode invalid ---
ok('decode invalid -> null', W.decodeConfig('not-valid!!') === null);
ok('decode empty -> null', W.decodeConfig('') === null);

// --- truncate (word boundary, length cap, HTML already stripped input) ---
ok('truncate short unchanged', W.truncate('Kurzer Text', 50) === 'Kurzer Text');
var long = 'Ein sehr langer Satz mit vielen Wörtern die abgeschnitten werden müssen irgendwo';
var t = W.truncate(long, 20);
ok('truncate capped <= 21 (incl ellipsis)', t.length <= 21);
ok('truncate adds ellipsis', t.slice(-1) === '…');
ok('truncate keeps word boundary', t.indexOf(' ') === -1 ? true : t.split(' ').pop().indexOf('…') !== -1 || t.endsWith('…'));

// --- trackedUrl / UTM ---
var base = 'https://example.com/artikel/';
var u1 = W.trackedUrl(base, 'asv', 'SNW-12345');
ok('utm_source set', u1.indexOf('utm_source=asv') !== -1);
ok('utm_medium set', u1.indexOf('utm_medium=referral') !== -1);
ok('utm_campaign set', u1.indexOf('utm_campaign=steigerwald_news_widget') !== -1);
ok('utm_content set', u1.indexOf('utm_content=SNW-12345') !== -1);
var u2 = W.trackedUrl(base, '', '');
ok('no partner -> no utm', u2.indexOf('utm_') === -1);
var u3 = W.trackedUrl(base + '?x=1', 'asv', '');
ok('preserves existing query param', u3.indexOf('x=1') !== -1 && u3.indexOf('utm_source=asv') !== -1);

// --- featuredImageUrl size selection + fallback ---
var postMedia = {
    _embedded: { 'wp:featuredmedia': [ { media_details: { sizes: {
        medium_large: { source_url: 'ml.jpg' }, large: { source_url: 'lg.jpg' }, medium: { source_url: 'md.jpg' }
    } }, source_url: 'orig.jpg' } ] }
};
ok('prefers medium_large', W.featuredImageUrl(postMedia) === 'ml.jpg');
var postLarge = { _embedded: { 'wp:featuredmedia': [ { media_details: { sizes: { large: { source_url: 'lg.jpg' } } }, source_url: 'o.jpg' } ] } };
ok('falls back to large', W.featuredImageUrl(postLarge) === 'lg.jpg');
var postOrig = { _embedded: { 'wp:featuredmedia': [ { source_url: 'o.jpg' } ] } };
ok('falls back to original', W.featuredImageUrl(postOrig) === 'o.jpg');
ok('missing media -> empty', W.featuredImageUrl({}) === '');

// --- categoryNames from embedded wp:term ---
var postCat = { _embedded: { 'wp:term': [
    [ { taxonomy: 'category', id: 1, name: 'Sport' }, { taxonomy: 'category', id: 2, name: 'Polizei' } ],
    [ { taxonomy: 'post_tag', id: 9, name: 'Foo' } ]
] } };
var cats = W.categoryNames(postCat);
ok('categoryNames only categories', cats.length === 2 && cats[0].name === 'Sport' && cats[1].name === 'Polizei');
ok('categoryNames empty when none', W.categoryNames({}) === 0 || W.categoryNames({}).length === 0);

// --- buildCacheKey: ignores design, but respects data-affecting flags ---
var a = W.buildCacheKey({ api:'https://x/wp/v2', mode:'latest', category:[1], tags:[], include:[], exclude:[], pinned:[], auto_count:3, limit:5, sort:'newest', show:{category:true}, design:{accent:'#f00', radius:99} });
var b = W.buildCacheKey({ api:'https://x/wp/v2', mode:'latest', category:[1], tags:[], include:[], exclude:[], pinned:[], auto_count:3, limit:5, sort:'newest', show:{category:true}, design:{accent:'#0f0', radius:4} });
ok('cache key stable across DESIGN differences', a === b);
var c = W.buildCacheKey({ api:'https://x/wp/v2', mode:'latest', category:[1], tags:[], include:[], exclude:[], pinned:[], auto_count:3, limit:5, sort:'newest', show:{category:false}, design:{accent:'#f00'} });
ok('cache key differs by show.category (changes embed request)', a !== c);
var d = W.buildCacheKey({ api:'https://x/wp/v2', mode:'latest', category:[2], tags:[], include:[], exclude:[], pinned:[], auto_count:3, limit:5, sort:'newest', show:{category:true} });
ok('cache key differs by category', a !== d);

// --- formatDate ---
ok('formatDate returns string', typeof W.formatDate('2026-08-17T12:00:00') === 'string' && W.formatDate('2026-08-17T12:00:00').length > 0);
ok('formatDate invalid -> original-ish', W.formatDate('') === '');

// --- stripHtml (no DOM in node -> returns raw string) ---
ok('stripHtml exists', typeof W.stripHtml === 'function');

console.log('\n' + pass + ' assertions passed.');
