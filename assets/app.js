/* Fluence — shared helpers */
window.FLU = (function () {
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var ICONS = window.FL_ICONS || {};

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function icon(name, cls) {
    return '<svg class="ic ' + (cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  }
  function avatar(initials, hue, size) {
    return '<span class="av ' + (size || '') + '" style="--h:' + (+hue || 0) + '">' + esc(initials) + '</span>';
  }
  function kfmt(v) {
    v = Math.round(+v || 0);
    var a = Math.abs(v), s = v < 0 ? '-' : '';
    if (a >= 1000000) return s + (Math.round(a / 100000) / 10) + 'M';
    if (a >= 1000) return s + (Math.round(a / 100) / 10) + 'k';
    return s + a;
  }
  function num(v) { return v == null ? '—' : Number(v).toLocaleString('en-US'); }
  function money(v) { return v == null ? '—' : (v < 0 ? '-$' : '$') + kfmt(Math.abs(v)); }
  function growth(v) { if (v == null) return '—'; return (v > 0 ? '+' : '') + kfmt(v); }
  function pctDelta(cur, prev) { if (cur == null || prev == null || prev === 0) return null; return Math.round((cur - prev) / Math.abs(prev) * 100); }

  /* toasts */
  function toast(msg, type) {
    var box = document.getElementById('toasts'); if (!box) return;
    var t = document.createElement('div');
    t.className = 'toast ' + (type || 'ok');
    t.innerHTML = icon(type === 'err' ? 'x' : 'check') + '<span>' + esc(msg) + '</span>';
    box.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('in'); });
    setTimeout(function () { t.classList.remove('in'); setTimeout(function () { t.remove(); }, 300); }, type === 'err' ? 4200 : 2200);
  }

  function api(payload) {
    return fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
      credentials: 'same-origin',
      keepalive: true,
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Server error (' + r.status + ')' }; });
    }).catch(function () { return { ok: false, error: 'No connection — not saved.' }; });
  }

  /* debounce with flush() so nothing is lost when leaving the page */
  var pending = [];
  function debounce(fn, ms) {
    var t, args, self;
    function run() { t = null; var i = pending.indexOf(d); if (i > -1) pending.splice(i, 1); fn.apply(self, args); }
    function d() { args = arguments; self = this; clearTimeout(t); t = setTimeout(run, ms); if (pending.indexOf(d) < 0) pending.push(d); }
    d.flush = function () { if (t) { clearTimeout(t); run(); } };
    return d;
  }
  function flushAll() { pending.slice().forEach(function (d) { d.flush(); }); }
  window.addEventListener('pagehide', flushAll);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flushAll(); });
  document.addEventListener('click', function (e) { var a = e.target.closest('a[href]'); if (a) flushAll(); }, true);

  /* copy buttons: <button data-copy="#selector"> */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var el = document.querySelector(b.dataset.copy); if (!el) return;
    var txt = el.value != null ? el.value : el.innerText;
    (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { toast('Copied to clipboard'); }, function () {
      var r = document.createRange(); r.selectNodeContents(el); var s = getSelection(); s.removeAllRanges(); s.addRange(r);
      toast('Press Ctrl/Cmd + C to copy', 'err');
    });
  });

  /* keyboard: "/" focuses search, ←/→ switch month */
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.key === '/') { var s = document.querySelector('input[type=search]'); if (s) { e.preventDefault(); s.focus(); } }
    if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && !document.querySelector('.modal:not([hidden])')) {
      var a = document.querySelector('.monthnav [data-key="' + e.key + '"]'); if (a) location.href = a.href;
    }
  });

  /* themed month picker */
  var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  document.querySelectorAll('.monthnav').forEach(function (nav) {
    var trigger = nav.querySelector('.mpick');
    var wrap = nav.querySelector('.monthpick-wrap');
    var value = nav.querySelector('input[name="m"]');
    if (!trigger || !wrap || !value) return;

    var match = /^(\d{4})-(\d{2})$/.exec(value.value);
    var selectedYear = match ? Number(match[1]) : new Date().getFullYear();
    var selectedMonth = match ? Number(match[2]) : new Date().getMonth() + 1;
    var visibleYear = selectedYear;
    var picker = document.createElement('div');
    picker.className = 'mpicker';
    picker.hidden = true;
    picker.setAttribute('role', 'dialog');
    picker.setAttribute('aria-label', 'Choose a month');

    function renderPicker() {
      var now = new Date();
      picker.innerHTML =
        '<div class="mpicker-head">' +
          '<button class="mpicker-nav" type="button" data-year="-1" aria-label="Previous year">‹</button>' +
          '<span class="mpicker-year">' + visibleYear + '</span>' +
          '<button class="mpicker-nav" type="button" data-year="1" aria-label="Next year">›</button>' +
        '</div>' +
        '<div class="mpicker-months">' + monthNames.map(function (name, i) {
          var month = i + 1;
          var chosen = visibleYear === selectedYear && month === selectedMonth;
          var current = visibleYear === now.getFullYear() && month === now.getMonth() + 1;
          return '<button class="mpicker-month' + (chosen ? ' selected' : '') + (current ? ' current' : '') +
            '" type="button" data-month="' + month + '"' + (chosen ? ' aria-pressed="true"' : '') + '>' +
            name.slice(0, 3) + '</button>';
        }).join('') + '</div>' +
        '<div class="mpicker-foot">' +
          '<button class="mpicker-action" type="button" data-clear>Clear</button>' +
          '<button class="mpicker-action" type="button" data-current>This month</button>' +
        '</div>';
    }

    function closePicker(returnFocus) {
      picker.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      if (returnFocus) trigger.focus();
    }

    function submitMonth(year, month) {
      value.value = year + '-' + String(month).padStart(2, '0');
      nav.requestSubmit();
    }

    function positionPicker() {
      picker.style.left = '0';
      picker.style.right = 'auto';
      if (wrap.getBoundingClientRect().left + picker.offsetWidth > window.innerWidth - 12) {
        picker.style.left = 'auto';
        picker.style.right = '0';
      }
    }

    renderPicker();
    wrap.appendChild(picker);
    trigger.addEventListener('click', function () {
      var opening = picker.hidden;
      picker.hidden = !opening;
      trigger.setAttribute('aria-expanded', String(opening));
      if (opening) {
        renderPicker();
        positionPicker();
        picker.querySelector('.selected')?.focus();
      }
    });
    window.addEventListener('resize', function () {
      if (!picker.hidden) positionPicker();
    });
    picker.addEventListener('click', function (e) {
      var button = e.target.closest('button');
      if (!button) return;
      if (button.hasAttribute('data-year')) {
        visibleYear = Math.max(1, Math.min(9999, visibleYear + Number(button.dataset.year)));
        renderPicker();
        picker.querySelector('[data-year="' + button.dataset.year + '"]').focus();
      } else if (button.hasAttribute('data-month')) {
        selectedYear = visibleYear;
        selectedMonth = Number(button.dataset.month);
        submitMonth(selectedYear, selectedMonth);
      } else if (button.hasAttribute('data-current')) {
        var now = new Date();
        submitMonth(now.getFullYear(), now.getMonth() + 1);
      } else if (button.hasAttribute('data-clear')) {
        value.disabled = true;
        nav.requestSubmit();
      }
    });
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) closePicker(false);
    });
    picker.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closePicker(true);
      }
    });
  });

  /* tiny sparkline (values may contain nulls) */
  function spark(vals, w, h, color) {
    w = w || 120; h = h || 34; color = color || '#22d3ee';
    var pts = vals.map(function (v, i) { return v == null ? null : [i, +v]; }).filter(Boolean);
    if (pts.length < 2) return '<span class="spark-empty">Not enough data</span>';
    var max = Math.max.apply(null, pts.map(function (p) { return p[1]; })), min = Math.min.apply(null, pts.map(function (p) { return p[1]; }));
    if (max === min) { max += 1; min -= 1; }
    var n = vals.length - 1 || 1, pad = 3;
    var xy = pts.map(function (p) { return [pad + p[0] / n * (w - pad * 2), pad + (1 - (p[1] - min) / (max - min)) * (h - pad * 2)]; });
    var line = xy.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var area = xy[0][0].toFixed(1) + ',' + h + ' ' + line + ' ' + xy[xy.length - 1][0].toFixed(1) + ',' + h;
    var last = xy[xy.length - 1];
    var gid = 'sg' + Math.random().toString(36).slice(2, 8);
    return '<svg class="spark" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + color + '" stop-opacity=".28"/><stop offset="1" stop-color="' + color + '" stop-opacity="0"/></linearGradient></defs>' +
      '<polygon points="' + area + '" fill="url(#' + gid + ')"/><polyline points="' + line + '" fill="none" stroke="' + color + '" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>' +
      '<circle cx="' + last[0].toFixed(1) + '" cy="' + last[1].toFixed(1) + '" r="2.6" fill="' + color + '"/></svg>';
  }

  /* progress ring */
  function ring(pct, size, stroke) {
    size = size || 120; stroke = stroke || 10;
    var r = (size - stroke) / 2, c = 2 * Math.PI * r, off = c * (1 - Math.max(0, Math.min(100, pct)) / 100);
    var gid = 'rg' + Math.random().toString(36).slice(2, 8);
    return '<svg class="ring" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '" aria-hidden="true">' +
      '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + (pct >= 100 ? '#34d399' : '#6366f1') + '"/><stop offset="1" stop-color="' + (pct >= 100 ? '#34d399' : '#22d3ee') + '"/></linearGradient></defs>' +
      '<circle cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="' + stroke + '"/>' +
      '<circle class="ring-v" cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" fill="none" stroke="url(#' + gid + ')" stroke-width="' + stroke + '" stroke-linecap="round" stroke-dasharray="' + c.toFixed(2) + '" stroke-dashoffset="' + off.toFixed(2) + '" transform="rotate(-90 ' + size / 2 + ' ' + size / 2 + ')"/></svg>';
  }

  return { esc: esc, icon: icon, avatar: avatar, kfmt: kfmt, num: num, money: money, growth: growth, pctDelta: pctDelta,
           toast: toast, api: api, debounce: debounce, flushAll: flushAll, spark: spark, ring: ring };
})();
