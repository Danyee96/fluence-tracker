/* Fluence — users page */
(function () {
  var form = document.getElementById('newform');
  var openBtn = document.getElementById('openNew'), closeBtn = document.getElementById('closeNew');
  function toggle(show) { form.hidden = !show; if (show) { var f = form.querySelector('input[name=name]'); if (f) f.focus(); } }
  openBtn.addEventListener('click', function () { toggle(form.hidden); });
  closeBtn.addEventListener('click', function () { toggle(false); });

  // creator-only fields
  var role = document.getElementById('newRole'), co = form.querySelectorAll('.creator-only');
  function syncRole() { co.forEach(function (section) { section.hidden = role.value !== 'creator'; section.querySelectorAll('input,select,textarea').forEach(function (input) { input.disabled = section.hidden; }); }); }
  role.addEventListener('change', syncRole); syncRole();
  document.querySelectorAll('.uedit form select[name=role]').forEach(function (select) {
    var extra = select.form.querySelector('.creator-extra-fields'); if (!extra) return;
    function syncExistingRole() { extra.hidden = select.value !== 'creator'; extra.querySelectorAll('input,select,textarea').forEach(function (input) { input.disabled = extra.hidden; }); }
    select.addEventListener('change',syncExistingRole); syncExistingRole();
  });

  // Repeatable creator goals and public social profiles.
  document.addEventListener('click', function (event) {
    var add = event.target.closest('[data-add-repeat]'), remove = event.target.closest('.remove-repeat');
    if (add) {
      var editor = add.closest('[data-details-editor]'), kind = add.dataset.addRepeat;
      var rows = editor.querySelector('.repeat-' + kind), index = +rows.dataset.nextIndex || 0;
      var template = document.getElementById(kind === 'goals' ? 'creator-goal-template' : 'creator-social-template');
      rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(index)));
      rows.dataset.nextIndex = String(index + 1);
      rows.lastElementChild.querySelector('input:not([type=hidden]),select').focus();
    } else if (remove) {
      var row = remove.closest('.repeat-row'), saved = row.querySelector('input[type=hidden]').value;
      if (saved && saved !== '0' && !confirm('Remove this item from the creator’s current details? Saved history will be retained.')) return;
      row.remove();
    }
  });

  // Start queued first syncs after the user has been saved; cron is the fallback.
  var queue = window.FL_SOCIAL_QUEUE;
  if (queue && queue.ids && queue.ids.length) {
    var token = document.querySelector('meta[name=csrf]').content;
    queue.ids.reduce(function (chain, id) {
      return chain.then(function () {
        return fetch('social-refresh.php', {method:'POST', credentials:'same-origin', headers:{'X-CSRF':token}, body:new URLSearchParams({cid:queue.cid, account_id:id})}).catch(function () {});
      });
    }, Promise.resolve());
  }

  // regenerate password
  document.getElementById('regen').addEventListener('click', function () {
    var c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789', out = '', a = new Uint32Array(12);
    crypto.getRandomValues(a); a.forEach(function (n) { out += c[n % c.length]; });
    document.getElementById('newPass').value = out;
  });

  // search + role chips
  var q = '', r = '';
  var rows = [].slice.call(document.querySelectorAll('.urow'));
  function filter() {
    var shown = 0;
    rows.forEach(function (row) {
      var ok = (!r || row.dataset.role === r) && (!q || row.dataset.q.indexOf(q) > -1);
      row.hidden = !ok; if (ok) shown++;
    });
    document.getElementById('uempty').hidden = shown > 0;
  }
  document.getElementById('usearch').addEventListener('input', function () { q = this.value.trim().toLowerCase(); filter(); });
  document.querySelectorAll('#uchips .chip').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#uchips .chip').forEach(function (x) { x.classList.remove('on'); });
      this.classList.add('on'); r = this.dataset.r; filter();
    });
  });

  // open row from #hash
  if (location.hash) { var el = document.querySelector(location.hash + '.urow'); if (el) { el.open = true; el.scrollIntoView({ block: 'center' }); } }

  // confirm deletes
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });
})();
