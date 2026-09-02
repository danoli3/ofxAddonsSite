<div class="page-head">
  <h1>Banned</h1>
</div>
<p class="page-intro">
  Repos matching the "ofx" name prefix by coincidence, with nothing to do with openFrameworks.
  <a href="/admin/repos">&larr; Back to admin</a>
</p>

<?php if (empty($repos)): ?>
  <p class="empty-state">Nothing banned.</p>
<?php endif; ?>

<div class="table-scroll">
<table class="admin-table" id="admin-table">
  <thead>
    <tr>
      <th>Repo</th>
      <th>Description</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($repos as $repo): ?>
      <tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>">
        <td>
          <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
            <?= ofx_h($repo['name']) ?>
          </a>
          <div class="admin-row__owner"><?= ofx_h($repo['user_login'] ?? '') ?></div>
        </td>
        <td class="admin-row__desc-static"><?= ofx_h($repo['description'] ?: '') ?></td>
        <td class="admin-row__actions">
          <button type="button" class="admin-row__unban">Unban</button>
          <span class="admin-row__status"></span>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
