<div class="page-head">
  <h1>Users</h1>
</div>
<p class="page-intro">
  Every account that has actually signed in with GitHub (not just repo owners the crawler has seen),
  most recently logged in first. <a href="/admin/repos">&larr; Back to admin</a>
</p>

<div class="table-scroll">
<table class="admin-table">
  <thead>
    <tr>
      <th>User</th>
      <th>First seen</th>
      <th>Last login</th>
      <th>Access</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr class="admin-row" data-user-id="<?= (int)$u['id'] ?>">
        <td>
          <a href="https://github.com/<?= ofx_h($u['login']) ?>" target="_blank" rel="noopener">
            <img class="contributor-card__avatar" style="width:24px;height:24px;vertical-align:middle;border-radius:50%;" src="<?= ofx_h(ofx_avatar_url($u['avatar_url'])) ?>" alt="">
            @<?= ofx_h($u['login']) ?>
          </a>
        </td>
        <td><?= ofx_h(ofx_time_ago($u['created_at'])) ?></td>
        <td><?= ofx_h(ofx_time_ago($u['last_login_at'])) ?></td>
        <td>
          <?php if (!empty($u['super_admin'])): ?>
            <span class="tag tag--super-admin">Super Admin</span>
          <?php elseif ($u['admin']): ?>
            <span class="tag">Admin</span>
          <?php else: ?>
            User
          <?php endif; ?>
        </td>
        <td>
          <?php if ((int)$u['id'] !== $currentUserId): ?>
            <button type="button" class="admin-user__toggle" data-admin="<?= $u['admin'] ? '1' : '0' ?>">
              <?= $u['admin'] ? 'Revoke admin' : 'Make admin' ?>
            </button>
            <?php if ($isSuperAdmin && $u['admin']): ?>
              <button type="button" class="admin-user__toggle-super" data-super-admin="<?= !empty($u['super_admin']) ? '1' : '0' ?>">
                <?= !empty($u['super_admin']) ? 'Revoke super admin' : 'Make super admin' ?>
              </button>
            <?php endif; ?>
          <?php else: ?>
            <span class="admin-row__status">(you)</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
