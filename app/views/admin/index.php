<div class="page-head">
  <h1>Admin &mdash; Categorize</h1>
</div>
<p class="page-intro">
  <strong>Recently pushed</strong> sorts by the addon's last Github commit. <strong>Recently updated</strong>
  sorts by when this database last touched the row &mdash; a daily crawl sync or an admin/owner edit, not
  necessarily a new commit.
</p>

<div class="admin-toolbar">
  <div class="admin-toolbar__group">
    <span class="admin-toolbar__label">Export</span>
    <a href="/admin/export.json">JSON</a>
    <a href="/admin/export.xml">XML</a>
  </div>
  <div class="admin-toolbar__group">
    <span class="admin-toolbar__label">AI triage</span>
    <a href="/admin/export-triage.json" title="Unsorted/Incomplete/Spam repos + category list, for feeding to a local model">Download</a>
  </div>
  <div class="admin-toolbar__group">
    <span class="admin-toolbar__label">Database</span>
    <a href="/admin/backup.sql.gz" title="Full schema + data dump of every table, gzipped">Backup .sql.gz</a>
  </div>
  <form class="admin-toolbar__group" action="/admin/import/preview" method="post" enctype="multipart/form-data">
    <span class="admin-toolbar__label">Import</span>
    <input type="hidden" name="_csrf" value="<?= ofx_h(ofx_csrf_token()) ?>">
    <input type="file" name="file" accept=".json,.xml" required>
    <button type="submit" title="Review a diff before anything is saved">Preview</button>
  </form>
  <div class="admin-toolbar__group">
    <span class="admin-toolbar__label">Data</span>
    <button type="button" id="admin-sync-now">Pull latest release</button>
    <span id="admin-sync-status" class="admin-row__status"></span>
  </div>
  <div class="admin-toolbar__group">
    <span class="admin-toolbar__label">Add repo</span>
    <input type="text" id="admin-add-repo-input" placeholder="owner/repo or Github URL">
    <button type="button" id="admin-add-repo" title="For addons the crawler's 'ofx' name search won't find, e.g. drawcall/ofmUI">Add</button>
    <span id="admin-add-repo-status" class="admin-row__status"></span>
  </div>
  <a class="admin-toolbar__link" href="/admin/log">Log &rarr;</a>
  <a class="admin-toolbar__link" href="/admin/admins">Users &rarr;</a>
  <a class="admin-toolbar__link" href="/admin/banned">Banned addons &rarr;</a>
  <a class="admin-toolbar__link" href="/admin/review">Review requests<?= $reviewCount > 0 ? ' (' . $reviewCount . ')' : '' ?> &rarr;</a>
  <a class="admin-toolbar__link" href="/admin/duplicates">Possible duplicates<?= $dupeCount > 0 ? ' (' . $dupeCount . ')' : '' ?> &rarr;</a>
</div>

<?php $qSuffix = $search !== '' ? '&q=' . urlencode($search) : ''; ?>

<input type="text" class="filter-box" id="admin-search" placeholder="Search by repo name&hellip;" value="<?= ofx_h($search) ?>">

<div class="admin-tabs">
  <?php foreach ([...OFX_ADMIN_TYPES, OFX_ADMIN_CURATED_TAB, OFX_ADMIN_NO_DESC_TAB] as $t): ?>
    <a href="/admin/repos?type=<?= ofx_h($t) ?>&sort=<?= ofx_h($sort) ?><?= $qSuffix ?>" class="admin-tab <?= $type === $t ? 'active' : '' ?>" data-type="<?= ofx_h($t) ?>">
      <?= $t === OFX_ADMIN_NO_DESC_TAB ? 'No Description' : ofx_h($t) ?> <span class="count"><?= $counts[$t] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="admin-tabs admin-tabs--sort">
  <a href="/admin/repos?type=<?= ofx_h($type) ?>&sort=pushed<?= $qSuffix ?>" class="admin-tab <?= $sort === 'pushed' ? 'active' : '' ?>">
    Recently pushed
  </a>
  <a href="/admin/repos?type=<?= ofx_h($type) ?>&sort=updated<?= $qSuffix ?>" class="admin-tab <?= $sort === 'updated' ? 'active' : '' ?>">
    Recently updated
  </a>
</div>

<div class="table-scroll">
<table class="admin-table" id="admin-table" data-endpoint="/admin/repos">
  <thead>
    <tr>
      <th>Repo</th>
      <th>Description</th>
      <th>Type</th>
      <th>Categories</th>
      <th></th>
    </tr>
  </thead>
  <tbody id="admin-tbody" data-has-more="<?= $hasMore ? '1' : '0' ?>" data-next-url="<?= ofx_h($nextUrl) ?>">
    <?php foreach ($repos as $repo): ?>
      <?php ofx_admin_row_partial($repo, $categories, $repoCategoryIds[$repo['id']] ?? []); ?>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<div class="grid-sentinel" id="admin-sentinel"></div>
<div class="grid-loading" hidden>
  <span class="spinner"></span> Loading more&hellip;
</div>
<p class="grid-end" hidden>You&rsquo;ve reached the end.</p>
