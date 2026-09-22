<div class="page-head">
  <h1>Sync Log</h1>
</div>
<p class="page-intro">
  Every attempt to pull the crawler's latest release from
  <a href="https://github.com/danoli3/ofxAddons" target="_blank" rel="noopener">danoli3/ofxAddons</a>
  &mdash; the daily cron, the crawler's own webhook, or an admin's manual pull &mdash; with the
  added/updated/removed diff from that pull. <?= number_format($total) ?> total.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($entries)): ?>
  <p class="empty-state">No syncs logged yet.</p>
<?php endif; ?>

<div class="table-scroll">
<table class="admin-table sync-log-table">
  <thead>
    <tr>
      <th>When</th>
      <th>Source</th>
      <th>Added</th>
      <th>Updated</th>
      <th>Removed</th>
      <th>Skipped (banned)</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($entries as $entry): ?>
      <?php $isError = ($entry['status'] ?? 'ok') === 'error'; ?>
      <tr class="admin-row<?= $isError ? ' is-error' : '' ?>">
        <td class="admin-row__desc-static" title="<?= ofx_h($entry['created_at']) ?>">
          <?= ofx_h(ofx_time_ago($entry['created_at'])) ?>
          <?php if (!empty($entry['generated_at'])): ?>
            <span class="admin-row__updated">release: <?= ofx_h(ofx_time_ago($entry['generated_at'])) ?></span>
          <?php endif; ?>
        </td>
        <td class="admin-row__desc-static">
          <?= ofx_h(ucfirst($entry['source'])) ?>
          <?php if (!empty($entry['user_login'])): ?>
            <br>
            <img class="log-avatar" src="<?= ofx_h(ofx_avatar_url($entry['user_avatar_url'], 18)) ?>" alt="" loading="lazy">
            <a href="https://github.com/<?= ofx_h($entry['user_login']) ?>" target="_blank" rel="noopener">
              @<?= ofx_h($entry['user_login']) ?>
            </a>
          <?php endif; ?>
        </td>
        <td class="admin-row__desc-static">+<?= number_format((int)$entry['added']) ?></td>
        <td class="admin-row__desc-static"><?= number_format((int)$entry['updated']) ?></td>
        <td class="admin-row__desc-static">
          <?= $entry['removed'] > 0 ? '-' . number_format((int)$entry['removed']) : '0' ?>
        </td>
        <td class="admin-row__desc-static"><?= number_format((int)$entry['skipped_banned']) ?></td>
        <td class="admin-row__desc-static">
          <?php if ($isError): ?>
            <span class="admin-row__status is-error">Failed<?= !empty($entry['error']) ? ': ' . ofx_h($entry['error']) : '' ?></span>
          <?php else: ?>
            OK
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="pager" aria-label="Sync log pages">
    <?php if ($page > 1): ?>
      <a class="pager__link" href="/admin/sync-log?page=<?= $page - 1 ?>">&larr; Newer</a>
    <?php endif; ?>
    <span class="pager__status">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a class="pager__link" href="/admin/sync-log?page=<?= $page + 1 ?>">Older &rarr;</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
