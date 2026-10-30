(function () {
  function sparklines() {
    document.querySelectorAll('.social-spark').forEach(function (box) {
      try { box.innerHTML = FLU.spark(JSON.parse(box.dataset.values), 260, 45); } catch (e) {}
    });
  }
  sparklines();
  var box = document.getElementById('social-channels'), timer, busy = false;
  if (!box || !box.querySelector('[data-social-id]')) return;
  var csrf = document.querySelector('meta[name=csrf]').content;
  function schedule(delay) { clearTimeout(timer); timer = setTimeout(poll, delay); }
  function poll() {
    if (document.hidden || busy) { schedule(10000); return; }
    busy = true;
    fetch('social-status.php', {
      method:'POST', credentials:'same-origin', headers:{'X-CSRF':csrf},
      body:new URLSearchParams({cid:box.dataset.cid,manage:box.dataset.manage || '1',sync_due:'1'})
    }).then(function (r) { if (!r.ok) throw Error(); return r.json(); }).then(function (result) {
      if (!result.ok) throw Error();
      var pending = false;
      result.accounts.forEach(function (account) {
        var card = box.querySelector('[data-social-id="' + account.id + '"]');
        if (card && !card.querySelector('details[open]')) card.outerHTML = account.html;
        if (account.pending) pending = true;
      });
      sparklines();
      if (result.enabled) schedule(pending ? 10000 : 60000);
    }).catch(function () { schedule(15000); }).finally(function () { busy = false; });
  }
  box.addEventListener('click', function (event) {
    var button = event.target.closest('[data-social-sync]'); if (!button) return;
    button.disabled = true; button.textContent = 'Checking…';
    fetch('social-refresh.php', {method:'POST',credentials:'same-origin',headers:{'X-CSRF':csrf},body:new URLSearchParams({cid:box.dataset.cid,account_id:button.dataset.socialSync})})
      .then(function (r) { return r.json(); }).then(function (result) {
        if (!result.ok) FLU.toast(result.error || 'Could not sync.', 'err');
        schedule(500);
      }).catch(function () { FLU.toast('Could not connect. Try again.', 'err'); })
      .finally(function () { if (button.isConnected) { button.disabled = false; button.textContent = 'Sync now'; } });
  });
  schedule(500);
})();
