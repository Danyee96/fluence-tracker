(function () {
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

})();
