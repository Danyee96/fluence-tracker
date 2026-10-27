/* Fluence — users page */
(function () {
  var form = document.getElementById('newform');
  var openBtn = document.getElementById('openNew'), closeBtn = document.getElementById('closeNew');
  function toggle(show) { form.hidden = !show; if (show) { var f = form.querySelector('input[name=name]'); if (f) f.focus(); } }
  openBtn.addEventListener('click', function () { toggle(form.hidden); });
  closeBtn.addEventListener('click', function () { toggle(false); });

  // creator-only fields
  var role = document.getElementById('newRole'), co = form.querySelector('.creator-only');
  function syncRole() { co.hidden = role.value !== 'creator'; }
  role.addEventListener('change', syncRole); syncRole();

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
