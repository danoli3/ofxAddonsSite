<?php
/** @var array $groups */
/** @var int $totalGroups */
/** @var bool $hasMore */
/** @var string $search */
/** @var string $nextUrl */
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
    Each repo shown fetches its README live from Github, so this loads <?= OFX_ADMIN_DUPLICATES_PAGE_SIZE ?> groups
    at a time - scroll down for more. A group drops off this page on its own once every member's been decided
    one way or the other - see <a href="/admin/duplicates/confirmed">Confirmed &rarr;</a>.
  <?php endif; ?>
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<input type="text" class="filter-box" id="dupe-search" placeholder="Search by addon name&hellip;" value="<?= ofx_h($search) ?>">

<div class="admin-tabs">
  <a href="/admin/duplicates" class="admin-tab <?= $activeTab === 'pending' ? 'active' : '' ?>">Needs review</a>
  <a href="/admin/duplicates/confirmed" class="admin-tab <?= $activeTab === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
</div>

<p class="page-intro"><?= (int)$totalGroups ?> group<?= $totalGroups === 1 ? '' : 's' ?><?= $search !== '' ? ' matching "' . ofx_h($search) . '"' : '' ?></p>

<div id="dupe-groups" data-has-more="<?= $hasMore ? '1' : '0' ?>" data-next-url="<?= ofx_h($nextUrl) ?>">
  <?php if (empty($groups)): ?>
    <p class="empty-state">
      <?= $activeTab === 'confirmed' ? 'No confirmed duplicates yet.' : 'No name collisions found.' ?>
    </p>
  <?php else: ?>
    <?php ofx_admin_dupe_groups_partial($groups); ?>
  <?php endif; ?>
</div>
<div class="grid-sentinel" id="dupe-sentinel"></div>
<div class="grid-loading" hidden>
  <span class="spinner"></span> Loading more&hellip;
</div>
<p class="grid-end" hidden>You&rsquo;ve reached the end.</p>
