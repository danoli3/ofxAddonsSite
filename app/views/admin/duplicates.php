<?php
/** @var array $groups */
/** @var int $totalGroups */
/** @var string $activeTab 'pending' or 'confirmed' */
?>
<div class="page-head">
  <h1><?= $activeTab === 'confirmed' ? 'Confirmed duplicate addons' : 'Possible duplicate addons' ?></h1>
</div>
<p class="page-intro">
  Addons sharing the exact same name - usually the same addon twice: a fork Github's own metadata doesn't
  (or no longer does) mark as a fork. The oldest by creation date is assumed to be the original.
  <?php if ($activeTab === 'confirmed'): ?>
    Already-decided groups - an audit trail, not a queue. <strong>Undo</strong> if a call needs reversing;
    doing so puts that group back on <a href="/admin/duplicates">Possible duplicates</a>.
  <?php else: ?>
    Each repo shown fetches its README live from Github, so this page only ever loads
    <?= count($groups) ?> of <?= (int)$totalGroups ?> group<?= $totalGroups === 1 ? '' : 's' ?> at a time -
    resolving (or marking "Not a duplicate") the ones below brings the next batch in on reload. A group drops
    off this page on its own once every member's been decided one way or the other - see
    <a href="/admin/duplicates/confirmed">Confirmed &rarr;</a>.
  <?php endif; ?>
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<div class="admin-tabs">
  <a href="/admin/duplicates" class="admin-tab <?= $activeTab === 'pending' ? 'active' : '' ?>">Needs review</a>
  <a href="/admin/duplicates/confirmed" class="admin-tab <?= $activeTab === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
</div>

<?php if (empty($groups)): ?>
  <p class="empty-state">
    <?= $activeTab === 'confirmed' ? 'No confirmed duplicates yet.' : 'No name collisions found.' ?>
  </p>
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
            &middot; updated <?= ofx_h(ofx_time_ago($repo['pushed_at'] ?? null)) ?>
            &middot; &#9733; <?= (int)($repo['stargazers_count'] ?? 0) ?> stars
            &middot; <?= (int)($repo['forks_count'] ?? 0) ?> forks
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
        <?php if (!empty($repo['readme_tail'])): ?>
          <p class="dupe-item__readme">&hellip;<?= ofx_h($repo['readme_tail']) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
