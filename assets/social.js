(function () {
  function sparklines() {
    document.querySelectorAll('.social-spark').forEach(function (box) {
      try { box.innerHTML = FLU.spark(JSON.parse(box.dataset.values), 260, 45); } catch (e) {}
    });
  }
  sparklines();
  var pending = window.FL_SOCIAL_PENDING || [], box = document.getElementById('social-channels'), attempts = 0;
  if (!box || !pending.length) return;
  function poll() {
    if (document.hidden) { setTimeout(poll, 10000); return; }
    var body = new URLSearchParams({cid: box.dataset.cid});
    fetch('social-status.php', {method:'POST', credentials:'same-origin', headers:{'X-CSRF':document.querySelector('meta[name=csrf]').content}, body:body})
      .then(function (r) { if (!r.ok) throw Error(); return r.json(); })
      .then(function (result) {
        if (!result.ok) return;
        pending = [];
        result.accounts.forEach(function (account) {
          var card = box.querySelector('[data-social-id="' + account.id + '"]');
          if (card) card.outerHTML = account.html;
          if (account.pending) pending.push(account.id);
        });
        sparklines();
        if (pending.length && ++attempts < 30) setTimeout(poll, 10000);
      }).catch(function () { if (++attempts < 30) setTimeout(poll, 15000); });
  }
  setTimeout(poll, 5000);
})();
