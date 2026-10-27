/* Fluence — creator tracker (month-based) */
(function () {
  var F = window.FL, U = window.FLU, esc = U.esc, I = U.icon;
  var ORDER = ['todo', 'ip', 'cp', 'sk'], LABEL = { todo: 'To do', ip: 'In progress', cp: 'Complete', sk: 'Skipped' };
  var C = { spon: '#6366f1', info: '#0ea5b7', subs: '#22d3ee' };
  if (Array.isArray(F.statuses)) F.statuses = {};
  if (Array.isArray(F.notes)) F.notes = {};

  /* ---------- save state ---------- */
  var stateEl = document.getElementById('savestate'), stateT, inflight = 0;
  function setState(s, msg) {
    clearTimeout(stateT);
    stateEl.className = 'savestate ' + s;
    stateEl.innerHTML = s === 'saving' ? '<i class="spin"></i>Saving…' : s === 'ok' ? I('check') + 'Saved' : I('x') + esc(msg || 'Not saved');
    if (s === 'ok') stateT = setTimeout(function () { stateEl.className = 'savestate'; }, 1800);
  }
  function send(payload) {
    payload.cid = F.cid; payload.month = F.month;
    inflight++; setState('saving');
    return U.api(payload).then(function (res) {
      inflight--;
      if (res.ok) { if (!inflight) setState('ok'); }
      else { setState('err', res.error); U.toast(res.error || 'Not saved', 'err'); }
      return res;
    });
  }
  window.addEventListener('beforeunload', function (e) { if (inflight) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- progress ---------- */
  function st(key) { return F.statuses[key] || 'todo'; }
  function counts(p) {
    var d = 0, a = 0, ip = false;
    p.miles.forEach(function (m) { var s = st(m.key); if (s === 'sk') return; a++; if (s === 'cp') d++; if (s === 'ip') ip = true; });
    return { done: d, active: a, ip: ip };
  }
  function phaseStatus(p) { var c = counts(p); if (c.active === 0 || c.done === c.active) return 'cp'; return (c.done > 0 || c.ip) ? 'ip' : 'ns'; }
  function phasePct(p) { var c = counts(p); return c.active ? Math.round(c.done / c.active * 100) : 100; }
  function overall() { var t = 0, d = 0; F.phases.forEach(function (p) { var c = counts(p); t += c.active; d += c.done; }); return t ? Math.round(d / t * 100) : 0; }
  function currentIdx() { return F.phases.findIndex(function (p) { return phaseStatus(p) !== 'cp'; }); }

  function renderRing() {
    var pct = overall(), ci = currentIdx(), cur = F.phases[ci];
    var started = F.phases.some(function (p) { return phaseStatus(p) !== 'ns'; });
    document.getElementById('ring').innerHTML =
      '<div class="ring-wrap">' + U.ring(pct, 128, 11) + '<div class="ring-c"><b>' + pct + '<small>%</small></b><span>overall</span></div></div>' +
      '<div class="ring-txt"><span class="sl">Currently in</span><b>' + (cur ? esc(cur.num.replace('PHASE', 'Phase')) + ' · ' + esc(cur.name) : 'All phases complete 🎉') + '</b>' +
      '<span class="muted">' + (cur ? (started ? phasePct(cur) + '% through this phase' : 'Roadmap not started') : 'Roadmap finished') + '</span></div>';
  }

  function renderInherit() {
    var el = document.getElementById('inherit');
    el.hidden = !F.inheritedFrom;
    if (F.inheritedFrom) el.innerHTML = I('clock') + '<span>Step progress carried over from <b>' + esc(F.inheritedFrom) + '</b>.' + (F.canEdit ? ' Change any step to save ' + esc(F.monthLabel) + '’s own snapshot.' : '') + '</span>';
  }

  /* ---------- goals / KPI tiles ---------- */
  function totalRev(e) { return e ? (+e.spon || 0) + (+e.info || 0) : null; }
  function deltaHtml(cur, prev, kind) {
    if (cur == null || prev == null || !F.prev) return '<span class="delta">' + (F.prev ? 'No ' + esc(F.prev.name) + ' value' : 'First month logged') + '</span>';
    var diff = cur - prev; if (!diff) return '<span class="delta">Same as ' + esc(F.prev.name) + '</span>';
    var good = kind === 'lower' ? diff < 0 : diff > 0;
    var txt = kind === 'money' ? (U.pctDelta(cur, prev) != null ? Math.abs(U.pctDelta(cur, prev)) + '%' : U.money(Math.abs(diff)))
      : kind === 'lower' ? Math.abs(diff) + 'h' : U.kfmt(Math.abs(diff));
    return '<span class="delta ' + (good ? 'up' : 'down') + '">' + (diff > 0 ? '▲ ' : '▼ ') + txt + ' vs ' + esc(F.prev.name) + '</span>';
  }
  var GOALS = [
    { key: 'target_revenue', label: 'Monthly revenue', icon: 'dollar', fmt: function (v) { return '$' + U.num(v); }, cur: function (e) { return totalRev(e); }, prev: function (p) { return totalRev(p); }, kind: 'money' },
    { key: 'target_subs', label: 'Subscribers', icon: 'users', fmt: U.num, cur: function (e) { return e ? e.subs : null; }, prev: function (p) { return p ? p.subs : null; }, kind: 'num' },
    { key: 'target_streams', label: 'Income streams', icon: 'layers', fmt: U.num, cur: function (e) { return e ? e.streams : null; }, prev: function (p) { return p ? p.streams : null; }, kind: 'num' }
  ];
  function renderGoals() {
    var box = document.getElementById('goals');
    box.innerHTML = GOALS.map(function (g) {
      var tgt = +F.goals[g.key] || 0, cur = g.cur(F.entry), has = cur != null, pct = 0;
      if (has) pct = g.lower ? (cur <= tgt ? 100 : Math.max(0, Math.round(tgt / cur * 100))) : (tgt > 0 ? Math.min(100, Math.round(cur / tgt * 100)) : 0);
      var done = has && pct >= 100;
      var tgtHtml = F.canEdit ? '<button type="button" class="tgt" data-g="' + g.key + '" title="Change target">' + g.fmt(tgt) + I('edit') + '</button>' : '<span class="tgt ro">' + g.fmt(tgt) + '</span>';
      return '<div class="kpi goal' + (done ? ' done' : '') + '">' +
        '<div class="kpi-l">' + I(g.icon) + esc(g.label) + (done ? '<span class="hit">' + I('check') + 'Goal hit</span>' : '') + '</div>' +
        '<div class="kpi-v">' + (has ? g.fmt(cur) : '<span class="muted">—</span>') + '</div>' +
        '<div class="kpi-s">' + deltaHtml(cur, g.prev(F.prev), g.kind) + '</div>' +
        '<div class="gbar"><i style="width:' + pct + '%"></i></div>' +
        '<div class="gfoot"><span>' + (!has ? 'Log ' + esc(F.monthName) + ' to track' : g.lower ? (done ? 'At or under target' : pct + '% to target') : pct + '% of goal') + '</span><span class="gt">Goal ' + tgtHtml + '</span></div></div>';
    }).join('');
    box.querySelectorAll('button.tgt').forEach(function (b) {
      b.addEventListener('click', function () {
        var key = this.dataset.g, inp = document.createElement('input');
        inp.className = 'tgt-in'; inp.inputMode = 'decimal'; inp.value = F.goals[key];
        this.replaceWith(inp); inp.focus(); inp.select();
        var done = false;
        function commit(save) {
          if (done) return; done = true;
          var v = parseFloat(String(inp.value).replace(/[^0-9.]/g, ''));
          if (save && !isNaN(v) && v !== +F.goals[key]) { F.goals[key] = v; send({ action: 'goals', goals: F.goals }); }
          renderGoals();
        }
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') commit(true); if (e.key === 'Escape') commit(false); });
        inp.addEventListener('blur', function () { commit(true); });
      });
    });
  }

  /* ---------- this month's entry ---------- */
  var FIELDS = [
    { k: 'spon', label: 'Sponsor revenue', pre: '$', hintFmt: function (v) { return '$' + U.num(v); } },
    { k: 'info', label: 'Info product revenue', pre: '$', hintFmt: function (v) { return '$' + U.num(v); } },
    { k: 'subs', label: 'Subscribers', hintFmt: U.num },
    { k: 'streams', label: 'Income streams', hintFmt: U.num },
    { k: 'meeting', label: 'Last meeting', date: true, hintFmt: function (v) { return new Date(v + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); } }
  ];
  var saveEntry = U.debounce(function () {
    send({ action: 'entry', fields: F.entry }).then(function (r) { if (r.ok && r.meta) { F.entryMeta = r.meta; renderEntryMeta(); } });
  }, 700);

  function renderEntryMeta() {
    document.getElementById('entrymeta').innerHTML = F.entryMeta
      ? I('clock') + 'Last saved by ' + esc(F.entryMeta.by) + ' · ' + esc(F.entryMeta.ago)
      : (F.canEdit ? 'Not logged yet — fill these in and they save automatically.' : 'Not logged yet.');
    var act = document.getElementById('entryactions');
    act.innerHTML = F.canEdit && F.prev ? '<button type="button" class="btn sm ghost" id="copyprev">' + I('copy') + '<span>Copy from ' + esc(F.prev.name) + '</span></button>' : '';
    var cp = document.getElementById('copyprev');
    if (cp) cp.addEventListener('click', function () {
      var hasVals = F.entry && ['spon', 'info', 'subs', 'streams'].some(function (k) { return F.entry[k]; });
      if (hasVals && !confirm('Replace ' + F.monthName + '’s numbers with ' + F.prev.name + '’s?')) return;
      F.entry = { spon: F.prev.spon, info: F.prev.info, subs: F.prev.subs, streams: F.prev.streams, meeting: F.entry ? F.entry.meeting : null };
      syncHistory(); renderEntry(); renderGoals(); renderCharts(); renderTable();
      saveEntry(); saveEntry.flush();
      U.toast('Copied ' + F.prev.name + '’s numbers — update what changed');
    });
  }

  function renderEntry() {
    var box = document.getElementById('entry'), e = F.entry || {};
    box.innerHTML = FIELDS.map(function (f, idx) {
      var v = e[f.k], pv = F.prev ? F.prev[f.k] : null;
      var hint = F.prev && pv != null && pv !== '' ? '<small>' + esc(F.prev.name.slice(0, 3)) + ': ' + esc(f.hintFmt(pv)) + '</small>' : '<small>&nbsp;</small>';
      return '<label class="fld fld-' + f.k + (F.canEdit ? '' : ' ro') + '"><span>' + esc(f.label) + '</span><span class="adorn">' +
        (f.pre ? '<em>' + f.pre + '</em>' : '') +
        '<input data-k="' + f.k + '" ' + (f.date ? 'type="date"' : 'type="text" inputmode="decimal" placeholder="—"') +
        ' value="' + esc(v == null ? '' : (f.date ? v : (f.k === 'spon' || f.k === 'info') && !F.entry ? '' : v)) + '"' + (F.canEdit ? '' : ' disabled') + '>' +
        (f.post ? '<em>' + f.post + '</em>' : '') + '</span>' + hint + '</label>' +
        (idx === 2 ? '<div class="fld total"><span>Total revenue</span><b id="totrev">' + (F.entry ? '$' + U.num(totalRev(F.entry)) : '—') + '</b><small>sponsor + info</small></div>' : '');
    }).join('');

    if (!F.canEdit) return;
    box.querySelectorAll('input').forEach(function (inp) {
      inp.addEventListener('input', function () {
        if (!F.entry) F.entry = { spon: 0, info: 0, subs: null, streams: null, meeting: null };
        var k = this.dataset.k, raw = this.value.trim();
        if (k === 'meeting') F.entry[k] = raw || null;
        else { var n = parseFloat(raw.replace(/[^0-9.]/g, '')); F.entry[k] = isNaN(n) ? (k === 'spon' || k === 'info' ? 0 : null) : n; }
        syncHistory();
        document.getElementById('totrev').textContent = '$' + U.num(totalRev(F.entry));
        renderGoals(); renderCharts(); renderTable();
        saveEntry();
      });
      inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); saveEntry.flush(); this.blur(); } });
    });
  }

  function syncHistory() {
    var e = F.entry, h = F.history.find(function (x) { return x.month === F.month; });
    if (!h) {
      var d = new Date(F.month + '-01T00:00:00');
      h = { month: F.month, m: d.toLocaleString('en', { month: 'short' }), label: F.monthLabel };
      F.history.push(h);
      F.history.sort(function (a, b) { return a.month < b.month ? -1 : 1; });
    }
    ['spon', 'info', 'subs', 'streams', 'meeting'].forEach(function (k) { h[k] = e[k]; });
  }

  /* ---------- phases ---------- */
  function renderStepper() {
    var ci = currentIdx();
    document.getElementById('stepper').innerHTML = F.phases.map(function (p, i) {
      var s = phaseStatus(p), pct = phasePct(p);
      return '<button type="button" class="pstep ' + (s === 'cp' ? 'd' : s === 'ip' ? 'c' : 'g') + (i === ci ? ' cur' : '') + '" data-to="ph-' + esc(p.key) + '">' +
        '<span class="pdot">' + (s === 'cp' ? I('check') : (i + 1)) + '</span><span class="pl">' + esc(p.short || p.name) + '</span><span class="pv">' + pct + '%</span></button>';
    }).join('');
    document.querySelectorAll('#stepper .pstep').forEach(function (b) {
      b.addEventListener('click', function () { var t = document.getElementById(this.dataset.to); if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    });
  }

  var noteSavers = {};
  function renderPhases(focusKey) {
    var box = document.getElementById('phases');
    var ci = currentIdx();
    box.className = F.canEdit ? 'editable' : '';
    box.innerHTML = F.phases.map(function (p, pi) {
      var s = phaseStatus(p), pct = phasePct(p), isCur = pi === ci;
      var cls = 'phase ' + (s === 'cp' ? 'complete' : s === 'ip' ? 'inprogress' : '') + (isCur ? ' current' : '');
      var badge = s === 'cp' ? '<span class="badge cp">Complete</span>' : s === 'ip' ? '<span class="badge ip">In progress</span>' : '<span class="badge ns">Not started</span>';
      var nextKey = isCur ? (p.miles.find(function (m) { var x = st(m.key); return x === 'todo' || x === 'ip'; }) || {}).key : null;
      var c = counts(p);
      var miles = p.miles.map(function (m) {
        var ms = st(m.key);
        return '<li class="st-' + ms + (m.key === nextKey ? ' next' : '') + '" data-k="' + esc(m.key) + '"' + (F.canEdit ? ' role="button" tabindex="0" aria-label="' + esc(m.t) + ': ' + LABEL[ms] + '"' : '') + '>' +
          '<span class="sdot">' + (ms === 'cp' ? I('check') : '') + '</span><span class="mt">' + esc(m.t) + '</span>' +
          (m.key === nextKey ? '<span class="nextup">Next up</span>' : '') +
          (m.unlock ? '<span class="unlock">' + esc(m.unlock) + '</span>' : '') + '<span class="spill">' + LABEL[ms] + '</span></li>';
      }).join('');
      var note = F.notes[p.key] || '';
      var notes = F.canEdit
        ? '<textarea data-p="' + esc(p.key) + '" rows="2" placeholder="What we worked on, blockers, next step…">' + esc(note) + '</textarea>'
        : '<div class="ro">' + (note ? esc(note) : '<span class="muted">No notes this month.</span>') + '</div>';
      return '<div class="' + cls + '" id="ph-' + esc(p.key) + '"><span class="edge"></span>' +
        '<div class="ph-top"><div class="mid"><div class="num">' + esc(p.num) + ' · ' + c.done + '/' + c.active + ' steps</div><h3>' + esc(p.name) + '</h3></div>' + badge + (isCur ? '<span class="badge cur">Current</span>' : '') + '</div>' +
        '<div class="ph-prog"><div class="track"><i style="width:' + pct + '%"></i></div><span class="p">' + pct + '%</span></div>' +
        '<ul class="mile">' + miles + '</ul>' +
        '<div class="notes"><label>' + I('edit') + 'Notes · ' + esc(F.monthLabel) + '</label>' + notes + '</div></div>';
    }).join('');

    if (!F.canEdit) return;
    box.querySelectorAll('.mile li').forEach(function (li) {
      function cycle(dir) {
        var k = li.dataset.k, i = ORDER.indexOf(st(k)), next = ORDER[(i + (dir || 1) + ORDER.length) % ORDER.length];
        F.statuses[k] = next; F.inheritedFrom = null;
        send({ action: 'status', key: k, status: next });
        renderPhases(k); renderStepper(); renderRing(); renderInherit();
        if (next === 'cp' && phaseStatus(F.phases.find(function (p) { return p.miles.some(function (m) { return m.key === k; }); })) === 'cp') U.toast('Phase complete — nice work!');
      }
      li.addEventListener('click', function () { cycle(1); });
      li.addEventListener('contextmenu', function (e) { e.preventDefault(); cycle(-1); });
      li.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cycle(1); }
        if (e.key === 'Backspace') { e.preventDefault(); cycle(-1); }
      });
    });
    box.querySelectorAll('textarea[data-p]').forEach(function (ta) {
      var key = ta.dataset.p;
      if (!noteSavers[key]) noteSavers[key] = U.debounce(function () { send({ action: 'note', phase: key, notes: F.notes[key] || '' }); }, 800);
      autosize(ta);
      ta.addEventListener('input', function () { F.notes[key] = this.value; autosize(this); noteSavers[key](); });
    });
    if (focusKey) { var again = box.querySelector('li[data-k="' + focusKey + '"]'); if (again) again.focus({ preventScroll: true }); }
  }
  function autosize(ta) { ta.style.height = 'auto'; ta.style.height = Math.min(320, ta.scrollHeight + 2) + 'px'; }

  /* ---------- feedback thread ---------- */
  function fbItem(f) {
    var mine = f.authorId === F.me.id;
    return '<div class="msg' + (mine ? ' mine' : '') + '" data-id="' + f.id + '">' + U.avatar(f.initials, f.hue, 'sm') +
      '<div class="bubble"><div class="mh"><b>' + esc(f.author) + '</b>' + (f.role ? '<span class="role-chip r-' + esc(f.role) + '">' + esc(f.role === 'superadmin' ? 'Super Admin' : f.role === 'admin' ? 'Admin' : 'Creator') + '</span>' : '') +
      '<span class="muted">' + esc(f.ago) + '</span>' + (f.canDelete ? '<button type="button" class="del" title="Delete" aria-label="Delete">' + I('trash') + '</button>' : '') + '</div>' +
      '<div class="mb">' + esc(f.body).replace(/\n/g, '<br>') + '</div></div></div>';
  }
  function renderThread() {
    var box = document.getElementById('thread');
    var list = F.feedback.length ? F.feedback.map(fbItem).join('') : '<div class="empty small">' + I('chat') + '<span>No feedback for ' + esc(F.monthLabel) + ' yet.' + (F.isSelf ? ' Questions for the team? Post them here.' : ' Leave a note for the creator.') + '</span></div>';
    box.innerHTML = '<div class="msgs" id="msgs">' + list + '</div>' +
      '<form class="composer" id="composer">' + U.avatar(F.me.name.split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase(), (F.me.id * 47 + 190) % 360, 'sm') +
      '<textarea id="fbbody" rows="1" placeholder="' + (F.isSelf ? 'Ask the team or share an update…' : 'Write feedback for ' + esc(F.monthName) + '…') + '"></textarea>' +
      '<button class="btn primary sm" type="submit">' + I('send') + '<span>Post</span></button></form><p class="hint small">Ctrl/⌘ + Enter to post.</p>';
    var cnt = document.getElementById('fbcount'); cnt.textContent = F.feedback.length || ''; cnt.hidden = !F.feedback.length;
    var ta = document.getElementById('fbbody');
    ta.addEventListener('input', function () { autosize(this); });
    ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); document.getElementById('composer').requestSubmit(); } });
    document.getElementById('composer').addEventListener('submit', function (e) {
      e.preventDefault();
      var body = ta.value.trim(); if (!body) return;
      var btn = this.querySelector('button'); btn.disabled = true;
      send({ action: 'feedback_add', body: body }).then(function (r) {
        btn.disabled = false;
        if (r.ok && r.item) { F.feedback.push(r.item); renderThread(); U.toast('Feedback posted'); var m = document.getElementById('msgs'); m.lastElementChild.scrollIntoView({ block: 'nearest' }); }
      });
    });
    box.querySelectorAll('.msg .del').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = +this.closest('.msg').dataset.id;
        if (!confirm('Delete this feedback?')) return;
        send({ action: 'feedback_del', id: id }).then(function (r) {
          if (r.ok) { F.feedback = F.feedback.filter(function (f) { return f.id !== id; }); renderThread(); }
        });
      });
    });
  }

  /* ---------- charts (with hover tooltips) ---------- */
  function niceCeil(v) { if (v <= 0) return 10; var mag = Math.pow(10, Math.floor(Math.log10(v))), n = v / mag; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * mag; }
  function grid(W, L, R, y, maxV, fmt) {
    var g = '';
    for (var i = 0; i <= 4; i++) { var val = maxV * i / 4, yy = y(val); g += '<line x1="' + L + '" y1="' + yy + '" x2="' + (W - R) + '" y2="' + yy + '" stroke="rgba(255,255,255,' + (i ? '.06' : '.12') + ')"/><text x="' + (L - 8) + '" y="' + (yy + 3.5) + '" text-anchor="end" class="axt">' + fmt(val) + '</text>'; }
    return g;
  }
  function barsSVG(data) {
    var W = 720, H = 240, L = 52, R = 14, T = 16, B = 34, pw = W - L - R, ph = H - T - B;
    if (!data.length) return '<div class="noplot">' + I('trend') + 'No months logged yet.</div>';
    var maxV = niceCeil(Math.max(1, Math.max.apply(null, data.map(function (d) { return (+d.spon || 0) + (+d.info || 0); }))));
    var slot = pw / data.length, bw = Math.min(46, slot * 0.52);
    function y(v) { return T + ph - (v / maxV) * ph; }
    var g = grid(W, L, R, y, maxV, function (v) { return '$' + U.kfmt(v); });
    data.forEach(function (d, i) {
      var cx = L + i * slot + slot / 2, x = cx - bw / 2, sp = +d.spon || 0, inf = +d.info || 0, yBase = T + ph, ySp = y(sp);
      var sel = d.month === F.month, op = sel ? '1' : '.6';
      if (sp > 0) g += '<rect x="' + x + '" y="' + ySp + '" width="' + bw + '" height="' + Math.max(0, yBase - ySp) + '" rx="4" fill="' + C.spon + '" opacity="' + op + '"/>';
      if (inf > 0) { var top = y(sp + inf), bot = sp > 0 ? ySp - 2 : yBase; g += '<rect x="' + x + '" y="' + top + '" width="' + bw + '" height="' + Math.max(0, bot - top) + '" rx="4" fill="' + C.info + '" opacity="' + op + '"/>'; }
      if (sel && sp + inf > 0) g += '<text x="' + cx + '" y="' + (y(sp + inf) - 7) + '" text-anchor="middle" class="axt hl">$' + U.kfmt(sp + inf) + '</text>';
      g += '<text x="' + cx + '" y="' + (H - 12) + '" text-anchor="middle" class="axt' + (sel ? ' hl' : '') + '">' + esc(d.m) + '</text>';
      g += '<rect class="hitbox" data-i="' + i + '" x="' + (cx - slot / 2) + '" y="' + T + '" width="' + slot + '" height="' + ph + '" fill="transparent"/>';
    });
    return '<svg viewBox="0 0 ' + W + ' ' + H + '" class="chart" preserveAspectRatio="xMidYMid meet">' + g + '</svg>';
  }
  function lineSVG(data) {
    var W = 720, H = 210, L = 52, R = 14, T = 16, B = 34, pw = W - L - R, ph = H - T - B;
    if (!data.length) return '<div class="noplot">' + I('users') + 'No subscriber counts logged yet.</div>';
    var maxV = niceCeil(Math.max(1, Math.max.apply(null, data.map(function (d) { return +d.subs || 0; }))));
    var slot = pw / data.length;
    function cx(i) { return L + i * slot + slot / 2; }
    function y(v) { return T + ph - (v / maxV) * ph; }
    var g = grid(W, L, R, y, maxV, U.kfmt);
    var pts = data.map(function (d, i) { return cx(i) + ',' + y(+d.subs || 0); });
    g += '<defs><linearGradient id="subfill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + C.subs + '" stop-opacity=".22"/><stop offset="1" stop-color="' + C.subs + '" stop-opacity="0"/></linearGradient></defs>';
    g += '<polygon points="' + cx(0) + ',' + (T + ph) + ' ' + pts.join(' ') + ' ' + cx(data.length - 1) + ',' + (T + ph) + '" fill="url(#subfill)"/>';
    g += '<polyline points="' + pts.join(' ') + '" fill="none" stroke="' + C.subs + '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
    data.forEach(function (d, i) {
      var sel = d.month === F.month;
      g += '<circle cx="' + cx(i) + '" cy="' + y(+d.subs || 0) + '" r="' + (sel ? 6 : 4) + '" fill="' + C.subs + '" stroke="#0a0c11" stroke-width="2"/>' +
        '<text x="' + cx(i) + '" y="' + (H - 12) + '" text-anchor="middle" class="axt' + (sel ? ' hl' : '') + '">' + esc(d.m) + '</text>' +
        '<rect class="hitbox" data-i="' + i + '" x="' + (cx(i) - slot / 2) + '" y="' + T + '" width="' + slot + '" height="' + ph + '" fill="transparent"/>';
    });
    return '<svg viewBox="0 0 ' + W + ' ' + H + '" class="chart" preserveAspectRatio="xMidYMid meet">' + g + '</svg>';
  }
  function attachTip(card, data, fmt) {
    var tip = card.querySelector('.tip');
    card.querySelectorAll('.hitbox').forEach(function (r) {
      function show() {
        var d = data[+r.dataset.i]; tip.innerHTML = fmt(d); tip.hidden = false;
        var cb = card.getBoundingClientRect(), rb = r.getBoundingClientRect();
        var left = rb.left - cb.left + rb.width / 2;
        tip.style.left = Math.max(70, Math.min(cb.width - 70, left)) + 'px';
        tip.style.top = (rb.top - cb.top + 4) + 'px';
        card.querySelectorAll('.hitbox').forEach(function (h) { h.classList.toggle('on', h === r); });
      }
      r.addEventListener('mouseenter', show); r.addEventListener('click', show);
    });
    card.addEventListener('mouseleave', function () { tip.hidden = true; card.querySelectorAll('.hitbox').forEach(function (h) { h.classList.remove('on'); }); });
  }
  function renderCharts() {
    var rev = F.history, subs = F.history.filter(function (d) { return d.subs != null; });
    var legend = '<div class="legend"><span><i style="background:' + C.spon + '"></i>Sponsor</span><span><i style="background:' + C.info + '"></i>Info product</span></div>';
    var box = document.getElementById('charts');
    box.innerHTML = '<div class="chcard" id="ch-rev"><div class="chh"><h4>' + I('dollar') + 'Revenue</h4>' + legend + '</div>' + barsSVG(rev) + '<div class="tip" hidden></div></div>' +
      '<div class="chcard" id="ch-subs"><div class="chh"><h4>' + I('users') + 'Subscribers</h4><span class="chsub">growth over time</span></div>' + lineSVG(subs) + '<div class="tip" hidden></div></div>';
    attachTip(document.getElementById('ch-rev'), rev, function (d) {
      return '<b>' + esc(d.label) + '</b><span><i style="background:' + C.spon + '"></i>Sponsor $' + U.num(+d.spon || 0) + '</span><span><i style="background:' + C.info + '"></i>Info $' + U.num(+d.info || 0) + '</span><span class="tt">Total $' + U.num((+d.spon || 0) + (+d.info || 0)) + '</span>';
    });
    attachTip(document.getElementById('ch-subs'), subs, function (d) {
      var i = subs.indexOf(d), p = i > 0 ? subs[i - 1].subs : null;
      return '<b>' + esc(d.label) + '</b><span class="tt">' + U.num(d.subs) + ' subs</span>' + (p != null ? '<span class="' + (d.subs - p >= 0 ? 'up' : 'down') + '">' + U.growth(d.subs - p) + ' vs ' + esc(subs[i - 1].m) + '</span>' : '');
    });
  }

  function renderTable() {
    var t = document.getElementById('mtable');
    var h = F.history;
    var rows = h.slice().reverse().map(function (d) {
      var i = h.indexOf(d), p = i > 0 ? h[i - 1] : null;
      var sg = p && d.subs != null && p.subs != null ? d.subs - p.subs : null;
      var href = 'creator.php?' + (F.isSelf ? '' : 'id=' + F.cid + '&') + 'm=' + d.month;
      return '<tr' + (d.month === F.month ? ' class="sel"' : '') + '><td><a href="' + href + '">' + esc(d.label) + '</a></td>' +
        '<td class="n">$' + U.num((+d.spon || 0) + (+d.info || 0)) + '</td>' +
        '<td class="n">' + U.num(d.subs) + '</td>' +
        '<td class="n ' + (sg > 0 ? 'up' : sg < 0 ? 'down' : '') + '">' + U.growth(sg) + '</td>' +
        '<td class="n">' + (d.streams == null ? '—' : d.streams) + '</td></tr>';
    }).join('');
    t.innerHTML = '<thead><tr><th>Month</th><th class="n">Revenue</th><th class="n">Subscribers</th><th class="n">Growth</th><th class="n">Streams</th></tr></thead>' +
      '<tbody>' + (rows || '<tr><td colspan="5" class="muted">No months logged yet.</td></tr>') + '</tbody>';
  }

  function renderFeed() {
    var box = document.getElementById('feed');
    box.innerHTML = F.activity.length ? F.activity.map(function (a) {
      return '<div class="fi"><span class="fdot ' + (a.self ? 'c' : 't') + '"></span><div class="ft"><b>' + esc(a.actor) + '</b> ' + esc(a.text) + '</div><span class="fa">' + esc(a.ago) + '</span></div>';
    }).join('') : '<div class="empty small">No activity yet.</div>';
  }

  /* ---------- sticky sub-nav highlighting ---------- */
  function subnav() {
    var links = [].slice.call(document.querySelectorAll('#subnav a'));
    var secs = links.map(function (a) { return document.querySelector(a.getAttribute('href')); });
    links.forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); document.querySelector(a.getAttribute('href')).scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    });
    if (!('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { var i = secs.indexOf(en.target); links.forEach(function (l, j) { l.classList.toggle('on', i === j); }); }
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    secs.forEach(function (s) { if (s) io.observe(s); });
  }

  renderRing(); renderInherit(); renderGoals(); renderEntryMeta(); renderEntry();
  renderStepper(); renderPhases(); renderThread(); renderCharts(); renderTable(); renderFeed(); subnav();
})();
