<div class="page-head">
  <h1>Unsorted</h1>
  <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
</div>
<p class="page-intro">Addons the crawler has found on GitHub but nobody has categorized yet.</p>

<div class="sort-tabs">
  <a href="/unsorted" class="<?= $sort === '' ? 'active' : '' ?>">Suggested</a>
  <a href="/unsorted?sort=pushed" class="<?= $sort === 'pushed' ? 'active' : '' ?>">Recently pushed</a>
  <a href="/unsorted?sort=updated" class="<?= $sort === 'updated' ? 'active' : '' ?>">Recently updated</a>
</div>

<?php if (empty($repos)): ?>
  <p class="empty-state">Nothing unsorted right now.</p>
<?php endif; ?>

<?php ofx_addon_grid($repos, $hasMore, $nextUrl); ?>
