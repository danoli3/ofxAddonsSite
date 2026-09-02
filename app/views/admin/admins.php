<div class="page-head">
  <h1>Admins</h1>
</div>
<p class="page-intro">
  Accounts with admin access. <a href="/admin/repos">&larr; Back to admin</a>
</p>

<div class="contributor-grid">
  <?php foreach ($admins as $admin): ?>
    <a class="contributor-card" href="https://github.com/<?= ofx_h($admin['login']) ?>" target="_blank" rel="noopener">
      <img class="contributor-card__avatar" src="<?= ofx_h(ofx_avatar_url($admin['avatar_url'])) ?>" alt="" loading="lazy">
      <span class="contributor-card__login"><?= ofx_h($admin['name'] ?: $admin['login']) ?></span>
      <span class="contributor-card__count">@<?= ofx_h($admin['login']) ?></span>
    </a>
  <?php endforeach; ?>
</div>
