/* Fluence — creator tracker (month-based) */
(function () {
  var F = window.FL, U = window.FLU, esc = U.esc, I = U.icon;
  var LABEL = { todo: 'To do', ip: 'In progress', cp: 'Complete', sk: 'Skipped' };
  var drafts = {};
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
  window.addEventListener('beforeunload', function (e) { if (inflight || Object.keys(drafts).length) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- progress ---------- */
  function itemProgress(item, saved, legacy) {
    var type = item.type || 'check', value = saved ? saved.value : null, target = String(item.target == null ? '' : item.target).trim();
    if (type === 'check') {
      if (value == null || value === '') return {status:legacy || 'todo', percent:legacy === 'cp' ? 100 : 0};
      var checked = value === true || value === 1 || value === '1';
      return {status:checked ? 'cp' : 'todo', percent:checked ? 100 : 0};
    }
    if (value == null && legacy === 'sk') return {status:'sk', percent:0};
    value = String(value == null ? '' : value).trim();
    if (!value || ((type === 'number' || type === 'money') && +value <= 0)) return {status:'todo', percent:0};
    if (type === 'text') {
      var done = !target || value.toLowerCase() === target.toLowerCase();
      return {status:done ? 'cp' : 'ip', percent:done ? 100 : 0};
    }
    if (!target || +target <= 0) return {status:'ip', percent:0};
    var pct = Math.min(100, +value / +target * 100);
    return {status:pct >= 100 ? 'cp' : 'ip', percent:pct};
  }
  function displayedPct(progress) { return progress.status === 'cp' ? 100 : Math.min(99, Math.round(progress.percent)); }
  function resultState(item) { return itemProgress(item, drafts[item.key] || F.values[item.key], F.statuses[item.key] || (F.values[item.key] || {}).status); }
  function leaves(nodes) {
    return nodes.reduce(function (all, node) { return all.concat(node.children && node.children.length ? leaves(node.children) : [node]); }, []);
  }
  function aggregate(nodes) {
    var active = 0, done = 0, sum = 0, started = false;
    leaves(nodes).forEach(function (item) {
      var p = resultState(item);
      if (p.status === 'sk') return;
      active++; sum += p.percent;
      if (p.status === 'cp') done++;
      if (p.status !== 'todo') started = true;
    });
    var complete = nodes.length && (!active || done === active);
    return {status:complete ? 'cp' : started ? 'ip' : 'todo', percent:active ? sum / active : complete ? 100 : 0, done:done, active:active};
  }
  function nodeProgress(node) { return node.children && node.children.length ? aggregate(node.children) : resultState(node); }
  function nodeStatus(node) { return nodeProgress(node).status; }
  function phaseStatus(p) { var status = aggregate(p.miles).status; return status === 'todo' ? 'ns' : status; }
  function phasePct(p) { return displayedPct(aggregate(p.miles)); }
  function overall() { return displayedPct(aggregate(F.phases.reduce(function (all, p) { return all.concat(p.miles); }, []))); }
  function currentIdx() { return F.phases.findIndex(function (p) { return phaseStatus(p) !== 'cp'; }); }

  function renderRing() {
    var pct = overall(), ci = currentIdx(), cur = F.phases[ci];
    var started = F.phases.some(function (p) { return phaseStatus(p) !== 'ns'; });
    document.getElementById('ring').innerHTML =
      '<div class="ring-wrap">' + U.ring(pct, 128, 11) + '<div class="ring-c"><b>' + pct + '<small>%</small></b><span>overall</span></div></div>' +
      '<div class="ring-txt"><span class="sl">Currently in</span><b>' + (cur ? esc(cur.num.replace('PHASE', 'Phase')) + ' · ' + esc(cur.name) : (F.phases.length ? 'All phases complete 🎉' : 'No path phases yet')) + '</b>' +
      '<span class="muted">' + (cur ? (started ? phasePct(cur) + '% through this phase' : 'Roadmap not started') : (F.phases.length ? 'Roadmap finished' : 'An admin can build your path')) + '</span></div>';
  }

  function renderInherit() {
    var el = document.getElementById('inherit');
    el.hidden = !F.inheritedFrom;
    if (F.inheritedFrom) el.innerHTML = I('clock') + '<span>Step progress carried over from <b>' + esc(F.inheritedFrom) + '</b>.' + (F.canEdit ? ' Change any step to save ' + esc(F.monthLabel) + '’s own snapshot.' : '') + '</span>';
  }

  /* ---------- goals / KPI tiles ---------- */
  function totalRev(e) { return e ? (+e.spon || 0) + (+e.info || 0) : null; }
  function valueFormat(type, value) {
    if (value == null || value === '') return '—';
    return type === 'money' ? '$' + U.num(value) : type === 'number' ? U.num(value) : esc(value);
  }
  var resultSavers = {}, resultBusy = {}, resultAgain = {}, itemIndex = {};
  F.goalDefinitions.forEach(function (item) { itemIndex[item.key] = item; });
  function indexTasks(nodes) { nodes.forEach(function (item) { itemIndex[item.key] = item; indexTasks(item.children || []); }); }
  F.phases.forEach(function (phase) { indexTasks(phase.miles); });
  function resultForm(item, kind) {
    var saved = drafts[item.key] || F.values[item.key] || {}, type = item.type || 'check';
    var checked = resultState(item).status === 'cp';
    return '<form class="result-form" data-item="' + esc(item.key) + '" data-kind="' + kind + '">' +
      (type === 'check' ? '<label class="task-check"><input name="value" type="checkbox"' + (checked ? ' checked' : '') + '>Mark complete</label>' :
        '<label>Your result' + (type === 'money' ? ' ($)' : '') +
        (type === 'text' ? '<textarea name="value" rows="2" maxlength="3000">' + esc(saved.value || '') + '</textarea>' :
          '<input name="value" type="number" min="0" max="999999999999" step="0.01" value="' + esc(saved.value == null ? '' : saved.value) + '">') + '</label>') +
      '<span class="result-message" role="status">Changes save automatically.</span></form>';
  }
  function resultMessage(key, message, failed) {
    document.querySelectorAll('.result-form').forEach(function (form) {
      if (form.dataset.item !== key) return;
      var el = form.querySelector('.result-message'); el.textContent = message;
      if (failed) {
        var retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn sm ghost'; retry.textContent = 'Retry';
        retry.addEventListener('click', function () { saveResult(key); }); el.appendChild(retry);
      }
    });
  }
  function saveResult(key) {
    if (!drafts[key]) return;
    if (resultBusy[key]) { resultAgain[key] = true; return; }
    var value = drafts[key].value;
    resultBusy[key] = true; resultMessage(key, 'Saving…');
    send({action:'item_value', key:key, value:value}).then(function (res) {
      resultBusy[key] = false;
      if (res.ok) {
        F.values[key] = {value:res.value, status:res.status}; F.statuses[key] = res.status;
        if (drafts[key] && drafts[key].value === value) delete drafts[key];
        if (!F.goalDefinitions.some(function (g) { return g.key === key; })) { F.inheritedFrom = null; renderInherit(); }
        resultMessage(key, drafts[key] ? 'Saving…' : 'Saved');
        paintProgress();
      } else resultMessage(key, res.error || 'Not saved. Please retry.', true);
      if (resultAgain[key]) { resultAgain[key] = false; saveResult(key); }
    });
  }
  function bindResults(box) {
    box.querySelectorAll('.result-form').forEach(function (form) {
      var key = form.dataset.item, input = form.elements.value;
      if (!resultSavers[key]) resultSavers[key] = U.debounce(function () { saveResult(key); }, 650);
      function changed() {
        if (!input.checkValidity()) { resultMessage(key, input.validationMessage); return; }
        drafts[key] = {value:input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value};
        resultMessage(key, 'Saving…'); paintProgress(); resultSavers[key]();
      }
      input.addEventListener('input', changed);
      input.addEventListener('change', function () { changed(); resultSavers[key].flush(); });
      input.addEventListener('blur', function () { resultSavers[key].flush(); });
      form.addEventListener('submit', function (event) { event.preventDefault(); resultSavers[key].flush(); });
    });
  }
  function summarySteps(nodes) {
    return nodes.map(function (node, i) {
      var p = nodeProgress(node), pct = displayedPct(p);
      return '<button type="button" class="pstep ' + (p.status === 'cp' ? 'd' : p.status === 'ip' ? 'c' : 'g') + '" data-task-to="' + esc(node.key) + '"><span class="pdot">' + (p.status === 'cp' ? I('check') : i + 1) + '</span><span class="pl">' + esc(node.t) + '</span><span class="pv">' + pct + '%</span><span class="task-summary-track" aria-hidden="true"><span style="width:' + pct + '%"></span></span></button>';
    }).join('');
  }
  function paintProgress() {
    renderRing(); renderStepper();
    var currentPhase = F.phases[currentIdx()];
    F.phases.forEach(function (phase) {
      var el = document.getElementById('ph-' + phase.key); if (!el) return;
      var p = aggregate(phase.miles), pct = displayedPct(p);
      el.classList.toggle('current', !!currentPhase && phase.key === currentPhase.key);
      el.classList.toggle('complete', p.status === 'cp'); el.classList.toggle('inprogress', p.status === 'ip');
      el.querySelector('.phase-percent').textContent = pct + '%';
      el.querySelector('.ph-prog .track i').style.width = pct + '%';
      var badge = el.querySelector('.phase-status'); badge.textContent = LABEL[p.status]; badge.className = 'badge phase-status ' + p.status;
      el.querySelector('.task-summary-rail').innerHTML = summarySteps(phase.miles);
    });
    document.querySelectorAll('.task-node[data-task]').forEach(function (el) {
      var item = itemIndex[el.dataset.task], p = nodeProgress(item), pct = displayedPct(p);
      el.classList.remove('st-todo','st-ip','st-cp','st-sk'); el.classList.add('st-' + p.status);
      el.querySelector('.task-progress-label').textContent = LABEL[p.status] + ' · ' + pct + '%';
      el.querySelector('.task-progress-fill').style.width = pct + '%';
    });
    document.querySelectorAll('.goal[data-goal]').forEach(function (el) {
      var g = itemIndex[el.dataset.goal], p = goalProgress(g), current = goalCurrent(g);
      el.classList.toggle('done', p.status === 'cp');
      el.querySelector('.goal-result').innerHTML = g.type === 'check' ? esc(LABEL[p.status]) : valueFormat(g.type, current);
      el.querySelector('.goal-auto-status').textContent = LABEL[p.status] + ' · ' + displayedPct(p) + '%';
      var bar = el.querySelector('.gbar i'); if (bar) bar.style.width = displayedPct(p) + '%';
    });
  }
  function goalCurrent(g) {
    return !g.source || g.source === 'manual' ? ((drafts[g.key] || F.values[g.key] || {}).value) : g.source === 'revenue' ? totalRev(F.entry) : F.entry ? F.entry[g.source] : null;
  }
  function goalProgress(g) { return itemProgress(g, {value:goalCurrent(g)}, (F.values[g.key] || {}).status); }

  function renderGoals() {
    var box = document.getElementById('goals');
    box.innerHTML = F.goalDefinitions.map(function (g) {
      var manual = !g.source || g.source === 'manual', saved = drafts[g.key] || F.values[g.key] || {};
      var current = goalCurrent(g);
      var comparison = '';
      if (!manual && F.prev) {
        var previous = g.source === 'revenue' ? totalRev(F.prev) : F.prev[g.source];
        if (current != null && previous != null) {
          var diff = +current - +previous;
          comparison = '<div class="delta ' + (diff > 0 ? 'up' : diff < 0 ? 'down' : '') + '">' +
            (diff > 0 ? '+' : diff < 0 ? '−' : '') + valueFormat(g.type, Math.abs(diff)) + ' vs ' + esc(F.prev.name) + '</div>';
        }
      }
      var numeric = g.type === 'money' || g.type === 'number';
      var has = current != null && current !== '', target = g.target, pct = 0;
      if (numeric && has && target !== '' && +target > 0) pct = Math.min(100, Math.round(+current / +target * 100));
      var progress = goalProgress(g), complete = progress.status === 'cp';
      pct = displayedPct(progress);
      return '<div class="kpi goal' + (complete ? ' done' : '') + '" data-goal="' + esc(g.key) + '"><div class="kpi-l">' + I(g.type === 'money' ? 'dollar' : 'target') + esc(g.title) + '</div>' +
        (g.description ? '<p class="goal-description">' + esc(g.description) + '</p>' : '') +
        '<div class="goal-result">' + (g.type === 'check' ? esc(LABEL[progress.status]) : valueFormat(g.type, current)) + '</div>' +
        comparison + (target !== '' && target != null ? '<div class="goal-target">Target: ' + valueFormat(g.type, target) + '</div>' : '') +
        (numeric && target !== '' ? '<div class="gbar"><i style="width:' + pct + '%"></i></div>' : '') +
        '<div class="goal-auto-status">' + LABEL[progress.status] + ' · ' + pct + '%</div>' +
        (manual ? (F.canRespond ? resultForm(g, 'goal') : '<span class="spill">' + esc(LABEL[progress.status]) + '</span>') : '<p class="hint">From monthly numbers' + (complete ? ' · Goal hit' : '') + '</p>') + '</div>';
    }).join('') || '<div class="empty">No destination goals yet. An admin can add them in Edit destinations &amp; path.</div>';
    bindResults(box);
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

  var noteSavers = {}, expandedPhases = {};
  function renderTasks(nodes) {
    return '<ul class="task-tree">' + nodes.map(function (m) {
      var children = m.children || [], leaf = !children.length, p = nodeProgress(m), pct = displayedPct(p), saved = drafts[m.key] || F.values[m.key] || {};
      return '<li class="task-node st-' + p.status + '" data-task="' + esc(m.key) + '" id="task-' + esc(m.key) + '"><div class="task-heading"><h4>' + esc(m.t) + '</h4><span class="spill task-progress-label">' + LABEL[p.status] + ' · ' + pct + '%</span></div>' +
        '<div class="task-progress-track"><i class="task-progress-fill" style="width:' + pct + '%"></i></div>' +
        (m.description ? '<p class="task-description">' + esc(m.description) + '</p>' : '') +
        (m.target !== '' && m.target != null ? '<div class="goal-target">Target: ' + valueFormat(m.type || 'check', m.target) + '</div>' : '') +
        ((m.type === 'number' || m.type === 'money') && !(+m.target > 0) ? '<p class="hint">An admin can add a positive target to measure completion.</p>' : '') +
        (leaf ? (F.canRespond ? resultForm(m, 'task') : (m.type && m.type !== 'check' ? '<div class="goal-result">' + valueFormat(m.type, saved.value) + '</div>' : '')) : renderTasks(children)) + '</li>';
    }).join('') + '</ul>';
  }
  function renderPhases() {
    var box = document.getElementById('phases'), ci = currentIdx();
    box.innerHTML = F.phases.map(function (p, pi) {
      var progress = aggregate(p.miles), status = progress.status, pct = displayedPct(progress), note = F.notes[p.key] || '';
      var notes = F.canEdit ? '<textarea data-p="' + esc(p.key) + '" rows="2" placeholder="What we worked on, blockers, next step…">' + esc(note) + '</textarea>'
        : '<div class="ro">' + (note ? esc(note) : '<span class="muted">No notes this month.</span>') + '</div>';
      return '<section class="phase ' + (status === 'cp' ? 'complete' : status === 'ip' ? 'inprogress' : '') + (pi === ci ? ' current' : '') + '" id="ph-' + esc(p.key) + '"><span class="edge"></span>' +
        '<div class="ph-top"><div class="mid"><div class="num">' + esc(p.num) + '</div><h3>' + esc(p.name) + '</h3></div><span class="badge phase-status ' + status + '">' + LABEL[status] + '</span></div>' +
        '<div class="ph-prog"><div class="track"><i style="width:' + pct + '%"></i></div><span class="p phase-percent">' + pct + '%</span></div>' +
        '<div class="task-summary-rail" aria-label="' + esc(p.name) + ' tasks">' + summarySteps(p.miles) + '</div>' +
        '<button class="btn sm ghost phase-expand" type="button" data-phase="' + esc(p.key) + '" aria-expanded="' + !!expandedPhases[p.key] + '" aria-controls="phase-details-' + esc(p.key) + '">' + (expandedPhases[p.key] ? 'Collapse details' : 'Expand details') + I('chevron') + '</button>' +
        '<div class="phase-details" id="phase-details-' + esc(p.key) + '"' + (expandedPhases[p.key] ? '' : ' hidden') + '>' +
        (p.miles.length ? renderTasks(p.miles) : '<p class="muted">No tasks yet. An admin can add tasks to this phase.</p>') +
        '<div class="notes"><label>' + I('edit') + 'Notes · ' + esc(F.monthLabel) + '</label>' + notes + '</div></div></section>';
    }).join('') || '<div class="empty">No path phases yet. An admin can add them in Edit destinations &amp; path.</div>';
    bindResults(box);
    box.querySelectorAll('textarea[data-p]').forEach(function (ta) {
      var key = ta.dataset.p;
      if (!noteSavers[key]) noteSavers[key] = U.debounce(function () { send({ action:'note', phase:key, notes:F.notes[key] || '' }); }, 800);
      ta.addEventListener('input', function () { F.notes[key] = this.value; autosize(this); noteSavers[key](); });
    });
    box.onclick = function (event) {
      var toggle = event.target.closest('.phase-expand'), step = event.target.closest('[data-task-to]');
      if (toggle) {
        var key = toggle.dataset.phase; expandedPhases[key] = !expandedPhases[key];
        document.getElementById('phase-details-' + key).hidden = !expandedPhases[key];
        toggle.setAttribute('aria-expanded', String(expandedPhases[key]));
        toggle.innerHTML = (expandedPhases[key] ? 'Collapse details' : 'Expand details') + I('chevron');
        if (expandedPhases[key]) document.getElementById('phase-details-' + key).querySelectorAll('textarea[data-p]').forEach(autosize);
      } else if (step) {
        var phase = step.closest('.phase'), button = phase.querySelector('.phase-expand');
        if (button.getAttribute('aria-expanded') !== 'true') button.click();
        var task = document.getElementById('task-' + step.dataset.taskTo);
        if (task) { task.scrollIntoView({behavior:'smooth', block:'center'}); var input = task.querySelector('input, textarea'); if (input) input.focus({preventScroll:true}); }
      }
    };
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
    var nav = document.getElementById('subnav'), header = document.querySelector('.nav');
    function measureToolbar() {
      var headerHeight = header ? header.getBoundingClientRect().height : 0;
      document.documentElement.style.setProperty('--tracker-header-height', headerHeight + 'px');
      document.documentElement.style.scrollPaddingTop = (headerHeight + nav.getBoundingClientRect().height + 18) + 'px';
    }
    measureToolbar();
    if ('ResizeObserver' in window) {
      var toolbarObserver = new ResizeObserver(measureToolbar);
      if (header) toolbarObserver.observe(header);
      toolbarObserver.observe(nav);
    }
    window.addEventListener('resize', measureToolbar);
    var links = [].slice.call(document.querySelectorAll('#subnav a[href^="#"]'));
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
