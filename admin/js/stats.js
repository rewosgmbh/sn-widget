/**
 * Steigerwald-News Widget — Statistik dashboard (vanilla JS, no dependencies).
 *
 * Talks to the Telemetry REST API (admin-authenticated). All failures are
 * contained: a broken API never breaks the WordPress admin, and telemetry
 * here is fully separate from the public widget renderer.
 *
 * Run: node tests/js/stats.test.js  (chart + formatting helpers)
 */
(function () {
    'use strict';

    var C = (typeof window !== 'undefined' && window.SNW_Stats) ? window.SNW_Stats : {};
    var I = C.i18n || {};

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    // --- Filter + API ---------------------------------------------------
    function filtersParams() {
        var p = {};
        var range = $('#snw-filter-range') ? $('#snw-filter-range').value : '30';
        p.range = range;
        if (range === 'custom') {
            p.start = $('#snw-filter-start').value;
            p.end = $('#snw-filter-end').value;
        }
        var w = $('#snw-filter-widget').value.trim(); if (w) { p.widget_id = w; }
        var pa = $('#snw-filter-partner').value.trim(); if (pa) { p.partner = pa; }
        var h = $('#snw-filter-host').value.trim(); if (h) { p.host = h; }
        var pg = $('#snw-filter-page').value.trim(); if (pg) { p.page = pg; }
        var b = $('#snw-filter-bots').value; if (b) { p.bots = b; }
        return p;
    }

    function buildQuery(params) {
        var parts = [];
        for (var k in params) {
            if (Object.prototype.hasOwnProperty.call(params, k) && params[k] !== '' && params[k] !== null && params[k] !== undefined) {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
            }
        }
        return parts.join('&');
    }

    function apiGet(route, params) {
        var url = String(C.restUrl || '').replace(/\/$/, '') + '/' + route;
        var qs = buildQuery(params || {});
        if (qs) { url += '?' + qs; }
        return fetch(url, {
            method: 'GET',
            headers: { 'X-WP-Nonce': C.restNonce || '', 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (res.status === 401 || res.status === 403) { throw new Error('auth'); }
            if (!res.ok) { throw new Error('http_' + res.status); }
            return res.json();
        });
    }

    function postAjax(action, fields) {
        return new Promise(function (resolve, reject) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append(C.nonceField || 'snw_nonce', C.nonce || '');
            for (var k in (fields || {})) {
                if (Object.prototype.hasOwnProperty.call(fields, k)) { fd.append(k, fields[k]); }
            }
            fetch(C.ajaxUrl || '', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) { resolve(j); })
                .catch(function (e) { reject(e); });
        });
    }

    // --- Formatting -----------------------------------------------------
    function fmtNum(n) { return (Number(n) || 0).toLocaleString('de-DE'); }
    function fmtPct(n) { return (Number(n) || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %'; }
    function fmtMs(n) { return (Number(n) || 0).toLocaleString('de-DE') + ' ms'; }
    function fmtDelta(cur, prev) {
        cur = Number(cur) || 0; prev = Number(prev) || 0;
        if (prev === 0) { return { text: '—', dir: 'flat' }; }
        var d = ((cur - prev) / prev) * 100;
        var dir = d > 0.05 ? 'up' : (d < -0.05 ? 'down' : 'flat');
        var sign = d > 0 ? '+' : '';
        return { text: sign + d.toLocaleString('de-DE', { maximumFractionDigits: 1 }) + ' %', dir: dir };
    }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function t(key, fallback) { return (I && I[key]) ? I[key] : (fallback || key); }
    function el(tag, cls, html) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (html !== undefined) { e.innerHTML = html; }
        return e;
    }

    // --- SVG line chart (dependency-free) -------------------------------
    // opts: { labels:[], series:[{name,color,data:[]}], yFormat:fn, legend:true }
    function renderLineChart(container, opts) {
        container.innerHTML = '';
        container.classList.add('snw-chart');

        var W = 820, H = 300;
        var padL = 52, padR = 14, padT = 14, padB = 34;
        var plotW = W - padL - padR, plotH = H - padT - padB;

        var series = opts.series || [];
        var labels = opts.labels || [];
        var n = labels.length;
        if (!n) {
            container.appendChild(el('p', 'snw-chart-empty', esc(t('noData', 'Keine Daten'))));
            return;
        }

        var maxV = 0;
        series.forEach(function (s) {
            s.data.forEach(function (v) { if (Number(v) > maxV) { maxV = Number(v); } });
        });
        if (maxV <= 0) { maxV = 1; }
        // Round max up to a nice value.
        var mag = Math.pow(10, Math.floor(Math.log10(maxV)));
        maxV = Math.ceil(maxV / mag) * mag;

        var svgNS = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', H);
        svg.style.display = 'block';

        function xAt(i) { return padL + (n <= 1 ? plotW / 2 : (i / (n - 1)) * plotW); }
        function yAt(v) { return padT + plotH - (Number(v) / maxV) * plotH; }

        // Gridlines + Y labels.
        var ticks = 4;
        for (var g = 0; g <= ticks; g++) {
            var val = (maxV / ticks) * g;
            var y = yAt(val);
            var line = document.createElementNS(svgNS, 'line');
            line.setAttribute('x1', padL); line.setAttribute('x2', W - padR);
            line.setAttribute('y1', y); line.setAttribute('y2', y);
            line.setAttribute('class', 'snw-chart-grid');
            svg.appendChild(line);
            var lbl = document.createElementNS(svgNS, 'text');
            lbl.setAttribute('x', padL - 8); lbl.setAttribute('y', y + 4);
            lbl.setAttribute('class', 'snw-chart-y'); lbl.setAttribute('text-anchor', 'end');
            lbl.textContent = (opts.yFormat || fmtNum)(val);
            svg.appendChild(lbl);
        }

        // X labels (subset).
        var maxLabels = 10;
        var stepX = Math.max(1, Math.ceil(n / maxLabels));
        for (var i = 0; i < n; i += stepX) {
            var xl = document.createElementNS(svgNS, 'text');
            xl.setAttribute('x', xAt(i)); xl.setAttribute('y', H - 12);
            xl.setAttribute('class', 'snw-chart-x'); xl.setAttribute('text-anchor', 'middle');
            xl.textContent = String(labels[i]);
            svg.appendChild(xl);
        }

        // Series polylines.
        var hidden = {};
        series.forEach(function (s) {
            if (hidden[s.name]) { return; }
            var pts = [];
            for (var i = 0; i < n; i++) { pts.push(xAt(i) + ',' + yAt(s.data[i])); }
            var poly = document.createElementNS(svgNS, 'polyline');
            poly.setAttribute('points', pts.join(' '));
            poly.setAttribute('fill', 'none');
            poly.setAttribute('stroke', s.color);
            poly.setAttribute('stroke-width', '2');
            poly.setAttribute('class', 'snw-chart-line');
            svg.appendChild(poly);
        });

        container.appendChild(svg);

        // Tooltip overlay.
        var tip = el('div', 'snw-chart-tip');
        tip.style.display = 'none';
        container.appendChild(tip);
        var vline = document.createElementNS(svgNS, 'line');
        vline.setAttribute('class', 'snw-chart-vline');
        vline.setAttribute('y1', padT); vline.setAttribute('y2', padT + plotH);
        vline.style.display = 'none';
        svg.appendChild(vline);

        function onMove(ev) {
            var rect = svg.getBoundingClientRect();
            var clientX = (ev.touches ? ev.touches[0].clientX : ev.clientX);
            var relX = (clientX - rect.left) / rect.width * W;
            var i = Math.round(((relX - padL) / plotW) * (n - 1));
            if (i < 0) { i = 0; } if (i > n - 1) { i = n - 1; }
            vline.setAttribute('x1', xAt(i)); vline.setAttribute('x2', xAt(i));
            vline.style.display = '';
            var html = '<strong>' + esc(String(labels[i])) + '</strong>';
            series.forEach(function (s) {
                if (hidden[s.name]) { return; }
                html += '<div><span class="snw-dot" style="background:' + s.color + '"></span>' +
                    esc(s.name) + ': ' + esc((opts.yFormat || fmtNum)(s.data[i])) + '</div>';
            });
            tip.innerHTML = html;
            tip.style.display = '';
            var tipX = (xAt(i) / W) * rect.width + 12;
            if (tipX + tip.offsetWidth > rect.width) { tipX = (xAt(i) / W) * rect.width - tip.offsetWidth - 12; }
            tip.style.left = tipX + 'px';
            tip.style.top = '8px';
        }
        svg.addEventListener('mousemove', onMove);
        svg.addEventListener('mouseleave', function () { tip.style.display = 'none'; vline.style.display = 'none'; });
        svg.addEventListener('touchmove', onMove, { passive: true });

        // Legend (toggle series).
        if (opts.legend !== false) {
            var leg = el('div', 'snw-chart-legend');
            series.forEach(function (s) {
                var item = el('button', 'snw-legend-item', '<span class="snw-dot" style="background:' + s.color + '"></span>' + esc(s.name));
                item.type = 'button';
                item.addEventListener('click', function () {
                    if (hidden[s.name]) { delete hidden[s.name]; item.classList.remove('is-off'); }
                    else { hidden[s.name] = true; item.classList.add('is-off'); }
                    renderLineChart(container, opts);
                });
                leg.appendChild(item);
            });
            container.appendChild(leg);
        }
    }

    // --- KPI cards ------------------------------------------------------
    function kpiCard(label, value, delta, sub) {
        var card = el('div', 'snw-kpi-card');
        card.appendChild(el('div', 'snw-kpi-label', esc(label)));
        card.appendChild(el('div', 'snw-kpi-value', esc(value)));
        if (delta) {
            var d = el('div', 'snw-kpi-delta snw-delta-' + delta.dir, esc(delta.text));
            card.appendChild(d);
        }
        if (sub) { card.appendChild(el('div', 'snw-kpi-sub', esc(sub))); }
        return card;
    }

    // --- Overview -------------------------------------------------------
    function renderOverview(panel) {
        panel.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        apiGet('stats', filtersParams()).then(function (data) {
            var k = data.kpis || {};
            var prev = k.prev || {};
            panel.innerHTML = '';

            var grid = el('div', 'snw-kpi-grid');
            grid.appendChild(kpiCard(t('rawLoads'), fmtNum(k.raw_loads), fmtDelta(k.raw_loads, prev.raw_loads)));
            grid.appendChild(kpiCard(t('viewable'), fmtNum(k.viewable_impressions), fmtDelta(k.viewable_impressions, prev.viewable_impressions), fmtPct(k.viewability_rate) + ' ' + t('viewability')));
            grid.appendChild(kpiCard(t('visitors'), fmtNum(k.unique_visitors), fmtDelta(k.unique_visitors, prev.unique_visitors)));
            grid.appendChild(kpiCard(t('clicks'), fmtNum(k.clicks), fmtDelta(k.clicks, prev.clicks), fmtPct(k.ctr) + ' ' + t('ctr')));
            grid.appendChild(kpiCard(t('uniqueClickers'), fmtNum(k.unique_clickers), fmtDelta(k.unique_clickers, prev.unique_clickers)));
            grid.appendChild(kpiCard(t('ctr'), fmtPct(k.ctr), fmtDelta(k.ctr, prev.ctr)));
            grid.appendChild(kpiCard(t('viewability'), fmtPct(k.viewability_rate), fmtDelta(k.viewability_rate, prev.viewability_rate)));
            grid.appendChild(kpiCard(t('activeWidgets'), fmtNum(k.active_widgets)));
            panel.appendChild(grid);

            // Charts.
            var chartWrap = el('div', 'snw-charts');
            var c1 = el('div', 'snw-chart-box');
            c1.appendChild(el('h3', null, esc(t('rawLoads')) + ' / ' + esc(t('viewable')) + ' / ' + esc(t('visitors')) + ' / ' + esc(t('clicks'))));
            var c1chart = el('div', 'snw-chart-host'); c1.appendChild(c1chart);
            chartWrap.appendChild(c1);

            var c2 = el('div', 'snw-chart-box');
            c2.appendChild(el('h3', null, esc(t('ctr')) + ' / ' + esc(t('viewability'))));
            var c2chart = el('div', 'snw-chart-host'); c2.appendChild(c2chart);
            chartWrap.appendChild(c2);
            panel.appendChild(chartWrap);

            var s = data.series || {};
            renderLineChart(c1chart, {
                labels: s.labels || [],
                yFormat: fmtNum,
                series: [
                    { name: t('rawLoads'), color: '#c59a20', data: s.loads || [] },
                    { name: t('viewable'), color: '#2e7d32', data: s.viewable || [] },
                    { name: t('visitors'), color: '#1565c0', data: s.unique || [] },
                    { name: t('clicks'), color: '#ad1457', data: s.clicks || [] }
                ]
            });
            renderLineChart(c2chart, {
                labels: s.labels || [],
                yFormat: fmtPct,
                series: [
                    { name: t('ctr'), color: '#ad1457', data: s.ctr_series || [] },
                    { name: t('viewability'), color: '#2e7d32', data: s.view_series || [] }
                ]
            });

            // Mini widget ranking inside overview.
            var wk = el('div', 'snw-mini-ranking');
            wk.appendChild(el('h3', null, esc(t('widget')) + ' · Top 5'));
            var tbl = buildWidgetTable([]);
            wk.appendChild(tbl);
            panel.appendChild(wk);
            apiGet('widgets', filtersParams()).then(function (rows) {
                fillWidgetTable(tbl, (rows || []).slice(0, 5));
            }).catch(function () {});
        }).catch(function (err) {
            panel.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>';
        });
    }

    // --- Tables ---------------------------------------------------------
    function buildWidgetTable(rows) {
        var table = el('table', 'wp-list-table widefat fixed striped snw-data-table');
        table.innerHTML = '<thead><tr>' +
            '<th>' + esc(t('widget')) + '</th>' +
            '<th>' + esc(t('partner')) + '</th>' +
            '<th>' + esc(t('loads')) + '</th>' +
            '<th>' + esc(t('viewable')) + '</th>' +
            '<th>' + esc(t('unique')) + '</th>' +
            '<th>' + esc(t('clicks')) + '</th>' +
            '<th>' + esc(t('ctr')) + '</th>' +
            '<th>' + esc(t('viewability')) + '</th>' +
            '<th>' + esc(t('lastSeen')) + '</th>' +
            '<th>' + esc(t('status')) + '</th>' +
            '</tr></thead>';
        var tb = el('tbody'); table.appendChild(tb);
        fillWidgetTable(table, rows);
        return table;
    }
    function fillWidgetTable(table, rows) {
        var tb = table.querySelector('tbody');
        tb.innerHTML = '';
        if (!rows.length) { tb.innerHTML = '<tr><td colspan="10">' + esc(t('noData')) + '</td></tr>'; return; }
        rows.forEach(function (r) {
            var tr = el('tr');
            var statusMap = { active: '🟢', idle: '🟡', removed: '🔴', unknown: '⚪' };
            var statusText = { active: t('active'), idle: t('idle'), removed: t('removed'), unknown: t('unknown') };
            tr.innerHTML =
                '<td><strong>' + esc(r.widget_id) + '</strong></td>' +
                '<td>' + esc(r.partner) + '</td>' +
                '<td>' + fmtNum(r.loads) + '</td>' +
                '<td>' + fmtNum(r.viewable) + '</td>' +
                '<td>' + fmtNum(r.unique) + '</td>' +
                '<td>' + fmtNum(r.clicks) + '</td>' +
                '<td>' + fmtPct(r.ctr) + '</td>' +
                '<td>' + fmtPct(r.viewability) + '</td>' +
                '<td>' + esc(r.last_seen || '—') + '</td>' +
                '<td>' + (statusMap[r.status] || '⚪') + ' ' + esc(statusText[r.status] || '') + '</td>';
            tb.appendChild(tr);
        });
    }

    function renderWidgets(panel) {
        panel.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        apiGet('widgets', filtersParams()).then(function (rows) {
            panel.innerHTML = '';
            var table = buildWidgetTable([]);
            panel.appendChild(table);
            fillWidgetTable(table, rows || []);
            $all('tbody tr', table).forEach(function (tr, idx) {
                if (!rows[idx] || !rows[idx].widget_id) { return; }
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', function () { renderWidgetDetail(panel, rows[idx].widget_id); });
            });
            // Detail container (toggled on row click).
            var detail = el('div', 'snw-widget-detail');
            detail.hidden = true;
            panel.appendChild(detail);
        }).catch(function () { panel.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
    }

    function renderWidgetDetail(panel, widgetId) {
        var detail = $('.snw-widget-detail', panel);
        if (!detail) { return; }
        detail.hidden = false;
        detail.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        var params = filtersParams();
        params.widget_id = widgetId;
        apiGet('stats', params).then(function (data) {
            var k = data.kpis || {};
            detail.innerHTML = '';
            detail.appendChild(el('h3', null, esc(widgetId)));
            var grid = el('div', 'snw-kpi-grid');
            grid.appendChild(kpiCard(t('rawLoads'), fmtNum(k.raw_loads)));
            grid.appendChild(kpiCard(t('viewable'), fmtNum(k.viewable_impressions), null, fmtPct(k.viewability_rate) + ' ' + t('viewability')));
            grid.appendChild(kpiCard(t('visitors'), fmtNum(k.unique_visitors)));
            grid.appendChild(kpiCard(t('clicks'), fmtNum(k.clicks), null, fmtPct(k.ctr) + ' ' + t('ctr')));
            grid.appendChild(kpiCard(t('uniqueClickers'), fmtNum(k.unique_clickers)));
            grid.appendChild(kpiCard(t('activeWidgets'), fmtNum(k.active_widgets)));
            detail.appendChild(grid);

            var charts = el('div', 'snw-charts');
            var c1 = el('div', 'snw-chart-box'); c1.appendChild(el('h3', null, esc(t('rawLoads')) + ' / ' + esc(t('viewable')) + ' / ' + esc(t('clicks'))));
            var c1h = el('div', 'snw-chart-host'); c1.appendChild(c1h);
            var c2 = el('div', 'snw-chart-box'); c2.appendChild(el('h3', null, esc(t('ctr')) + ' / ' + esc(t('viewability'))));
            var c2h = el('div', 'snw-chart-host'); c2.appendChild(c2h);
            charts.appendChild(c1); charts.appendChild(c2);
            detail.appendChild(charts);
            var s = data.series || {};
            renderLineChart(c1h, { labels: s.labels || [], yFormat: fmtNum, series: [
                { name: t('rawLoads'), color: '#c59a20', data: s.loads || [] },
                { name: t('viewable'), color: '#2e7d32', data: s.viewable || [] },
                { name: t('clicks'), color: '#ad1457', data: s.clicks || [] }
            ]});
            renderLineChart(c2h, { labels: s.labels || [], yFormat: fmtPct, series: [
                { name: t('ctr'), color: '#ad1457', data: s.ctr_series || [] },
                { name: t('viewability'), color: '#2e7d32', data: s.view_series || [] }
            ]});

            // Installed On (pages for this widget).
            detail.appendChild(el('h3', null, esc(t('host') + ' / ' + t('page'))));
            var pagesParams = filtersParams(); pagesParams.widget_id = widgetId;
            apiGet('pages', pagesParams).then(function (prows) {
                var pt = el('table', 'wp-list-table widefat fixed striped snw-data-table');
                pt.innerHTML = '<thead><tr><th>' + esc(t('host')) + '</th><th>' + esc(t('page')) + '</th><th>' + esc(t('loads')) + '</th><th>' + esc(t('viewable')) + '</th><th>' + esc(t('clicks')) + '</th><th>' + esc(t('ctr')) + '</th></tr></thead>';
                var tb = el('tbody'); pt.appendChild(tb);
                if (!(prows || []).length) { tb.innerHTML = '<tr><td colspan="6">' + esc(t('noData')) + '</td></tr>'; }
                (prows || []).forEach(function (r) {
                    var tr = el('tr');
                    tr.innerHTML = '<td>' + esc(r.host) + '</td><td>' + esc(r.page_path) + '</td><td>' + fmtNum(r.loads) + '</td><td>' + fmtNum(r.viewable) + '</td><td>' + fmtNum(r.clicks) + '</td><td>' + fmtPct(r.ctr) + '</td>';
                    tb.appendChild(tr);
                });
                detail.appendChild(pt);
            }).catch(function () {});
        }).catch(function () { detail.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
    }

    function renderPages(panel) {
        panel.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        apiGet('pages', filtersParams()).then(function (rows) {
            panel.innerHTML = '';
            var table = el('table', 'wp-list-table widefat fixed striped snw-data-table');
            table.innerHTML = '<thead><tr>' +
                '<th>' + esc(t('host')) + '</th><th>' + esc(t('page')) + '</th>' +
                '<th>' + esc(t('widget')) + '</th><th>' + esc(t('loads')) + '</th>' +
                '<th>' + esc(t('viewable')) + '</th><th>' + esc(t('unique')) + '</th>' +
                '<th>' + esc(t('clicks')) + '</th><th>' + esc(t('ctr')) + '</th>' +
                '<th>' + esc(t('viewability')) + '</th></tr></thead>';
            var tb = el('tbody'); table.appendChild(tb);
            if (!(rows || []).length) { tb.innerHTML = '<tr><td colspan="9">' + esc(t('noData')) + '</td></tr>'; }
            (rows || []).forEach(function (r) {
                var tr = el('tr');
                tr.innerHTML = '<td>' + esc(r.host) + '</td><td>' + esc(r.page_path) + '</td><td>' + esc(r.widget_id) + '</td>' +
                    '<td>' + fmtNum(r.loads) + '</td><td>' + fmtNum(r.viewable) + '</td><td>' + fmtNum(r.unique) + '</td>' +
                    '<td>' + fmtNum(r.clicks) + '</td><td>' + fmtPct(r.ctr) + '</td><td>' + fmtPct(r.viewability) + '</td>';
                tb.appendChild(tr);
            });
            panel.appendChild(table);
        }).catch(function () { panel.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
    }

    function renderArticles(panel) {
        panel.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        apiGet('articles', filtersParams()).then(function (rows) {
            panel.innerHTML = '';
            var table = el('table', 'wp-list-table widefat fixed striped snw-data-table');
            table.innerHTML = '<thead><tr>' +
                '<th>' + esc(t('article')) + '</th><th>' + esc(t('title')) + '</th>' +
                '<th>' + esc(t('widget')) + '</th><th>' + esc(t('clicks')) + '</th>' +
                '<th>' + esc(t('uniqueClickers')) + '</th></tr></thead>';
            var tb = el('tbody'); table.appendChild(tb);
            if (!(rows || []).length) { tb.innerHTML = '<tr><td colspan="5">' + esc(t('noData')) + '</td></tr>'; }
            (rows || []).forEach(function (r) {
                var tr = el('tr');
                tr.innerHTML = '<td>' + esc(r.article_id) + '</td><td>' + esc(r.title || ('#' + r.article_id)) + '</td>' +
                    '<td>' + esc(r.widget_id) + '</td><td>' + fmtNum(r.clicks) + '</td><td>' + fmtNum(r.unique_clickers) + '</td>';
                tb.appendChild(tr);
            });
            panel.appendChild(table);
        }).catch(function () { panel.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
    }

    function renderPartners(panel) {
        panel.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        apiGet('partners', filtersParams()).then(function (rows) {
            panel.innerHTML = '';
            var table = el('table', 'wp-list-table widefat fixed striped snw-data-table');
            table.innerHTML = '<thead><tr>' +
                '<th>' + esc(t('partner')) + '</th><th>' + esc(t('widgets')) + '</th>' +
                '<th>' + esc(t('loads')) + '</th><th>' + esc(t('viewable')) + '</th>' +
                '<th>' + esc(t('clicks')) + '</th><th>' + esc(t('ctr')) + '</th>' +
                '<th>' + esc(t('viewability')) + '</th><th>' + esc(t('unique')) + '</th></tr></thead>';
            var tb = el('tbody'); table.appendChild(tb);
            if (!(rows || []).length) { tb.innerHTML = '<tr><td colspan="8">' + esc(t('noData')) + '</td></tr>'; }
            (rows || []).forEach(function (r) {
                var tr = el('tr');
                tr.innerHTML = '<td>' + esc(r.partner) + '</td><td>' + fmtNum(r.widgets) + '</td>' +
                    '<td>' + fmtNum(r.loads) + '</td><td>' + fmtNum(r.viewable) + '</td><td>' + fmtNum(r.clicks) + '</td>' +
                    '<td>' + fmtPct(r.ctr) + '</td><td>' + fmtPct(r.viewability) + '</td><td>' + fmtNum(r.unique) + '</td>';
                tb.appendChild(tr);
            });
            panel.appendChild(table);
        }).catch(function () { panel.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
    }

    // --- Realtime -------------------------------------------------------
    var realtimeTimer = null;
    function renderRealtime(panel) {
        panel.innerHTML = '';
        panel.appendChild(el('h3', null, esc(t('rawLoads')) + ' / ' + esc(t('viewable')) + ' / ' + esc(t('clicks')) + ' · ' + esc(t('lastSeen') || 'Letzte 60 Min.')));
        var box = el('div', 'snw-realtime-box');
        panel.appendChild(box);
        function tick() {
            apiGet('realtime', {}).then(function (d) {
                box.innerHTML = '';
                var g = el('div', 'snw-kpi-grid');
                g.appendChild(kpiCard(t('rawLoads'), fmtNum(d.loads)));
                g.appendChild(kpiCard(t('viewable'), fmtNum(d.viewable)));
                g.appendChild(kpiCard(t('clicks'), fmtNum(d.clicks)));
                g.appendChild(kpiCard(t('activeWidgets'), fmtNum(d.widgets)));
                box.appendChild(g);
                box.appendChild(el('p', 'snw-realtime-since', esc('seit ') + esc(d.since)));
            }).catch(function () { box.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
        }
        tick();
        if (realtimeTimer) { clearInterval(realtimeTimer); }
        realtimeTimer = setInterval(tick, 60000);
    }

    // --- Settings -------------------------------------------------------
    function renderSettings(panel) {
        panel.innerHTML = '';
        var form = el('form', 'snw-settings-form');
        form.innerHTML =
            '<h3>' + esc(t('settings', 'Einstellungen')) + '</h3>' +
            '<label class="snw-switch"><input type="checkbox" id="snw-set-enabled" checked> ' + esc('Telemetry aktiviert') + '</label>' +
            '<label class="snw-switch"><input type="checkbox" id="snw-set-bot"> ' + esc('Bot-Filterung') + '</label>' +
            '<label class="snw-switch"><input type="checkbox" id="snw-set-debug"> ' + esc('Telemetry Debug') + '</label>' +
            '<label>' + esc('Raw-Event Aufbewahrung (Tage)') + '<br><input type="number" id="snw-set-retention" min="1" max="3650" value="90" class="small-text"></label>' +
            '<label>' + esc('Unique-Rotation (Tage)') + '<br><input type="number" id="snw-set-rotation" min="1" max="30" value="1" class="small-text"></label>' +
            '<label>' + esc('Telemetry Endpoint') + '<br><input type="text" id="snw-set-endpoint" class="regular-text" readonly></label>' +
            '<div class="snw-settings-actions">' +
                '<button type="button" id="snw-set-save" class="button button-primary">' + esc('Speichern') + '</button> ' +
                '<button type="button" id="snw-set-aggregate" class="button">' + esc(t('aggregated', 'Jetzt aggregieren')) + '</button> ' +
                '<button type="button" id="snw-set-purge" class="button">' + esc('Alle Daten löschen') + '</button>' +
            '</div>' +
            '<div id="snw-set-msg" class="snw-set-msg" aria-live="polite"></div>';
        panel.appendChild(form);
        $('#snw-set-endpoint').value = C.publicAlias || '';

        $('#snw-set-save').addEventListener('click', function () {
            var msg = $('#snw-set-msg');
            postAjax('snw_telemetry_save_settings', {
                enabled: $('#snw-set-enabled').checked ? 'yes' : 'no',
                bot_filter: $('#snw-set-bot').checked ? 'yes' : 'no',
                debug: $('#snw-set-debug').checked ? '1' : '0',
                retention: $('#snw-set-retention').value,
                rotation: $('#snw-set-rotation').value
            }).then(function (j) {
                msg.textContent = (j && j.success) ? t('saved') : 'Fehler';
                msg.className = 'snw-set-msg ' + (j && j.success ? 'is-ok' : 'is-err');
            }).catch(function () { msg.textContent = 'Fehler'; msg.className = 'snw-set-msg is-err'; });
        });
        $('#snw-set-aggregate').addEventListener('click', function () {
            apiGet('aggregate', {}).then(function () {
                var msg = $('#snw-set-msg'); msg.textContent = t('aggregated'); msg.className = 'snw-set-msg is-ok';
                refreshActive();
            }).catch(function () {});
        });
        $('#snw-set-purge').addEventListener('click', function () {
            if (!window.confirm('Wirklich alle Telemetriedaten löschen?')) { return; }
            postAjax('snw_telemetry_purge', {}).then(function (j) {
                var msg = $('#snw-set-msg');
                msg.textContent = (j && j.success) ? t('purged') : 'Fehler';
                msg.className = 'snw-set-msg ' + (j && j.success ? 'is-ok' : 'is-err');
            });
        });
    }

    // --- Debug ----------------------------------------------------------
    function renderDebug(panel) {
        panel.innerHTML = '<p class="snw-loading">' + esc(t('loading')) + '</p>';
        // Public health probe (no auth).
        var healthOk = false;
        fetch(String(C.restUrl || '').replace(/\/$/, '') + '/health', { method: 'GET', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (h) { healthOk = (h && h.status === 'ok'); })
            .catch(function () {});

        postAjax('snw_telemetry_status', {}).then(function (j) {
            var d = (j && j.success) ? j.data : {};
            panel.innerHTML = '';
            panel.appendChild(el('h3', null, esc(t('debug', 'Debug'))));
            var list = el('ul', 'snw-debug-list');
            list.innerHTML =
                '<li>' + esc('Endpoint erreichbar') + ': ' + (healthOk ? '✅ ok' : '⚠️') + ' <code>' + esc(C.publicAlias || '') + '</code></li>' +
                '<li>' + esc('Schema-Version') + ': ' + esc(d.schema) + '</li>' +
                '<li>' + esc('Aktiv') + ': ' + esc(d.enabled) + '</li>' +
                '<li>' + esc('Raw Events') + ': ' + fmtNum(d.raw_events) + '</li>' +
                '<li>' + esc('Daily Rows') + ': ' + fmtNum(d.daily_rows) + '</li>' +
                '<li>' + esc('Letzte Aggregation') + ': ' + esc(d.last_aggregated || '—') + '</li>' +
                '<li>' + esc('Letzter Aggregationslauf') + ': ' + esc(d.last_aggregate_run || '—') + '</li>' +
                '<li>' + esc('Bot-Filter') + ': ' + esc(d.bot_filter) + '</li>' +
                '<li>' + esc('Rotation (Tage)') + ': ' + esc(d.rotation) + '</li>';
            panel.appendChild(list);

            var testBtn = el('button', 'button', esc('Testevent senden'));
            testBtn.type = 'button';
            testBtn.addEventListener('click', function () {
                try {
                    var ep = String(C.publicAlias || '').replace(/\/event$/, '');
                    var body = JSON.stringify({ event: 'widget_load', widget_id: 'SNW-TEST', partner: 'debug', host: 'debug.local', page_path: '/', widget_version: C.version || '1.6.0', layout: 'list', mode: 'latest', article_ids: [], performance: { rest_ms: 1, render_ms: 1 } });
                    if (navigator.sendBeacon) { navigator.sendBeacon(ep + '/event', new Blob([body], { type: 'application/json' })); }
                    testBtn.textContent = 'Gesendet (SNW-TEST)';
                } catch (e) { testBtn.textContent = 'Fehler'; }
            });
            panel.appendChild(testBtn);
        }).catch(function () { panel.innerHTML = '<p class="snw-error">' + esc(t('error')) + '</p>'; });
    }

    // --- CSV export -----------------------------------------------------
    function exportCsv(type) {
        var f = filtersParams();
        var fd = new FormData();
        fd.append('action', 'snw_telemetry_export');
        fd.append(C.nonceField || 'snw_nonce', C.nonce || '');
        fd.append('type', type);
        fd.append('range', f.range);
        if (f.start) { fd.append('start', f.start); }
        if (f.end) { fd.append('end', f.end); }
        if (f.widget_id) { fd.append('widget_id', f.widget_id); }
        if (f.partner) { fd.append('partner', f.partner); }
        if (f.host) { fd.append('host', f.host); }
        if (f.page) { fd.append('page', f.page); }
        if (f.bots) { fd.append('bots', f.bots); }
        // Submit as a real form post so the browser downloads the CSV.
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = C.ajaxUrl || '';
        form.style.display = 'none';
        fd.forEach(function (v, k) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = k; inp.value = v;
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    // --- Tab / lifecycle ------------------------------------------------
    var loaded = {};
    function refreshActive() {
        var active = $('.snw-stats-tab.is-active');
        if (!active) { return; }
        var tab = active.getAttribute('data-tab');
        renderPanel(tab);
    }
    function renderPanel(tab) {
        var panel = document.querySelector('.snw-stats-panel[data-panel="' + tab + '"]');
        if (!panel) { return; }
        if (realtimeTimer && tab !== 'realtime') { clearInterval(realtimeTimer); realtimeTimer = null; }
        if (tab === 'overview') { renderOverview(panel); }
        else if (tab === 'widgets') { renderWidgets(panel); }
        else if (tab === 'pages') { renderPages(panel); }
        else if (tab === 'articles') { renderArticles(panel); }
        else if (tab === 'partners') { renderPartners(panel); }
        else if (tab === 'realtime') { renderRealtime(panel); }
        else if (tab === 'settings') { if (!loaded.settings) { renderSettings(panel); loaded.settings = true; } }
        else if (tab === 'debug') { renderDebug(panel); }
        loaded[tab] = true;
    }

    function init() {
        if (!document.getElementById('snw-stats-tabs')) { return; }
        $all('.snw-stats-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                $all('.snw-stats-tab').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                var tab = btn.getAttribute('data-tab');
                $all('.snw-stats-panel').forEach(function (p) { p.hidden = (p.getAttribute('data-panel') !== tab); });
                renderPanel(tab);
            });
        });
        $('#snw-filter-apply').addEventListener('click', function () { refreshActive(); });
        $('#snw-filter-range').addEventListener('change', function () {
            $('#snw-filter-custom').hidden = (this.value !== 'custom');
            refreshActive();
        });
        $('#snw-export-daily').addEventListener('click', function () { exportCsv('daily'); });

        // Initial load.
        renderPanel('overview');
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }

    // Expose for unit tests.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { fmtNum: fmtNum, fmtPct: fmtPct, fmtDelta: fmtDelta, buildQuery: buildQuery, renderLineChart: renderLineChart };
    }
})();
