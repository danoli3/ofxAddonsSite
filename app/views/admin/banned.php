<?php
$appealed = array_values(array_filter($repos, fn($r) => !empty($r['ban_appealed'])));
$rest = array_values(array_filter($repos, fn($r) => empty($r['ban_appealed'])));
?>
<div class="page-head">
  <h1>Banned</h1>
</div>
<p class="page-intro">
  Repos matching the "ofx" name prefix by coincidence, with nothing to do with openFrameworks.
  <a href="/admin/export-banned.json">Download JSON</a> &middot;
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($repos)): ?>
  <p class="empty-state">Nothing banned.</p>
<?php endif; ?>

<?php if (!empty($appealed)): ?>
  <div class="page-head"><h2>Appealed</h2></div>
  <p class="page-intro">The owner has asked for these to be reconsidered.</p>
  <div class="table-scroll">
  <table class="admin-table" data-endpoint="/admin/repos">
    <thead>
      <tr>
        <th>Repo</th>
        <th>Description</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($appealed as $repo): ?>
        <tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>">
          <td>
            <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
              <?= ofx_h($repo['name']) ?>
            </a>
            <div class="admin-row__owner"><?= ofx_h($repo['user_login'] ?? '') ?></div>
          </td>
          <td class="admin-row__desc-static"><?= ofx_h($repo['description'] ?: '') ?></td>
          <td class="admin-row__actions">
            <div class="admin-row__actions-inner">
              <button type="button" class="admin-row__unban">Unban</button>
              <button type="button" class="admin-row__dismiss-appeal">Dismiss appeal</button>
              <span class="admin-row__status"></span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php if (!empty($rest)): ?>
  <div class="page-head"><h2>All banned</h2></div>
  <div class="table-scroll">
  <table class="admin-table" data-endpoint="/admin/repos">
    <thead>
      <tr>
        <th>Repo</th>
        <th>Description</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rest as $repo): ?>
        <tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>">
          <td>
            <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
              <?= ofx_h($repo['name']) ?>
            </a>
            <div class="admin-row__owner"><?= ofx_h($repo['user_login'] ?? '') ?></div>
          </td>
          <td class="admin-row__desc-static"><?= ofx_h($repo['description'] ?: '') ?></td>
          <td class="admin-row__actions">
            <div class="admin-row__actions-inner">
              <button type="button" class="admin-row__unban">Unban</button>
              <span class="admin-row__status"></span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
