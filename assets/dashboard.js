/* Fluence — admin dashboard */
(function () {
  var D = window.FL_DASH, U = window.FLU, esc = U.esc, I = U.icon;
  var list = D.creators, K = D.kpi;
  var state = { q: '', filter: 'all', sort: 'name', view: 'grid' };
  try { var saved = JSON.parse(localStorage.getItem('fl_dash') || '{}'); if (saved.sort) state.sort = saved.sort; if (saved.view) state.view = saved.view; } catch (e) {}
  function persist() { try { localStorage.setItem('fl_dash', JSON.stringify({ sort: state.sort, view: state.view })); } catch (e) {} }

  function tone(cr) { return cr.overall >= 100 ? 'd' : (cr.current.k === 'new' ? 'g' : 'c'); }
  function sgc(v) { return v > 0 ? 'up' : (v < 0 ? 'down' : ''); }
  function needs(cr) { return cr.active && !cr.logged; }

  /* ---------- KPI strip ---------- */
  function renderKpis() {
    var d = U.pctDelta(K.rev, K.revPrev);
    var dHtml = d == null ? '<span class="delta">' + (K.revPrev ? '' : 'No ' + esc(D.prevName) + ' data') + '</span>'
      : '<span class="delta ' + (d >= 0 ? 'up' : 'down') + '">' + (d >= 0 ? '▲ ' : '▼ ') + Math.abs(d) + '% vs ' + esc(D.prevName) + '</span>';
    var pending = K.active - K.logged;
    var lp = K.active ? Math.round(K.logged / K.active * 100) : 0;
    document.getElementById('kpis').innerHTML =
      tile('dollar', esc(D.monthName) + ' revenue', U.money(K.rev), dHtml) +
      tile('trend', 'Net sub growth', '<span class="' + sgc(K.subGrowth) + '">' + U.growth(K.subGrowth) + '</span>', '<span class="delta">across creators who logged</span>') +
      '<div class="kpi ring-kpi"><div class="kpi-ring">' + U.ring(K.avgProgress, 58, 7) + '<b>' + K.avgProgress + '%</b></div><div><div class="kpi-l">' + I('target') + 'Avg. roadmap progress</div><div class="kpi-s">' + K.active + ' active creator' + (K.active === 1 ? '' : 's') + '</div></div></div>' +
      tile('calendar', 'Logged ' + esc(D.monthName), K.logged + '<small>/' + K.active + '</small>',
        '<div class="mini-bar"><i style="width:' + lp + '%"></i></div><span class="delta' + (pending ? ' warn' : ' up') + '">' + (pending ? pending + ' still to update' : 'Everyone is up to date') + '</span>');
  }
  function tile(ic, label, val, foot) {
    return '<div class="kpi"><div class="kpi-l">' + I(ic) + label + '</div><div class="kpi-v">' + val + '</div><div class="kpi-s">' + foot + '</div></div>';
  }

  /* ---------- filter chips ---------- */
  var FILTERS = [{ k: 'all', label: 'All', test: function (c) { return true; } },
    { k: 'needs', label: 'Needs update', test: needs, warn: true }]
    .concat(D.phases.map(function (p) { return { k: p.key, label: p.label, test: function (c) { return c.active && c.current.k === p.key; } }; }))
    .concat([{ k: 'done', label: 'Complete', test: function (c) { return c.active && c.current.k === 'done'; } },
      { k: 'new', label: 'Not started', test: function (c) { return c.active && c.current.k === 'new'; } },
      { k: 'off', label: 'Disabled', test: function (c) { return !c.active; } }]);

  function renderChips() {
    document.getElementById('chips').innerHTML = FILTERS.map(function (f) {
      var n = list.filter(f.test).length;
      if (!n && f.k !== 'all' && f.k !== state.filter && (f.k === 'off' || f.k === 'new' || f.k === 'needs')) return '';
      return '<button type="button" role="tab" class="chip' + (state.filter === f.k ? ' on' : '') + (f.warn && n ? ' warn' : '') + '" data-f="' + f.k + '">' + esc(f.label) + ' <i>' + n + '</i></button>';
    }).join('');
    document.querySelectorAll('#chips .chip').forEach(function (b) {
      b.addEventListener('click', function () { state.filter = this.dataset.f; renderChips(); renderGrid(); });
    });
  }

  /* ---------- grid / list ---------- */
  function sorted(arr) {
    var s = state.sort, a = arr.slice();
    a.sort(function (x, y) {
      if (x.active !== y.active) return x.active ? -1 : 1;
      if (s === 'progress') return y.overall - x.overall;
      if (s === 'rev') return (y.rev == null ? -1 : y.rev) - (x.rev == null ? -1 : x.rev);
      if (s === 'growth') return (y.subGrowth == null ? -1e12 : y.subGrowth) - (x.subGrowth == null ? -1e12 : x.subGrowth);
      if (s === 'updated') return y.updatedAt - x.updatedAt;
      return x.name.localeCompare(y.name);
    });
    return a;
  }
  function segbar(cr) {
    return '<div class="segbar" title="Progress by phase">' + cr.phases.map(function (p) {
      return '<span class="seg ' + (p.pct >= 100 ? 'd' : '') + '" title="' + esc(p.label) + ' ' + p.pct + '%"><i style="width:' + p.pct + '%"></i></span>';
    }).join('') + '</div>';
  }
  function sub(cr) {
    var parts = [cr.handle, cr.niche].filter(Boolean);
    return parts.length ? esc(parts.join(' · ')) : esc(cr.email);
  }
  function tag(cr) {
    if (!cr.active) return '<span class="tag dis">Disabled</span>';
    if (!cr.logged) return '<span class="tag nolog">Needs update</span>';
    return '';
  }
  function statsHtml(cr) {
    return '<div class="stat"><span class="sl">' + esc(D.monthName.slice(0, 3)) + ' revenue</span><span class="sv">' + U.money(cr.rev) + '</span></div>' +
      '<div class="stat"><span class="sl">Sub growth</span><span class="sv ' + sgc(cr.subGrowth) + '">' + U.growth(cr.subGrowth) + '</span></div>' +
      '<div class="stat"><span class="sl">Last meeting</span><span class="sv sm">' + esc(cr.lastMeeting || '—') + '</span></div>';
  }

  function goalValue(type, value) {
    if (value == null || value === '') return '—';
    if (type === 'money') return '$' + U.num(value);
    if (type === 'number') return U.num(value);
    return esc(value);
  }
  function goalsHtml(cr, compact) {
    var goals = cr.goals || [], shown = compact ? goals.slice(0, 3) : goals;
    var completed = goals.filter(function (goal) { return goal.status === 'cp'; }).length;
    var labels = {todo:'To do', ip:'In progress', cp:'Complete', sk:'Skipped'};
    return '<div class="dash-goals' + (compact ? ' compact' : '') + '"><div class="dash-goals-heading"><span>Destination goals</span><small>' + completed + '/' + goals.length + ' complete</small></div>' +
      (goals.length ? shown.map(function (goal) {
        var current = goal.type === 'check' ? (goal.status === 'cp' ? 'Complete' : labels[goal.status]) : goalValue(goal.type, goal.current);
        var target = goal.target !== '' && goal.target != null ? goalValue(goal.type, goal.target) : '';
        var status = goal.measurable ? goal.percent + '%' : labels[goal.status];
        return '<div class="dash-goal' + (goal.status === 'cp' ? ' achieved' : '') + '" data-goal-key="' + esc(goal.key) + '"><div class="dash-goal-top"><span class="dash-goal-name">' + esc(goal.title) + '</span><b>' + esc(status) + '</b></div>' +
          '<div class="dash-goal-numbers"><span class="dash-goal-current">' + current + '</span>' + (target ? '<span class="dash-goal-target"> / ' + target + '</span>' : '') + '</div>' +
          (goal.measurable ? '<div class="dash-goal-bar"><i style="width:' + goal.percent + '%"></i></div>' : '') + '</div>';
      }).join('') : '<p class="hint">No destination goals set.</p>') +
      (compact && goals.length > shown.length ? '<span class="dash-goals-more">+' + (goals.length - shown.length) + ' more · Open tracker to see all</span>' : '') + '</div>';
  }

  function socialsHtml(cr, compact) {
    var accounts = cr.socials || [], shown = compact ? accounts.slice(0,4) : accounts;
    return '<div class="dash-socials' + (compact ? ' compact' : '') + '"><div class="dash-goals-heading"><span>Social analytics · latest</span><small>' + accounts.length + ' profile' + (accounts.length === 1 ? '' : 's') + '</small></div>' +
      (shown.length ? shown.map(function (account) {
        var status = account.configured === false ? 'Setup required' : !account.active ? 'Paused' : account.status === 'syncing' ? 'Updating…' : account.error ? 'Refresh failed' : account.stale ? 'Outdated / awaiting sync' : 'Updated ' + account.updatedAgo;
        var identity = compact ? '<span>' + esc(account.label) + ' · @' + esc(account.handle) + '</span>' : '<a href="' + esc(account.url) + '" target="_blank" rel="noopener noreferrer">' + esc(account.label) + ' · @' + esc(account.handle) + I('external') + '</a>';
        var metrics = '<span><b>' + U.num(account.followers) + '</b> ' + esc(account.metric) + '</span>';
        if (!compact) ['posts','likes','views'].forEach(function (key) {
          if (account[key] != null) metrics += '<span><b>' + U.num(account[key]) + '</b> ' + ({posts:'Posts / videos', likes:'Total likes', views:'Channel views'})[key] + '</span>';
        });
        return '<div class="dash-social' + (account.stale || account.error ? ' stale' : '') + '"><div class="dash-social-identity">' + identity + '</div><div class="dash-social-metrics">' + metrics + '</div>' +
          '<span class="dash-social-updated" title="' + esc(account.lastSynced || 'No successful sync') + '">' + esc(status) + (account.lastSynced && (account.error || account.stale || account.status === 'syncing') ? ' · Last success ' + esc(account.updatedAgo) : '') + '</span>' +
          (!compact && account.error ? '<p class="social-error">' + esc(account.error) + '</p>' : '') +
          (!compact && account.growth != null ? '<p class="delta ' + (account.growth >= 0 ? 'up' : 'down') + '">' + U.growth(account.growth) + ' since ' + esc(account.history[0].date) + '</p>' : '') +
          (!compact && account.history.length > 1 ? U.spark(account.history.map(function (day) { return day.followers; }), 260, 35) : '') +
          (!compact && account.active ? '<button class="btn sm ghost" type="button" data-social-refresh="' + account.id + '" data-cid="' + cr.id + '">Refresh stats</button>' : '') + '</div>';
      }).join('') : '<p class="hint">No social profiles linked.</p>') +
      (compact && accounts.length > shown.length ? '<span class="dash-goals-more">+' + (accounts.length - shown.length) + ' more profiles in tracker</span>' : '') +
      (!compact ? '<p class="hint">Public counts reported by the provider; some platforms round or hide counts. Syncs run on the configured schedule. Refreshes are limited to once per hour per profile.</p>' : '') + '</div>';
  }

  function renderGrid() {
    var f = FILTERS.find(function (x) { return x.k === state.filter; }) || FILTERS[0];
    var shown = sorted(list.filter(function (c) {
      return f.test(c) && (!state.q || (c.name + ' ' + c.email + ' ' + (c.handle || '') + ' ' + (c.niche || '')).toLowerCase().indexOf(state.q) > -1);
    }));
    var box = document.getElementById('grid');
    document.querySelectorAll('.seg [data-view]').forEach(function (b) { b.classList.toggle('on', b.dataset.view === state.view); });
    document.getElementById('sort').value = state.sort;

    if (!list.length) {
      box.className = '';
      box.innerHTML = '<div class="empty-state">' + I('users') + '<h2>No creators yet</h2><p>' + (D.role === 'superadmin' ? 'Add your first creator login to start tracking.' : 'A Super Admin needs to add creators first.') + '</p>' +
        (D.role === 'superadmin' ? '<a class="btn primary" href="users.php?new=creator">' + I('plus') + '<span>Add creator</span></a>' : '') + '</div>';
      return;
    }
    if (!shown.length) { box.className = ''; box.innerHTML = '<div class="empty-state small">' + I('search') + '<p>No creators match this filter.</p></div>'; return; }

    if (state.view === 'list') {
      box.className = 'listview';
      box.innerHTML = '<div class="mtable-wrap"><table class="mtable dtable"><thead><tr><th>Creator</th><th>Phase</th><th>Path progress</th><th>Destination goals</th><th class="n">Revenue</th><th class="n">Sub growth</th><th>Last meeting</th><th>Updated</th></tr></thead><tbody>' +
        shown.map(function (cr) {
          var i = list.indexOf(cr);
          return '<tr data-i="' + i + '" data-url="' + esc(cr.url) + '" tabindex="0" class="' + (cr.active ? '' : 'off') + '"><td><div class="who-cell">' + U.avatar(cr.initials, cr.hue, 'sm') + '<div><b><a class="creator-row-link" href="' + esc(cr.url) + '">' + esc(cr.name) + '</a></b><small>' + sub(cr) + '</small></div>' + tag(cr) + '</div></td>' +
            '<td><span class="phpill ' + tone(cr) + '">' + esc(cr.current.label) + '</span></td>' +
            '<td><div class="prog-cell">' + segbar(cr) + '<b>' + cr.overall + '%</b></div></td>' +
            '<td class="dash-goals-cell">' + goalsHtml(cr, true) + '</td>' +
            '<td class="n">' + U.money(cr.rev) + '</td><td class="n ' + sgc(cr.subGrowth) + '">' + U.growth(cr.subGrowth) + '</td>' +
            '<td>' + esc(cr.lastMeeting || '—') + '</td><td class="muted">' + esc(cr.updatedAgo || '—') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
      box.querySelectorAll('tr[data-i]').forEach(function (tr) {
        tr.addEventListener('click', function (event) { if (!event.target.closest('a,button,form,input,select')) location.href = this.dataset.url; });
        tr.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target === this) location.href = this.dataset.url; });
      });
      return;
    }

    box.className = 'grid';
    box.innerHTML = shown.map(function (cr) {
      var k = tone(cr), i = list.indexOf(cr);
      return '<a href="' + esc(cr.url) + '" class="card' + (cr.active ? '' : ' off') + (needs(cr) ? ' needs' : '') + '" data-i="' + i + '">' +
        '<div class="card-top">' + U.avatar(cr.initials, cr.hue) + '<div class="ct"><span class="cname">' + esc(cr.name) + '</span><span class="csub">' + sub(cr) + '</span></div>' + tag(cr) + '</div>' +
        '<div class="phrow"><span class="phpill ' + k + '">' + esc(cr.current.label) + '</span><span class="pct">' + cr.overall + '<small>%</small></span></div>' +
        segbar(cr) +
        '<div class="stats">' + statsHtml(cr) + '</div>' +
        goalsHtml(cr, true) + socialsHtml(cr, true) +
        '<div class="card-spark"><span class="sl">Revenue · 6 mo</span>' + U.spark(cr.spark, 220, 34, '#22d3ee') + '</div>' +
        '<div class="card-foot"><span>' + I('clock') + (cr.updatedAgo ? 'Updated ' + esc(cr.updatedAgo) : 'No activity yet') + '</span>' +
        (cr.feedback ? '<span>' + I('chat') + cr.feedback + '</span>' : '') +
        (cr.programMonth ? '<span class="pm">Mo ' + cr.programMonth + '</span>' : '') + '</div></a>';
    }).join('');
  }

  /* ---------- activity feed ---------- */
  function renderFeed() {
    var box = document.getElementById('feed');
    if (!D.feed.length) { box.innerHTML = '<div class="empty small">Nothing yet — updates from creators and the team show up here.</div>'; return; }
    box.innerHTML = D.feed.map(function (a) {
      return '<div class="fi"><span class="fdot ' + (a.self ? 'c' : 't') + '"></span><div class="ft"><b>' + esc(a.actor) + '</b> ' + esc(a.text) +
        (a.self ? '' : ' <span class="muted">for</span> <a href="creator.php?id=' + a.creatorId + (a.month ? '&m=' + a.month : '') + '">' + esc(a.creator) + '</a>') +
        (a.self && a.month ? ' <a class="muted" href="creator.php?id=' + a.creatorId + '&m=' + a.month + '">' + I('arrow') + '</a>' : '') +
        '</div><span class="fa">' + esc(a.ago) + '</span></div>';
    }).join('');
  }

  /* ---------- toolbar ---------- */
  document.getElementById('search').addEventListener('input', function () { state.q = this.value.trim().toLowerCase(); renderGrid(); });
  document.getElementById('sort').addEventListener('change', function () { state.sort = this.value; persist(); renderGrid(); });
  document.querySelectorAll('.seg [data-view]').forEach(function (b) {
    b.addEventListener('click', function () { state.view = this.dataset.view; persist(); renderGrid(); });
  });

  renderKpis(); renderChips(); renderGrid(); renderFeed();
})();
