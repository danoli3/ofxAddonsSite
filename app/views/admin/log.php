<div class="page-head">
  <h1>Admin Log</h1>
</div>
<p class="page-intro">
  Last <?= OFX_ADMIN_LOG_LIMIT ?> admin actions.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($entries)): ?>
  <p class="empty-state">Nothing logged yet.</p>
<?php endif; ?>

<div class="table-scroll">
<table class="admin-table">
  <thead>
    <tr>
      <th>When</th>
      <th>Github account</th>
      <th>Action</th>
      <th>Repo</th>
      <th>Details</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($entries as $entry): ?>
      <tr class="admin-row">
        <td class="admin-row__desc-static"><?= ofx_h(ofx_time_ago($entry['created_at'])) ?></td>
        <td>
          <?php if (!empty($entry['user_login'])): ?>
            <img class="log-avatar" src="<?= ofx_h(ofx_avatar_url($entry['user_avatar_url'])) ?>" alt="" loading="lazy">
            <a href="https://github.com/<?= ofx_h($entry['user_login']) ?>" target="_blank" rel="noopener">
              @<?= ofx_h($entry['user_login']) ?>
            </a>
          <?php else: ?>
            <span class="admin-row__desc-static">unknown</span>
          <?php endif; ?>
        </td>
        <td class="admin-row__desc-static"><?= ofx_h($entry['action']) ?></td>
        <td class="admin-row__desc-static">
          <?php if (!empty($entry['repo_full_name'])): ?>
            <a href="https://github.com/<?= ofx_h($entry['repo_full_name']) ?>" target="_blank" rel="noopener">
              <?= ofx_h($entry['repo_name'] ?? $entry['repo_full_name']) ?>
            </a>
          <?php endif; ?>
        </td>
        <td class="admin-row__desc-static"><?= ofx_h($entry['details'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
