<div class="page-head">
  <h1>Possible duplicate addons</h1>
</div>
<p class="page-intro">
  Addons sharing the exact same name - usually the same addon twice: a fork Github's own metadata doesn't
  (or no longer does) mark as a fork. The oldest by creation date is assumed to be the original.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($groups)): ?>
  <p class="empty-state">No name collisions found.</p>
<?php endif; ?>

<?php foreach ($groups as $nameKey => $members): ?>
  <div class="page-head"><h2><?= ofx_h($members[0]['name']) ?></h2></div>
  <div class="dupe-group">
    <?php foreach ($members as $i => $repo): ?>
      <div class="dupe-item<?= $i === 0 ? ' dupe-item--original' : '' ?>" data-repo-id="<?= (int)$repo['id'] ?>">
        <div class="dupe-item__info">
          <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
            <?= ofx_h($repo['full_name']) ?>
          </a>
          <span class="dupe-item__meta">
            created <?= ofx_h(ofx_time_ago($repo['created_at'] ?? null)) ?>
            <?= $i === 0 ? '&middot; presumed original' : '' ?>
          </span>
        </div>
        <?php if (!empty($repo['confirmed_fork_of'])): ?>
          <span class="tag tag--curated">Confirmed fork of <?= ofx_h($members[0]['full_name']) ?></span>
          <?php if (!empty($repo['fork_hidden_by_admin'])): ?>
            <span class="tag tag--archived">Hidden from public</span>
          <?php endif; ?>
          <button type="button" class="dupe-item__unconfirm" data-repo-id="<?= (int)$repo['id'] ?>">Undo</button>
        <?php elseif (!empty($repo['confirmed_unique'])): ?>
          <span class="tag tag--curated">Not a duplicate</span>
          <button type="button" class="dupe-item__unconfirm-unique" data-repo-id="<?= (int)$repo['id'] ?>">Undo</button>
        <?php else: ?>
          <?php if ($i > 0): ?>
            <button type="button" class="dupe-item__confirm" data-repo-id="<?= (int)$repo['id'] ?>"
                    data-parent-id="<?= (int)$members[0]['id'] ?>" data-hide="0">
              Confirm fork of original
            </button>
            <button type="button" class="dupe-item__confirm" data-repo-id="<?= (int)$repo['id'] ?>"
                    data-parent-id="<?= (int)$members[0]['id'] ?>" data-hide="1">
              Confirm + hide from public
            </button>
          <?php endif; ?>
          <button type="button" class="dupe-item__confirm-unique" data-repo-id="<?= (int)$repo['id'] ?>"
                  title="These are unrelated addons that just happen to share a name">
            Not a duplicate
          </button>
        <?php endif; ?>
        <span class="dupe-item__status"></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
